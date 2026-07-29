<?php

declare(strict_types=1);

namespace App\Domains\Orchestrator\Actions;

use App\Domains\Analytics\Models\CostUsageRecord;
use App\Domains\Analytics\Models\TokenUsageRecord;
use App\Domains\Conversations\Enums\ConversationOutcome;
use App\Domains\Conversations\Enums\MessageRole;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Knowledge\Actions\RecordKnowledgeGap;
use App\Domains\Knowledge\Enums\FeedbackKind;
use App\Domains\Knowledge\Enums\FeedbackSource;
use App\Domains\Orchestrator\Contracts\AiDriver;
use App\Domains\Orchestrator\DTOs\AssembledContext;
use App\Domains\Orchestrator\DTOs\AssistantRequest;
use App\Domains\Orchestrator\DTOs\DriverReply;
use App\Domains\Orchestrator\DTOs\OrchestratedReply;
use App\Domains\Orchestrator\Services\ContextAssembler;
use App\Domains\Orchestrator\Services\DriverRegistry;
use App\Domains\Providers\Models\AiModel;
use App\Domains\Providers\Models\AiProvider;
use Illuminate\Support\Str;

/**
 * **البوابة الوحيدة إلى أي مزود ذكاء** — وثيقة 02 §4، وقاعدة CLAUDE.md رقم ٢.
 *
 * كل نداء يمرّ من هنا لأن ثلاثة أشياء يجب ألّا تُنسى مع أيّ منها: المحاسبة
 * (رموزًا وكلفةً)، والتحويل عند إخفاق المزود، والأثر (محادثةً ورسائل). نداءٌ
 * يتجاوز هذه الطبقة يعمل — ويترك اللوحة تعرض أرقامًا أقلّ من الحقيقة، وهو
 * أسوأ من ألّا تعرض شيئًا.
 *
 * والتحويل هنا **محاولةٌ واحدة على المرشّح التالي**: سلسلةٌ بلا حدّ على عطلٍ
 * عامّ تستنفد كل مزود وتضاعف الكلفة قبل أن تُبلغ المستخدم بشيء.
 */
final readonly class RunAssistant
{
    public function __construct(
        private DriverRegistry $registry,
        private ContextAssembler $context,
        private RecordKnowledgeGap $gaps,
    ) {}

    public function handle(AssistantRequest $request): OrchestratedReply
    {
        $provider = AiProvider::query()
            ->where('is_active', true)
            ->where('is_enabled', true)
            ->first();

        if ($provider === null) {
            return OrchestratedReply::failed('لا مزود نشط — فعّل واحدًا من شاشة المزودين.');
        }

        // تُبنى مرةً واحدة: التحويل يغيّر المزود لا المرجع، وإعادةُ بنائها تعني
        // استعلامَ معرفةٍ ثانيًا لأجل النتيجة نفسها.
        $context = $this->context->assemble($request);

        $attempt = $this->attempt($request, $provider, $context);

        if ($attempt['reply']->ok) {
            return $this->record($request, $attempt, false, $context);
        }

        // مرشّحٌ واحد لا سلسلة: الثاني يكشف عطل الأول، والثالث يضاعف الكلفة بلا
        // معلومة جديدة.
        $fallback = AiProvider::query()
            ->where('is_enabled', true)
            ->where('id', '!=', $provider->id)
            ->orderBy('priority')
            ->first();

        if ($fallback === null) {
            return OrchestratedReply::failed(
                $attempt['reply']->error ?? 'أخفق المزود.',
                $provider->name,
                $attempt['reply']->latencyMs,
            );
        }

        $second = $this->attempt($request, $fallback, $context);

        if (! $second['reply']->ok) {
            return OrchestratedReply::failed(
                $second['reply']->error ?? 'أخفق المزودان.',
                $fallback->name,
                $second['reply']->latencyMs,
            );
        }

        return $this->record($request, $second, true, $context);
    }

    /**
     * @return array{reply: DriverReply, provider: AiProvider, model: AiModel|null}
     */
    private function attempt(AssistantRequest $request, AiProvider $provider, AssembledContext $context): array
    {
        $driver = $this->registry->for($provider);
        $model = $provider->models()->where('is_enabled', true)->orderByDesc('is_default')->first();
        $credential = $provider->activeCredential();

        if (! $driver instanceof AiDriver) {
            return [
                'reply' => DriverReply::failure("لا مهايئ للمزود «{$provider->slug}» في هذه النسخة."),
                'provider' => $provider,
                'model' => $model,
            ];
        }

        if (! $model instanceof AiModel) {
            return [
                'reply' => DriverReply::failure("لا نموذج مفعَّل لدى «{$provider->name}»."),
                'provider' => $provider,
                'model' => null,
            ];
        }

        if ($credential === null) {
            // مفتاحٌ غائب يُقال، ولا يُستبدل بردٍّ مصطنع يبدو إجابةً.
            return [
                'reply' => DriverReply::failure("لا مفتاح فعّال لدى «{$provider->name}»."),
                'provider' => $provider,
                'model' => $model,
            ];
        }

        return [
            'reply' => $driver->complete(
                $provider,
                $model,
                $credential->api_key,
                $context->system,
                $request->messages,
                $this->context->maxTokens($request),
                $this->context->temperature($request),
            ),
            'provider' => $provider,
            'model' => $model,
        ];
    }

    /**
     * @param  array{reply: DriverReply, provider: AiProvider, model: AiModel|null}  $attempt
     */
    private function record(
        AssistantRequest $request,
        array $attempt,
        bool $failedOver,
        AssembledContext $context,
    ): OrchestratedReply {
        $reply = $attempt['reply'];
        $provider = $attempt['provider'];
        $model = $attempt['model'];
        $cost = $this->cost($reply, $model);
        $answered = $context->hasReference;
        $conversation = $this->conversation($request, $reply, $provider, $model, $cost, $answered);

        $this->usage($request, $reply, $cost);

        if (! $answered) {
            $this->raiseGap($request, $context, $conversation->id);
        }

        return OrchestratedReply::ok(
            $reply->text,
            $reply->inputTokens,
            $reply->outputTokens,
            $cost,
            $reply->latencyMs,
            $provider->name,
            $model === null ? '—' : $model->display_name,
            $conversation,
            $failedOver,
        );
    }

    /**
     * الكلفة من تسعير النموذج في اللوحة.
     *
     * نموذجٌ بلا تسعير، أو ردٌّ بلا محاسبة كاملة، يعيد **null لا صفرًا**: صفرٌ
     * في الفاتورة يُقرأ «مجاني» لا «غير معلوم».
     */
    private function cost(DriverReply $reply, ?AiModel $model): ?float
    {
        if ($model === null || ! $reply->isMetered()) {
            return null;
        }

        $in = $model->input_cost_per_million;
        $out = $model->output_cost_per_million;

        if ($in === null || $out === null) {
            return null;
        }

        return round(
            ($reply->inputTokens ?? 0) / 1_000_000 * (float) $in
            + ($reply->outputTokens ?? 0) / 1_000_000 * (float) $out,
            6,
        );
    }

    /**
     * يرفع الثغرة إلى الطابور البشري — رصدًا لا حكمًا.
     *
     * المساعد أدرى الناس بأنه أجاب بلا مرجع، وسكوتُه عن ذلك يترك الثغرة تُكتشف
     * حين يشتكي مستخدم، إن اشتكى. ولا يُرفع شيء عند سؤالٍ فارغ: صفٌّ بجسدٍ
     * فارغ لا يقول لمحرِّر المعرفة ما ينقص.
     */
    private function raiseGap(AssistantRequest $request, AssembledContext $context, int $conversationId): void
    {
        $question = trim($request->lastUserMessage());

        if (mb_strlen($question) < 3) {
            return;
        }

        $this->gaps->handle(
            project: $request->project,
            body: $question,
            screen: $context->screen,
            section: $context->section,
            kind: FeedbackKind::Unanswered,
            source: FeedbackSource::Assistant,
            conversationId: $conversationId,
        );
    }

    private function conversation(
        AssistantRequest $request,
        DriverReply $reply,
        AiProvider $provider,
        ?AiModel $model,
        ?float $cost,
        bool $answered,
    ): Conversation {
        $conversation = Conversation::query()->updateOrCreate(
            [
                'project_id' => $request->project->id,
                'reference' => $request->reference ?? 'orc-'.Str::lower((string) Str::ulid()),
            ],
            [
                'ulid' => (string) Str::ulid(),
                'provider_id' => $provider->id,
                'model_id' => $model?->id,
                'screen_key' => $request->screenKey,
                'section' => $request->sectionName ?? 'غير محدد',
                'user_label' => $request->userLabel,
                'external_user_id' => $request->externalUserId,
                'level' => $request->level,
                // «تم الحل» تعني أن المساعد وجد ما يجيب به. مساعدٌ بلا مرجع
                // أُمر أن يقول «لا أعرف»، وطاعتُه ليست حلًّا — وعدُّها حلًّا
                // يرفع نسبةَ الحل عن معرفةٍ لم تُكتب بعد، وهي أكثر رقمٍ يُقرأ.
                'outcome' => $answered ? ConversationOutcome::Resolved : ConversationOutcome::Ticket,
                'message_count' => count($request->messages) + 1,
                'total_tokens' => $reply->totalTokens(),
                'cost' => number_format($cost ?? 0, 4, '.', ''),
                'first_response_ms' => $reply->latencyMs,
                'avg_response_ms' => $reply->latencyMs,
                'started_at' => now(),
                'ended_at' => now(),
            ],
        );

        $conversation->messages()->create([
            'role' => MessageRole::User,
            'content' => $request->lastUserMessage(),
            'tokens' => $reply->inputTokens ?? 0,
        ]);

        $conversation->messages()->create([
            'role' => MessageRole::Assistant,
            'content' => $reply->text,
            'tokens' => $reply->outputTokens ?? 0,
            'latency_ms' => $reply->latencyMs,
        ]);

        return $conversation;
    }

    private function usage(AssistantRequest $request, DriverReply $reply, ?float $cost): void
    {
        if ($reply->isMetered()) {
            TokenUsageRecord::query()->create([
                'project_id' => $request->project->id,
                'input_tokens' => $reply->inputTokens ?? 0,
                'output_tokens' => $reply->outputTokens ?? 0,
                'knowledge_tokens' => 0,
                'attachment_tokens' => 0,
                'tool_tokens' => 0,
                'section' => $request->sectionName,
                'recorded_on' => now()->toDateString(),
            ]);
        }

        // كلفةٌ غير معلومة لا تُسجَّل صفرًا: صفٌّ بصفر يخفض المتوسط ويُقرأ قياسًا.
        if ($cost !== null) {
            CostUsageRecord::query()->create([
                'project_id' => $request->project->id,
                'amount' => number_format($cost, 4, '.', ''),
                'currency' => 'USD',
                'section' => $request->sectionName,
                'operation' => 'assistant',
                'recorded_on' => now()->toDateString(),
            ]);
        }
    }
}
