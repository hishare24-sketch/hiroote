<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Alerts\Actions\EvaluateAlertRules;
use App\Domains\Alerts\Enums\AlertChannel;
use App\Domains\Alerts\Enums\AlertComparison;
use App\Domains\Alerts\Enums\AlertEventStatus;
use App\Domains\Alerts\Enums\AlertMetric;
use App\Domains\Alerts\Enums\AlertSeverity;
use App\Domains\Alerts\Enums\DeliveryStatus;
use App\Domains\Alerts\Models\AlertEvent;
use App\Domains\Alerts\Models\AlertRule;
use App\Domains\Conversations\Enums\ConversationOutcome;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlertEngineTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = ProjectFactory::default();
    }

    #[Test]
    public function a_breach_opens_one_event_and_a_second_evaluation_does_not_open_another(): void
    {
        $rule = $this->rule(AlertMetric::EscalationRate, AlertComparison::GreaterThan, 20);

        // ٣ من ٥ محادثات منتهية تحوّلت إلى بشري = ٦٠٪.
        $this->conversations(ConversationOutcome::Human, 3);
        $this->conversations(ConversationOutcome::Resolved, 2);

        $evaluate = app(EvaluateAlertRules::class);

        $first = $evaluate->handle($this->project);
        $this->assertSame(1, $first['triggered']);

        $second = $evaluate->handle($this->project);
        $this->assertSame(0, $second['triggered']);

        $this->assertSame(1, AlertEvent::query()->count());
        $this->assertSame(1, $rule->fresh()?->trigger_count);
    }

    #[Test]
    public function an_open_event_keeps_the_worst_value_not_the_latest(): void
    {
        $this->rule(AlertMetric::EscalationRate, AlertComparison::GreaterThan, 20);

        $this->conversations(ConversationOutcome::Human, 3);
        $this->conversations(ConversationOutcome::Resolved, 2);

        $evaluate = app(EvaluateAlertRules::class);
        $evaluate->handle($this->project);

        // تحسّن الوضع دون النزول تحت الحد: ٣ من ١٠ = ٣٠٪.
        $this->conversations(ConversationOutcome::Resolved, 5);
        $evaluate->handle($this->project);

        $event = AlertEvent::query()->firstOrFail();

        $this->assertSame(30.0, $event->observed_value);
        $this->assertSame(60.0, $event->peak_value);
    }

    #[Test]
    public function the_event_closes_itself_when_the_metric_returns_below_the_threshold(): void
    {
        $this->rule(AlertMetric::EscalationRate, AlertComparison::GreaterThan, 20);

        $this->conversations(ConversationOutcome::Human, 3);
        $this->conversations(ConversationOutcome::Resolved, 2);

        $evaluate = app(EvaluateAlertRules::class);
        $evaluate->handle($this->project);

        // ٣ من ٣٠ = ١٠٪ — تحت الحد.
        $this->conversations(ConversationOutcome::Resolved, 25);
        $summary = $evaluate->handle($this->project);

        $this->assertSame(1, $summary['resolved']);
        $this->assertSame(
            AlertEventStatus::Resolved,
            AlertEvent::query()->firstOrFail()->status,
        );
    }

    #[Test]
    public function cooling_down_suppresses_the_next_event_but_not_the_measurement(): void
    {
        $rule = $this->rule(AlertMetric::EscalationRate, AlertComparison::GreaterThan, 20, [
            'cooldown_minutes' => 120,
        ]);

        $this->conversations(ConversationOutcome::Human, 3);
        $this->conversations(ConversationOutcome::Resolved, 2);

        $evaluate = app(EvaluateAlertRules::class);
        $evaluate->handle($this->project);

        // يعود المؤشر للطبيعي فيُغلق الحدث، ثم يتجاوز ثانيةً داخل التهدئة.
        $this->conversations(ConversationOutcome::Resolved, 25);
        $evaluate->handle($this->project);

        $this->conversations(ConversationOutcome::Human, 20);
        $evaluate->handle($this->project);

        $this->assertSame(1, AlertEvent::query()->count());
        $this->assertSame(1, $rule->fresh()?->trigger_count);
        // القياس استمر رغم كتم الإشعار.
        $this->assertNotNull($rule->fresh()?->last_value);
    }

    #[Test]
    public function an_unmeasurable_rule_is_skipped_rather_than_read_as_zero(): void
    {
        // «أقل من ٥٪» على مشروع بلا محادثات: لو عوملت القراءة صفرًا لانفجر التنبيه.
        $rule = $this->rule(AlertMetric::EscalationRate, AlertComparison::LessThan, 5);

        $summary = app(EvaluateAlertRules::class)->handle($this->project);

        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(0, $summary['triggered']);
        $this->assertSame(0, AlertEvent::query()->count());
        $this->assertNull($rule->fresh()?->last_evaluated_at);
    }

    #[Test]
    public function a_disabled_rule_is_not_evaluated_at_all(): void
    {
        $this->rule(AlertMetric::EscalationRate, AlertComparison::GreaterThan, 20, [
            'is_enabled' => false,
        ]);

        $this->conversations(ConversationOutcome::Human, 5);

        $summary = app(EvaluateAlertRules::class)->handle($this->project);

        $this->assertSame(0, $summary['evaluated']);
        $this->assertSame(0, AlertEvent::query()->count());
    }

    #[Test]
    public function an_unwired_channel_is_recorded_pending_never_delivered(): void
    {
        $rule = $this->rule(AlertMetric::EscalationRate, AlertComparison::GreaterThan, 20);
        $watcher = User::factory()->role(Role::AiManager)->create();

        $rule->recipients()->create(['user_id' => $watcher->id, 'channel' => AlertChannel::InApp]);
        $rule->recipients()->create(['email' => 'ops@hiroote.test', 'channel' => AlertChannel::Email]);

        $this->conversations(ConversationOutcome::Human, 3);
        $this->conversations(ConversationOutcome::Resolved, 2);

        app(EvaluateAlertRules::class)->handle($this->project);

        $deliveries = AlertEvent::query()->firstOrFail()->deliveries;

        $this->assertSame(2, $deliveries->count());
        $this->assertSame(
            DeliveryStatus::Delivered,
            $deliveries->firstWhere('channel', AlertChannel::InApp)?->status,
        );
        $this->assertSame(
            DeliveryStatus::Pending,
            $deliveries->firstWhere('channel', AlertChannel::Email)?->status,
        );
    }

    #[Test]
    public function another_projects_conversations_never_reach_this_projects_rule(): void
    {
        $this->rule(AlertMetric::EscalationRate, AlertComparison::GreaterThan, 20);

        $other = Project::factory()->create(['slug' => 'other-project', 'sort_order' => 9]);

        Conversation::factory()->count(5)->create([
            'project_id' => $other->id,
            'outcome' => ConversationOutcome::Human,
            'started_at' => now()->subMinutes(30),
        ]);

        $summary = app(EvaluateAlertRules::class)->handle($this->project);

        $this->assertSame(1, $summary['skipped']);
        $this->assertSame(0, AlertEvent::query()->count());
    }

    #[Test]
    public function the_event_snapshots_the_rule_so_later_edits_do_not_rewrite_history(): void
    {
        $rule = $this->rule(AlertMetric::EscalationRate, AlertComparison::GreaterThan, 20);

        $this->conversations(ConversationOutcome::Human, 3);
        $this->conversations(ConversationOutcome::Resolved, 2);

        app(EvaluateAlertRules::class)->handle($this->project);

        $rule->forceFill(['threshold' => 90, 'severity' => AlertSeverity::Info])->save();

        $event = AlertEvent::query()->firstOrFail();

        $this->assertSame(20.0, $event->threshold);
        $this->assertSame(AlertSeverity::Warning, $event->severity);
    }

    /** @param array<string, mixed> $overrides */
    private function rule(
        AlertMetric $metric,
        AlertComparison $comparison,
        float $threshold,
        array $overrides = [],
    ): AlertRule {
        return AlertRule::query()->create([
            'project_id' => $this->project->id,
            'name' => "قاعدة {$metric->value}",
            'metric' => $metric,
            'comparison' => $comparison,
            'threshold' => $threshold,
            'window_minutes' => $metric->isWindowed() ? 1440 : 0,
            'severity' => AlertSeverity::Warning,
            'cooldown_minutes' => 0,
            'is_enabled' => true,
            ...$overrides,
        ]);
    }

    private function conversations(ConversationOutcome $outcome, int $count): void
    {
        Conversation::factory()->count($count)->create([
            'project_id' => $this->project->id,
            'outcome' => $outcome,
            'started_at' => now()->subMinutes(30),
        ]);
    }
}
