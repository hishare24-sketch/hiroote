<?php

declare(strict_types=1);

namespace App\Domains\Conversations\Services;

use App\Domains\Conversations\Enums\ConversationOutcome;
use App\Domains\Conversations\Enums\EscalationSeverity;
use App\Domains\Conversations\Enums\EscalationTarget;
use App\Domains\Conversations\Models\Conversation;
use App\Domains\Conversations\Models\ConversationEscalation;
use App\Domains\Projects\Models\Project;
use App\Support\Enums\EnumPayload;
use App\Support\Http\Period;
use Illuminate\Database\Eloquent\Builder;

/**
 * تقارير شاشة التحويل والتصعيد — وثيقة 06 §10.
 *
 * المسارات الثلاثة تُحسب دائمًا الثلاثة حتى لو كان أحدها صفرًا: بطاقة بصفر
 * تقول «لم يحدث»، وغيابها يقول «لا نعرف» — والفرق مهم للمشغّل.
 */
final readonly class EscalationReport
{
    public function __construct(private Period $period, private Project $project) {}

    /**
     * @return list<array{
     *     target: array{value: string, label: string, tone: string},
     *     count: int, share: float,
     *     avg_wait_seconds: int|null, avg_handling_seconds: int|null, open_count: int
     * }>
     */
    public function paths(): array
    {
        $total = $this->baseQuery()->count();

        return array_map(function (EscalationTarget $target) use ($total): array {
            /** @var object{total: int, open: int|null, avg_wait: string|null, avg_handling: string|null} $row */
            $row = $this->baseQuery()
                ->where('target', $target->value)
                ->selectRaw('count(*) as total')
                ->selectRaw('count(*) filter (where resolved_at is null) as open')
                ->selectRaw('avg(wait_seconds) as avg_wait')
                ->selectRaw('avg(handling_seconds) as avg_handling')
                ->toBase()
                ->first();

            return [
                'target' => EnumPayload::from($target),
                'count' => (int) $row->total,
                'share' => $this->rate((int) $row->total, $total),
                'avg_wait_seconds' => $row->avg_wait === null ? null : (int) round((float) $row->avg_wait),
                'avg_handling_seconds' => $row->avg_handling === null ? null : (int) round((float) $row->avg_handling),
                'open_count' => (int) ($row->open ?? 0),
            ];
        }, EscalationTarget::cases());
    }

    /**
     * مخطط رحلة التحويل — من كل المحادثات إلى الحالة التي ما زالت مفتوحة.
     *
     * @return list<array{label: string, detail: string, count: int, share: float}>
     */
    public function journey(): array
    {
        $conversations = Conversation::query()
            ->forProject($this->project)
            ->whereBetween('started_at', [$this->period->from, $this->period->to]);

        $total = (clone $conversations)->count();
        $escalated = $this->baseQuery()->count();
        $lowConfidence = (clone $conversations)->where('confidence', '<', 70)->count();
        $open = $this->baseQuery()->whereNull('resolved_at')->count();

        $steps = [
            ['label' => 'محادثات الفترة', 'detail' => 'كل المحادثات الداخلة قبل أي تحويل', 'count' => $total],
            ['label' => 'ثقة دون العتبة', 'detail' => 'ثقة النية أقل من 70% — مرشّحة للتحويل', 'count' => $lowConfidence],
            ['label' => 'حُوِّلت فعلًا', 'detail' => 'خرجت من المساعد العام إلى أحد المسارات الثلاثة', 'count' => $escalated],
            ['label' => 'ما زالت مفتوحة', 'detail' => 'لم تُغلق بعد وتحتاج متابعة', 'count' => $open],
        ];

        return array_map(fn (array $step): array => [
            'label' => $step['label'],
            'detail' => $step['detail'],
            'count' => $step['count'],
            'share' => $this->rate($step['count'], $total),
        ], $steps);
    }

    /**
     * أسباب التحويل الأكثر تكرارًا — وثيقة 06 §10.
     *
     * @return list<array{label: string, caption: string|null, count: int, share: float, tone: string}>
     */
    public function reasons(int $limit = 6): array
    {
        $total = $this->baseQuery()->count();

        /** @var list<object{label: string, total: int, section: string|null}> $rows */
        $rows = $this->baseQuery()
            ->selectRaw('reason as label, count(*) as total')
            ->selectRaw('mode() within group (order by section) as section')
            ->groupBy('reason')
            ->orderByDesc('total')
            ->limit($limit)
            ->toBase()
            ->get()
            ->all();

        return array_map(fn (object $row): array => [
            'label' => $row->label,
            'caption' => $row->section === null ? null : 'أكثره في '.$row->section,
            'count' => (int) $row->total,
            'share' => $this->rate((int) $row->total, $total),
            'tone' => 'warning',
        ], $rows);
    }

    /**
     * قواعد تحديد النية والثقة وقواعد التصعيد حسب الحساسية — وثيقة 06 §10.
     *
     * ثابتة الآن ومصدرها الوثيقة؛ تنتقل إلى جدول قابل للتحرير مع شاشة مستويات
     * المساعد في الموجة التالية.
     *
     * @return list<array{condition: string, action: string, severity: array{value: string, label: string, tone: string}|null}>
     */
    public function rules(): array
    {
        return [
            [
                'condition' => 'ثقة النية أقل من 70%',
                'action' => 'إعادة صياغة السؤال مرة واحدة ثم التحويل إن بقيت الثقة منخفضة',
                'severity' => null,
            ],
            [
                'condition' => 'النية تخص قسمًا له مساعد متخصص',
                'action' => 'تحويل إلى المساعد المتخصص مع نقل سياق المحادثة كاملًا',
                'severity' => EnumPayload::from(EscalationSeverity::Low),
            ],
            [
                'condition' => 'طلب إجراء مالي (سحب أو تعديل رصيد)',
                'action' => 'تحويل مباشر إلى موظف بشري — لا ينفّذ المساعد إجراءً ماليًا',
                'severity' => EnumPayload::from(EscalationSeverity::Critical),
            ],
            [
                'condition' => 'سؤال حساس أو شكوى',
                'action' => 'تحويل إلى موظف بشري مع تعليم المحادثة للمراجعة',
                'severity' => EnumPayload::from(EscalationSeverity::High),
            ],
            [
                'condition' => 'تعذر استدعاء البيانات من Hi-Share بعد محاولتين',
                'action' => 'فتح تذكرة تلقائية وإبلاغ المستخدم بزمن المتابعة',
                'severity' => EnumPayload::from(EscalationSeverity::Medium),
            ],
            [
                'condition' => 'طلب المستخدم موظفًا صراحةً',
                'action' => 'تحويل فوري دون محاولة إقناع',
                'severity' => EnumPayload::from(EscalationSeverity::Medium),
            ],
        ];
    }

    /**
     * قائمة الحالات المفتوحة — وثيقة 06 §10، الأحرج أولًا ثم الأقدم.
     *
     * @return list<array<string, mixed>>
     */
    public function openCases(int $limit = 12): array
    {
        return EscalationPresenter::rows(
            ConversationEscalation::query()
                ->forProject($this->project)
                ->whereNull('resolved_at')
                ->with('conversation:id,reference')
                ->orderByRaw("case severity when 'critical' then 0 when 'high' then 1 when 'medium' then 2 else 3 end")
                ->orderBy('created_at')
                ->limit($limit)
                ->get(),
        );
    }

    /**
     * @return array{escalated: int, escalation_rate: float, open: int, avg_wait_seconds: int|null, resolved_without_escalation: float}
     */
    public function totals(): array
    {
        $conversations = Conversation::query()
            ->forProject($this->project)
            ->whereBetween('started_at', [$this->period->from, $this->period->to]);

        $total = (clone $conversations)->count();
        $escalated = $this->baseQuery()->count();

        /** @var object{avg_wait: string|null} $wait */
        $wait = $this->baseQuery()->selectRaw('avg(wait_seconds) as avg_wait')->toBase()->first();

        return [
            'escalated' => $escalated,
            'escalation_rate' => $this->rate($escalated, $total),
            'open' => $this->baseQuery()->whereNull('resolved_at')->count(),
            'avg_wait_seconds' => $wait->avg_wait === null ? null : (int) round((float) $wait->avg_wait),
            'resolved_without_escalation' => $this->rate(
                (clone $conversations)->where('outcome', ConversationOutcome::Resolved->value)->count(),
                $total,
            ),
        ];
    }

    /** @return Builder<ConversationEscalation> */
    private function baseQuery(): Builder
    {
        return ConversationEscalation::query()
            ->forProject($this->project)
            ->whereBetween('created_at', [$this->period->from, $this->period->to]);
    }

    private function rate(int $part, int $total): float
    {
        return $total === 0 ? 0.0 : round($part / $total * 100, 1);
    }
}
