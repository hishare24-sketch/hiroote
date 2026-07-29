<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Domains\Administration\Enums\Permission;
use App\Domains\Administration\Enums\Role;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RolePermissionMatrixTest extends TestCase
{
    #[Test]
    public function system_admin_covers_the_entire_permission_vocabulary(): void
    {
        $this->assertSame(
            Permission::cases(),
            Role::SystemAdmin->permissions(),
        );
    }

    #[Test]
    public function every_role_resolves_a_non_empty_permission_list(): void
    {
        foreach (Role::cases() as $role) {
            $this->assertNotEmpty($role->permissions(), "Role [{$role->value}] grants nothing.");
        }
    }

    #[Test]
    public function every_permission_is_granted_to_at_least_one_role(): void
    {
        foreach (Permission::cases() as $permission) {
            $granted = array_filter(
                Role::cases(),
                static fn (Role $role): bool => $role->grants($permission),
            );

            $this->assertNotEmpty(
                $granted,
                "Permission [{$permission->value}] is orphaned — no role grants it.",
            );
        }
    }

    #[Test]
    public function role_labels_are_defined_for_every_case(): void
    {
        foreach (Role::cases() as $role) {
            $this->assertNotSame('', $role->label());
        }
    }
}
