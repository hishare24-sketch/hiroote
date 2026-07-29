<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\AuditLog;
use App\Domains\Administration\Models\User;
use App\Domains\Providers\Models\AiProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProviderManagementTest extends TestCase
{
    use RefreshDatabase;

    private function aiManager(): User
    {
        return User::factory()->role(Role::AiManager)->create();
    }

    #[Test]
    public function providers_screen_requires_view_permission(): void
    {
        AiProvider::factory()->create();

        $this->get('/providers')->assertRedirect('/login');

        $support = User::factory()->role(Role::SupportAgent)->create();
        $this->actingAs($support)->get('/providers')->assertForbidden();

        $this->actingAs($this->aiManager())->get('/providers')->assertOk();
    }

    #[Test]
    public function manager_can_toggle_provider(): void
    {
        $provider = AiProvider::factory()->create(['is_enabled' => false]);

        $this->actingAs($this->aiManager())
            ->post("/providers/{$provider->id}/toggle", ['enabled' => true])
            ->assertRedirect();

        $this->assertTrue($provider->refresh()->is_enabled);
        $this->assertSame(1, AuditLog::query()->forAction('providers.enable')->count());
    }

    #[Test]
    public function disabling_active_provider_fails_over_to_next_candidate(): void
    {
        $active = AiProvider::factory()->active()->create(['priority' => 1]);
        $backup = AiProvider::factory()->create(['priority' => 2]);

        $this->actingAs($this->aiManager())
            ->post("/providers/{$active->id}/toggle", ['enabled' => false])
            ->assertRedirect();

        $this->assertFalse($active->refresh()->is_active);
        $this->assertTrue($backup->refresh()->is_active);
        $this->assertDatabaseHas('ai_failover_events', [
            'from_provider_id' => $active->id,
            'to_provider_id' => $backup->id,
            'reason' => 'provider_disabled',
        ]);
    }

    #[Test]
    public function manual_failover_switches_active_provider_and_audits(): void
    {
        $active = AiProvider::factory()->active()->create(['priority' => 1]);
        $target = AiProvider::factory()->create(['priority' => 2]);

        $this->actingAs($this->aiManager())
            ->post("/providers/{$target->id}/activate")
            ->assertRedirect();

        $this->assertFalse($active->refresh()->is_active);
        $this->assertTrue($target->refresh()->is_active);
        $this->assertSame(1, AuditLog::query()->forAction('providers.failover')->count());
    }

    #[Test]
    public function failover_to_disabled_provider_is_rejected(): void
    {
        AiProvider::factory()->active()->create(['priority' => 1]);
        $disabled = AiProvider::factory()->disabled()->create(['priority' => 2]);

        $this->actingAs($this->aiManager())
            ->post("/providers/{$disabled->id}/activate")
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertFalse($disabled->refresh()->is_active);
    }

    #[Test]
    public function reorder_updates_priorities(): void
    {
        $first = AiProvider::factory()->create(['priority' => 1]);
        $second = AiProvider::factory()->create(['priority' => 2]);

        $this->actingAs($this->aiManager())
            ->post('/providers/reorder', ['order' => [$second->id, $first->id]])
            ->assertRedirect();

        $this->assertSame(1, $second->refresh()->priority);
        $this->assertSame(2, $first->refresh()->priority);
    }

    #[Test]
    public function cost_analyst_cannot_manage_providers(): void
    {
        $provider = AiProvider::factory()->create();
        $analyst = User::factory()->role(Role::CostAnalyst)->create();

        $this->actingAs($analyst)->get('/providers')->assertOk();
        $this->actingAs($analyst)
            ->post("/providers/{$provider->id}/toggle", ['enabled' => false])
            ->assertForbidden();
    }
}
