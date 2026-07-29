<?php

declare(strict_types=1);

namespace App\Domains\Projects\Http;

use App\Domains\Administration\Enums\Permission;
use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Analytics\Models\CostUsageRecord;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Integrations\Actions\IssueProjectApiKey;
use App\Domains\Integrations\Models\ProjectApiKey;
use App\Domains\Projects\Actions\RemoveProjectMember;
use App\Domains\Projects\Actions\SaveProject;
use App\Domains\Projects\Actions\SaveProjectMembership;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\CurrentProject;
use App\Http\Controllers\Controller;
use App\Support\Http\SystemStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * شاشة إدارة المشاريع والعضوية — ADR-0003.
 *
 * إنشاء المشروع وحذفه فعلٌ على مستوى الشركة لا داخل مشروع، فلا مشروعَ تُحلّ
 * مقابله صلاحية. لذلك يُشترط `is_platform_admin` صراحةً هنا وحده: مصفوفة
 * `Role::permissions()` تبقى المرجع لكل ما يقع **داخل** مشروع (CLAUDE.md §3).
 */
class ProjectsController extends Controller
{
    public function __construct(private readonly CurrentProject $current) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        abort_if($user === null, 403);

        $isPlatformAdmin = $user->is_platform_admin;

        $projects = ($isPlatformAdmin
            ? Project::query()
            : Project::query()->whereHas(
                'members',
                fn ($members) => $members->where('users.id', $user->id),
            ))
            ->with(['members', 'apiKeys' => fn ($keys) => $keys->latest('id')])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $conversations = Conversation::query()
            ->selectRaw('project_id, count(*) as total')
            ->groupBy('project_id')
            ->toBase()
            ->pluck('total', 'project_id');

        $costs = CostUsageRecord::query()
            ->selectRaw('project_id, sum(amount) as total')
            ->groupBy('project_id')
            ->toBase()
            ->pluck('total', 'project_id');

        $sections = ProjectSection::query()
            ->selectRaw('project_id, count(*) as total')
            ->groupBy('project_id')
            ->toBase()
            ->pluck('total', 'project_id');

        return Inertia::render('Projects/Index', [
            'systemStatus' => SystemStatus::current(),
            'isPlatformAdmin' => $isPlatformAdmin,
            'currentProjectId' => $this->current->id(),
            'projects' => $projects->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'slug' => $project->slug,
                'description' => $project->description,
                // نصٌّ فارغ ليس عنوانًا: توحيده إلى null يجعل الواجهة تقول
                // «لم يُربط» بدل أن تترك سطرًا خاليًا يبدو خطأ عرض.
                'api_base_url' => $project->api_base_url === '' ? null : $project->api_base_url,
                'is_active' => $project->is_active,
                'sort_order' => $project->sort_order,
                'conversations' => (int) ($conversations[$project->id] ?? 0),
                'cost' => round((float) ($costs[$project->id] ?? 0), 2),
                'sections' => (int) ($sections[$project->id] ?? 0),
                'api_keys' => $project->apiKeys
                    ->map(fn (ProjectApiKey $key): array => [
                        'id' => $key->id,
                        'name' => $key->name,
                        'prefix' => $key->prefix,
                        'status' => $key->statusLabel(),
                        'usable' => $key->isUsable(),
                        'last_used_at' => $key->last_used_at?->toIso8601String(),
                        'expires_at' => $key->expires_at?->toIso8601String(),
                    ])
                    ->values()
                    ->all(),
                'members' => $project->members
                    ->map(fn (User $member): array => [
                        'id' => $member->id,
                        'name' => $member->name,
                        'email' => $member->email,
                        'role' => (string) $member->getRelationValue('pivot')?->getAttribute('role'),
                        'is_platform_admin' => $member->is_platform_admin,
                    ])
                    ->values()
                    ->all(),
            ])->values()->all(),
            'roleOptions' => array_map(
                fn (Role $role): array => ['value' => $role->value, 'label' => $role->label()],
                Role::cases(),
            ),
            'assignableUsers' => User::query()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'email'])
                ->map(fn (User $user): array => [
                    'value' => (string) $user->id,
                    'label' => "{$user->name} — {$user->email}",
                ])
                ->all(),
        ]);
    }

    public function issueKey(Request $request, Project $project, IssueProjectApiKey $action): RedirectResponse
    {
        $this->authorizeManage($request, $project);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'expires_at' => ['nullable', 'date', 'after:today'],
        ]);

        $issued = $action->issue(
            $project,
            $validated['name'],
            isset($validated['expires_at']) ? Carbon::parse($validated['expires_at']) : null,
        );

        // المفتاح في الجلسة مرة واحدة: لا يُحفظ في جدول ولا يُسترجع، وعرضه
        // ثانيةً في أي شاشة يعني أنه قابل للسرقة منها.
        return back()->with('issued_api_key', $issued['token']);
    }

    public function revokeKey(Request $request, Project $project, ProjectApiKey $key, IssueProjectApiKey $action): RedirectResponse
    {
        $this->authorizeManage($request, $project);
        abort_unless($key->project_id === $project->id, 404);

        $action->revoke($key);

        return back()->with('success', 'أُبطل المفتاح.');
    }

    public function store(Request $request, SaveProject $action): RedirectResponse
    {
        $this->authorizePlatform($request);

        $action->handle($request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:300'],
            'api_base_url' => ['nullable', 'url', 'max:200'],
        ]));

        return back()->with('success', 'أُنشئ المشروع وجُهّز سلوك مساعده.');
    }

    public function update(Request $request, Project $project, SaveProject $action): RedirectResponse
    {
        $this->authorizeManage($request, $project);

        $action->handle($request->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:300'],
            'api_base_url' => ['nullable', 'url', 'max:200'],
            'is_active' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:999'],
        ]), $project);

        return back()->with('success', 'حُفظ المشروع.');
    }

    public function addMember(Request $request, Project $project, SaveProjectMembership $action): RedirectResponse
    {
        $this->authorizeManage($request, $project);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'role' => ['required', 'string'],
        ]);

        $role = Role::tryFrom($validated['role']);
        abort_if($role === null, 404);

        /** @var User $member */
        $member = User::query()->findOrFail($validated['user_id']);

        $action->handle($project, $member, $role);

        return back()->with('success', 'حُدِّثت العضوية.');
    }

    public function removeMember(
        Request $request,
        Project $project,
        User $user,
        RemoveProjectMember $action,
    ): RedirectResponse {
        $this->authorizeManage($request, $project);

        try {
            $action->handle($project, $user);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['member' => $exception->getMessage()]);
        }

        return back()->with('success', 'سُحبت العضوية.');
    }

    /**
     * إنشاء مشروع أو حذفه — لا مشروع يُحلّ مقابله دور، فالشرط على مستوى المنصة.
     */
    private function authorizePlatform(Request $request): void
    {
        abort_unless($request->user()?->is_platform_admin === true, 403);
    }

    /**
     * تعديل مشروع أو عضويته — صلاحية داخل المشروع نفسه، تمرّ بالمصفوفة.
     */
    private function authorizeManage(Request $request, Project $project): void
    {
        $user = $request->user();

        abort_if($user === null, 403);
        abort_unless(
            $user->is_platform_admin
                || $user->roleIn($project)?->grants(Permission::ProjectManage) === true,
            403,
        );
    }
}
