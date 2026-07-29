<?php

declare(strict_types=1);

namespace Tests\Feature\Orchestrator;

use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Conversations\Enums\ConversationOutcome;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Knowledge\Enums\FeedbackKind;
use App\Domains\Knowledge\Enums\FeedbackSource;
use App\Domains\Knowledge\Enums\KnowledgeKind;
use App\Domains\Knowledge\Enums\KnowledgeStatus;
use App\Domains\Knowledge\Models\KnowledgeFeedback;
use App\Domains\Knowledge\Models\KnowledgeItem;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Orchestrator\Actions\RunAssistant;
use App\Domains\Orchestrator\DTOs\AssistantRequest;
use App\Domains\Projects\Models\Project;
use App\Domains\Providers\Models\AiProvider;
use App\Domains\Providers\Models\AiProviderCredential;
use Database\Factories\ProjectFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * ردٌّ بلا مرجع ليس حلًّا — ويرفع ثغرته بنفسه.
 *
 * كانت كل محادثة ناجحة تُسجَّل «تم الحل»، حتى حين لم يكن في يد المساعد شيء
 * يجيب به. ونسبةُ الحل أكثر رقمٍ يُقرأ في اللوحة: رفعُها عن معرفةٍ لم تُكتب
 * بعد يجعل الشاشة تطمئن المالك إلى قسمٍ يخذل مستخدميه كل يوم.
 *
 * ولم يكن المساعد يرفع ثغرةً قط، مع أنه أدرى الناس بأنه أجاب بلا مرجع —
 * فتبقى الثغرة حتى يشتكي مستخدم، إن اشتكى.
 */
class UnansweredIsNotResolvedTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://api.anthropic.test/v1/messages';

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

        KnowledgeScreen::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $this->section->id,
            'key' => 'finance-page',
            'name' => 'المالية',
            'description' => 'تبويبات الدخل والمصروف.',
        ]);

        $this->provider();
        Http::fake([self::ENDPOINT => Http::response([
            'content' => [['type' => 'text', 'text' => 'لا أعرف.']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 100, 'output_tokens' => 20],
        ])]);
    }

    #[Test]
    public function a_section_without_published_knowledge_is_not_recorded_resolved(): void
    {
        app(RunAssistant::class)->handle($this->ask('كيف أسجّل مصروفًا؟'));

        $this->assertSame(
            ConversationOutcome::Ticket,
            Conversation::query()->firstOrFail()->outcome,
            'محادثةٌ بلا مرجع سُجّلت «تم الحل» — فترتفع نسبة الحل عن معرفةٍ لم تُكتب.',
        );
    }

    #[Test]
    public function the_assistant_raises_the_gap_itself_with_the_question_asked(): void
    {
        app(RunAssistant::class)->handle($this->ask('كيف أسجّل مصروفًا؟'));

        $gap = KnowledgeFeedback::query()->firstOrFail();

        $this->assertSame('كيف أسجّل مصروفًا؟', $gap->body);
        $this->assertSame(FeedbackKind::Unanswered, $gap->kind);
        $this->assertSame(FeedbackSource::Assistant, $gap->source);
        $this->assertSame($this->section->id, $gap->section_id);
        // الثغرة موصولة بمحادثتها: رصدٌ بلا محادثة يُقرأ بلا سياق.
        $this->assertSame(Conversation::query()->firstOrFail()->id, $gap->conversation_id);
    }

    #[Test]
    public function published_knowledge_makes_the_answer_a_real_resolution_and_raises_nothing(): void
    {
        KnowledgeItem::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $this->section->id,
            'title' => 'تسجيل المصروف',
            'kind' => KnowledgeKind::Faq,
            'status' => KnowledgeStatus::Published,
            'body' => 'من تبويب المصروفات، اختر التصنيف ثم احفظ.',
        ]);

        app(RunAssistant::class)->handle($this->ask('كيف أسجّل مصروفًا؟'));

        $this->assertSame(ConversationOutcome::Resolved, Conversation::query()->firstOrFail()->outcome);
        $this->assertSame(0, KnowledgeFeedback::query()->count());
    }

    #[Test]
    public function a_draft_alone_is_not_a_reference(): void
    {
        // المسودة عملٌ لم يعتمده أحد ولا تعبر إلى التعليمات؛ فعدُّها مرجعًا
        // يخفي ثغرةً قائمة خلف عملٍ لم يُنشر بعد.
        KnowledgeItem::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $this->section->id,
            'title' => 'مسودة لم تُعتمد',
            'kind' => KnowledgeKind::Faq,
            'status' => KnowledgeStatus::Draft,
            'body' => 'نصٌّ داخلي.',
        ]);

        app(RunAssistant::class)->handle($this->ask('كيف أسجّل مصروفًا؟'));

        $this->assertSame(ConversationOutcome::Ticket, Conversation::query()->firstOrFail()->outcome);
        $this->assertSame(1, KnowledgeFeedback::query()->count());
    }

    #[Test]
    public function the_same_question_twice_is_one_row_with_a_counter(): void
    {
        // سبعُ نسخ من سؤال واحد تُخفي أنه سؤال واحد سأله سبعة — وهي القاعدة
        // نفسها التي يتبعها جسر الوارد، لا قاعدةً ثانية تخالفها.
        app(RunAssistant::class)->handle($this->ask('كيف أسجّل مصروفًا؟', 'conv-1'));
        app(RunAssistant::class)->handle($this->ask('كيف أسجّل مصروفًا؟', 'conv-2'));

        $this->assertSame(1, KnowledgeFeedback::query()->count());
        $this->assertSame(2, KnowledgeFeedback::query()->firstOrFail()->occurrences);
    }

    #[Test]
    public function a_gap_without_a_screen_still_dedupes(): void
    {
        // `where('screen_id', null)` في SQL الخام لا يطابق شيئًا أبدًا، فيولد
        // صفًّا جديدًا لكل سؤال بلا شاشة — ويبدو طابور الرصد فائضًا بأسئلة
        // مختلفة وهو سؤالٌ واحد تكرّر.
        $request = new AssistantRequest(
            project: $this->project,
            messages: [['role' => 'user', 'content' => 'سؤال بلا شاشة']],
            reference: 'no-screen-1',
        );

        app(RunAssistant::class)->handle($request);
        app(RunAssistant::class)->handle($request);

        $this->assertSame(1, KnowledgeFeedback::query()->count());

        $gap = KnowledgeFeedback::query()->firstOrFail();
        $this->assertSame(2, $gap->occurrences);
        $this->assertNull($gap->screen_id);
    }

    private function ask(string $question, string $reference = 'gap-1'): AssistantRequest
    {
        return new AssistantRequest(
            project: $this->project,
            messages: [['role' => 'user', 'content' => $question]],
            screenKey: 'finance-page',
            reference: $reference,
        );
    }

    private function provider(): AiProvider
    {
        $provider = AiProvider::query()->create([
            'ulid' => (string) Str::ulid(),
            'name' => 'مزود الاختبار',
            'slug' => 'anthropic',
            'base_url' => 'https://api.anthropic.test',
            'priority' => 1,
            'is_enabled' => true,
            'is_active' => true,
        ]);

        $provider->models()->create([
            'name' => 'claude-sonnet-test',
            'display_name' => 'Sonnet',
            'is_enabled' => true,
            'is_default' => true,
            'input_cost_per_million' => '3.0000',
            'output_cost_per_million' => '15.0000',
        ]);

        AiProviderCredential::query()->create([
            'provider_id' => $provider->id,
            'label' => 'اختبار',
            'api_key' => 'sk-fake-gap',
            'key_hint' => 'gap',
            'is_active' => true,
        ]);

        return $provider;
    }
}
