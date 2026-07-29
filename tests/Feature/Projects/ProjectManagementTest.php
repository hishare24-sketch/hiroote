<?php

declare(strict_types=1);

namespace Tests\Feature\Projects;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Assistants\Models\AssistantLevelSetting;
use App\Domains\Assistants\Models\AssistantProfile;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = ProjectFactory::default();
    }

    #[Test]
    public function creating_a_project_provisions_its_assistant(): void
    {
        $admin = User::factory()->role(Role::SystemAdmin)->platformAdmin()->create();

        $this->actingAs($admin)
            ->post('/projects', [
                'name' => 'مشروع ثالث',
                'description' => 'تجربة',
                'api_base_url' => 'https://api.third.test/v1',
            ])
            ->assertRedirect();

        $created = Project::query()->where('name', 'مشروع ثالث')->firstOrFail();

        $this->assertSame('مشروع-ثالث', $created->slug);
        $this->assertSame(4, AssistantLevelSetting::query()->forProject($created)->count());
        $this->assertNotNull(AssistantProfile::query()->where('project_id', $created->id)->first());
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.create']);
    }

    #[Test]
    public function only_a_platform_admin_may_create_a_project(): void
    {
        // مدير نظام داخل مشروعه، لكن الإنشاء فعلٌ على مستوى الشركة.
        $projectAdmin = User::factory()->role(Role::SystemAdmin)->create();

        $this->actingAs($projectAdmin)
            ->post('/projects', ['name' => 'محاولة'])
            ->assertForbidden();

        $this->assertSame(1, Project::query()->count());
    }

    #[Test]
    public function a_project_administrator_may_edit_their_own_project(): void
    {
        $projectAdmin = User::factory()->role(Role::SystemAdmin)->create();

        $this->actingAs($projectAdmin)
            ->put("/projects/{$this->project->id}", [
                'name' => 'Hi-Share المحدَّث',
                'description' => null,
                'api_base_url' => 'https://api.hishare.test/v2',
                'is_active' => true,
                'sort_order' => 1,
            ])
            ->assertRedirect();

        $this->project->refresh();
        $this->assertSame('Hi-Share المحدَّث', $this->project->name);
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.update']);
    }

    #[Test]
    public function a_project_administrator_cannot_edit_another_project(): void
    {
        $other = Project::factory()->create(['slug' => 'other', 'sort_order' => 2]);
        $projectAdmin = User::factory()->role(Role::SystemAdmin)->create();

        $this->actingAs($projectAdmin)
            ->put("/projects/{$other->id}", [
                'name' => 'اختطاف',
                'description' => null,
                'api_base_url' => null,
                'is_active' => true,
                'sort_order' => 2,
            ])
            ->assertForbidden();
    }

    #[Test]
    public function membership_is_added_with_a_role_and_audited(): void
    {
        $admin = User::factory()->role(Role::SystemAdmin)->create();
        $newcomer = User::factory()->role(Role::SupportAgent)->withoutProject()->create();

        $this->actingAs($admin)
            ->post("/projects/{$this->project->id}/members", [
                'user_id' => $newcomer->id,
                'role' => Role::KnowledgeManager->value,
            ])
            ->assertRedirect();

        // الدور المخزَّن هو المختار لا الدور الافتراضي للمستخدم.
        $this->assertSame(Role::KnowledgeManager, $newcomer->roleIn($this->project));
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.member_add']);
    }

    #[Test]
    public function the_last_administrator_cannot_be_removed(): void
    {
        $admin = User::factory()->role(Role::SystemAdmin)->create();

        $this->actingAs($admin)
            ->from('/projects')
            ->delete("/projects/{$this->project->id}/members/{$admin->id}")
            ->assertSessionHasErrors('member');

        $this->assertSame(Role::SystemAdmin, $admin->roleIn($this->project));
    }

    #[Test]
    public function an_administrator_may_be_removed_once_another_remains(): void
    {
        $first = User::factory()->role(Role::SystemAdmin)->create();
        $second = User::factory()->role(Role::SystemAdmin)->create();

        $this->actingAs($first)
            ->delete("/projects/{$this->project->id}/members/{$second->id}")
            ->assertRedirect();

        $this->assertNull($second->roleIn($this->project));
        $this->assertDatabaseHas('audit_logs', ['action' => 'project.member_remove']);
    }

    #[Test]
    public function the_list_shows_only_projects_the_operator_belongs_to(): void
    {
        Project::factory()->create(['slug' => 'other', 'sort_order' => 2]);

        $member = User::factory()->role(Role::AiManager)->create();

        $this->actingAs($member)
            ->get('/projects')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Projects/Index')
                ->where('isPlatformAdmin', false)
                ->has('projects', 1)
                ->where('projects.0.slug', 'hi-share'));

        $admin = User::factory()->role(Role::SystemAdmin)->platformAdmin()->create();

        $this->actingAs($admin)
            ->get('/projects')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('isPlatformAdmin', true)
                ->has('projects', 2));
    }

    /**
     * حمولة الصفحة لا تبتلع حمولة المبدّل.
     *
     * كانت الصفحة تسمّي حمولتها `projects` وهو اسم الحمولة المشتركة للمبدّل،
     * وحمولة الصفحة تسبق المشتركة، فيصبح `available` غير معرّف ويسقط الـ Layout
     * كله بشاشة بيضاء. الاسمان منفصلان الآن، وهذا الاختبار يمنع عودتهما.
     */
    #[Test]
    public function the_projects_page_keeps_the_switcher_payload_intact(): void
    {
        Project::factory()->create(['slug' => 'other', 'sort_order' => 2]);

        $admin = User::factory()->role(Role::SystemAdmin)->platformAdmin()->create();

        $this->actingAs($admin)
            ->get('/projects')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('projects', 2)
                ->has('projectSwitcher.available', 2)
                ->where('projectSwitcher.current.slug', 'hi-share'));
    }

    #[Test]
    public function a_role_without_the_permission_cannot_reach_the_screen(): void
    {
        $support = User::factory()->role(Role::SupportAgent)->create();

        $this->actingAs($support)->get('/projects')->assertForbidden();
    }
}
