<?php

declare(strict_types=1);

namespace App\Domains\Administration\Http;

use App\Domains\Administration\Actions\SaveUser;
use App\Domains\Administration\Actions\SetUserActive;
use App\Domains\Administration\Enums\Permission;
use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Projects\Actions\RemoveProjectMember;
use App\Domains\Projects\Actions\SaveProjectMembership;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\CurrentProject;
use App\Http\Controllers\Controller;
use App\Support\Http\SystemStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * المستخدمون والصلاحيات — وثيقة 06 §11.
 *
 * الشاشة تعرض ما يملكه كل دور **مقروءًا من `Role::permissions()` نفسها** لا
 * مكتوبًا بجانبها: مصفوفةٌ تُنسخ يدويًّا تتقادم بأول صلاحية جديدة، فتَعِد
 * القارئ بما لا تفعله البوابة.
 *
 * والدور المعروض في صفّ المستخدم هو **افتراضه عند الإضافة لمشروع**، لا صلاحيته
 * النافذة. النافذ يُحلّ لكل مشروع من `project_user` (ADR-0003 §3)، ولذلك يعرض
 * الصفّ عضويّاته بأدوارها إلى جانبه.
 */
class UsersController extends Controller
{
    public function __construct(private readonly CurrentProject $current) {}

    public function index(Request $request): Response
    {
        $actor = $request->user();
        abort_if($actor === null, 403);

        $manage = $actor->hasPermission(Permission::UsersManage, $this->current->get());

        $users = User::query()
            ->with(['projects' => fn ($projects) => $projects->orderBy('sort_order')])
            ->orderByDesc('is_platform_admin')
            ->orderBy('name')
            ->get();

        return Inertia::render('Users/Index', [
            'systemStatus' => SystemStatus::current(),
            'canManage' => $manage,
            'actorId' => $actor->id,
            'users' => $users->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
                'role_label' => $user->role->label(),
                'is_active' => $user->is_active,
                'is_platform_admin' => $user->is_platform_admin,
                // الرقم الذي لا يُقاس ليس صفرًا: من لم يدخل قط يُقال عنه ذلك.
                'last_login_at' => $user->last_login_at?->toIso8601String(),
                'memberships' => $user->projects
                    ->map(fn (Project $project): array => [
                        'project_id' => $project->id,
                        'project' => $project->name,
                        'role' => (string) $project->getRelationValue('pivot')?->getAttribute('role'),
                    ])
                    ->values()
                    ->all(),
            ])->values()->all(),
            'projects' => Project::query()
                ->orderBy('sort_order')
                ->get(['id', 'name'])
                ->map(fn (Project $project): array => [
                    'value' => (string) $project->id,
                    'label' => $project->name,
                ])
                ->values()
                ->all(),
            'roles' => array_map(
                fn (Role $role): array => ['value' => $role->value, 'label' => $role->label()],
                Role::cases(),
            ),
            'matrix' => $this->matrix(),
        ]);
    }

    public function store(Request $request, SaveUser $save): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['required', 'string', 'min:8', 'max:200'],
            'is_platform_admin' => ['boolean'],
        ]);

        $save->handle(null, [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => Role::from($data['role']),
            'password' => $data['password'],
            'is_platform_admin' => (bool) ($data['is_platform_admin'] ?? false),
        ]);

        return back()->with('success', 'أُنشئ الحساب. سلّم كلمة المرور لصاحبه — لا بريد يُرسلها.');
    }

    public function update(Request $request, User $user, SaveUser $save): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:150', Rule::unique('users', 'email')->ignore($user->id)],
            'role' => ['required', Rule::enum(Role::class)],
            'password' => ['nullable', 'string', 'min:8', 'max:200'],
            'is_platform_admin' => ['boolean'],
        ]);

        $save->handle($user, [
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => Role::from($data['role']),
            'password' => $data['password'] ?? null,
            'is_platform_admin' => (bool) ($data['is_platform_admin'] ?? false),
        ]);

        return back()->with('success', 'حُفظ الحساب.');
    }

    public function toggle(Request $request, User $user, SetUserActive $set): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate(['is_active' => ['required', 'boolean']]);

        try {
            $set->handle($user, (bool) $data['is_active'], $request->user());
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['is_active' => $exception->getMessage()]);
        }

        return back()->with('success', 'حُدّثت حالة الحساب.');
    }

    public function attach(Request $request, User $user, SaveProjectMembership $save): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
            'role' => ['required', Rule::enum(Role::class)],
        ]);

        $project = Project::query()->findOrFail((int) $data['project_id']);
        $save->handle($project, $user, Role::from($data['role']));

        return back()->with('success', 'حُدّثت العضوية.');
    }

    public function detach(Request $request, User $user, RemoveProjectMember $remove): RedirectResponse
    {
        $this->authorizeManage($request);

        $data = $request->validate([
            'project_id' => ['required', 'integer', Rule::exists('projects', 'id')],
        ]);

        $project = Project::query()->findOrFail((int) $data['project_id']);

        try {
            $remove->handle($project, $user);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['project_id' => $exception->getMessage()]);
        }

        return back()->with('success', 'سُحبت العضوية.');
    }

    /**
     * مصفوفة الصلاحيات مقروءةً من مصدرها الوحيد.
     *
     * @return list<array{permission: string, label: string, group: string, roles: array<string, bool>}>
     */
    private function matrix(): array
    {
        $rows = [];

        foreach (Permission::cases() as $permission) {
            $grants = [];

            foreach (Role::cases() as $role) {
                $grants[$role->value] = $role->grants($permission);
            }

            $rows[] = [
                'permission' => $permission->value,
                'label' => $permission->label(),
                'group' => explode('.', $permission->value)[0],
                'roles' => $grants,
            ];
        }

        return $rows;
    }

    /**
     * إدارة الحسابات فعلٌ على مستوى الشركة لا داخل مشروع، لكن الصلاحية تبقى
     * تُقرأ من `Role::permissions()` وحدها (CLAUDE.md §3) — تُحلّ مقابل المشروع
     * النشط لأن الدور يُحلّ لكل مشروع.
     */
    private function authorizeManage(Request $request): void
    {
        $actor = $request->user();

        abort_if(
            $actor === null || ! $actor->hasPermission(Permission::UsersManage, $this->current->get()),
            403,
        );
    }
}
