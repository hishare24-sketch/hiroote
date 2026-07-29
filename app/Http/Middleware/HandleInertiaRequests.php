<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Administration\Models\User;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\CurrentProject;
use App\Support\Http\RequestId;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props every page receives.
     *
     * The permission list is shipped so the UI can hide what a role cannot do.
     * It is a rendering hint only — every action is re-checked server side
     * (وثيقة 05 §8 — Authorization يعاد التحقق منه، ولا يعتمد على قرار الواجهة).
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();
        $current = app(CurrentProject::class);
        $project = $current->get();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user instanceof User ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    // الدور المعروض هو النافذ في هذا المشروع لا الافتراضي.
                    'role' => $user->roleIn($project)?->value,
                    'role_label' => $user->roleIn($project)?->label(),
                    'is_platform_admin' => $user->is_platform_admin,
                ] : null,
                'permissions' => $user instanceof User ? $user->permissionNames($project) : [],
            ],
            // اسم مخصَّص لا `projects`: أي صفحة تعرض مشاريع ستسمّي حمولتها
            // `projects`، وحمولة الصفحة تسبق المشتركة فيختفي المبدّل بصمت.
            'projectSwitcher' => [
                'current' => $project === null ? null : self::projectPayload($project),
                'available' => $user instanceof User
                    ? $current->availableTo($user)
                        ->map(fn (Project $item): array => self::projectPayload($item))
                        ->values()
                        ->all()
                    : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'warning' => fn () => $request->session()->get('warning'),
                'error' => fn () => $request->session()->get('error'),
                // المفتاح المُصدَر يعبر مرة واحدة عبر الجلسة ولا يُحفظ في أي
                // مكان آخر: ما لا يُخزَّن لا يُسرَق.
                'issued_api_key' => fn () => $request->session()->get('issued_api_key'),
            ],
            'requestId' => fn (): string => RequestId::current(),
        ];
    }

    /** @return array{id: int, name: string, slug: string} */
    private static function projectPayload(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'slug' => $project->slug,
        ];
    }
}
