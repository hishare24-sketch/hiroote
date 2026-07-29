<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Domains\Administration\Enums\Permission;
use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PermissionGateTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function every_permission_has_a_registered_gate(): void
    {
        $user = User::factory()->role(Role::SystemAdmin)->create();
        $this->withProject();

        foreach (Permission::cases() as $permission) {
            $this->assertTrue(
                $user->can($permission->value),
                "SystemAdmin denied [{$permission->value}] — is the gate registered?",
            );
        }
    }

    #[Test]
    public function support_agent_cannot_manage_providers(): void
    {
        $user = User::factory()->role(Role::SupportAgent)->create();
        $this->withProject();

        $this->assertTrue($user->can(Permission::ConversationsView->value));
        $this->assertFalse($user->can(Permission::ProvidersManage->value));
        $this->assertFalse($user->can(Permission::UsersManage->value));
    }

    #[Test]
    public function security_auditor_is_read_only(): void
    {
        $user = User::factory()->role(Role::SecurityAuditor)->create();
        $this->withProject();

        $this->assertTrue($user->can(Permission::AuditView->value));
        $this->assertTrue($user->can(Permission::AuditExport->value));

        foreach ([
            Permission::ProvidersManage,
            Permission::AssistantsManage,
            Permission::KnowledgeManage,
            Permission::AlertsManage,
            Permission::UsersManage,
            Permission::SettingsManage,
            Permission::MaintenanceToggle,
        ] as $writePermission) {
            $this->assertFalse(
                $user->can($writePermission->value),
                "SecurityAuditor unexpectedly granted [{$writePermission->value}]",
            );
        }
    }

    #[Test]
    public function deactivated_user_loses_every_permission(): void
    {
        $user = User::factory()->role(Role::SystemAdmin)->inactive()->create();
        $this->withProject();

        foreach (Permission::cases() as $permission) {
            $this->assertFalse($user->can($permission->value));
        }
    }

    #[Test]
    public function overview_requires_permission_middleware(): void
    {
        $this->get('/')->assertRedirect('/login');

        $user = User::factory()->role(Role::AiManager)->create();
        $this->withProject();
        $this->actingAs($user)->get('/')->assertOk();
    }
}
