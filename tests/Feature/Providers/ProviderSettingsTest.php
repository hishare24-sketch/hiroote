<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\AuditLog;
use App\Domains\Administration\Models\User;
use App\Domains\Providers\Enums\ProviderSetting;
use App\Domains\Providers\Models\AiProvider;
use App\Domains\Providers\Models\ProviderSettingValue;
use App\Domains\Providers\Services\ProviderHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProviderSettingsTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function unset_switch_falls_back_to_its_declared_default(): void
    {
        $this->assertTrue(ProviderSettingValue::isEnabled(ProviderSetting::AutoFailover));
        $this->assertFalse(ProviderSettingValue::isEnabled(ProviderSetting::MaintenanceMode));
    }

    #[Test]
    public function toggling_a_switch_persists_and_is_audited(): void
    {
        $manager = User::factory()->role(Role::AiManager)->create();

        $this->actingAs($manager)
            ->post('/settings/toggle', [
                'key' => ProviderSetting::MaintenanceMode->value,
                'enabled' => true,
            ])
            ->assertRedirect();

        $this->assertTrue(ProviderSettingValue::isEnabled(ProviderSetting::MaintenanceMode));
        $this->assertSame(1, AuditLog::query()->forAction('settings.toggle')->count());
    }

    #[Test]
    public function toggling_to_the_same_value_writes_no_audit_noise(): void
    {
        $manager = User::factory()->role(Role::AiManager)->create();

        // AutoFailover is already on by default — re-enabling it changes nothing.
        $this->actingAs($manager)->post('/settings/toggle', [
            'key' => ProviderSetting::AutoFailover->value,
            'enabled' => true,
        ]);

        $this->assertSame(0, AuditLog::query()->forAction('settings.toggle')->count());
    }

    #[Test]
    public function unknown_setting_key_is_rejected(): void
    {
        $manager = User::factory()->role(Role::AiManager)->create();

        $this->actingAs($manager)
            ->post('/settings/toggle', ['key' => 'not_a_setting', 'enabled' => true])
            ->assertSessionHasErrors('key');
    }

    #[Test]
    public function support_agent_cannot_toggle_settings(): void
    {
        $support = User::factory()->role(Role::SupportAgent)->create();

        $this->actingAs($support)
            ->post('/settings/toggle', [
                'key' => ProviderSetting::MaintenanceMode->value,
                'enabled' => true,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function disabling_auto_failover_keeps_a_down_provider_active(): void
    {
        ProviderSettingValue::query()->create([
            'key' => ProviderSetting::AutoFailover->value,
            'enabled' => false,
        ]);

        Http::fake(['api.openai.test/*' => Http::response('error', 503)]);

        $active = AiProvider::factory()->active()->create([
            'base_url' => 'https://api.openai.test/v1',
            'priority' => 1,
        ]);
        $active->credentials()->create([
            'label' => 'اختبار',
            'api_key' => 'sk-live-1234',
            'key_hint' => '1234',
            'is_active' => true,
        ]);
        $backup = AiProvider::factory()->create(['priority' => 2]);

        $service = app(ProviderHealthService::class);
        foreach (range(1, config()->integer('hiroote.health_check.failure_threshold')) as $ignored) {
            $service->check($active);
        }

        $this->assertTrue($active->refresh()->is_active);
        $this->assertFalse($backup->refresh()->is_active);
        $this->assertDatabaseCount('ai_failover_events', 0);
    }
}
