<?php

declare(strict_types=1);

namespace Tests\Feature\Assistants;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Enums\KnowledgeKind;
use App\Domains\Knowledge\Enums\KnowledgeStatus;
use App\Domains\Knowledge\Models\KnowledgeItem;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SectionDeletionGuardTest extends TestCase
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
        $this->manager = User::factory()->role(Role::AiManager)->create();
    }

    #[Test]
    public function a_section_holding_screens_refuses_to_be_deleted(): void
    {
        // الحذف كان يمحو الشاشة بوصفها — ساعاتِ توثيق — بلا تحذير.
        KnowledgeScreen::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $this->section->id,
            'name' => 'طلب سحب',
            'description' => 'وصف الشاشة.',
        ]);

        $this->actingAs($this->manager)
            ->delete("/integrations/sections/{$this->section->id}")
            ->assertSessionHasErrors('section');

        $this->assertDatabaseHas('project_sections', ['id' => $this->section->id]);
        $this->assertSame(1, KnowledgeScreen::query()->count());
    }

    #[Test]
    public function a_section_holding_knowledge_refuses_to_be_deleted(): void
    {
        // العنصر كان ينجو بـ section_id فارغ فلا يظهر في أي شاشة: بقاءٌ في الصف
        // يعادل الفقد في الأثر.
        $item = KnowledgeItem::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $this->section->id,
            'title' => 'مواعيد الصرف',
            'kind' => KnowledgeKind::Faq,
            'status' => KnowledgeStatus::Published,
            'body' => 'نص',
        ]);

        $this->actingAs($this->manager)
            ->delete("/integrations/sections/{$this->section->id}")
            ->assertSessionHasErrors('section');

        $this->assertSame($this->section->id, $item->fresh()?->section_id);
    }

    #[Test]
    public function an_empty_section_is_deleted_and_audited(): void
    {
        $this->actingAs($this->manager)
            ->delete("/integrations/sections/{$this->section->id}")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('project_sections', ['id' => $this->section->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'integrations.section_delete']);
    }
}
