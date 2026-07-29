<?php

declare(strict_types=1);

namespace Tests\Feature\Alerts;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Alerts\Enums\AlertComparison;
use App\Domains\Alerts\Enums\AlertEventStatus;
use App\Domains\Alerts\Enums\AlertMetric;
use App\Domains\Alerts\Enums\AlertSeverity;
use App\Domains\Alerts\Models\AlertEvent;
use App\Domains\Alerts\Models\AlertRule;
use App\Domains\Conversations\Enums\ConversationOutcome;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class AlertsScreenTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = ProjectFactory::default();
        $this->manager = User::factory()->role(Role::AiManager)->create();
    }

    #[Test]
    public function the_screen_shows_the_value_measured_now_not_the_stored_one(): void
    {
        $rule = $this->rule(AlertMetric::EscalationRate, AlertComparison::GreaterThan, 20);

        // قيمة قديمة محفوظة على القاعدة تخالف الواقع الحالي.
        $rule->forceFill(['last_value' => 99, 'last_evaluated_at' => now()->subDay()])->save();

        Conversation::factory()->count(1)->create([
            'project_id' => $this->project->id,
            'outcome' => ConversationOutcome::Human,
            'started_at' => now()->subMinutes(10),
        ]);
        Conversation::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'outcome' => ConversationOutcome::Resolved,
            'started_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($this->manager)
            ->get('/alerts')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Alerts/Index')
                ->where('rules.0.current_value', 25)
                ->where('rules.0.breached', true));
    }

    #[Test]
    public function an_unmeasurable_rule_reports_null_not_zero(): void
    {
        $this->rule(AlertMetric::EscalationRate, AlertComparison::LessThan, 5);

        $this->actingAs($this->manager)
            ->get('/alerts')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('rules.0.current_value', null)
                ->where('rules.0.breached', false));
    }

    #[Test]
    public function creating_a_rule_records_it_with_its_recipients(): void
    {
        $watcher = User::factory()->role(Role::SystemAdmin)->create();

        $this->actingAs($this->manager)
            ->post('/alerts', [
                'name' => 'ارتفاع التكلفة',
                'description' => 'تكلفة اليوم تتجاوز السقف.',
                'metric' => AlertMetric::CostTotal->value,
                'comparison' => AlertComparison::GreaterThan->value,
                'threshold' => 500,
                'window_minutes' => 1440,
                'severity' => AlertSeverity::Critical->value,
                'cooldown_minutes' => 720,
                'auto_action' => 'notify_only',
                'is_enabled' => true,
                'recipients' => [
                    ['user_id' => $watcher->id, 'channel' => 'in_app'],
                    ['email' => 'ops@hiroote.test', 'channel' => 'email'],
                ],
            ])
            ->assertRedirect();

        $rule = AlertRule::query()->forProject($this->project)->firstOrFail();

        $this->assertSame('ارتفاع التكلفة', $rule->name);
        $this->assertSame(2, $rule->recipients()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'alerts.create']);
    }

    #[Test]
    public function an_instant_metric_is_saved_without_a_window(): void
    {
        // النافذة تُرسل ويجب أن تُهمَل: حقل محفوظ لا يقرأه أحد يوهم بضبط لم يحدث.
        $this->actingAs($this->manager)
            ->post('/alerts', [
                'name' => 'نفاد الرصيد',
                'metric' => AlertMetric::ProviderBalance->value,
                'comparison' => AlertComparison::LessThan->value,
                'threshold' => 100,
                'window_minutes' => 1440,
                'severity' => 'critical',
                'cooldown_minutes' => 60,
                'auto_action' => 'notify_only',
            ])
            ->assertRedirect();

        $this->assertSame(0, AlertRule::query()->firstOrFail()->window_minutes);
    }

    #[Test]
    public function a_threshold_above_the_units_ceiling_is_rejected(): void
    {
        $this->actingAs($this->manager)
            ->post('/alerts', [
                'name' => 'نسبة مستحيلة',
                'metric' => AlertMetric::EscalationRate->value,
                'comparison' => 'gt',
                'threshold' => 400,
                'window_minutes' => 1440,
                'severity' => 'warning',
                'cooldown_minutes' => 60,
                'auto_action' => 'notify_only',
            ])
            ->assertStatus(422);

        $this->assertSame(0, AlertRule::query()->count());
    }

    #[Test]
    public function testing_a_rule_reports_the_value_without_opening_an_event(): void
    {
        $rule = $this->rule(AlertMetric::EscalationRate, AlertComparison::GreaterThan, 20);

        Conversation::factory()->count(4)->create([
            'project_id' => $this->project->id,
            'outcome' => ConversationOutcome::Human,
            'started_at' => now()->subMinutes(10),
        ]);

        $this->actingAs($this->manager)
            ->post("/alerts/{$rule->id}/test")
            ->assertRedirect()
            ->assertSessionHas('warning');

        $this->assertSame(0, AlertEvent::query()->count());
        $this->assertSame(0, $rule->fresh()?->trigger_count);
    }

    #[Test]
    public function acknowledging_is_not_closing(): void
    {
        $rule = $this->rule(AlertMetric::EscalationRate, AlertComparison::GreaterThan, 20);
        $event = $this->event($rule);

        $this->actingAs($this->manager)
            ->post("/alerts/events/{$event->id}", ['status' => 'acknowledged'])
            ->assertRedirect();

        $event->refresh();

        $this->assertSame(AlertEventStatus::Acknowledged, $event->status);
        $this->assertNull($event->resolved_at);
        $this->assertSame($this->manager->id, $event->acknowledged_by);
        $this->assertDatabaseHas('audit_logs', ['action' => 'alerts.acknowledge']);
    }

    #[Test]
    public function a_rule_from_another_project_is_not_found(): void
    {
        $other = Project::factory()->create(['slug' => 'far-project', 'sort_order' => 8]);

        $foreign = AlertRule::query()->create([
            'project_id' => $other->id,
            'name' => 'قاعدة بعيدة',
            'metric' => AlertMetric::EscalationRate,
            'comparison' => AlertComparison::GreaterThan,
            'threshold' => 10,
            'window_minutes' => 1440,
            'severity' => AlertSeverity::Warning,
            'cooldown_minutes' => 0,
        ]);

        $this->actingAs($this->manager)
            ->post("/alerts/{$foreign->id}/test")
            ->assertNotFound();

        $this->actingAs($this->manager)
            ->delete("/alerts/{$foreign->id}")
            ->assertNotFound();
    }

    #[Test]
    public function a_read_only_role_sees_alerts_but_cannot_change_them(): void
    {
        $rule = $this->rule(AlertMetric::EscalationRate, AlertComparison::GreaterThan, 20);
        $auditor = User::factory()->role(Role::SecurityAuditor)->create();

        $this->actingAs($auditor)->get('/alerts')->assertOk();
        $this->actingAs($auditor)->post("/alerts/{$rule->id}/test")->assertForbidden();
        $this->actingAs($auditor)->delete("/alerts/{$rule->id}")->assertForbidden();
    }

    private function rule(
        AlertMetric $metric,
        AlertComparison $comparison,
        float $threshold,
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
        ]);
    }

    private function event(AlertRule $rule): AlertEvent
    {
        return AlertEvent::query()->create([
            'project_id' => $this->project->id,
            'alert_rule_id' => $rule->id,
            'status' => AlertEventStatus::Triggered,
            'severity' => $rule->severity,
            'metric' => $rule->metric,
            'comparison' => $rule->comparison,
            'threshold' => $rule->threshold,
            'observed_value' => 60,
            'peak_value' => 60,
            'window_minutes' => $rule->window_minutes,
            'triggered_at' => now(),
        ]);
    }
}
