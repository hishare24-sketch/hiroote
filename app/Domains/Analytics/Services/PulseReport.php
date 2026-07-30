<?php

declare(strict_types=1);

namespace App\Domains\Analytics\Services;

use App\Domains\Analytics\Models\ProjectPulse;
use App\Domains\Analytics\Models\ProjectScreenPulse;
use App\Domains\Projects\Models\Project;
use App\Support\Http\Period;
use Illuminate\Support\Collection;

/**
 * قراءة النبض اليومي — **والقاعدة الحاكمة هنا واحدة: غير المقاس ليس صفرًا.**
 *
 * كل رقم في هذا التقرير يخرج ومعه عدد الأيام التي قيس فيها. فمتوسّطٌ على ثلاثين
 * يومًا وصلت منها ثلاثة ليس متوسّطًا شهريًّا، ومن يقرؤه دون أن يعرف ذلك يبني
 * عليه قرارًا لا تحتمله بياناته.
 *
 * ولذلك لا يُحسب أي متوسّط على طول الفترة — بل على الأيام المقيسة وحدها،
 * ويُعلَن العدد بجانبه.
 *
 * @phpstan-type MetricSummary array{
 *     total: int|null, average: float|null, peak: int|null, low: int|null,
 *     measured_days: int, change_percent: float|null
 * }
 */
final readonly class PulseReport
{
    /** @var Collection<int, ProjectPulse> */
    private Collection $days;

    /** @var Collection<int, ProjectPulse> */
    private Collection $previousDays;

    public function __construct(
        private Period $period,
        private Project $project,
    ) {
        $this->days = $this->load($this->period);
        $this->previousDays = $this->load($this->period->previous());
    }

    /**
     * التغطية — تُقرأ قبل أي رقم آخر.
     *
     * يومٌ لم تصل دفعتُه **فجوةٌ لا صفر**، ويومٌ وصل ناقصًا يُعلَن ناقصًا. وبلا
     * هذين الرقمين تبدو الفترة كاملةً دائمًا، ويُقرأ انقطاعُ الإرسال هبوطًا في
     * النشاط.
     *
     * @return array{expected: int, received: int, missing: int, partial: int, revised: int, has_any: bool}
     */
    public function coverage(): array
    {
        $expected = max(1, (int) $this->period->from->copy()->startOfDay()
            ->diffInDays($this->period->to->copy()->startOfDay()) + 1);

        $received = $this->days->count();

        return [
            'expected' => $expected,
            'received' => $received,
            'missing' => max(0, $expected - $received),
            'partial' => $this->days->where('is_final', false)->count(),
            'revised' => $this->days->where('revision', '>', 1)->count(),
            'has_any' => $received > 0,
        ];
    }

    /**
     * كل مقياس بمجموعه ومتوسّطه وذروته وقاعه وعدد أيامه وتغيّره عن الفترة السابقة.
     *
     * @return array<string, MetricSummary>
     */
    public function metrics(): array
    {
        $summaries = [];

        foreach (ProjectPulse::METRICS as $metric) {
            $summaries[$metric] = $this->summarise($metric);
        }

        return $summaries;
    }

    /**
     * معدّلاتٌ مشتقّة — تُحسب على الأيام التي قيس فيها **طرفاها معًا**.
     *
     * يومٌ عُرف فيه عدد الجلسات ولم يُعرف عدد النشطين لا يدخل «الجلسات لكل
     * نشِط»: قسمةٌ على مجهول تُخرج رقمًا يبدو معلومًا.
     *
     * @return list<array{key: string, label: string, value: float|null, unit: string, measured_days: int, about: string}>
     */
    public function ratios(): array
    {
        return [
            $this->ratio(
                'sessions_per_user', 'الجلسات لكل نشِط', 'sessions', 'active_users', 'جلسة',
                'كم مرة يعود المستخدم الواحد في اليوم — ارتفاعه اعتيادٌ لا تعثّر بالضرورة.',
            ),
            $this->ratio(
                'minutes_per_session', 'الدقائق لكل جلسة', 'presence_minutes', 'sessions', 'دقيقة',
                'طول الجلسة الواحدة. اقرأه مع طبيعة العمل: طولٌ في شاشة إدخال قد يعني بطئًا.',
            ),
            $this->ratio(
                'logins_per_user', 'الدخول لكل نشِط', 'logins', 'active_users', 'مرة',
                'ارتفاعه المفاجئ مع ثبات النشِطين يعني جلساتٍ تنقطع فيُعاد الدخول.',
            ),
            $this->ratio(
                'storage_per_user', 'التخزين لكل نشِط', 'storage_megabytes', 'active_users', 'ميغابايت',
                'مجموعٌ لا نصيبُ فرد — يقيس اتجاه النمو لا استهلاك أحد.',
            ),
        ];
    }

    /**
     * السلسلة الزمنية لمقياس واحد — **بلا سدّ الفجوات**.
     *
     * اليوم الغائب لا يُرسل صفرًا ولا يُستكمل بالجوار: الأول يكذب، والثاني
     * يخترع. يُرسل ما وصل، وتقول التغطية كم لم يصل.
     *
     * @return list<array{date: string, value: int, secondary: string}>
     */
    public function series(string $metric): array
    {
        if (! in_array($metric, ProjectPulse::METRICS, strict: true)) {
            return [];
        }

        return array_values($this->days
            ->filter(static fn (ProjectPulse $day): bool => $day->getAttribute($metric) !== null)
            ->map(static fn (ProjectPulse $day): array => [
                'date' => $day->pulse_date->toDateString(),
                'value' => (int) $day->getAttribute($metric),
                'secondary' => $day->is_final ? '' : 'يوم ناقص',
            ])
            ->all());
    }

    /**
     * الشاشات — مشاهداتٍ ونقراتٍ ومعدّل نقر.
     *
     * @return list<array{key: string, views: int|null, clicks: int|null, click_rate: float|null, days: int}>
     */
    public function screens(): array
    {
        $ids = $this->days->pluck('id')->all();

        if ($ids === []) {
            return [];
        }

        $rows = ProjectScreenPulse::query()
            ->whereIn('project_pulse_id', $ids)
            ->get(['screen_key', 'views', 'clicks']);

        $grouped = [];

        foreach ($rows as $row) {
            $key = $row->screen_key;
            $grouped[$key] ??= ['views' => null, 'clicks' => null, 'days' => 0];
            $grouped[$key]['days']++;

            foreach (['views', 'clicks'] as $field) {
                $value = $row->getAttribute($field);

                if ($value !== null) {
                    $grouped[$key][$field] = ($grouped[$key][$field] ?? 0) + (int) $value;
                }
            }
        }

        $screens = [];

        foreach ($grouped as $key => $totals) {
            $views = $totals['views'];
            $clicks = $totals['clicks'];

            $screens[] = [
                'key' => $key,
                'views' => $views,
                'clicks' => $clicks,
                // معدّل النقر بلا مشاهدات ليس صفرًا — هو غير معرَّف.
                'click_rate' => $views === null || $clicks === null || $views === 0
                    ? null
                    : round($clicks / $views * 100, 1),
                'days' => $totals['days'],
            ];
        }

        usort($screens, static fn (array $a, array $b): int => ($b['views'] ?? -1) <=> ($a['views'] ?? -1));

        return $screens;
    }

    /**
     * الأقسام — مجموع إجراءات كل قسم على أيام الفترة.
     *
     * @return list<array{name: string, actions: int, days: int}>
     */
    public function sections(): array
    {
        $grouped = [];

        foreach ($this->days as $day) {
            foreach ($day->section_actions ?? [] as $name => $actions) {
                $grouped[$name] ??= ['actions' => 0, 'days' => 0];
                $grouped[$name]['actions'] += (int) $actions;
                $grouped[$name]['days']++;
            }
        }

        $sections = [];

        foreach ($grouped as $name => $totals) {
            $sections[] = ['name' => $name, 'actions' => $totals['actions'], 'days' => $totals['days']];
        }

        usort($sections, static fn (array $a, array $b): int => $b['actions'] <=> $a['actions']);

        return $sections;
    }

    /**
     * ساعة الذروة الغالبة — أكثر ساعةٍ تكرّرت ذروةً في أيام الفترة.
     *
     * @return array{hour: int, days: int}|null
     */
    public function peakHour(): ?array
    {
        $hours = $this->days
            ->pluck('peak_hour')
            ->filter(static fn (?int $hour): bool => $hour !== null)
            ->countBy()
            ->sortDesc();

        $hour = $hours->keys()->first();

        if ($hour === null) {
            return null;
        }

        return ['hour' => (int) $hour, 'days' => (int) $hours->first()];
    }

    /**
     * آخر صورةٍ للباقات ومؤشّرات الصحّة — لقطةُ حالةٍ لا مجموعٌ عبر الزمن.
     *
     * جمعُ «عدد المشتركين في الباقة» عبر ثلاثين يومًا يُنتج ثلاثين ضعفًا لعددٍ
     * لم يتغيّر. فالحالة تُقرأ من آخر يومٍ وصل، ويُقال أيّ يومٍ هو.
     *
     * @return array{as_of: string, packages: list<array{name: string, subscribers: int}>, health: array<string, float>}|null
     */
    public function snapshot(): ?array
    {
        $latest = $this->days->last();

        if (! $latest instanceof ProjectPulse) {
            return null;
        }

        return [
            'as_of' => $latest->pulse_date->toDateString(),
            'packages' => $latest->packages ?? [],
            'health' => $latest->health_indicators ?? [],
        ];
    }

    /** @return MetricSummary */
    private function summarise(string $metric): array
    {
        $values = $this->values($this->days, $metric);
        $count = count($values);

        if ($count === 0) {
            return [
                'total' => null, 'average' => null, 'peak' => null, 'low' => null,
                'measured_days' => 0, 'change_percent' => null,
            ];
        }

        $previous = $this->values($this->previousDays, $metric);
        $previousAverage = $previous === [] ? null : array_sum($previous) / count($previous);
        $average = array_sum($values) / $count;

        return [
            'total' => (int) array_sum($values),
            'average' => round($average, 1),
            'peak' => max($values),
            'low' => min($values),
            'measured_days' => $count,
            // المقارنة بالمتوسّط لا بالمجموع: فترةٌ وصل منها ثلاثة أيام ومثيلتها
            // وصل منها ثلاثون تجعل فرق المجاميع كلَّه فرقَ تغطية.
            'change_percent' => $previousAverage === null || $previousAverage <= 0.0
                ? null
                : round(($average - $previousAverage) / $previousAverage * 100, 1),
        ];
    }

    /**
     * @param  Collection<int, ProjectPulse>  $days
     * @return list<int>
     */
    private function values(Collection $days, string $metric): array
    {
        return array_values($days
            ->map(static fn (ProjectPulse $day): mixed => $day->getAttribute($metric))
            ->filter(static fn (mixed $value): bool => $value !== null)
            ->map(static fn (mixed $value): int => (int) $value)
            ->all());
    }

    /**
     * @return array{key: string, label: string, value: float|null, unit: string, measured_days: int, about: string}
     */
    private function ratio(
        string $key,
        string $label,
        string $numerator,
        string $denominator,
        string $unit,
        string $about,
    ): array {
        $sum = 0.0;
        $bottom = 0.0;
        $days = 0;

        foreach ($this->days as $day) {
            $top = $day->getAttribute($numerator);
            $under = $day->getAttribute($denominator);

            if ($top === null || $under === null || (int) $under === 0) {
                continue;
            }

            $sum += (float) $top;
            $bottom += (float) $under;
            $days++;
        }

        return [
            'key' => $key,
            'label' => $label,
            'value' => $days === 0 ? null : round($sum / $bottom, 2),
            'unit' => $unit,
            'measured_days' => $days,
            'about' => $about,
        ];
    }

    /** @return Collection<int, ProjectPulse> */
    private function load(Period $period): Collection
    {
        return ProjectPulse::query()
            ->forProject($this->project)
            ->whereBetween('pulse_date', [
                $period->from->toDateString(),
                $period->to->toDateString(),
            ])
            ->orderBy('pulse_date')
            ->get();
    }
}
