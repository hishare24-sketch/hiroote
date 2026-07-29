<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Analytics\Models\CostUsageRecord;
use App\Domains\Analytics\Models\TokenUsageRecord;
use App\Domains\Conversations\Enums\EscalationSeverity;
use App\Domains\Conversations\Enums\EscalationTarget;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Projects\Models\Project;
use App\Domains\Projects\Services\CurrentProject;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * عزل المشاريع — ADR-0003.
 *
 * هذه الاختبارات هي المبرر الوحيد للثقة بأن التقييد الصريح كافٍ: كل شاشة تُسأل
 * صراحةً «هل تسرّب بيانات المشروع الآخر؟» بدل الاكتفاء بأن الكود يبدو صحيحًا.
 */
class ProjectIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Project $mine;

    private Project $other;

    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mine = ProjectFactory::default();

        // ترتيبه بعد الافتراضي عمدًا: المشروع النشط أول المتاح، والاختبارات
        // أدناه تقيس ما يحدث داخل `mine` قبل التبديل الصريح إلى `other`.
        $this->other = Project::factory()->create([
            'name' => 'مشروع آخر',
            'slug' => 'other',
            'sort_order' => 2,
        ]);

        // مدير منصة: عضو ضمنًا في المشروعين، فيستطيع التبديل بينهما ويُظهر أن
        // العزل يقوم على المشروع النشط لا على ما يستطيع الوصول إليه.
        $this->operator = User::factory()->role(Role::SystemAdmin)->platformAdmin()->create();
    }

    #[Test]
    public function the_conversations_screen_shows_only_the_active_project(): void
    {
        Conversation::factory()->count(3)->create([
            'project_id' => $this->mine->id,
            'started_at' => now()->subHour(),
        ]);
        Conversation::factory()->count(5)->create([
            'project_id' => $this->other->id,
            'started_at' => now()->subHour(),
        ]);

        $this->actingAs($this->operator)
            ->get('/conversations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('metrics.conversations', 3)
                ->has('conversations.data', 3));
    }

    #[Test]
    public function a_conversation_from_another_project_is_not_found(): void
    {
        $foreign = Conversation::factory()->create(['project_id' => $this->other->id]);

        $this->actingAs($this->operator)
            ->get("/conversations/{$foreign->id}")
            ->assertNotFound();
    }

    #[Test]
    public function usage_totals_never_include_another_project(): void
    {
        TokenUsageRecord::query()->create([
            'project_id' => $this->mine->id,
            'input_tokens' => 1_000,
            'recorded_on' => today(),
        ]);
        TokenUsageRecord::query()->create([
            'project_id' => $this->other->id,
            'input_tokens' => 9_000,
            'recorded_on' => today(),
        ]);

        CostUsageRecord::query()->create([
            'project_id' => $this->mine->id,
            'amount' => '10.00',
            'recorded_on' => today(),
        ]);
        CostUsageRecord::query()->create([
            'project_id' => $this->other->id,
            'amount' => '90.00',
            'recorded_on' => today(),
        ]);

        $this->actingAs($this->operator)
            ->get('/usage')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('totals.total_tokens', 1000)
                ->where('totals.total_cost', 10));
    }

    #[Test]
    public function open_escalations_are_scoped_to_the_active_project(): void
    {
        foreach ([$this->mine, $this->other] as $index => $project) {
            $conversation = Conversation::factory()->create([
                'project_id' => $project->id,
                'started_at' => now()->subHour(),
            ]);

            $conversation->escalations()->create([
                'reference' => '#W-'.(7000 + $index),
                'target' => EscalationTarget::HumanAgent,
                'severity' => EscalationSeverity::Critical,
                'reason' => 'طلب إجراء مالي',
                'section' => 'المحفظة',
                'subject' => 'سحب رصيد',
            ]);
        }

        $this->actingAs($this->operator)
            ->get('/escalations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('openCases', 1)
                ->where('openCases.0.reference', '#W-7000')
                ->where('totals.open', 1));
    }

    #[Test]
    public function switching_project_changes_what_every_screen_reports(): void
    {
        Conversation::factory()->count(2)->create([
            'project_id' => $this->mine->id,
            'started_at' => now()->subHour(),
        ]);
        Conversation::factory()->count(7)->create([
            'project_id' => $this->other->id,
            'started_at' => now()->subHour(),
        ]);

        $this->actingAs($this->operator)
            ->get('/conversations')
            ->assertInertia(fn ($page) => $page->where('metrics.conversations', 2));

        $this->actingAs($this->operator)
            ->post("/projects/{$this->other->id}/switch")
            ->assertRedirect();

        $this->actingAs($this->operator)
            ->get('/conversations')
            ->assertInertia(fn ($page) => $page->where('metrics.conversations', 7));
    }

    #[Test]
    public function a_member_cannot_switch_into_a_project_they_do_not_belong_to(): void
    {
        // عضو في المشروع الافتراضي وحده — «مشروع آخر» ليس في متناوله.
        $member = User::factory()->role(Role::AiManager)->create();

        $this->actingAs($member)
            ->post("/projects/{$this->other->id}/switch")
            ->assertNotFound();

        $this->assertSame(
            $this->mine->id,
            session(CurrentProject::SESSION_KEY),
        );
    }

    #[Test]
    public function the_switcher_lists_only_projects_the_user_belongs_to(): void
    {
        $member = User::factory()->role(Role::AiManager)->create();

        $this->actingAs($member)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('projects.available', 1)
                ->where('projects.current.slug', 'hi-share'));

        // مدير المنصة عضو ضمنًا في كل مشروع، فيراهما معًا.
        $this->actingAs($this->operator)
            ->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('projects.available', 2));
    }

    #[Test]
    public function the_role_is_resolved_per_project_not_per_person(): void
    {
        $person = User::factory()->role(Role::CostAnalyst)->create();
        $this->other->members()->attach($person->id, ['role' => Role::SupportAgent->value]);

        $this->assertSame(Role::CostAnalyst, $person->roleIn($this->mine));
        $this->assertSame(Role::SupportAgent, $person->roleIn($this->other));
    }

    #[Test]
    public function a_user_without_membership_has_no_permission_at_all(): void
    {
        $stranger = User::factory()->role(Role::SystemAdmin)->withoutProject()->create();

        $this->assertSame([], $stranger->permissionNames($this->mine));

        $this->actingAs($stranger)->get('/')->assertForbidden();
    }
}
