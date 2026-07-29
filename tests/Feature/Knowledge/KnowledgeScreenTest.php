<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Enums\FeedbackKind;
use App\Domains\Knowledge\Enums\KnowledgeKind;
use App\Domains\Knowledge\Enums\KnowledgeStatus;
use App\Domains\Knowledge\Models\KnowledgeFeedback;
use App\Domains\Knowledge\Models\KnowledgeItem;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Knowledge\Models\KnowledgeSource;
use App\Domains\Knowledge\Models\KnowledgeVersion;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class KnowledgeScreenTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private ProjectSection $section;

    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = ProjectFactory::default();
        $this->section = ProjectSection::query()->create([
            'project_id' => $this->project->id,
            'name' => 'المحفظة',
            'slug' => 'wallet',
        ]);

        $this->manager = User::factory()->role(Role::KnowledgeManager)->create();
    }

    #[Test]
    public function completion_measures_coverage_not_volume(): void
    {
        // عشرة عناصر كلها مسودات لا تقرّب القسم من الاكتمال.
        for ($i = 0; $i < 10; $i++) {
            $this->item(KnowledgeStatus::Draft, "مسودة {$i}");
        }

        $this->actingAs($this->manager)
            ->get('/knowledge')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Knowledge/Index')
                ->where('sections.0.items', 10)
                ->where('sections.0.published', 0)
                ->where('sections.0.completion', 25)
                ->where('sections.0.status', 'partial'));
    }

    #[Test]
    public function a_wholly_empty_section_reports_zero_not_a_quarter(): void
    {
        // لا ملاحظات مفتوحة لأن لا معرفة أصلًا — غياب الشكوى ليس إنجازًا.
        $this->actingAs($this->manager)
            ->get('/knowledge')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sections.0.completion', 0)
                ->where('sections.0.status', 'empty'));
    }

    #[Test]
    public function a_fully_covered_section_reaches_one_hundred(): void
    {
        $this->item(KnowledgeStatus::Published, 'مواعيد الصرف');

        KnowledgeScreen::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $this->section->id,
            'name' => 'المحفظة / السجل',
        ]);

        KnowledgeSource::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $this->section->id,
            'kind' => 'link',
            'label' => 'السياسة',
            'url' => 'https://example.test',
        ]);

        $this->actingAs($this->manager)
            ->get('/knowledge')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('sections.0.completion', 100)
                ->where('sections.0.status', 'complete'));
    }

    #[Test]
    public function an_open_note_holds_the_section_back(): void
    {
        $this->item(KnowledgeStatus::Published, 'مواعيد الصرف');

        KnowledgeScreen::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $this->section->id,
            'name' => 'شاشة',
        ]);
        KnowledgeSource::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $this->section->id,
            'kind' => 'note',
            'label' => 'ملاحظة',
        ]);

        $note = KnowledgeFeedback::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $this->section->id,
            'kind' => FeedbackKind::Unanswered,
            'body' => 'كم رسوم التحويل الدولي؟',
        ]);

        $this->actingAs($this->manager)
            ->get('/knowledge')
            ->assertInertia(fn ($page) => $page->where('sections.0.completion', 75));

        $this->actingAs($this->manager)
            ->post("/knowledge/feedback/{$note->id}", ['resolved' => true])
            ->assertRedirect();

        $this->actingAs($this->manager)
            ->get('/knowledge')
            ->assertInertia(fn ($page) => $page->where('sections.0.completion', 100));

        $this->assertDatabaseHas('audit_logs', ['action' => 'knowledge.feedback_resolve']);
    }

    #[Test]
    public function creating_an_item_snapshots_its_first_version(): void
    {
        $this->actingAs($this->manager)
            ->post("/knowledge/sections/{$this->section->id}/items", [
                'title' => 'مواعيد صرف الأرباح',
                'summary' => 'اليوم الخامس من كل شهر.',
                'body' => 'تُصرف الأرباح في اليوم الخامس.',
                'kind' => KnowledgeKind::Policy->value,
                'status' => KnowledgeStatus::Published->value,
                'tags' => ['أرباح', 'صرف'],
            ])
            ->assertRedirect();

        $item = KnowledgeItem::query()->forProject($this->project)->firstOrFail();

        $this->assertSame(1, $item->version);
        $this->assertNotNull($item->published_at);
        $this->assertSame(2, $item->tags()->count());
        $this->assertSame(1, $item->versions()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'knowledge.create']);
    }

    #[Test]
    public function each_edit_keeps_the_previous_text_recoverable(): void
    {
        $item = $this->item(KnowledgeStatus::Published, 'النسخة الأولى', 'نص أول');

        $this->actingAs($this->manager)
            ->put("/knowledge/items/{$item->id}", [
                'title' => 'النسخة الثانية',
                'summary' => null,
                'body' => 'نص ثانٍ',
                'kind' => KnowledgeKind::Faq->value,
                'status' => KnowledgeStatus::Published->value,
                'tags' => [],
                'change_note' => 'تصحيح المواعيد',
            ])
            ->assertRedirect();

        $item->refresh();

        $this->assertSame(2, $item->version);
        $this->assertSame('نص ثانٍ', $item->body);

        // اللقطة تحمل ما كان لا ما صار — وإلا لتعذّر الرجوع.
        $first = KnowledgeVersion::query()
            ->where('knowledge_item_id', $item->id)
            ->where('version', 1)
            ->firstOrFail();

        $this->assertSame('نص أول', $first->body);
    }

    #[Test]
    public function restoring_a_version_adds_a_version_rather_than_erasing_history(): void
    {
        $item = $this->item(KnowledgeStatus::Published, 'الأصل', 'النص الأصلي');

        $this->actingAs($this->manager)->put("/knowledge/items/{$item->id}", [
            'title' => 'المعدَّل',
            'summary' => null,
            'body' => 'النص المعدَّل',
            'kind' => KnowledgeKind::Faq->value,
            'status' => KnowledgeStatus::Published->value,
            'tags' => [],
        ]);

        $first = KnowledgeVersion::query()
            ->where('knowledge_item_id', $item->id)
            ->where('version', 1)
            ->firstOrFail();

        $this->actingAs($this->manager)
            ->post("/knowledge/items/{$item->id}/versions/{$first->id}/restore")
            ->assertRedirect();

        $item->refresh();

        $this->assertSame('النص الأصلي', $item->body);
        $this->assertSame(3, $item->version);
        // الإصدارات الثلاثة كلها محفوظة: الأصل والمعدَّل والراجع.
        $this->assertSame(3, $item->versions()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'knowledge.restore']);
    }

    #[Test]
    public function knowledge_from_another_project_is_not_found(): void
    {
        $other = Project::factory()->create(['slug' => 'other', 'sort_order' => 2]);
        $foreignSection = ProjectSection::query()->create([
            'project_id' => $other->id,
            'name' => 'قسم بعيد',
            'slug' => 'far',
        ]);

        $this->actingAs($this->manager)
            ->get("/knowledge/sections/{$foreignSection->id}")
            ->assertNotFound();

        $this->actingAs($this->manager)
            ->post("/knowledge/sections/{$foreignSection->id}/items", [
                'title' => 'اختطاف',
                'body' => 'نص',
                'kind' => KnowledgeKind::Faq->value,
                'status' => KnowledgeStatus::Draft->value,
            ])
            ->assertNotFound();
    }

    #[Test]
    public function a_read_only_role_sees_knowledge_but_cannot_write_it(): void
    {
        $item = $this->item(KnowledgeStatus::Published, 'عنصر');

        $auditor = User::factory()->role(Role::SecurityAuditor)->create();

        $this->actingAs($auditor)->get('/knowledge')->assertOk();
        $this->actingAs($auditor)->get("/knowledge/sections/{$this->section->id}")->assertOk();

        $this->actingAs($auditor)
            ->put("/knowledge/items/{$item->id}", [
                'title' => 'تعديل',
                'body' => 'نص',
                'kind' => KnowledgeKind::Faq->value,
                'status' => KnowledgeStatus::Draft->value,
            ])
            ->assertForbidden();
    }

    private function item(KnowledgeStatus $status, string $title, string $body = 'نص'): KnowledgeItem
    {
        $item = KnowledgeItem::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $this->section->id,
            'title' => $title,
            'kind' => KnowledgeKind::Faq,
            'status' => $status,
            'body' => $body,
            'published_at' => $status->isLive() ? now() : null,
        ]);

        KnowledgeVersion::query()->create([
            'knowledge_item_id' => $item->id,
            'version' => 1,
            'title' => $item->title,
            'body' => $item->body,
            'status' => $item->status,
            'change_note' => 'الإصدار الأول',
        ]);

        return $item;
    }
}
