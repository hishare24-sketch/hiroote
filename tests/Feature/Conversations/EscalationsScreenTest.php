<?php

declare(strict_types=1);

namespace Tests\Feature\Conversations;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Conversations\Enums\ConversationOutcome;
use App\Domains\Conversations\Enums\EscalationSeverity;
use App\Domains\Conversations\Enums\EscalationTarget;
use App\Domains\Conversations\Models\Conversation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EscalationsScreenTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function the_three_paths_are_always_present_even_when_empty(): void
    {
        $admin = User::factory()->role(Role::SystemAdmin)->create();

        $this->actingAs($admin)
            ->get('/escalations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Escalations/Index')
                ->has('paths', 3)
                ->where('paths.0.target.value', 'specialist_assistant')
                ->where('paths.1.target.value', 'human_agent')
                ->where('paths.2.target.value', 'ticket')
                ->where('paths.0.count', 0));
    }

    #[Test]
    public function open_cases_are_ordered_by_severity_then_age(): void
    {
        $conversation = Conversation::factory()->create([
            'outcome' => ConversationOutcome::Human,
            'started_at' => now()->subHours(3),
        ]);

        $conversation->escalations()->create([
            'reference' => '#W-1001',
            'target' => EscalationTarget::HumanAgent,
            'severity' => EscalationSeverity::Medium,
            'reason' => 'سؤال حساس',
            'section' => 'الدعم الفني',
            'subject' => 'شكوى',
            'created_at' => now()->subHours(5),
        ]);
        $conversation->escalations()->create([
            'reference' => '#W-1002',
            'target' => EscalationTarget::HumanAgent,
            'severity' => EscalationSeverity::Critical,
            'reason' => 'طلب إجراء مالي',
            'section' => 'المحفظة',
            'subject' => 'سحب رصيد',
            'created_at' => now()->subHour(),
        ]);

        $admin = User::factory()->role(Role::SystemAdmin)->create();

        $this->actingAs($admin)
            ->get('/escalations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('openCases', 2)
                // الحرجة أولًا رغم أنها الأحدث.
                ->where('openCases.0.reference', '#W-1002')
                ->where('openCases.0.severity.tone', 'danger')
                ->where('openCases.1.reference', '#W-1001')
                ->where('totals.open', 2));
    }

    #[Test]
    public function resolved_cases_leave_the_open_list(): void
    {
        $conversation = Conversation::factory()->create(['started_at' => now()->subHours(2)]);

        $conversation->escalations()->create([
            'reference' => '#T-2001',
            'target' => EscalationTarget::Ticket,
            'severity' => EscalationSeverity::Low,
            'reason' => 'تعذر استدعاء البيانات',
            'section' => 'القسائم',
            'subject' => 'تحقق متأخر',
            'resolved_at' => now()->subHour(),
            'created_at' => now()->subHours(2),
        ]);

        $admin = User::factory()->role(Role::SystemAdmin)->create();

        $this->actingAs($admin)
            ->get('/escalations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('openCases', 0)
                ->where('totals.open', 0)
                ->where('totals.escalated', 1)
                ->where('paths.2.count', 1));
    }

    #[Test]
    public function the_journey_never_reports_a_share_without_a_denominator(): void
    {
        $admin = User::factory()->role(Role::SystemAdmin)->create();

        $this->actingAs($admin)
            ->get('/escalations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('journey', 4)
                ->where('journey.0.count', 0)
                ->where('journey.0.share', 0));
    }
}
