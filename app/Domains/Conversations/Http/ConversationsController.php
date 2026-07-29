<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Http;

use App\Domains\Administration\Enums\Permission;
use App\Domains\Conversations\Enums\ConversationOutcome;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\ConversationEscalation;
use App\Domains\Conversations\Services\ConversationReport;
use App\Domains\Conversations\Services\EscalationPresenter;
use App\Domains\Providers\Models\AiProvider;
use App\Http\Controllers\Controller;
use App\Support\Enums\EnumPayload;
use App\Support\Http\Period;
use App\Support\Http\SystemStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * شاشة الأداء والمحادثات وتفاصيل المحادثة — وثيقة 06 §6.
 */
class ConversationsController extends Controller
{
    public function index(Request $request): Response
    {
        $period = Period::fromRequest($request);

        $filters = [
            'period' => $period->key,
            'from' => $request->date('from')?->toDateString(),
            'to' => $request->date('to')?->toDateString(),
            'section' => $request->string('section')->trim()->value(),
            'outcome' => $request->string('outcome')->trim()->value(),
            'provider' => $request->string('provider')->trim()->value(),
            'search' => $request->string('search')->trim()->value(),
        ];

        $report = new ConversationReport($period);

        $conversations = $this->baseQuery($period, $filters)
            ->with(['provider:id,name', 'model:id,display_name', 'escalations:id,conversation_id,target'])
            ->latest('started_at')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Conversation $conversation): array => $this->row($conversation));

        return Inertia::render('Conversations/Index', [
            'systemStatus' => SystemStatus::current(),
            'period' => $period->toArray(),
            'periodOptions' => $this->periodOptions(),
            'filters' => $filters,
            'metrics' => $report->metrics($this->baseQuery($period, $filters)),
            'comparison' => $report->metrics($this->baseQuery($period->previous(), $filters)),
            'topIntents' => $report->topIntents($this->baseQuery($period, $filters)),
            'topSections' => $report->topSections($this->baseQuery($period, $filters)),
            'frictionPoints' => $report->frictionPoints($this->baseQuery($period, $filters)),
            'conversations' => $conversations,
            'outcomeOptions' => $this->outcomeOptions(),
            'sectionOptions' => Conversation::query()->distinct()->orderBy('section')->pluck('section'),
            'providerOptions' => AiProvider::query()->orderBy('priority')->pluck('name', 'slug'),
        ]);
    }

    public function show(Request $request, Conversation $conversation): Response
    {
        // النص الخام لمحادثة مستخدم Hi-Share صلاحية منفصلة (وثيقة 05 §8):
        // محلل التكلفة والمراجع الأمني يريان المقاييس والمسار، لا ما قاله المستخدم.
        $canViewContent = $request->user()?->can(Permission::ConversationsViewContent->value) ?? false;

        $conversation->load([
            'provider:id,name',
            'model:id,display_name',
            'messages',
            'events',
            'tools',
            'clicks',
            'escalations',
        ]);

        /** @var ConversationEscalation|null $escalation */
        $escalation = $conversation->escalations->first();

        return Inertia::render('Conversations/Show', [
            'systemStatus' => SystemStatus::current(),
            'conversation' => [
                ...$this->row($conversation),
                'external_user_id' => $conversation->external_user_id,
                'detected_intent' => $conversation->detected_intent,
                'confidence' => $conversation->confidence,
                'resolved_first_answer' => $conversation->resolved_first_answer,
                'understood_intent' => $conversation->understood_intent,
                'rephrased' => $conversation->rephrased,
                'first_response_ms' => $conversation->first_response_ms,
                'avg_response_ms' => $conversation->avg_response_ms,
                'ended_at' => $conversation->ended_at?->toIso8601String(),
                'can_view_content' => $canViewContent,
                'messages' => $conversation->messages->map(fn ($message): array => [
                    'id' => $message->id,
                    'role' => EnumPayload::from($message->role),
                    'content' => $canViewContent ? $message->content : null,
                    'tokens' => $message->tokens,
                    'latency_ms' => $message->latency_ms,
                    'created_at' => $message->created_at->toIso8601String(),
                ])->all(),
                'timeline' => $conversation->events->map(fn ($event): array => [
                    'id' => $event->id,
                    'type' => $event->type,
                    'label' => $event->label,
                    'detail' => $event->detail,
                    'created_at' => $event->created_at->toIso8601String(),
                ])->all(),
                'tools' => $conversation->tools->map(fn ($tool): array => [
                    'id' => $tool->id,
                    'name' => $tool->name,
                    'outcome' => EnumPayload::from($tool->outcome),
                    'duration_ms' => $tool->duration_ms,
                    'error_message' => $tool->error_message,
                    'created_at' => $tool->created_at->toIso8601String(),
                ])->all(),
                'clicks' => $conversation->clicks->map(fn ($click): array => [
                    'id' => $click->id,
                    'screen' => $click->screen,
                    'path' => $click->path,
                    'led_to_resolution' => $click->led_to_resolution,
                    'created_at' => $click->created_at->toIso8601String(),
                ])->all(),
                'escalation_detail' => $escalation === null
                    ? null
                    : EscalationPresenter::row($escalation, $conversation->reference),
            ],
        ]);
    }

    /**
     * @param  array{period: string, from: ?string, to: ?string, section: string, outcome: string, provider: string, search: string}  $filters
     * @return Builder<Conversation>
     */
    private function baseQuery(Period $period, array $filters): Builder
    {
        return Conversation::query()
            ->whereBetween('started_at', [$period->from, $period->to])
            ->when($filters['section'] !== '', fn (Builder $q) => $q->where('section', $filters['section']))
            ->when($filters['outcome'] !== '', fn (Builder $q) => $q->where('outcome', $filters['outcome']))
            ->when($filters['provider'] !== '', fn (Builder $q) => $q->whereHas(
                'provider',
                fn (Builder $p) => $p->where('slug', $filters['provider']),
            ))
            ->when($filters['search'] !== '', function (Builder $q) use ($filters): void {
                $term = '%'.$filters['search'].'%';
                $q->where(fn (Builder $inner) => $inner
                    ->where('reference', 'ilike', $term)
                    ->orWhere('user_label', 'ilike', $term)
                    ->orWhere('detected_intent', 'ilike', $term));
            });
    }

    /**
     * صف الجدول — أعمدة وثيقة 06 §6 بالترتيب نفسه.
     *
     * @return array<string, mixed>
     */
    private function row(Conversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'reference' => $conversation->reference,
            'user_label' => $conversation->user_label,
            'section' => $conversation->section,
            'assistant' => $conversation->assistant,
            'level' => EnumPayload::from($conversation->level),
            'provider' => $conversation->provider?->name,
            'model' => $conversation->model?->display_name,
            'duration_seconds' => $conversation->duration_seconds,
            'message_count' => $conversation->message_count,
            'total_tokens' => $conversation->total_tokens,
            'cost' => (float) $conversation->cost,
            'outcome' => EnumPayload::from($conversation->outcome),
            'escalation' => EnumPayload::fromNullable($conversation->escalations->first()?->target),
            'rating' => $conversation->rating === null ? null : (float) $conversation->rating,
            'started_at' => $conversation->started_at->toIso8601String(),
        ];
    }

    /** @return list<array{value: string, label: string}> */
    private function periodOptions(): array
    {
        return array_map(
            fn (string $key, string $label): array => ['value' => $key, 'label' => $label],
            array_keys(Period::OPTIONS),
            array_values(Period::OPTIONS),
        );
    }

    /** @return list<array{value: string, label: string}> */
    private function outcomeOptions(): array
    {
        return array_map(
            fn (ConversationOutcome $outcome): array => [
                'value' => $outcome->value,
                'label' => $outcome->label(),
            ],
            ConversationOutcome::cases(),
        );
    }
}
