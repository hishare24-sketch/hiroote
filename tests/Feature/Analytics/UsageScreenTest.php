<?php

declare(strict_types=1);

namespace Tests\Feature\Analytics;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Analytics\Models\CostUsageRecord;
use App\Domains\Analytics\Models\TokenUsageRecord;
use App\Domains\Analytics\Models\UsageBudget;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class UsageScreenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function screen_requires_the_usage_permission(): void
    {
        $this->get('/usage')->assertRedirect('/login');

        $support = User::factory()->role(Role::SupportAgent)->create();
        $this->actingAs($support)->get('/usage')->assertForbidden();

        $analyst = User::factory()->role(Role::CostAnalyst)->create();
        $this->actingAs($analyst)->get('/usage')->assertOk();
    }

    #[Test]
    public function totals_sum_every_token_category(): void
    {
        TokenUsageRecord::query()->create([
            'project_id' => ProjectFactory::default()->id,
            'section' => 'المحفظة',
            'input_tokens' => 620,
            'output_tokens' => 290,
            'knowledge_tokens' => 60,
            'attachment_tokens' => 20,
            'tool_tokens' => 10,
            'recorded_on' => today(),
        ]);

        $analyst = User::factory()->role(Role::CostAnalyst)->create();

        $this->actingAs($analyst)
            ->get('/usage')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Usage/Index')
                ->where('totals.total_tokens', 1000)
                ->where('totals.input_tokens', 620)
                ->where('totals.tool_tokens', 10)
                ->where('tokenBreakdown.0.share', 62));
    }

    #[Test]
    public function the_series_fills_days_with_no_usage(): void
    {
        TokenUsageRecord::query()->create([
            'project_id' => ProjectFactory::default()->id,
            'input_tokens' => 500,
            'recorded_on' => today(),
        ]);

        $analyst = User::factory()->role(Role::CostAnalyst)->create();

        $this->actingAs($analyst)
            ->get('/usage?period=week')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                // 7 أيام مضت + اليوم = 8 نقاط، بلا فجوات.
                ->has('series', 8)
                ->where('series.0.tokens', 0)
                ->where('series.7.tokens', 500));
    }

    #[Test]
    public function the_budget_alert_escalates_with_consumption(): void
    {
        UsageBudget::query()->create([
            'project_id' => ProjectFactory::default()->id,
            'scope' => 'platform',
            'monthly_limit' => '1000.00',
            'warn_at_percent' => 70,
            'critical_at_percent' => 85,
        ]);

        CostUsageRecord::query()->create([
            'project_id' => ProjectFactory::default()->id,
            'amount' => '900.00',
            'recorded_on' => today(),
        ]);

        $analyst = User::factory()->role(Role::CostAnalyst)->create();

        $this->actingAs($analyst)
            ->get('/usage')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('budget.consumed_percent', 90)
                ->where('budget.tone', 'danger'));
    }

    #[Test]
    public function usage_records_stay_append_only(): void
    {
        $record = CostUsageRecord::query()->create(['amount' => '12.50', 'recorded_on' => today()]);

        $this->expectExceptionMessageMatches('/append-only/');

        $record->forceFill(['amount' => '0.00'])->save();
    }

    #[Test]
    public function comparison_returns_null_when_the_previous_period_was_empty(): void
    {
        TokenUsageRecord::query()->create([
            'project_id' => ProjectFactory::default()->id,
            'input_tokens' => 100,
            'recorded_on' => today(),
        ]);

        $analyst = User::factory()->role(Role::CostAnalyst)->create();

        $this->actingAs($analyst)
            ->get('/usage?period=today')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('comparison.previous_tokens', 0)
                // لا نسبة تغيّر من صفر — null لا 0% ولا ∞.
                ->where('comparison.tokens_change', null));
    }
}
