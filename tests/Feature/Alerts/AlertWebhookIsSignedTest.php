<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use App\Domains\Alerts\Actions\DispatchAlertNotifications;
use App\Domains\Alerts\Enums\AlertChannel;
use App\Domains\Alerts\Enums\AlertComparison;
use App\Domains\Alerts\Enums\AlertMetric;
use App\Domains\Alerts\Enums\AlertSeverity;
use App\Domains\Alerts\Enums\DeliveryStatus;
use App\Domains\Alerts\Models\AlertEvent;
use App\Domains\Alerts\Models\AlertRule;
use App\Domains\Alerts\Models\NotificationDelivery;
use App\Domains\Alerts\Models\ProjectWebhook;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * التنبيه المدفوع يُوقَّع، ويُقال حين لا يصل.
 */
class AlertWebhookIsSignedTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://project.test/hooks/hiroote';

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = ProjectFactory::default();
    }

    #[Test]
    public function the_payload_is_signed_with_the_timestamp_inside_it(): void
    {
        // التوقيع وحده يُثبت أن الجسم من هاي روت ولا يمنع إعادة بثّ دفعةٍ
        // قديمة بحذافيرها. وطابعٌ خارج النصّ الموقَّع يُبدَّل بلا أن يبطل
        // التوقيع — فلا يمنع شيئًا.
        $this->webhook('سرّ-التوقيع');
        Http::fake([self::ENDPOINT => Http::response([], 200)]);

        [$event, $rule] = $this->openEvent();
        app(DispatchAlertNotifications::class)->handle($event, $rule);

        $request = Http::recorded()[0][0];
        $timestamp = $request->header('X-Hiroote-Timestamp')[0];
        $signature = $request->header('X-Hiroote-Signature')[0];

        $this->assertSame(
            'sha256='.hash_hmac('sha256', $timestamp.'.'.$request->body(), 'سرّ-التوقيع'),
            $signature,
        );

        $this->assertSame(DeliveryStatus::Delivered, NotificationDelivery::query()->firstOrFail()->status);
    }

    #[Test]
    public function a_refusing_endpoint_is_recorded_failed_and_remembered(): void
    {
        // شاشةٌ تقول «تعمل» ووجهةٌ ساقطة منذ أمس تُقنع المشغّل بأن إنذاره يصل.
        $webhook = $this->webhook();
        Http::fake([self::ENDPOINT => Http::response([], 500)]);

        [$event, $rule] = $this->openEvent();
        app(DispatchAlertNotifications::class)->handle($event, $rule);

        $this->assertSame(DeliveryStatus::Failed, NotificationDelivery::query()->firstOrFail()->status);

        $fresh = $webhook->fresh();
        $this->assertNotNull($fresh?->last_error);
        $this->assertSame('أخفقت', $fresh->statusLabel());
    }

    #[Test]
    public function a_project_without_a_destination_stays_pending_and_sends_nothing(): void
    {
        Http::fake();

        [$event, $rule] = $this->openEvent();
        app(DispatchAlertNotifications::class)->handle($event, $rule);

        Http::assertNothingSent();

        $delivery = NotificationDelivery::query()->firstOrFail();
        $this->assertSame(DeliveryStatus::Pending, $delivery->status);
        $this->assertStringContainsString('لا وجهة مضبوطة', (string) $delivery->note);
    }

    #[Test]
    public function the_secret_never_rests_in_plain_text(): void
    {
        $this->webhook('سرّ-لا-يُقرأ');

        $raw = (string) DB::table('project_webhooks')->value('secret');

        $this->assertStringNotContainsString('سرّ-لا-يُقرأ', $raw);
        $this->assertSame('سرّ-لا-يُقرأ', ProjectWebhook::query()->firstOrFail()->secret);
    }

    private function webhook(string $secret = 'secret'): ProjectWebhook
    {
        return ProjectWebhook::query()->create([
            'project_id' => $this->project->id,
            'url' => self::ENDPOINT,
            'secret' => $secret,
            'is_enabled' => true,
        ]);
    }

    /** @return array{0: AlertEvent, 1: AlertRule} */
    private function openEvent(): array
    {
        $rule = AlertRule::query()->create([
            'project_id' => $this->project->id,
            'name' => 'قاعدة',
            'metric' => AlertMetric::cases()[0],
            'comparison' => AlertComparison::cases()[0],
            'threshold' => 10,
            'severity' => AlertSeverity::Critical,
            'is_enabled' => true,
            'section_ids' => [],
            'provider_ids' => [],
        ]);

        $rule->recipients()->create(['email' => null, 'channel' => AlertChannel::Webhook]);

        $event = AlertEvent::query()->create([
            'project_id' => $this->project->id,
            'alert_rule_id' => $rule->id,
            'severity' => AlertSeverity::Critical,
            'metric' => $rule->metric,
            'comparison' => $rule->comparison,
            'threshold' => 10,
            'observed_value' => 42,
            'peak_value' => 42,
            'window_minutes' => 60,
            'triggered_at' => now(),
        ]);

        return [$event, $rule->fresh(['recipients']) ?? $rule];
    }
}
