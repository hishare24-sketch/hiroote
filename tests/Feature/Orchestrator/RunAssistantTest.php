<?php

declare(strict_types=1);

namespace Tests\Feature\Orchestrator;

use App\Domains\Analytics\Models\CostUsageRecord;
use App\Domains\Analytics\Models\TokenUsageRecord;
use App\Domains\Assistants\Models\ProjectSection;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Knowledge\Enums\KnowledgeKind;
use App\Domains\Knowledge\Enums\KnowledgeStatus;
use App\Domains\Knowledge\Models\KnowledgeItem;
use App\Domains\Knowledge\Models\KnowledgeScreen;
use App\Domains\Orchestrator\Actions\RunAssistant;
use App\Domains\Orchestrator\DTOs\AssistantRequest;
use App\Domains\Projects\Models\Project;
use App\Domains\Providers\Models\AiProvider;
use App\Domains\Providers\Models\AiProviderCredential;
use Database\Factories\ProjectFactory;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RunAssistantTest extends TestCase
{
    use RefreshDatabase;

    private const ENDPOINT = 'https://api.anthropic.test/v1/messages';

    private Project $project;

    protected function setUp(): void
    {
        parent::setUp();

        $this->project = ProjectFactory::default();

        $section = ProjectSection::query()->create([
            'project_id' => $this->project->id,
            'name' => 'المالية',
            'slug' => 'finance',
            'description' => 'العمليات المالية.',
        ]);

        KnowledgeScreen::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $section->id,
            'key' => 'finance-page',
            'name' => 'المالية',
            'description' => 'تبويبات الدخل والمصروف.',
            'elements' => ['جدول العمليات'],
        ]);

        KnowledgeItem::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $section->id,
            'title' => 'كيف يُسجَّل المصروف',
            'kind' => KnowledgeKind::Faq,
            'status' => KnowledgeStatus::Published,
            'body' => 'من تبويب المصروفات، اختر التصنيف ثم احفظ.',
        ]);

        KnowledgeItem::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $section->id,
            'title' => 'مسودة لم تُعتمد',
            'kind' => KnowledgeKind::Faq,
            'status' => KnowledgeStatus::Draft,
            'body' => 'نصٌّ داخلي لم يعتمده أحد.',
        ]);
    }

    #[Test]
    public function a_successful_call_is_metered_priced_and_recorded(): void
    {
        $this->provider();
        Http::fake([self::ENDPOINT => $this->anthropicReply()]);

        $reply = app(RunAssistant::class)->handle($this->request());

        $this->assertTrue($reply->ok);
        $this->assertSame('مرحبًا', $reply->text);
        $this->assertSame(1200, $reply->inputTokens);
        $this->assertSame(300, $reply->outputTokens);

        // ١٢٠٠ إدخال × ٣$/مليون + ٣٠٠ إخراج × ١٥$/مليون
        $this->assertSame(0.0081, $reply->cost);

        $conversation = Conversation::query()->firstOrFail();
        $this->assertSame(1500, $conversation->total_tokens);
        $this->assertSame('finance-page', $conversation->screen_key);
        $this->assertSame(2, $conversation->messages()->count());

        $this->assertSame(1, TokenUsageRecord::query()->count());
        $this->assertSame(1, CostUsageRecord::query()->count());
    }

    #[Test]
    public function an_unpriced_model_records_no_cost_instead_of_zero(): void
    {
        // صفرٌ في الفاتورة يُقرأ «مجاني» لا «غير مُسعَّر»، ويخفض كل متوسط بعده.
        $provider = $this->provider();
        $provider->models()->update(['input_cost_per_million' => null, 'output_cost_per_million' => null]);

        Http::fake([self::ENDPOINT => $this->anthropicReply()]);

        $reply = app(RunAssistant::class)->handle($this->request());

        $this->assertTrue($reply->ok);
        $this->assertNull($reply->cost);
        $this->assertSame(0, CostUsageRecord::query()->count());
        // والرموز تُسجَّل: غياب التسعير لا يمحو ما استُهلك فعلًا.
        $this->assertSame(1, TokenUsageRecord::query()->count());
    }

    #[Test]
    public function a_reply_without_usage_is_not_counted_as_free_traffic(): void
    {
        // مزوّدٌ لم يرسل محاسبته: تقديرُ الرموز يجعل الفاتورة تخمينًا يُقرأ قياسًا.
        $this->provider();
        Http::fake([self::ENDPOINT => Http::response([
            'content' => [['type' => 'text', 'text' => 'ردّ']],
            'stop_reason' => 'end_turn',
        ])]);

        $reply = app(RunAssistant::class)->handle($this->request());

        $this->assertTrue($reply->ok);
        $this->assertNull($reply->inputTokens);
        $this->assertNull($reply->cost);
        $this->assertSame(0, TokenUsageRecord::query()->count());
        $this->assertSame(0, CostUsageRecord::query()->count());
    }

    #[Test]
    public function a_failing_provider_falls_over_once_and_says_so(): void
    {
        // المزود الثاني بنبذة أخرى: النبذة فريدة، والتحويل الحقيقي يقع بين
        // مزودين مختلفين لا بين نسختين من واحد.
        $primary = $this->provider();
        $secondary = $this->provider('openai', 'https://api.openai.test', active: false);

        Http::fake([
            self::ENDPOINT => Http::response(['error' => ['message' => 'overloaded']], 529),
            'https://api.openai.test/v1/chat/completions' => Http::response([
                'choices' => [['message' => ['content' => 'مرحبًا'], 'finish_reason' => 'stop']],
                'usage' => ['prompt_tokens' => 1200, 'completion_tokens' => 300],
            ]),
        ]);

        $reply = app(RunAssistant::class)->handle($this->request());

        $this->assertTrue($reply->ok);
        $this->assertTrue($reply->failedOver);
        $this->assertSame($secondary->name, $reply->provider);
        $this->assertNotSame($primary->name, $reply->provider);
    }

    #[Test]
    public function both_providers_failing_reports_the_reason_and_records_nothing(): void
    {
        $this->provider();
        $this->provider('openai', 'https://api.openai.test', active: false);

        Http::fake([
            'https://api.anthropic.test/*' => Http::response([], 500),
            'https://api.openai.test/*' => Http::response([], 500),
        ]);

        $reply = app(RunAssistant::class)->handle($this->request());

        $this->assertFalse($reply->ok);
        $this->assertStringContainsString('500', (string) $reply->error);
        // لا محادثة ولا رموز: نداءٌ لم ينجح لا يُسجَّل حركةً.
        $this->assertSame(0, Conversation::query()->count());
        $this->assertSame(0, TokenUsageRecord::query()->count());
    }

    #[Test]
    public function a_provider_without_a_credential_says_so_rather_than_answering(): void
    {
        // ردٌّ مصطنع مكان المفتاح الغائب يبدو إجابةً — وهو أخطر من الإخفاق.
        $provider = $this->provider();
        $provider->credentials()->update(['is_active' => false]);

        Http::fake();

        $reply = app(RunAssistant::class)->handle($this->request());

        $this->assertFalse($reply->ok);
        $this->assertStringContainsString('لا مفتاح فعّال', (string) $reply->error);
        Http::assertNothingSent();
    }

    #[Test]
    public function the_system_prompt_carries_the_screen_and_published_knowledge_only(): void
    {
        $this->provider();
        Http::fake([self::ENDPOINT => $this->anthropicReply()]);

        app(RunAssistant::class)->handle($this->request());

        $sent = Http::recorded()[0][0]->data();
        $system = (string) ($sent['system'] ?? '');

        $this->assertStringContainsString('finance-page', $system);
        $this->assertStringContainsString('كيف يُسجَّل المصروف', $system);
        // المنشور وحده يعبر: المسودة عملٌ لم يعتمده أحد، وإجابةٌ بها تبلغ
        // مستخدمًا حقيقيًّا باسم المنصّة.
        $this->assertStringNotContainsString('مسودة لم تُعتمد', $system);
    }

    #[Test]
    public function knowledge_is_chosen_by_the_question_not_by_recency(): void
    {
        // قسمٌ يفيض عن سقف المرجع: الأحدثُ وحده يُسقط ما يجيب السؤال صامتًا،
        // فيقول المساعد «لا أعرف» عن معرفةٍ منشورة.
        $section = ProjectSection::query()
            ->where('project_id', $this->project->id)
            ->where('name', 'المالية')
            ->firstOrFail();

        // العنصر الذي يجيب — أقدمُ الجميع.
        KnowledgeItem::query()->create([
            'project_id' => $this->project->id,
            'section_id' => $section->id,
            'title' => 'حدّ التحويل بين المشاريع',
            'kind' => KnowledgeKind::Faq,
            'status' => KnowledgeStatus::Published,
            'body' => 'الحدّ الأعلى للتحويل بين المشاريع خمسون ألفًا في اليوم.',
            'updated_at' => now()->subYear(),
        ]);

        // ثلاثون عنصرًا أحدث منه، لا علاقة لها بالسؤال.
        for ($i = 0; $i < 30; $i++) {
            KnowledgeItem::query()->create([
                'project_id' => $this->project->id,
                'section_id' => $section->id,
                'title' => "عنصر حشو {$i}",
                'kind' => KnowledgeKind::Faq,
                'status' => KnowledgeStatus::Published,
                'body' => 'نصٌّ لا صلة له بالسؤال.',
            ]);
        }

        $this->provider();
        Http::fake([self::ENDPOINT => $this->anthropicReply()]);

        app(RunAssistant::class)->handle(new AssistantRequest(
            project: $this->project,
            messages: [['role' => 'user', 'content' => 'ما حدّ التحويل بين المشاريع؟']],
            screenKey: 'finance-page',
            reference: 'relevance-1',
        ));

        $system = (string) (Http::recorded()[0][0]->data()['system'] ?? '');

        $this->assertStringContainsString('حدّ التحويل بين المشاريع', $system);
        // والاقتطاع يُعلَن: مرجعٌ ناقص يظنّه المساعد كاملًا يجعله ينفي وجود
        // ما هو منشور فعلًا.
        $this->assertStringContainsString('المرجع مقتطع', $system);
    }

    private function request(): AssistantRequest
    {
        return new AssistantRequest(
            project: $this->project,
            messages: [['role' => 'user', 'content' => 'كيف أسجّل مصروفًا؟']],
            screenKey: 'finance-page',
            reference: 'test-conv-1',
        );
    }

    private function provider(
        string $slug = 'anthropic',
        string $baseUrl = 'https://api.anthropic.test',
        bool $active = true,
    ): AiProvider {
        $provider = AiProvider::query()->create([
            'ulid' => (string) Str::ulid(),
            'name' => "مزود {$slug}",
            'slug' => $slug,
            'base_url' => $baseUrl,
            'priority' => $active ? 1 : 2,
            'is_enabled' => true,
            'is_active' => $active,
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
            'api_key' => 'sk-fake-orchestrator',
            'key_hint' => 'ator',
            'is_active' => true,
        ]);

        return $provider;
    }

    private function anthropicReply(): PromiseInterface
    {
        return Http::response([
            'content' => [['type' => 'text', 'text' => 'مرحبًا']],
            'stop_reason' => 'end_turn',
            'usage' => ['input_tokens' => 1200, 'output_tokens' => 300],
        ]);
    }
}
