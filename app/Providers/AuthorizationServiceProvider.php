<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domains\Administration\Enums\Permission;
use App\Domains\Administration\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Registers one Gate per Permission case (وثيقة 03 §2 — Policies وGates للصلاحيات).
 *
 * There is deliberately no `Gate::before` super-admin bypass: SystemAdmin
 * receives its access through the same matrix as everyone else, so the
 * effective permissions of every role can be read off `Role::permissions()`
 * alone.
 */
class AuthorizationServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        foreach (Permission::cases() as $permission) {
            Gate::define(
                $permission->value,
                static fn (User $user): bool => $user->hasPermission($permission),
            );
        }
    }
}
