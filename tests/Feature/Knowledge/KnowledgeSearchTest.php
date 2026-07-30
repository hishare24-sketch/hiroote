<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Domains\Administration\Enums\Role;
use App\Domains\Administration\Models\User;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Knowledge\Enums\KnowledgeKind;
use App\Domains\Knowledge\Enums\KnowledgeStatus;
use App\Domains\Knowledge\Models\KnowledgeItem;
use App\Domains\Knowledge\Services\KnowledgeSearch;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * البحث في المعرفة — وقاعدته هي قاعدة المساعد نفسها.
 *
 * قسمٌ فيه مئة عنصر لا يُقرأ بالتصفّح، والبحث بقاعدة تخالف قاعدة المساعد يجعل
 * المحرِّر يجد العنصر في الشاشة ويسمع أن المساعد لا يعرفه، فيبحث عن العطل في
 * المعرفة وهو في اختلاف القاعدتين.
 */
class KnowledgeSearchTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private ProjectSection $section;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = ProjectFactory::default();

        $this->section = ProjectSection::query()->create([
            'project_id' => $this->project->id,
            'name' => 'المالية',
            'slug' => 'finance',
            'description' => 'العمليات المالية.',
        ]);
    }

    #[Test]
    public function it_finds_an_item_by_a_word_in_its_body(): void
    {
        $this->item('حدّ التحويل', 'الحدّ الأعلى للتحويل بين المشاريع خمسون ألفًا في اليوم.');
        $this->item('شيء آخر', 'نصٌّ لا صلة له.');

        $results = $this->search('التحويل');

        $this->assertSame(1, $results['total']);
        $this->assertSame('حدّ التحويل', $results['items'][0]['title']);
        $this->assertSame('المالية', $results['items'][0]['section']);
    }

    #[Test]
    public function a_draft_is_shown_but_marked_as_unseen_by_the_assistant(): void
    {
        // محرِّرٌ لا يرى مسوّدته يكتبها ثانيةً؛ ومن يراها بلا وسم يظنّ المساعد
        // يجيب بها، فيبحث عن سبب «الجواب الخاطئ» في مكانٍ آخر.
        $this->item('مسودة التحويل', 'نصٌّ عن التحويل لم يُعتمد.', KnowledgeStatus::Draft);

        $hit = $this->search('التحويل')['items'][0];

        $this->assertFalse($hit['visible_to_assistant']);
        $this->assertSame('مسودة', $hit['status']['label']);
    }

    #[Test]
    public function the_published_comes_before_the_draft(): void
    {
        // المنشور هو ما يجيب به المساعد فعلًا، فهو أولى بأول الشاشة.
        $this->item('مسودة التحويل', 'نصٌّ عن التحويل.', KnowledgeStatus::Draft);
        $this->item('منشور التحويل', 'نصٌّ آخر عن التحويل.');

        $items = $this->search('التحويل')['items'];

        $this->assertSame('منشور التحويل', $items[0]['title']);
    }

    #[Test]
    public function a_truncated_result_set_declares_its_own_total(): void
    {
        // قائمةٌ مبتورة صامتًا تُقرأ «هذا كل ما لديك» — فيكتب المحرِّر عنصرًا
        // موجودًا لأنه لم يره.
        for ($i = 0; $i < 35; $i++) {
            $this->item("عنصر التحويل {$i}", 'نصٌّ عن التحويل.');
        }

        $results = $this->search('التحويل');

        $this->assertSame(35, $results['total']);
        $this->assertSame(30, $results['shown']);
    }

    #[Test]
    public function the_search_never_crosses_into_another_project(): void
    {
        $other = Project::factory()->create(['slug' => 'other', 'sort_order' => 9]);

        $section = ProjectSection::query()->create([
            'project_id' => $other->id,
            'name' => 'مالية الآخر',
            'slug' => 'other-finance',
        ]);

        KnowledgeItem::query()->create([
            'project_id' => $other->id,
            'section_id' => $section->id,
            'title' => 'سرّ التحويل في مشروع آخر',
            'kind' => KnowledgeKind::Faq,
            'status' => KnowledgeStatus::Published,
            'body' => 'نصٌّ عن التحويل يخصّ مشروعًا آخر.',
        ]);

        $this->assertSame(0, $this->search('التحويل')['total']);
    }

    #[Test]
    public function the_excerpt_is_taken_around_the_match_not_from_the_start(): void
    {
        // أولُ النص يتشابه في كل العناصر (مقدّمةٌ واحدة)، فتُقرأ النتائج
        // متطابقةً ويُفتح كلٌّ منها ليُعرف أيّها المقصود.
        $prefix = str_repeat('مقدمة عامة تتكرر في كل عنصر. ', 12);
        $this->item('عنصر طويل', $prefix.'وأما التحويل فحدّه خمسون ألفًا في اليوم.');

        $excerpt = $this->search('التحويل')['items'][0]['excerpt'];

        $this->assertStringContainsString('التحويل', $excerpt);
    }

    #[Test]
    public function the_screen_and_the_assistant_share_one_matching_rule(): void
    {
        // الحارس الحقيقي: قاعدتان تفترقان يومًا تجعلان الشاشة تصف حالةً غير
        // التي يعمل بها المساعد — ولا شيء يكشف الفرق إلا شكوى مستخدم.
        $search = app(KnowledgeSearch::class);

        $terms = $search->terms('ما حدّ التحويل بين المشاريع؟');

        // علامة الاستفهام العربية داخل نطاق `\p{Arabic}`: لصقُها بالكلمة كان
        // يجعل «المشاريع؟» لا تطابق «المشاريع» في أي نصّ — فتُهدر آخر كلمة في
        // كل سؤال، وهي غالبًا أهمّها.
        $this->assertContains('المشاريع', $terms);
        $this->assertNotContains('المشاريع؟', $terms);

        // والشدّة علامةٌ لا حرف: بدونها تنقطع «حدّ» فتصير حرفين وتسقط.
        $this->assertContains('حدّ', $terms);

        // والكلمات الأقصر من ثلاثة أحرف تطابق كل شيء فلا تميّز.
        $this->assertSame([], $search->terms('ما هو ذا؟'));
    }

    /** @return array{total: int, shown: int, items: list<array<string, mixed>>} */
    private function search(string $query): array
    {
        $response = $this->actingAs(User::factory()->role(Role::KnowledgeManager)->create())
            ->get('/knowledge?q='.urlencode($query));

        $response->assertOk();

        /** @var array{total: int, shown: int, items: list<array<string, mixed>>} $results */
        $results = $response->viewData('page')['props']['results'];

        return $results;
    }

    private function item(string $title, string $body, KnowledgeStatus $status = KnowledgeStatus::Published): void
    {
        KnowledgeItem::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $this->section->id,
            'title' => $title,
            'kind' => KnowledgeKind::Faq,
            'status' => $status,
            'body' => $body,
        ]);
    }
}
