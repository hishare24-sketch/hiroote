<?php

declare(strict_types=1);

namespace Tests\Feature\Assistants;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Assistants\Models\ProjectChatPolicy;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Integrations\Models\ProjectApiKey;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * حوكمة الشات — **إذنٌ لا محتوى**.
 *
 * لا جدول رسائل في هاي روت عمدًا: رسائل مستخدمي المشروع وهويّاتهم تبقى عنده
 * (وثيقة 01 §6، وقاعدة CLAUDE.md رقم ١). ما يُحفظ هنا هو ما سمح به المالك،
 * ويقرأه المشروع من جسر الوارد.
 */
class ChatPolicyTest extends TestCase
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
    public function hiroote_stores_the_permission_and_never_a_message(): void
    {
        // الجدول الوحيد المضاف للشات هو جدول إذن — وغيابُ جدول رسائل قرارٌ في
        // البنية لا وعدٌ في التوثيق.
        $this->assertTrue(DB::getSchemaBuilder()->hasTable('project_chat_policies'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('chat_messages'));
        $this->assertFalse(DB::getSchemaBuilder()->hasTable('chat_channels'));
    }

    #[Test]
    public function a_project_starts_with_chat_off(): void
    {
        // الشات يفتح قناةً بين بشرٍ وبشر، وافتراض السماح يفتحها بلا قرار من أحد.
        $this->actingAs($this->manager)
            ->get('/assistants')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('chat.is_enabled', false)
                ->where('chat.channel_kinds', [])
                ->where('chat.scopes', []));
    }

    #[Test]
    public function enabling_without_a_kind_or_a_scope_is_refused(): void
    {
        // قناةٌ بلا دائرة تُحفظ «مفعّلة» ولا يعمل منها شيء، فيُقرأ العطل عيبًا
        // في المشروع لا نقصًا في الإذن.
        $this->actingAs($this->manager)
            ->put('/assistants/chat', [
                'is_enabled' => true,
                'channel_kinds' => [],
                'scopes' => ['project'],
                'assistant_participates' => true,
                'attachments_allowed' => false,
                'retention_days' => 0,
            ])
            ->assertSessionHasErrors('is_enabled');

        $this->assertSame(0, ProjectChatPolicy::query()->count());
    }

    #[Test]
    public function an_unknown_kind_is_dropped_rather_than_stored(): void
    {
        $this->actingAs($this->manager)
            ->put('/assistants/chat', [
                'is_enabled' => true,
                'channel_kinds' => ['assistant', 'group', 'telepathy'],
                'scopes' => ['project'],
                'assistant_participates' => true,
                'attachments_allowed' => false,
                'retention_days' => 30,
            ])
            ->assertSessionHasErrors('channel_kinds.2');

        $this->assertSame(0, ProjectChatPolicy::query()->count());
    }

    #[Test]
    public function the_saved_permission_is_audited_and_reaches_the_project_through_the_bridge(): void
    {
        $section = ProjectSection::query()->create([
            'project_id' => $this->project->id,
            'name' => 'المالية',
            'slug' => 'finance',
        ]);

        KnowledgeScreen::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $section->id,
            'key' => 'finance-page',
            'name' => 'المالية',
        ]);

        $this->actingAs(User::factory()->role(Role::SystemAdmin)->create())
            ->put('/assistants/chat', [
                'is_enabled' => true,
                'channel_kinds' => ['assistant', 'group'],
                'scopes' => ['project', 'subscriber'],
                'assistant_participates' => true,
                'attachments_allowed' => true,
                'retention_days' => 0,
            ])
            ->assertRedirect();

        $this->assertSame(1, DB::table('audit_logs')
            ->where('action', 'assistants.chat_policy_save')
            ->count());

        $minted = ProjectApiKey::mint();
        ProjectApiKey::query()->create([
            'project_id' => $this->project->id,
            'name' => 'مفتاح',
            'prefix' => $minted['prefix'],
            'hash' => $minted['hash'],
        ]);

        $this->withToken($minted['token'])
            ->getJson('/api/v1/context?screen=finance-page')
            ->assertOk()
            ->assertJsonPath('chat.enabled', true)
            ->assertJsonPath('chat.kinds', ['assistant', 'group'])
            // صفرٌ في الحفظ يعني «بلا حدّ»، فيُرسل null كي لا يُقرأ صفرًا.
            ->assertJsonPath('chat.retention_days', null);
    }

    #[Test]
    public function a_read_only_role_cannot_widen_the_circle(): void
    {
        $auditor = User::factory()->role(Role::SecurityAuditor)->create();

        $this->actingAs($auditor)
            ->put('/assistants/chat', [
                'is_enabled' => true,
                'channel_kinds' => ['group'],
                'scopes' => ['platform'],
                'assistant_participates' => true,
                'attachments_allowed' => true,
                'retention_days' => 0,
            ])
            ->assertForbidden();

        $this->assertSame(0, ProjectChatPolicy::query()->count());
    }
}
