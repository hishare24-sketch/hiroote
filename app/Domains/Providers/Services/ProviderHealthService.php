<?php

declare(strict_types=1);

namespace App\Domains\Providers\Services;

use App\Domains\Providers\Actions\PerformFailover;
use App\Domains\Providers\Enums\FailoverReason;
use App\Domains\Providers\Enums\ProviderSetting;
use App\Domains\Providers\Enums\ProviderStatus;
use App\Domains\Providers\Models\AiProvider;
use App\Domains\Providers\Models\AiProviderHealthCheck;
use App\Domains\Providers\Models\ProviderSettingValue;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * الفحص الذاتي للمزودين — وثيقة التصميم §9.
 *
 * This is an infrastructure ping (list-models with the stored key), not an AI
 * call — the Orchestrator rule (وثيقة 02 §4) does not apply here.
 */
class ProviderHealthService
{
    public function __construct(private PerformFailover $failover) {}

    public function check(AiProvider $provider): AiProviderHealthCheck
    {
        $credential = $provider->activeCredential();

        if ($credential === null) {
            return $this->record($provider, healthy: false, latencyMs: null, error: 'لا يوجد مفتاح فعال لهذا المزود.');
        }

        $startedAt = hrtime(true);

        try {
            $response = Http::timeout(config()->integer('hiroote.health_check.timeout_seconds', 15))
                ->withHeaders($this->authHeaders($provider->slug, $credential->api_key))
                ->get($this->pingUrl($provider));

            $latencyMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

            if ($response->successful()) {
                $credential->forceFill(['last_used_at' => now()])->save();

                return $this->record($provider, healthy: true, latencyMs: $latencyMs, error: null);
            }

            return $this->record(
                $provider,
                healthy: false,
                latencyMs: $latencyMs,
                error: "استجابة غير ناجحة من المزود (HTTP {$response->status()}).",
            );
        } catch (Throwable $e) {
            $latencyMs = (int) ((hrtime(true) - $startedAt) / 1_000_000);

            return $this->record($provider, healthy: false, latencyMs: $latencyMs, error: mb_substr($e->getMessage(), 0, 250));
        }
    }

    /**
     * @return array<string, string>
     */
    private function authHeaders(string $slug, string $apiKey): array
    {
        return match ($slug) {
            'anthropic' => [
                'x-api-key' => $apiKey,
                'anthropic-version' => '2023-06-01',
            ],
            // OpenAI وأغلب المزودين المتوافقين معه يستخدمون Bearer.
            default => ['Authorization' => "Bearer {$apiKey}"],
        };
    }

    private function pingUrl(AiProvider $provider): string
    {
        return rtrim($provider->base_url, '/').'/models';
    }

    private function record(AiProvider $provider, bool $healthy, ?int $latencyMs, ?string $error): AiProviderHealthCheck
    {
        $check = AiProviderHealthCheck::query()->create([
            'provider_id' => $provider->id,
            'healthy' => $healthy,
            'latency_ms' => $latencyMs,
            'error_message' => $error,
            'checked_at' => now(),
        ]);

        $failures = $healthy ? 0 : $provider->consecutive_failures + 1;
        $threshold = config()->integer('hiroote.health_check.failure_threshold', 3);

        $provider->forceFill([
            'status' => match (true) {
                $healthy => ProviderStatus::Operational,
                $failures >= $threshold => ProviderStatus::Down,
                default => ProviderStatus::Degraded,
            },
            'consecutive_failures' => $failures,
            'last_checked_at' => now(),
            ...$this->rollingMetrics($provider, $healthy, $latencyMs),
        ])->save();

        // التحويل الاحتياطي التلقائي يقع فقط عندما يتجاوز المزود النشط العتبة،
        // وفقط إذا كانت سياسة التحويل التلقائي مفعلة (وثيقة التصميم §8).
        if (
            $provider->is_active
            && $provider->status === ProviderStatus::Down
            && ProviderSettingValue::isEnabled(ProviderSetting::AutoFailover)
        ) {
            $this->failover->handle(
                reason: FailoverReason::HealthCheckFailure,
                details: ['error' => $error, 'consecutive_failures' => $failures],
            );
        }

        return $check;
    }

    /**
     * متوسطات متجددة على آخر 20 فحصًا — رقم واحد شاذ لا يقلب المؤشر،
     * ولا نحتاج تجميعًا كاملًا للجدول عند كل فحص.
     *
     * @return array<string, int|string>
     */
    private function rollingMetrics(AiProvider $provider, bool $healthy, ?int $latencyMs): array
    {
        $recent = $provider->healthChecks()
            ->latest('id')
            ->limit(20)
            ->get(['healthy', 'latency_ms']);

        $failed = $recent->where('healthy', false)->count();
        $errorRate = $recent->isEmpty() ? 0.0 : ($failed / $recent->count()) * 100;

        $metrics = ['error_rate' => number_format($errorRate, 2, '.', '')];

        $latencies = $recent->whereNotNull('latency_ms')->pluck('latency_ms');

        if ($latencies->isNotEmpty()) {
            $metrics['avg_latency_ms'] = (int) round((float) $latencies->avg());
        } elseif ($healthy && $latencyMs !== null) {
            $metrics['avg_latency_ms'] = $latencyMs;
        }

        return $metrics;
    }
}
