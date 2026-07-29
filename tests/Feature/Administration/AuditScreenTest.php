<?php

declare(strict_types=1);

namespace Tests\Feature\Administration;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\AuditLog;
use App\Domains\Administration\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AuditScreenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function audit_screen_requires_permission(): void
    {
        $this->get('/audit')->assertRedirect('/login');

        $support = User::factory()->role(Role::SupportAgent)->create();
        $this->actingAs($support)->get('/audit')->assertForbidden();

        $auditor = User::factory()->role(Role::SecurityAuditor)->create();
        $this->actingAs($auditor)->get('/audit')->assertOk();
    }

    #[Test]
    public function entries_can_be_filtered_by_action(): void
    {
        AuditLog::query()->create(['action' => 'providers.enable', 'section' => 'providers']);
        AuditLog::query()->create(['action' => 'auth.login', 'section' => 'auth']);

        $auditor = User::factory()->role(Role::SecurityAuditor)->create();

        $this->actingAs($auditor)
            ->get('/audit?action=auth.login')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Audit/Index')
                ->has('logs.data', 1)
                ->where('logs.data.0.action', 'auth.login'));
    }

    #[Test]
    public function search_matches_actor_and_reason(): void
    {
        AuditLog::query()->create([
            'action' => 'providers.failover',
            'actor_label' => 'ops@hiroote.test',
            'reason' => 'فشل الفحص الذاتي',
        ]);
        AuditLog::query()->create(['action' => 'auth.login', 'actor_label' => 'other@hiroote.test']);

        $auditor = User::factory()->role(Role::SecurityAuditor)->create();

        $this->actingAs($auditor)
            ->get('/audit?search=ops@hiroote.test')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('logs.data', 1));
    }
}
