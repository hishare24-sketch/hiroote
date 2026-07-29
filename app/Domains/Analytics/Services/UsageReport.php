<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\CostUsageRecord;
use App\Domains\Analytics\Models\TokenUsageRecord;
use App\Domains\Analytics\Models\UsageBudget;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Providers\Models\AiProvider;
use App\Support\Http\Period;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * تقارير شاشة الاستهلاك والتكلفة — وثيقة 06 §7.
 *
 * المصدر جدولا `token_usage_records` و`cost_usage_records` المحميان بـ triggers
 * (وثيقة 02 §8): التقرير يقرأ ولا يجمّع في جدول وسيط، فلا ينشأ رقمان للحقيقة.
 */
final readonly class UsageReport
{
    private const TONES = ['accent', 'info', 'success', 'warning', 'neutral', 'danger'];

    public function __construct(private Period $period) {}

    /**
     * البطاقات الرئيسية التسع.
     *
     * @return array{
     *     total_tokens: int, input_tokens: int, output_tokens: int,
     *     knowledge_tokens: int, attachment_tokens: int, tool_tokens: int,
     *     total_cost: float, remaining_balance: float, projected_month_cost: float, currency: string
     * }
     */
    public function totals(): array
    {
        /** @var object{input: string|null, output: string|null, knowledge: string|null, attachment: string|null, tool: string|null} $tokens */
        $tokens = $this->tokenQuery()
            ->selectRaw('sum(input_tokens) as input')
            ->selectRaw('sum(output_tokens) as output')
            ->selectRaw('sum(knowledge_tokens) as knowledge')
            ->selectRaw('sum(attachment_tokens) as attachment')
            ->selectRaw('sum(tool_tokens) as tool')
            ->toBase()
            ->first();

        $input = (int) ($tokens->input ?? 0);
        $output = (int) ($tokens->output ?? 0);
        $knowledge = (int) ($tokens->knowledge ?? 0);
        $attachment = (int) ($tokens->attachment ?? 0);
        $tool = (int) ($tokens->tool ?? 0);

        return [
            'total_tokens' => $input + $output + $knowledge + $attachment + $tool,
            'input_tokens' => $input,
            'output_tokens' => $output,
            'knowledge_tokens' => $knowledge,
            'attachment_tokens' => $attachment,
            'tool_tokens' => $tool,
            'total_cost' => $this->costSum($this->costQuery()),
            'remaining_balance' => (float) AiProvider::query()->sum('balance'),
            'projected_month_cost' => $this->projectedMonthCost(),
            'currency' => (string) config('hiroote.currency', 'SAR'),
        ];
    }

    /**
     * تفصيل التوكن حسب النوع — يقابل بطاقات «إدخال/إخراج/معرفة/مرفقات/أدوات».
     *
     * @return list<array{key: string, label: string, tokens: int, share: float, tone: string}>
     */
    public function tokenBreakdown(): array
    {
        $totals = $this->totals();
        $grand = $totals['total_tokens'];

        $rows = [
            ['key' => 'input', 'label' => 'توكن الإدخال', 'tokens' => $totals['input_tokens'], 'tone' => 'accent'],
            ['key' => 'output', 'label' => 'توكن الإخراج', 'tokens' => $totals['output_tokens'], 'tone' => 'info'],
            ['key' => 'knowledge', 'label' => 'توكن المعرفة', 'tokens' => $totals['knowledge_tokens'], 'tone' => 'success'],
            ['key' => 'attachment', 'label' => 'توكن المرفقات', 'tokens' => $totals['attachment_tokens'], 'tone' => 'warning'],
            ['key' => 'tool', 'label' => 'توكن الأدوات', 'tokens' => $totals['tool_tokens'], 'tone' => 'neutral'],
        ];

        return array_map(fn (array $row): array => [
            'key' => $row['key'],
            'label' => $row['label'],
            'tokens' => $row['tokens'],
            'share' => $this->rate($row['tokens'], $grand),
            'tone' => $row['tone'],
        ], $rows);
    }

    /**
     * الاستهلاك عبر الزمن — نقطة لكل يوم في المدى، بما فيها الأيام الصفرية.
     *
     * الأيام الفارغة تُملأ عمدًا: منحنى يقفز فوق يوم بلا استهلاك يقرأ كأن
     * الاستهلاك استمر، وهو كذب بصري.
     *
     * @return list<array{date: string, tokens: int, cost: float}>
     */
    public function series(): array
    {
        /** @var array<string, int> $tokens */
        $tokens = $this->tokenQuery()
            ->selectRaw('recorded_on, sum(input_tokens + output_tokens + knowledge_tokens + attachment_tokens + tool_tokens) as total')
            ->groupBy('recorded_on')
            ->toBase()
            ->pluck('total', 'recorded_on')
            ->map(fn (mixed $value): int => (int) $value)
            ->all();

        /** @var array<string, float> $costs */
        $costs = $this->costQuery()
            ->selectRaw('recorded_on, sum(amount) as total')
            ->groupBy('recorded_on')
            ->toBase()
            ->pluck('total', 'recorded_on')
            ->map(fn (mixed $value): float => (float) $value)
            ->all();

        $points = [];
        $cursor = $this->period->from->copy()->startOfDay();
        $end = $this->period->to->copy()->startOfDay();

        while ($cursor->lessThanOrEqualTo($end)) {
            $key = $cursor->toDateString();
            $points[] = [
                'date' => $key,
                'tokens' => $tokens[$key] ?? 0,
                'cost' => round($costs[$key] ?? 0.0, 4),
            ];
            $cursor->addDay();
        }

        return $points;
    }

    /**
     * مقارنة الفترة الحالية بالسابقة.
     *
     * @return array{
     *     current_tokens: int, previous_tokens: int, current_cost: float, previous_cost: float,
     *     tokens_change: float|null, cost_change: float|null
     * }
     */
    public function comparison(): array
    {
        $previous = $this->period->previous();

        $currentTokens = $this->totals()['total_tokens'];
        $previousTokens = (int) TokenUsageRecord::query()
            ->whereBetween('created_at', [$previous->from, $previous->to])
            ->selectRaw('sum(input_tokens + output_tokens + knowledge_tokens + attachment_tokens + tool_tokens) as total')
            ->toBase()
            ->value('total');

        $currentCost = $this->costSum($this->costQuery());
        $previousCost = (float) CostUsageRecord::query()
            ->whereBetween('created_at', [$previous->from, $previous->to])
            ->sum('amount');

        return [
            'current_tokens' => $currentTokens,
            'previous_tokens' => $previousTokens,
            'current_cost' => round($currentCost, 2),
            'previous_cost' => round($previousCost, 2),
            'tokens_change' => $this->change((float) $currentTokens, (float) $previousTokens),
            'cost_change' => $this->change($currentCost, $previousCost),
        ];
    }

    /**
     * توزيع التكلفة حسب المزود — وثيقة 06 §7.
     *
     * @return list<array{label: string, tokens: int, cost: float, share: float, tone: string}>
     */
    public function byProvider(): array
    {
        /** @var list<object{label: string|null, cost: string|null, tokens: string|null}> $rows */
        $rows = $this->costQuery()
            ->leftJoin('ai_providers', 'ai_providers.id', '=', 'cost_usage_records.provider_id')
            ->selectRaw('ai_providers.name as label, sum(cost_usage_records.amount) as cost')
            ->selectRaw('(select coalesce(sum(t.input_tokens + t.output_tokens + t.knowledge_tokens + t.attachment_tokens + t.tool_tokens), 0) from token_usage_records t where t.provider_id = ai_providers.id) as tokens')
            ->groupBy('ai_providers.id', 'ai_providers.name')
            ->orderByDesc('cost')
            ->toBase()
            ->get()
            ->all();

        return $this->slices($rows);
    }

    /**
     * توزيع التوكن حسب القسم.
     *
     * @return list<array{label: string, tokens: int, cost: float, share: float, tone: string}>
     */
    public function bySection(): array
    {
        /** @var list<object{label: string|null, cost: string|null, tokens: string|null}> $rows */
        $rows = $this->tokenQuery()
            ->selectRaw('section as label')
            ->selectRaw('sum(input_tokens + output_tokens + knowledge_tokens + attachment_tokens + tool_tokens) as tokens')
            ->selectRaw('0 as cost')
            ->whereNotNull('section')
            ->groupBy('section')
            ->orderByDesc('tokens')
            ->toBase()
            ->get()
            ->all();

        return $this->slices($rows, byTokens: true);
    }

    /**
     * توزيع التوكن حسب النموذج.
     *
     * @return list<array{label: string, tokens: int, cost: float, share: float, tone: string}>
     */
    public function byModel(): array
    {
        /** @var list<object{label: string|null, cost: string|null, tokens: string|null}> $rows */
        $rows = $this->tokenQuery()
            ->leftJoin('ai_models', 'ai_models.id', '=', 'token_usage_records.model_id')
            ->selectRaw('ai_models.display_name as label')
            ->selectRaw('sum(token_usage_records.input_tokens + token_usage_records.output_tokens + token_usage_records.knowledge_tokens + token_usage_records.attachment_tokens + token_usage_records.tool_tokens) as tokens')
            ->selectRaw('0 as cost')
            ->groupBy('ai_models.id', 'ai_models.display_name')
            ->orderByDesc('tokens')
            ->toBase()
            ->get()
            ->all();

        return $this->slices($rows, byTokens: true);
    }

    /**
     * متوسط تكلفة المحادثة والرد والمستخدم.
     *
     * @return array{cost_per_conversation: float, cost_per_response: float, cost_per_user: float}
     */
    public function averages(): array
    {
        $cost = $this->costSum($this->costQuery());

        $conversations = Conversation::query()
            ->whereBetween('started_at', [$this->period->from, $this->period->to]);

        $count = (clone $conversations)->count();
        $responses = (int) (clone $conversations)->sum('message_count');
        $users = (clone $conversations)->distinct()->count('external_user_id');

        return [
            'cost_per_conversation' => $count === 0 ? 0.0 : round($cost / $count, 4),
            // نصف الرسائل تقريبًا ردود من المساعد؛ التقسيم على الردود لا الرسائل.
            'cost_per_response' => $responses === 0 ? 0.0 : round($cost / max(1, (int) round($responses / 2)), 4),
            'cost_per_user' => $users === 0 ? 0.0 : round($cost / $users, 4),
        ];
    }

    /**
     * أكثر العمليات تكلفة — وثيقة 06 §7.
     *
     * @return list<array{label: string, section: string|null, count: int, total_cost: float, avg_cost: float}>
     */
    public function costlyOperations(int $limit = 6): array
    {
        /** @var list<object{label: string|null, section: string|null, total: int, cost: string|null}> $rows */
        $rows = $this->costQuery()
            ->selectRaw('coalesce(operation, \'غير مصنّف\') as label, section, count(*) as total, sum(amount) as cost')
            ->groupBy('operation', 'section')
            ->orderByDesc('cost')
            ->limit($limit)
            ->toBase()
            ->get()
            ->all();

        return array_map(fn (object $row): array => [
            'label' => $row->label ?? 'غير مصنّف',
            'section' => $row->section,
            'count' => (int) $row->total,
            'total_cost' => round((float) ($row->cost ?? 0), 2),
            'avg_cost' => $row->total > 0 ? round((float) ($row->cost ?? 0) / (int) $row->total, 4) : 0.0,
        ], $rows);
    }

    /**
     * تنبيه الانحراف عن الميزانية — وثيقة 06 §7.
     *
     * @return array{
     *     monthly_limit: float, spent: float, consumed_percent: float,
     *     warn_at_percent: int, critical_at_percent: int, hard_stop: bool,
     *     tone: string, message: string, currency: string
     * }|null
     */
    public function budget(): ?array
    {
        $budget = UsageBudget::query()->where('scope', 'platform')->whereNull('scope_key')->first();

        if ($budget === null) {
            return null;
        }

        // الميزانية شهرية دائمًا — تُقاس على الشهر الجاري لا على مدى الفلتر.
        $spent = (float) CostUsageRecord::query()
            ->whereBetween('recorded_on', [
                Carbon::now()->startOfMonth()->toDateString(),
                Carbon::now()->endOfMonth()->toDateString(),
            ])
            ->sum('amount');

        $limit = (float) $budget->monthly_limit;
        $percent = $limit <= 0.0 ? 0.0 : round($spent / $limit * 100, 1);

        [$tone, $message] = match (true) {
            $percent >= (float) $budget->critical_at_percent => [
                'danger',
                'تجاوز الاستهلاك عتبة الإنذار — راجع الميزانية أو أوقف العمليات غير الضرورية.',
            ],
            $percent >= (float) $budget->warn_at_percent => [
                'warning',
                'الاستهلاك يقترب من السقف الشهري — تابعه يوميًا حتى نهاية الشهر.',
            ],
            default => ['success', 'الاستهلاك ضمن الميزانية الشهرية.'],
        };

        return [
            'monthly_limit' => $limit,
            'spent' => round($spent, 2),
            'consumed_percent' => $percent,
            'warn_at_percent' => $budget->warn_at_percent,
            'critical_at_percent' => $budget->critical_at_percent,
            'hard_stop' => $budget->hard_stop,
            'tone' => $tone,
            'message' => $message,
            'currency' => $budget->currency,
        ];
    }

    /** @return Builder<TokenUsageRecord> */
    private function tokenQuery(): Builder
    {
        return TokenUsageRecord::query()
            ->whereBetween('recorded_on', [
                $this->period->from->toDateString(),
                $this->period->to->toDateString(),
            ]);
    }

    /** @return Builder<CostUsageRecord> */
    private function costQuery(): Builder
    {
        return CostUsageRecord::query()
            ->whereBetween('recorded_on', [
                $this->period->from->toDateString(),
                $this->period->to->toDateString(),
            ]);
    }

    /** @param Builder<CostUsageRecord> $query */
    private function costSum(Builder $query): float
    {
        return round((float) $query->sum('amount'), 4);
    }

    /**
     * التكلفة المتوقعة حتى نهاية الشهر = المصروف + (المعدل اليومي × الأيام المتبقية).
     */
    private function projectedMonthCost(): float
    {
        $now = Carbon::now();

        $spent = (float) CostUsageRecord::query()
            ->whereBetween('recorded_on', [$now->copy()->startOfMonth()->toDateString(), $now->toDateString()])
            ->sum('amount');

        $elapsed = max(1, $now->day);
        $remaining = max(0, $now->daysInMonth - $now->day);

        return round($spent + ($spent / $elapsed * $remaining), 2);
    }

    /**
     * @param  list<object{label: string|null, cost: string|null, tokens: string|null}>  $rows
     * @return list<array{label: string, tokens: int, cost: float, share: float, tone: string}>
     */
    private function slices(array $rows, bool $byTokens = false): array
    {
        $total = 0.0;

        foreach ($rows as $row) {
            $total += $byTokens ? (float) ($row->tokens ?? 0) : (float) ($row->cost ?? 0);
        }

        $slices = [];

        foreach ($rows as $index => $row) {
            $value = $byTokens ? (float) ($row->tokens ?? 0) : (float) ($row->cost ?? 0);

            $slices[] = [
                'label' => $row->label ?? 'غير محدد',
                'tokens' => (int) ($row->tokens ?? 0),
                'cost' => round((float) ($row->cost ?? 0), 2),
                'share' => $total <= 0.0 ? 0.0 : round($value / $total * 100, 1),
                'tone' => self::TONES[$index % count(self::TONES)],
            ];
        }

        return $slices;
    }

    private function rate(int $part, int $total): float
    {
        return $total === 0 ? 0.0 : round($part / $total * 100, 1);
    }

    private function change(float $current, float $previous): ?float
    {
        return $previous <= 0.0 ? null : round(($current - $previous) / $previous * 100, 1);
    }
}
