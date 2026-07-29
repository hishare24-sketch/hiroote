<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Domains\Integrations\Models\ProjectApiKey;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Projects\Models\Project;
use Database\Seeders\MawazinScreensSeeder;
use Database\Seeders\ProjectsSeeder;
use Database\Seeders\SectionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * جسر الوارد لا يعمل بأن يُبنى، بل بأن يجد ما يجيب به.
 *
 * كان مشروع موازين يحمل **صفر شاشة بمفتاح**، فيردّ `GET /api/v1/context` بـ٤٠٤
 * على كل نداء مهما صحّ المفتاح والحدّ والمصادقة — جسرٌ سليمٌ فوق ضفةٍ خالية.
 */
class MawazinInboundContextTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ProjectsSeeder::class);
        $this->seed(SectionsSeeder::class);
        $this->seed(MawazinScreensSeeder::class);

        $this->project = Project::query()->where('slug', 'mawazin')->firstOrFail();

        $minted = ProjectApiKey::mint();
        ProjectApiKey::query()->create([
            'project_id' => $this->project->id,
            'name' => 'مفتاح اختبار',
            'prefix' => $minted['prefix'],
            'hash' => $minted['hash'],
        ]);

        $this->token = $minted['token'];
    }

    #[Test]
    public function every_seeded_screen_answers_the_context_endpoint(): void
    {
        // شاشةٌ مزروعة تحت قسمٍ غير موجود تُحفظ ولا تُقرأ — والفحص الوحيد الذي
        // يكشفها هو أن يُسأل عنها المسار نفسه.
        $screens = KnowledgeScreen::query()
            ->forProject($this->project)
            ->whereNotNull('key')
            ->get();

        $this->assertGreaterThanOrEqual(20, $screens->count());

        foreach ($screens as $screen) {
            $this->withToken($this->token)
                ->getJson('/api/v1/context?screen='.urlencode((string) $screen->key))
                ->assertOk()
                ->assertJsonPath('screen.key', $screen->key)
                ->assertJsonPath('screen.name', $screen->name)
                ->assertJsonStructure(['project', 'screen', 'section', 'knowledge']);
        }
    }

    #[Test]
    public function the_keys_are_mawazins_own_route_names(): void
    {
        // المفتاح اسم مسارٍ موجود في موازين أصلًا، لا اسمًا اخترعناه له: ما
        // نخترعه يلزم الطرف الآخر بخريطة تحويل، وأول تعديلٍ فيها يقطع الصلة
        // صامتًا (وثيقة 07 §2.4).
        $response = $this->withToken($this->token)
            ->getJson('/api/v1/context?screen=finance-page')
            ->assertOk();

        $response->assertJsonPath('screen.name', 'المالية');
        $response->assertJsonPath('project.slug', 'mawazin');
        // الوصف مقروء من قاعدة معرفة موازين نفسها لا مكتوبٌ تخمينًا.
        $this->assertStringContainsString('تبويبات', (string) $response->json('screen.description'));
    }

    #[Test]
    public function a_screen_key_that_mawazin_does_not_have_is_refused_not_guessed(): void
    {
        $this->withToken($this->token)
            ->getJson('/api/v1/context?screen=wallet.withdraw')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'screen_not_found');
    }
}
