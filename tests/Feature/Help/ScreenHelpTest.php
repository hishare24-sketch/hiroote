<?php

declare(strict_types=1);

namespace Tests\Feature\Help;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Enums\KnowledgeKind;
use App\Domains\Knowledge\Enums\KnowledgeStatus;
use App\Domains\Knowledge\Models\KnowledgeItem;
use App\Domains\Projects\Models\Project;
use App\Support\Help\HelpRegistry;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ScreenHelpTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = ProjectFactory::default();
        $this->admin = User::factory()->role(Role::SystemAdmin)->create();
    }

    /**
     * الاختبار الذي يجعل «أيقونة شرح في كل شاشة» وعدًا يُفرض لا نيّةً تُنسى:
     * يزور كل مسار في اللوحة، يقرأ اسم مكوّنه من ردّ Inertia، ويطلب شرحه.
     * شاشةٌ جديدة بلا شرح تسقط هنا قبل أن تصل المشغّل.
     */
    #[Test]
    public function every_screen_the_panel_can_render_has_a_help_topic(): void
    {
        $section = ProjectSection::query()->create([
            'project_id' => $this->project->id,
            'name' => 'المحفظة',
            'slug' => 'wallet',
        ]);

        $item = KnowledgeItem::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $section->id,
            'title' => 'عنصر',
            'kind' => KnowledgeKind::Faq,
            'status' => KnowledgeStatus::Published,
            'body' => 'نص',
        ]);

        $paths = [
            '/',
            '/projects',
            '/conversations',
            '/usage',
            '/providers',
            '/escalations',
            '/assistants',
            '/integrations',
            '/knowledge',
            "/knowledge/sections/{$section->id}",
            "/knowledge/items/{$item->id}/versions",
            '/bridge',
            '/playground',
            '/alerts',
            '/audit',
            '/users',
        ];

        foreach ($paths as $path) {
            $page = $this->actingAs($this->admin)->get($path)->assertOk();

            /** @var array{component: string} $props */
            $props = $page->viewData('page');
            $component = $props['component'];

            $this->actingAs($this->admin)
                ->getJson('/help/topic?screen='.urlencode($component))
                ->assertOk()
                ->assertJsonPath('screen', $component)
                ->assertJsonStructure(['title', 'purpose', 'reading', 'traps']);
        }
    }

    #[Test]
    public function the_note_shown_follows_the_readers_role_in_the_current_project(): void
    {
        // الدور يُحلّ لكل مشروع، فما يُعرض لمحلل التكلفة غير ما يُعرض لمدير الذكاء.
        $analyst = User::factory()->role(Role::CostAnalyst)->create();

        $forAnalyst = $this->actingAs($analyst)
            ->getJson('/help/topic?screen=Usage%2FIndex')
            ->assertOk()
            ->json('role_note');

        $forAdmin = $this->actingAs($this->admin)
            ->getJson('/help/topic?screen=Usage%2FIndex')
            ->assertOk()
            ->json('role_note');

        $this->assertNotNull($forAnalyst);
        $this->assertNull($forAdmin);
    }

    #[Test]
    public function an_unknown_screen_returns_the_standard_error_shape(): void
    {
        $this->actingAs($this->admin)
            ->getJson('/help/topic?screen=Nope%2FMissing')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'help_topic_missing');
    }

    #[Test]
    public function help_is_not_public(): void
    {
        $this->getJson('/help/topic?screen=Overview%2FIndex')->assertUnauthorized();
    }

    #[Test]
    public function no_topic_describes_a_screen_that_does_not_exist(): void
    {
        // الاتجاه الآخر: شرحٌ باقٍ لشاشة حُذفت يصف واجهةً لم يعد لها وجود.
        $components = [];

        foreach (glob(resource_path('js/Pages/**/*.tsx')) ?: [] as $file) {
            $components[] = str_replace(
                [resource_path('js/Pages/'), '.tsx'],
                '',
                $file,
            );
        }

        foreach (array_keys(app(HelpRegistry::class)->all()) as $screen) {
            $this->assertContains(
                $screen,
                $components,
                "الشرح «{$screen}» يصف شاشة غير موجودة.",
            );
        }
    }
}
