<?php

declare(strict_types=1);

namespace Tests\Feature\Assistants;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Enums\KnowledgeKind;
use App\Domains\Knowledge\Enums\KnowledgeStatus;
use App\Domains\Knowledge\Models\KnowledgeItem;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Database\Seeders\SectionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SectionDeduplicationTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = ProjectFactory::default();
    }

    #[Test]
    public function the_database_now_refuses_a_second_section_with_the_same_name(): void
    {
        // الفهرس الفريد هو ما يمنع تكرار ٣٢ قسمًا مكان ١٦ من الحدوث ثانيةً.
        $this->section('المحفظة');

        $this->expectException(QueryException::class);

        $this->section('المحفظة');
    }

    #[Test]
    public function the_same_name_may_live_in_two_different_projects(): void
    {
        $other = Project::factory()->create(['slug' => 'second', 'sort_order' => 3]);

        $this->section('المحفظة');

        ProjectSection::query()->create([
            'project_id' => $other->id,
            'name' => 'المحفظة',
            'slug' => 'wallet',
        ]);

        $this->assertSame(2, ProjectSection::query()->where('name', 'المحفظة')->count());
    }

    #[Test]
    public function reseeding_updates_the_existing_rows_instead_of_adding_a_second_set(): void
    {
        // هذا ما انكسر: تغيّرت دالة اشتقاق الرابط فلم يجد المزارع صفوفه.
        $hiShare = $this->project;

        $this->seed(SectionsSeeder::class);
        $first = ProjectSection::query()->forProject($hiShare)->count();

        $this->seed(SectionsSeeder::class);
        $second = ProjectSection::query()->forProject($hiShare)->count();

        $this->assertSame($first, $second);
        $this->assertGreaterThan(0, $first);
    }

    #[Test]
    public function reseeding_after_a_slug_change_still_finds_the_row_by_its_name(): void
    {
        $hiShare = $this->project;

        $this->seed(SectionsSeeder::class);

        $section = ProjectSection::query()
            ->forProject($hiShare)
            ->where('name', 'المحفظة')
            ->firstOrFail();

        // محاكاة الحالة القديمة: رابط لاتيني مختلف تمامًا عن الاشتقاق الحالي.
        $section->forceFill(['slug' => 'legacy-wallet', 'description' => null])->save();

        $this->seed(SectionsSeeder::class);

        $this->assertSame(
            1,
            ProjectSection::query()->forProject($hiShare)->where('name', 'المحفظة')->count(),
        );

        $this->assertNotNull($section->fresh()?->description);
    }

    #[Test]
    public function the_screen_explains_the_duplicate_in_arabic_rather_than_failing(): void
    {
        $this->section('المحفظة');

        $manager = User::factory()->role(Role::AiManager)->create();

        $this->actingAs($manager)
            ->post('/integrations/sections', ['name' => 'المحفظة'])
            ->assertSessionHasErrors(['name' => 'يوجد قسم بهذا الاسم في هذا المشروع.']);
    }

    #[Test]
    public function renaming_a_section_to_its_own_name_is_not_a_duplicate(): void
    {
        $section = $this->section('المحفظة');
        $manager = User::factory()->role(Role::AiManager)->create();

        $this->actingAs($manager)
            ->put("/integrations/sections/{$section->id}", [
                'name' => 'المحفظة',
                'description' => 'وصف جديد',
                'sort_order' => 1,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('وصف جديد', $section->fresh()?->description);
    }

    #[Test]
    public function knowledge_attached_to_a_section_survives_the_merge(): void
    {
        // الهجرة تُبقي الأقدم لأنه الصف الذي عُلِّقت عليه المعرفة.
        $section = $this->section('المحفظة');

        KnowledgeItem::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $section->id,
            'title' => 'مواعيد الصرف',
            'kind' => KnowledgeKind::Faq,
            'status' => KnowledgeStatus::Published,
            'body' => 'نص',
        ]);

        $this->assertSame(
            1,
            KnowledgeItem::query()->where('section_id', $section->id)->count(),
        );
    }

    private function section(string $name): ProjectSection
    {
        return ProjectSection::query()->create([
            'project_id' => $this->project->id,
            'name' => $name,
            'slug' => 'sec-'.mb_strlen($name).'-'.random_int(1000, 9999),
        ]);
    }
}
