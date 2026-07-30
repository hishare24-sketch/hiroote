import { Head } from '@inertiajs/react';
import {
    Activity,
    CalendarClock,
    HardDrive,
    KeyRound,
    LogIn,
    Users,
    Waypoints,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { StatusTone } from '@/types';
import type {
    PulseCoverage,
    PulseMetric,
    PulseMetricKey,
    PulseMetrics,
    PulseRatio,
    PulseScreenRow,
    PulseSectionRow,
    PulseSnapshot,
} from '@/types/pulse';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Alert } from '@/Components/ui/Alert';
import { Badge } from '@/Components/ui/Badge';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { DataTable, Td } from '@/Components/ui/DataTable';
import { EmptyState } from '@/Components/ui/EmptyState';
import { PageHeader } from '@/Components/ui/PageHeader';
import { PeriodFilter, type PeriodOption } from '@/Components/ui/PeriodFilter';
import { StatCard } from '@/Components/ui/StatCard';
import { TrendChart } from '@/Components/ui/TrendChart';
import { formatNumber, formatPercent } from '@/lib/format';

interface Props {
    systemStatus: { label: string; tone: StatusTone };
    period: { key: string; label: string; from: string; to: string };
    periodOptions: PeriodOption[];
    coverage: PulseCoverage;
    metrics: PulseMetrics;
    ratios: PulseRatio[];
    activeSeries: { date: string; value: number; secondary: string }[];
    sessionSeries: { date: string; value: number; secondary: string }[];
    screens: PulseScreenRow[];
    sections: PulseSectionRow[];
    peakHour: { hour: number; days: number } | null;
    snapshot: PulseSnapshot | null;
}

const CARDS: { key: PulseMetricKey; label: string; icon: LucideIcon; unit?: string }[] = [
    { key: 'active_users', label: 'النشِطون يوميًّا', icon: Users },
    { key: 'sessions', label: 'الجلسات يوميًّا', icon: Activity },
    { key: 'logins', label: 'الدخول يوميًّا', icon: LogIn },
    { key: 'peak_concurrent', label: 'ذروة المتزامنين', icon: Waypoints },
];

/** «—» لا «0»: الرقم الذي لم يُقَس ليس صفرًا، والخانة الفارغة تقول ذلك. */
function value(metric: PulseMetric, field: 'average' | 'peak' | 'total'): string {
    const raw = metric[field];

    return raw === null ? '—' : formatNumber(raw, Number.isInteger(raw) ? 0 : 1);
}

function caption(metric: PulseMetric, span: number): string {
    if (metric.measured_days === 0) {
        return 'لم يُقَس في أي يوم من الفترة';
    }

    const coverage =
        metric.measured_days < span
            ? `قيس في ${formatNumber(metric.measured_days)} من ${formatNumber(span)} يومًا`
            : `قيس في كل الأيام (${formatNumber(span)})`;

    if (metric.change_percent === null) {
        return `${coverage} · لا مقارنة`;
    }

    const direction = metric.change_percent >= 0 ? '▲' : '▼';

    return `${coverage} · ${direction} ${formatPercent(Math.abs(metric.change_percent))} عن الفترة السابقة`;
}

/** شاشة نبض المشروع — القيم والمعدّلات اليومية التي يرسلها المشروع عن نفسه. */
export default function PulseIndex({
    systemStatus,
    period,
    periodOptions,
    coverage,
    metrics,
    ratios,
    activeSeries,
    sessionSeries,
    screens,
    sections,
    peakHour,
    snapshot,
}: Props) {
    return (
        <AdminLayout>
            <Head title="نبض المشروع" />

            <PageHeader
                title="نبض المشروع"
                description="ما يرسله المشروع يوميًّا عن نفسه — مجاميعُ ومعدّلات، لا بيانات أفراد"
                systemStatus={systemStatus}
                period={period.label}
            />

            <PeriodFilter
                options={periodOptions}
                active={period.key}
                from={null}
                to={null}
                url="/pulse"
            />

            {!coverage.has_any ? (
                <Card>
                    <CardBody>
                        <EmptyState
                            icon={Activity}
                            title="لم تصل أي دفعة نبض في هذه الفترة"
                            description="المشروع يرسل دفعةً مجمَّعة كل يوم إلى POST /api/v1/pulse. وحتى تصل، تبقى هذه الشاشة فارغة — ولا يعني الفراغ أن النشاط صفر، بل أن شيئًا لم يُقَس."
                        />
                    </CardBody>
                </Card>
            ) : null}

            {/* التغطية تُقرأ قبل أي رقم: بلا هذا التنبيه تبدو الفترة كاملةً
                دائمًا، ويُقرأ انقطاعُ الإرسال هبوطًا في النشاط. */}
            {coverage.has_any && (coverage.missing > 0 || coverage.partial > 0) ? (
                <Alert tone="warning" title="الفترة ناقصة — اقرأ الأرقام على هذا الأساس">
                    <ul className="flex list-inside list-disc flex-col gap-1">
                        {coverage.missing > 0 ? (
                            <li>
                                {formatNumber(coverage.missing)} من{' '}
                                {formatNumber(coverage.expected)} يومًا لم تصل دفعتها. الفجوة فجوةٌ
                                لا صفر — لم تُحسب في أي متوسّط.
                            </li>
                        ) : null}
                        {coverage.partial > 0 ? (
                            <li>
                                {formatNumber(coverage.partial)} يومًا وصلت ناقصةً قبل انتهائها (‏
                                <span dir="ltr">final: false</span>‏) — انخفاضُها ليس هبوطًا.
                            </li>
                        ) : null}
                        {coverage.revised > 0 ? (
                            <li>
                                {formatNumber(coverage.revised)} يومًا أُعيد إرساله بقيمٍ مختلفة —
                                القيم السابقة محفوظة.
                            </li>
                        ) : null}
                    </ul>
                </Alert>
            ) : null}

            {coverage.has_any ? (
                <>
                    <section
                        aria-label="البطاقات الرئيسية"
                        className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                    >
                        {CARDS.map((card) => (
                            <StatCard
                                key={card.key}
                                label={card.label}
                                value={value(metrics[card.key], 'average')}
                                caption={caption(metrics[card.key], coverage.expected)}
                                icon={card.icon}
                                tone={metrics[card.key].measured_days === 0 ? 'neutral' : 'accent'}
                            />
                        ))}
                    </section>

                    <div className="grid gap-4 xl:grid-cols-2">
                        <Card>
                            <CardHeader
                                title="النشِطون يوميًّا"
                                description="نقطةٌ لكل يومٍ وصل — الأيام الغائبة لا تُستكمل بالجوار ولا تُرسم صفرًا"
                            />
                            <CardBody>
                                <TrendChart
                                    points={activeSeries}
                                    label="النشِطون"
                                    emptyTitle="لم يُقَس عدد النشِطين"
                                    emptyDescription="لم تحمل أي دفعة في هذه الفترة قيمةً لهذا المقياس."
                                />
                            </CardBody>
                        </Card>

                        <Card>
                            <CardHeader
                                title="الجلسات يوميًّا"
                                description="مع ذروة المتزامنين، تقرأ الحمل لا العدد وحده"
                            />
                            <CardBody>
                                <TrendChart
                                    points={sessionSeries}
                                    label="الجلسات"
                                    emptyTitle="لم تُقَس الجلسات"
                                    emptyDescription="لم تحمل أي دفعة في هذه الفترة قيمةً لهذا المقياس."
                                />
                            </CardBody>
                        </Card>
                    </div>

                    <Card>
                        <CardHeader
                            title="المعدّلات المشتقّة"
                            description="تُحسب على الأيام التي قيس فيها طرفاها معًا — قسمةٌ على مجهول تُخرج رقمًا يبدو معلومًا"
                        />
                        <CardBody className="grid gap-3 md:grid-cols-2">
                            {ratios.map((ratio) => (
                                <div
                                    key={ratio.key}
                                    className="flex flex-col gap-1 rounded-control border border-border-default p-4"
                                >
                                    <span className="flex flex-wrap items-baseline justify-between gap-2">
                                        <span className="text-caption font-bold text-fg-default">
                                            {ratio.label}
                                        </span>
                                        <span className="text-title font-extrabold text-fg-default">
                                            {ratio.value === null
                                                ? '—'
                                                : `${formatNumber(ratio.value, Number.isInteger(ratio.value) ? 0 : 2)} ${ratio.unit}`}
                                        </span>
                                    </span>
                                    <span className="text-micro text-fg-subtle">{ratio.about}</span>
                                    <span className="text-micro text-fg-subtle">
                                        {ratio.measured_days === 0
                                            ? 'لا يومَ اكتمل فيه طرفا النسبة'
                                            : `على ${formatNumber(ratio.measured_days)} يومًا`}
                                    </span>
                                </div>
                            ))}
                        </CardBody>
                    </Card>

                    <div className="grid gap-4 xl:grid-cols-3">
                        <StatCard
                            label="ساعة الذروة الغالبة"
                            value={
                                peakHour === null
                                    ? '—'
                                    : `${String(peakHour.hour).padStart(2, '0')}:00`
                            }
                            caption={
                                peakHour === null
                                    ? 'لم تُرسل ساعة ذروة في أي يوم'
                                    : `كانت الذروة في هذه الساعة ${formatNumber(peakHour.days)} يومًا — بتوقيت المُرسِل`
                            }
                            icon={CalendarClock}
                            tone={peakHour === null ? 'neutral' : 'info'}
                        />
                        <StatCard
                            label="التخزين المستهلك"
                            value={value(metrics.storage_megabytes, 'peak')}
                            caption={`ميغابايت — أعلى قيمة في الفترة · ${caption(metrics.storage_megabytes, coverage.expected)}`}
                            icon={HardDrive}
                            tone={
                                metrics.storage_megabytes.measured_days === 0 ? 'neutral' : 'info'
                            }
                        />
                        <StatCard
                            label="دقائق التواجد"
                            value={value(metrics.presence_minutes, 'total')}
                            caption={`مجموع الفترة · ${caption(metrics.presence_minutes, coverage.expected)}`}
                            icon={Activity}
                            tone={metrics.presence_minutes.measured_days === 0 ? 'neutral' : 'info'}
                        />
                    </div>

                    <Card>
                        <CardHeader
                            title="الشاشات"
                            description="المشاهدات والنقرات ومعدّل النقر — مفاتيح الشاشات المسجَّلة وحدها تُقبل"
                        />
                        {screens.length === 0 ? (
                            <CardBody>
                                <EmptyState
                                    title="لم تصل مقاييس شاشات"
                                    description="الدفعات التي وصلت لم تحمل قسم screens، أو حملت مفاتيح غير مسجَّلة فرُدّت في الردّ."
                                />
                            </CardBody>
                        ) : (
                            <DataTable
                                caption="مقاييس الشاشات في الفترة"
                                columns={['الشاشة', 'المشاهدات', 'النقرات', 'معدّل النقر', 'أيام']}
                            >
                                {screens.map((screen) => (
                                    <tr key={screen.key}>
                                        <Td className="font-bold text-fg-default">
                                            <span dir="ltr">{screen.key}</span>
                                        </Td>
                                        <Td>
                                            {screen.views === null
                                                ? '—'
                                                : formatNumber(screen.views)}
                                        </Td>
                                        <Td>
                                            {screen.clicks === null
                                                ? '—'
                                                : formatNumber(screen.clicks)}
                                        </Td>
                                        <Td>
                                            {screen.click_rate === null ? (
                                                <span title="غير معرَّف بلا مشاهدات — ليس صفرًا">
                                                    —
                                                </span>
                                            ) : (
                                                formatPercent(screen.click_rate)
                                            )}
                                        </Td>
                                        <Td>{formatNumber(screen.days)}</Td>
                                    </tr>
                                ))}
                            </DataTable>
                        )}
                    </Card>

                    <div className="grid gap-4 xl:grid-cols-2">
                        <Card>
                            <CardHeader
                                title="الإجراءات حسب القسم"
                                description="مجموع الفترة، والأقسام كما يسمّيها المشروع"
                            />
                            {sections.length === 0 ? (
                                <CardBody>
                                    <EmptyState
                                        title="لم تصل إجراءات أقسام"
                                        description="لم تحمل الدفعات قسم section_actions."
                                    />
                                </CardBody>
                            ) : (
                                <CardBody className="flex flex-col gap-2">
                                    {sections.map((section) => (
                                        <div
                                            key={section.name}
                                            className="flex items-center justify-between gap-3 rounded-control border border-border-default px-3 py-2.5"
                                        >
                                            <span className="text-caption font-bold text-fg-default">
                                                {section.name}
                                            </span>
                                            <span className="flex items-center gap-2">
                                                <span className="text-micro text-fg-subtle">
                                                    {formatNumber(section.days)} يومًا
                                                </span>
                                                <span className="text-caption font-extrabold text-fg-default">
                                                    {formatNumber(section.actions)}
                                                </span>
                                            </span>
                                        </div>
                                    ))}
                                </CardBody>
                            )}
                        </Card>

                        <Card>
                            <CardHeader
                                title="لقطة الحالة"
                                description={
                                    snapshot === null
                                        ? 'لا لقطة'
                                        : `الباقات ومؤشّرات الصحّة كما وصلت في ${snapshot.as_of}`
                                }
                            />
                            <CardBody className="flex flex-col gap-4">
                                {/* الباقات حالةٌ لا حركة: جمعُها عبر ثلاثين يومًا
                                    يُنتج ثلاثين ضعفًا لعددٍ لم يتغيّر. */}
                                {snapshot === null ||
                                (snapshot.packages.length === 0 &&
                                    Object.keys(snapshot.health).length === 0) ? (
                                    <EmptyState
                                        icon={KeyRound}
                                        title="لم تصل باقات ولا مؤشّرات صحّة"
                                        description="هذان حقلان اختياريّان في العقد، وغيابهما لا يعني صفرًا."
                                    />
                                ) : (
                                    <>
                                        {snapshot.packages.length > 0 ? (
                                            <div className="flex flex-col gap-2">
                                                <p className="text-caption font-bold text-fg-muted">
                                                    الباقات
                                                </p>
                                                {snapshot.packages.map((entry) => (
                                                    <div
                                                        key={entry.name}
                                                        className="flex items-center justify-between gap-3 rounded-control border border-border-default px-3 py-2"
                                                    >
                                                        <span className="text-caption text-fg-default">
                                                            {entry.name}
                                                        </span>
                                                        <Badge tone="neutral">
                                                            {formatNumber(entry.subscribers)} مشترك
                                                        </Badge>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : null}

                                        {Object.keys(snapshot.health).length > 0 ? (
                                            <div className="flex flex-col gap-2">
                                                <p className="text-caption font-bold text-fg-muted">
                                                    مؤشّرات الصحّة — بأسماء المُرسِل
                                                </p>
                                                {Object.entries(snapshot.health).map(
                                                    ([name, reading]) => (
                                                        <div
                                                            key={name}
                                                            className="flex items-center justify-between gap-3 rounded-control border border-border-default px-3 py-2"
                                                        >
                                                            <span
                                                                dir="ltr"
                                                                className="text-caption text-fg-default"
                                                            >
                                                                {name}
                                                            </span>
                                                            <span className="text-caption font-extrabold text-fg-default">
                                                                {formatNumber(
                                                                    reading,
                                                                    Number.isInteger(reading)
                                                                        ? 0
                                                                        : 2,
                                                                )}
                                                            </span>
                                                        </div>
                                                    ),
                                                )}
                                            </div>
                                        ) : null}
                                    </>
                                )}
                            </CardBody>
                        </Card>
                    </div>
                </>
            ) : null}
        </AdminLayout>
    );
}
