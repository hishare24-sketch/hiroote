<?php

declare(strict_types=1);

namespace Tests\Feature\Assistants;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Assistants\Enums\SectionCapability;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Conversations\Enums\ConversationOutcome;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SectionMatrixTest extends TestCase
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
    public function sections_are_created_edited_and_deleted_per_project(): void
    {
        $this->actingAs($this->manager)
            ->post('/integrations/sections', ['name' => 'المحفظة', 'description' => 'الرصيد والسحب'])
            ->assertRedirect();

        $section = ProjectSection::query()->forProject($this->project)->firstOrFail();

        $this->assertSame('المحفظة', $section->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'integrations.section_create']);

        $this->actingAs($this->manager)
            ->put("/integrations/sections/{$section->id}", [
                'name' => 'المحفظة والرصيد',
                'description' => null,
                'level' => 'expert',
                'model_id' => null,
                'sort_order' => 3,
            ])
            ->assertRedirect();

        $section->refresh();
        $this->assertSame('المحفظة والرصيد', $section->name);
        $this->assertSame('expert', $section->level?->value);

        $this->actingAs($this->manager)
            ->delete("/integrations/sections/{$section->id}")
            ->assertRedirect();

        $this->assertSame(0, ProjectSection::query()->forProject($this->project)->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'integrations.section_delete']);
    }

    #[Test]
    public function two_projects_may_hold_a_section_of_the_same_name(): void
    {
        $other = Project::factory()->create(['slug' => 'other', 'sort_order' => 2]);
        $admin = User::factory()->role(Role::AiManager)->platformAdmin()->create();

        $this->actingAs($admin)->post('/integrations/sections', ['name' => 'الدعم']);
        $this->actingAs($admin)->post("/projects/{$other->id}/switch");
        $this->actingAs($admin)->post('/integrations/sections', ['name' => 'الدعم']);

        $this->assertSame(1, ProjectSection::query()->forProject($this->project)->count());
        $this->assertSame(1, ProjectSection::query()->forProject($other)->count());
    }

    #[Test]
    public function a_section_from_another_project_is_not_found(): void
    {
        $other = Project::factory()->create(['slug' => 'other', 'sort_order' => 2]);

        $foreign = ProjectSection::query()->create([
            'project_id' => $other->id,
            'name' => 'قسم بعيد',
            'slug' => 'far',
        ]);

        $this->actingAs($this->manager)
            ->post("/integrations/sections/{$foreign->id}/toggle", [
                'capability' => SectionCapability::Knowledge->value,
                'enabled' => false,
            ])
            ->assertNotFound();
    }

    #[Test]
    public function switching_a_capability_off_switches_off_what_depends_on_it(): void
    {
        $section = ProjectSection::query()->create([
            'project_id' => $this->project->id,
            'name' => 'الحملات',
            'slug' => 'campaigns',
            'api_call' => true,
            'show_data' => true,
        ]);

        // عرض البيانات يعتمد على استدعاء API.
        $this->actingAs($this->manager)
            ->post("/integrations/sections/{$section->id}/toggle", [
                'capability' => SectionCapability::ApiCall->value,
                'enabled' => false,
            ])
            ->assertRedirect();

        $section->refresh();

        $this->assertFalse((bool) $section->getAttribute('api_call'));
        $this->assertFalse((bool) $section->getAttribute('show_data'));
        $this->assertDatabaseHas('audit_logs', ['action' => 'integrations.capability_toggle']);
    }

    #[Test]
    public function disabling_ai_for_a_section_revokes_every_capability_it_grants(): void
    {
        $section = ProjectSection::query()->create([
            'project_id' => $this->project->id,
            'name' => 'الإشعارات',
            'slug' => 'notifications',
            'ai_enabled' => false,
            'knowledge' => true,
            'api_call' => true,
        ]);

        // الصفوف تبقى كما هي، لكن القسم لا يمنح شيئًا وذكاؤه مطفأ.
        $this->assertTrue((bool) $section->getAttribute('knowledge'));
        $this->assertFalse($section->grants(SectionCapability::Knowledge));
        $this->assertFalse($section->grants(SectionCapability::ApiCall));
    }

    #[Test]
    public function the_matrix_reports_usage_from_real_conversations(): void
    {
        ProjectSection::query()->create([
            'project_id' => $this->project->id,
            'name' => 'المحفظة',
            'slug' => 'wallet',
        ]);

        Conversation::factory()->count(3)->create([
            'project_id' => $this->project->id,
            'section' => 'المحفظة',
            'outcome' => ConversationOutcome::Resolved,
            'started_at' => now()->subDay(),
        ]);
        Conversation::factory()->create([
            'project_id' => $this->project->id,
            'section' => 'المحفظة',
            'outcome' => ConversationOutcome::Human,
            'started_at' => now()->subDay(),
        ]);

        $this->actingAs($this->manager)
            ->get('/integrations')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Sections/Index')
                ->has('capabilities', 11)
                ->has('sections', 1)
                ->where('sections.0.conversations', 4)
                ->where('sections.0.resolution_rate', 75)
                ->where('sections.0.escalation_rate', 25));
    }

    #[Test]
    public function a_read_only_role_sees_the_matrix_but_cannot_change_it(): void
    {
        $section = ProjectSection::query()->create([
            'project_id' => $this->project->id,
            'name' => 'القسائم',
            'slug' => 'vouchers',
        ]);

        $auditor = User::factory()->role(Role::SecurityAuditor)->create();

        $this->actingAs($auditor)->get('/integrations')->assertOk();

        $this->actingAs($auditor)
            ->post("/integrations/sections/{$section->id}/toggle", [
                'capability' => SectionCapability::Knowledge->value,
                'enabled' => false,
            ])
            ->assertForbidden();

        $this->actingAs($auditor)
            ->post('/integrations/sections', ['name' => 'قسم جديد'])
            ->assertForbidden();
    }
}
