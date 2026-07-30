<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Domains\Assistants\Enums\ChatChannelKind;
use App\Domains\Assistants\Enums\ChatScope;
use App\Domains\Assistants\Models\ProjectChatPolicy;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Integrations\Models\ProjectApiKey;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Projects\Models\Project;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * «لا سياسة» ليست «أُغلق كل شيء».
 *
 * كان الفرعان يخرجان بالشكل نفسه، فمشروعٌ يصل الحقل لأول مرة يُطفئ مساعده
 * ودعمه ومحادثات أعضائه دفعةً واحدة — **بلا رسالة خطأ**، لأن الحمولة صحيحةٌ
 * نحويًّا وتقول قرارًا لم يُتَّخذ. وكشفه موازين في الإنتاج
 * (hishare24-sketch/hiroote#8) لأن هذا العقد لم يكن عليه اختبارٌ واحد.
 */
class ChatPolicyIsNotConfusedWithSilenceTest extends TestCase
{
    use RefreshDatabase;

    private Project $project;

    private string $key;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = ProjectFactory::default();

        $minted = ProjectApiKey::mint();

        ProjectApiKey::query()->create([
            'project_id' => $this->project->id,
            'name' => 'مفتاح اختبار',
            'prefix' => $minted['prefix'],
            'hash' => $minted['hash'],
        ]);

        $this->key = $minted['token'];

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
    }

    #[Test]
    public function no_policy_says_so_and_claims_nothing_else(): void
    {
        $chat = $this->context()['chat'];

        $this->assertFalse($chat['configured']);

        // ولا حقلَ آخر: قيمةٌ غائبة تُجبر القارئ على أن يقرّر، وقيمةٌ حاضرة
        // تُقرأ قرارًا. والفرق بينهما هو الفرق بين صمتٍ يُسأل عنه وصمتٍ يُفسَّر.
        $this->assertArrayNotHasKey('enabled', $chat);
        $this->assertArrayNotHasKey('kinds', $chat);
        $this->assertArrayNotHasKey('scopes', $chat);
    }

    #[Test]
    public function a_policy_that_disables_everything_is_marked_configured(): void
    {
        // وهذه هي الحالة التي تُشبه الأولى في الشكل وتخالفها في المعنى: قرارٌ
        // اتُّخذ فعلًا بالإغلاق، ويجب أن يُطاع.
        ProjectChatPolicy::query()->create([
            'project_id' => $this->project->id,
            'is_enabled' => false,
            'channel_kinds' => [],
            'scopes' => [],
            'assistant_participates' => false,
            'attachments_allowed' => false,
            'retention_days' => 0,
        ]);

        $chat = $this->context()['chat'];

        $this->assertTrue($chat['configured']);
        $this->assertFalse($chat['enabled']);
        $this->assertSame([], $chat['kinds']);
    }

    #[Test]
    public function a_live_policy_carries_its_own_values(): void
    {
        ProjectChatPolicy::query()->create([
            'project_id' => $this->project->id,
            'is_enabled' => true,
            'channel_kinds' => [ChatChannelKind::Assistant->value, ChatChannelKind::Group->value],
            'scopes' => [ChatScope::Project->value],
            'assistant_participates' => true,
            'attachments_allowed' => false,
            'retention_days' => 0,
        ]);

        $chat = $this->context()['chat'];

        $this->assertTrue($chat['configured']);
        $this->assertTrue($chat['enabled']);
        $this->assertSame(['assistant', 'group'], $chat['kinds']);
        // صفرٌ يعني «بلا حدّ» لا «لا تحفظ» — يُرسل null كي لا يُقرأ صفرًا.
        $this->assertNull($chat['retention_days']);
    }

    /** @return array<string, mixed> */
    private function context(): array
    {
        $response = $this->withHeader('Authorization', 'Bearer '.$this->key)
            ->getJson('/api/v1/context?screen=finance-page');

        $response->assertOk();

        /** @var array<string, mixed> $payload */
        $payload = $response->json();

        return $payload;
    }
}
