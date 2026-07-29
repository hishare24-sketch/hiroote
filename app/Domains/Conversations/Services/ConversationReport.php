<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Services;

use App\Domains\Conversations\Enums\ConversationOutcome;
use App\Domains\Conversations\Models\Conversation;
use App\Support\Http\Period;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * مؤشرات شاشة الأداء والمحادثات — وثيقة 06 §6.
 *
 * كل نسبة هنا 0–100 ومقرّبة رقمًا عشريًا واحدًا: الواجهة تعرض ولا تحسب، فلا
 * يختلف رقم البطاقة عن رقم الجدول.
 */
final readonly class ConversationReport
{
    public function __construct(private Period $period) {}

    /**
     * @param  Builder<Conversation>  $query  الاستعلام المفلتر نفسه الذي يغذّي الجدول.
     * @return array{
     *     conversations: int, messages: int, unique_users: int,
     *     avg_duration_seconds: int, avg_first_response_ms: int, avg_response_ms: int,
     *     first_answer_resolution_rate: float, unattended_resolution_rate: float,
     *     misunderstanding_rate: float, rephrase_rate: float, abandonment_rate: float,
     *     avg_rating: float|null, rated_count: int
     * }
     */
    public function metrics(Builder $query): array
    {
        /** @var object{
         *     conversations: int, messages: int|null, unique_users: int,
         *     avg_duration: string|null, avg_first: string|null, avg_response: string|null,
         *     first_answer: int|null, resolved: int|null, misunderstood: int|null,
         *     rephrased: int|null, abandoned: int|null,
         *     avg_rating: string|null, rated: int|null
         * } $row */
        $row = (clone $query)
            ->selectRaw('count(*) as conversations')
            ->selectRaw('sum(message_count) as messages')
            ->selectRaw('count(distinct external_user_id) as unique_users')
            ->selectRaw('avg(duration_seconds) as avg_duration')
            ->selectRaw('avg(first_response_ms) as avg_first')
            ->selectRaw('avg(avg_response_ms) as avg_response')
            ->selectRaw('count(*) filter (where resolved_first_answer) as first_answer')
            ->selectRaw('count(*) filter (where outcome = ?) as resolved', [ConversationOutcome::Resolved->value])
            ->selectRaw('count(*) filter (where not understood_intent) as misunderstood')
            ->selectRaw('count(*) filter (where rephrased) as rephrased')
            ->selectRaw('count(*) filter (where outcome = ?) as abandoned', [ConversationOutcome::Abandoned->value])
            ->selectRaw('avg(rating) as avg_rating')
            ->selectRaw('count(rating) as rated')
            ->toBase()
            ->first();

        $total = (int) $row->conversations;

        return [
            'conversations' => $total,
            'messages' => (int) ($row->messages ?? 0),
            'unique_users' => (int) $row->unique_users,
            'avg_duration_seconds' => (int) round((float) ($row->avg_duration ?? 0)),
            'avg_first_response_ms' => (int) round((float) ($row->avg_first ?? 0)),
            'avg_response_ms' => (int) round((float) ($row->avg_response ?? 0)),
            'first_answer_resolution_rate' => $this->rate((int) ($row->first_answer ?? 0), $total),
            'unattended_resolution_rate' => $this->rate((int) ($row->resolved ?? 0), $total),
            'misunderstanding_rate' => $this->rate((int) ($row->misunderstood ?? 0), $total),
            'rephrase_rate' => $this->rate((int) ($row->rephrased ?? 0), $total),
            'abandonment_rate' => $this->rate((int) ($row->abandoned ?? 0), $total),
            'avg_rating' => $row->avg_rating === null ? null : round((float) $row->avg_rating, 1),
            'rated_count' => (int) ($row->rated ?? 0),
        ];
    }

    /**
     * أكثر الأسئلة تكرارًا — وثيقة 06 §6.
     *
     * @param  Builder<Conversation>  $query
     * @return list<array{label: string, caption: string|null, count: int, share: float, tone: string}>
     */
    public function topIntents(Builder $query, int $limit = 5): array
    {
        $total = (clone $query)->whereNotNull('detected_intent')->count();

        /** @var list<object{label: string, total: int, resolved: int|null}> $rows */
        $rows = (clone $query)
            ->whereNotNull('detected_intent')
            ->selectRaw('detected_intent as label, count(*) as total')
            ->selectRaw('count(*) filter (where outcome = ?) as resolved', [ConversationOutcome::Resolved->value])
            ->groupBy('detected_intent')
            ->orderByDesc('total')
            ->limit($limit)
            ->toBase()
            ->get()
            ->all();

        return array_map(function (object $row) use ($total): array {
            $resolution = $this->rate((int) ($row->resolved ?? 0), (int) $row->total);

            return [
                'label' => $row->label,
                'caption' => 'نسبة الحل '.$this->percent($resolution),
                'count' => (int) $row->total,
                'share' => $this->rate((int) $row->total, $total),
                'tone' => $resolution >= 70.0 ? 'success' : ($resolution >= 45.0 ? 'warning' : 'danger'),
            ];
        }, $rows);
    }

    /**
     * أكثر الأقسام نشاطًا — وثيقة 06 §6.
     *
     * @param  Builder<Conversation>  $query
     * @return list<array{label: string, caption: string|null, count: int, share: float, tone: string}>
     */
    public function topSections(Builder $query, int $limit = 5): array
    {
        $total = (clone $query)->count();

        /** @var list<object{label: string, total: int}> $rows */
        $rows = (clone $query)
            ->selectRaw('section as label, count(*) as total')
            ->groupBy('section')
            ->orderByDesc('total')
            ->limit($limit)
            ->toBase()
            ->get()
            ->all();

        return array_map(fn (object $row): array => [
            'label' => $row->label,
            'caption' => null,
            'count' => (int) $row->total,
            'share' => $this->rate((int) $row->total, $total),
            'tone' => 'accent',
        ], $rows);
    }

    /**
     * نقاط التعثر — الأقسام التي تنتهي محادثاتها بانقطاع أو تحويل أكثر من غيرها.
     *
     * ترتّب بعدد المتعثرات لا بنسبتها: قسم بنسبة 100% من محادثتين ليس مشكلة
     * تشغيلية بحجم قسم بنسبة 30% من مئتين.
     *
     * @param  Builder<Conversation>  $query
     * @return list<array{label: string, caption: string|null, count: int, share: float, tone: string}>
     */
    public function frictionPoints(Builder $query, int $limit = 4): array
    {
        /** @var list<object{label: string, total: int, stuck: int|null, intent: string|null}> $rows */
        $rows = (clone $query)
            ->selectRaw('section as label, count(*) as total')
            ->selectRaw('count(*) filter (where outcome in (?, ?, ?)) as stuck', [
                ConversationOutcome::Abandoned->value,
                ConversationOutcome::Human->value,
                ConversationOutcome::Ticket->value,
            ])
            ->selectRaw('mode() within group (order by detected_intent) as intent')
            ->groupBy('section')
            ->orderByDesc(DB::raw('count(*) filter (where outcome in (\''.ConversationOutcome::Abandoned->value.'\', \''.ConversationOutcome::Human->value.'\', \''.ConversationOutcome::Ticket->value.'\'))'))
            ->limit($limit)
            ->toBase()
            ->get()
            ->all();

        return array_map(function (object $row): array {
            $share = $this->rate((int) ($row->stuck ?? 0), (int) $row->total);

            return [
                'label' => $row->label,
                'caption' => $row->intent,
                'count' => (int) ($row->stuck ?? 0),
                'share' => $share,
                'tone' => $share >= 50.0 ? 'danger' : ($share >= 30.0 ? 'warning' : 'neutral'),
            ];
        }, $rows);
    }

    public function period(): Period
    {
        return $this->period;
    }

    private function rate(int $part, int $total): float
    {
        return $total === 0 ? 0.0 : round($part / $total * 100, 1);
    }

    private function percent(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.').'%';
    }
}
