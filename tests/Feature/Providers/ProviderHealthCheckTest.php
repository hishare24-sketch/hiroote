<?php

declare(strict_types=1);

namespace Tests\Feature\Providers;

use App\Domains\Providers\Enums\ProviderStatus;
use App\Domains\Providers\Models\AiProvider;
use App\Domains\Providers\Services\ProviderHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProviderHealthCheckTest extends TestCase
{
    use RefreshDatabase;

    private function providerWithKey(array $attributes = []): AiProvider
    {
        $provider = AiProvider::factory()->create([
            'base_url' => 'https://api.openai.test/v1',
            ...$attributes,
        ]);

        $provider->credentials()->create([
            'label' => 'اختبار',
            'api_key' => 'sk-live-123456789',
            'key_hint' => '6789',
            'is_active' => true,
        ]);

        return $provider;
    }

    #[Test]
    public function successful_ping_marks_provider_operational(): void
    {
        Http::fake(['api.openai.test/*' => Http::response(['data' => []], 200)]);

        $provider = $this->providerWithKey();

        $check = app(ProviderHealthService::class)->check($provider);

        $this->assertTrue($check->healthy);
        $this->assertSame(ProviderStatus::Operational, $provider->refresh()->status);
        $this->assertSame(0, $provider->consecutive_failures);
        $this->assertNotNull($provider->last_checked_at);
        $this->assertNotNull($provider->activeCredential()?->last_used_at);
    }

    #[Test]
    public function failed_ping_degrades_then_downs_provider(): void
    {
        Http::fake(['api.openai.test/*' => Http::response('error', 500)]);

        $provider = $this->providerWithKey();
        $service = app(ProviderHealthService::class);

        $service->check($provider);
        $this->assertSame(ProviderStatus::Degraded, $provider->refresh()->status);

        $service->check($provider);
        $this->assertSame(ProviderStatus::Down, $provider->refresh()->status);
        $this->assertSame(2, $provider->consecutive_failures);
    }

    #[Test]
    public function provider_without_key_fails_the_check(): void
    {
        $provider = AiProvider::factory()->create();

        $check = app(ProviderHealthService::class)->check($provider);

        $this->assertFalse($check->healthy);
        $this->assertStringContainsString('مفتاح', (string) $check->error_message);
    }

    #[Test]
    public function active_provider_going_down_triggers_automatic_failover(): void
    {
        Http::fake(['api.openai.test/*' => Http::response('error', 503)]);

        $active = $this->providerWithKey(['is_active' => true, 'priority' => 1]);
        $backup = AiProvider::factory()->create(['priority' => 2]);

        $service = app(ProviderHealthService::class);
        $service->check($active);
        $service->check($active); // يتجاوز عتبة الفشل (2) فيتحول تلقائيًا

        $this->assertFalse($active->refresh()->is_active);
        $this->assertTrue($backup->refresh()->is_active);
        $this->assertDatabaseHas('ai_failover_events', [
            'from_provider_id' => $active->id,
            'to_provider_id' => $backup->id,
            'reason' => 'health_check_failure',
        ]);
    }

    #[Test]
    public function anthropic_ping_uses_its_own_auth_header(): void
    {
        Http::fake(['api.anthropic.test/*' => Http::response(['data' => []], 200)]);

        $provider = AiProvider::factory()->create([
            'slug' => 'anthropic',
            'base_url' => 'https://api.anthropic.test/v1',
        ]);
        $provider->credentials()->create([
            'label' => 'اختبار',
            'api_key' => 'sk-ant-9999',
            'key_hint' => '9999',
            'is_active' => true,
        ]);

        app(ProviderHealthService::class)->check($provider);

        Http::assertSent(function ($request): bool {
            return $request->hasHeader('x-api-key', 'sk-ant-9999')
                && $request->hasHeader('anthropic-version');
        });
    }
}
