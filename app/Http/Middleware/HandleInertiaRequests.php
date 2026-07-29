<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Administration\Models\User;
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

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user instanceof User ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => $user->role->value,
                    'role_label' => $user->role->label(),
                ] : null,
                'permissions' => $user instanceof User ? $user->permissionNames() : [],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'requestId' => fn (): string => RequestId::current(),
        ];
    }
}
