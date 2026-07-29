<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use App\Domains\Alerts\Actions\DispatchAlertNotifications;
use App\Domains\Alerts\Enums\AlertChannel;
use App\Domains\Alerts\Enums\AlertComparison;
use App\Domains\Alerts\Enums\AlertMetric;
use App\Domains\Alerts\Enums\AlertSeverity;
use App\Domains\Alerts\Enums\DeliveryStatus;
use App\Domains\Alerts\Mail\AlertOpened;
use App\Domains\Alerts\Models\AlertEvent;
use App\Domains\Alerts\Models\AlertRule;
use App\Domains\Alerts\Models\NotificationDelivery;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * «وصل» تعني أنه أُرسل فعلًا.
 *
 * كانت `isWired()` مثبَّتة في الكود: مالكٌ يضبط SMTP صحيحًا يبقى تنبيهُه
 * «معلّقًا» ولا يعرف السبب — وتفعيلُه يتطلّب تعديل كود لا إعدادًا.
 */
class AlertEmailIsActuallySentTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = ProjectFactory::default();
    }

    #[Test]
    public function a_log_mailer_is_not_treated_as_a_wired_channel(): void
    {
        // `log` و`array` تبتلعان الرسالة: «أُرسل» في الكود و«لم يصل» عند المستلم.
        config(['mail.default' => 'log']);
        $this->assertFalse(AlertChannel::Email->isWired());

        config(['mail.default' => 'array']);
        $this->assertFalse(AlertChannel::Email->isWired());

        config(['mail.default' => 'smtp']);
        $this->assertTrue(AlertChannel::Email->isWired());
    }

    #[Test]
    public function a_wired_mailer_actually_sends_and_records_delivery(): void
    {
        config(['mail.default' => 'smtp']);
        Mail::fake();

        [$event, $rule] = $this->openEvent('ops@hiroote.test');

        app(DispatchAlertNotifications::class)->handle($event, $rule);

        Mail::assertSent(AlertOpened::class, fn (AlertOpened $mail): bool => $mail->hasTo('ops@hiroote.test'));

        $delivery = NotificationDelivery::query()->firstOrFail();
        $this->assertSame(DeliveryStatus::Delivered, $delivery->status);
        $this->assertNotNull($delivery->delivered_at);
    }

    #[Test]
    public function an_unwired_mailer_records_pending_and_sends_nothing(): void
    {
        config(['mail.default' => 'log']);
        Mail::fake();

        [$event, $rule] = $this->openEvent('ops@hiroote.test');

        app(DispatchAlertNotifications::class)->handle($event, $rule);

        Mail::assertNothingSent();

        $delivery = NotificationDelivery::query()->firstOrFail();
        $this->assertSame(DeliveryStatus::Pending, $delivery->status);
        $this->assertNull($delivery->delivered_at);
        $this->assertStringContainsString('يبتلع الرسالة', (string) $delivery->note);
    }

    #[Test]
    public function a_recipient_without_an_address_is_recorded_failed_not_delivered(): void
    {
        // `—` عنوانٌ لا وجود له: عدُّه واصلًا يُقنع المشغّل بأن أحدًا أُبلغ.
        config(['mail.default' => 'smtp']);
        Mail::fake();

        [$event, $rule] = $this->openEvent(null);

        app(DispatchAlertNotifications::class)->handle($event, $rule);

        Mail::assertNothingSent();
        $this->assertSame(DeliveryStatus::Failed, NotificationDelivery::query()->firstOrFail()->status);
    }

    /** @return array{0: AlertEvent, 1: AlertRule} */
    private function openEvent(?string $email): array
    {
        $rule = AlertRule::query()->create([
            'project_id' => $this->project->id,
            'name' => 'قاعدة اختبار',
            'metric' => AlertMetric::cases()[0],
            'comparison' => AlertComparison::cases()[0],
            'threshold' => 10,
            'severity' => AlertSeverity::Critical,
            'is_enabled' => true,
            'section_ids' => [],
            'provider_ids' => [],
        ]);

        $rule->recipients()->create(['email' => $email, 'channel' => AlertChannel::Email]);

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
