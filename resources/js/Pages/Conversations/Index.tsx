import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    AlertTriangle,
    ArrowLeft,
    CheckCircle2,
    MessageSquare,
    Search,
    Star,
    Timer,
    Users,
} from 'lucide-react';
import type { StatusTone } from '@/types';
import type {
    ConversationFilters,
    ConversationMetrics,
    ConversationRow,
    Paginated,
    RankedItem,
} from '@/types/conversations';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Badge } from '@/Components/ui/Badge';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { DataTable, Td } from '@/Components/ui/DataTable';
import { EmptyState } from '@/Components/ui/EmptyState';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Pagination } from '@/Components/ui/Pagination';
import { PeriodFilter, type PeriodOption } from '@/Components/ui/PeriodFilter';
import { ShareList } from '@/Components/ui/ShareList';
import { StatCard } from '@/Components/ui/StatCard';
import {
    formatChange,
    formatDuration,
    formatLatency,
    formatMoney,
    formatNumber,
    formatPercent,
    formatRelative,
} from '@/lib/format';
import { cn } from '@/lib/cn';

interface Props {
    systemStatus: { label: string; tone: StatusTone };
    period: { key: string; label: string; from: string; to: string };
    periodOptions: PeriodOption[];
    filters: ConversationFilters;
    metrics: ConversationMetrics;
    comparison: ConversationMetrics;
    topIntents: RankedItem[];
    topSections: RankedItem[];
    frictionPoints: RankedItem[];
    conversations: Paginated<ConversationRow>;
    outcomeOptions: { value: string; label: string }[];
    sectionOptions: string[];
    providerOptions: Record<string, string>;
}

const COLUMNS = [
    'المحادثة والمستخدم',
    'القسم',
    'المساعد والمستوى',
    'المزود والنموذج',
    'المدة',
    'الرسائل',
    'التوكن والتكلفة',
    'الحالة',
    'التصعيد',
    'التقييم',
];

/** شاشة الأداء والمحادثات — وثيقة 06 §6. */
export default function ConversationsIndex({
    systemStatus,
    period,
    periodOptions,
    filters,
    metrics,
    comparison,
    topIntents,
    topSections,
    frictionPoints,
    conversations,
    outcomeOptions,
    sectionOptions,
    providerOptions,
}: Props) {
    const [search, setSearch] = useState(filters.search);

    const applyFilter = (patch: Partial<ConversationFilters>) => {
        router.get(
            '/conversations',
            { ...filters, ...patch },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    // بلا محادثات في الفترة السابقة لا توجد مقارنة: عرض الفارق كأنه تغيّر كذب.
    const hasComparison = comparison.conversations > 0;

    const change = (current: number, previous: number): string =>
        formatChange(
            previous === 0 ? null : Math.round(((current - previous) / previous) * 1000) / 10,
        );

    return (
        <AdminLayout>
            <Head title="الأداء والمحادثات" />

            <PageHeader
                title="الأداء والمحادثات"
                description="جودة الردود ومسار كل محادثة من السؤال إلى الحل"
                systemStatus={systemStatus}
                period={period.label}
            />

            <PeriodFilter
                options={periodOptions}
                active={period.key}
                from={filters.from}
                to={filters.to}
                url="/conversations"
            />

            <section
                aria-label="المؤشرات الرئيسية"
                className="grid gap-4 md:grid-cols-2 xl:grid-cols-5"
            >
                <StatCard
                    label="المحادثات"
                    value={formatNumber(metrics.conversations)}
                    caption={`${change(metrics.conversations, comparison.conversations)} عن الفترة السابقة`}
                    icon={MessageSquare}
                    tone="accent"
                />
                <StatCard
                    label="الرسائل والمستخدمون"
                    value={formatNumber(metrics.messages)}
                    caption={`${formatNumber(metrics.unique_users)} مستخدمًا فريدًا`}
                    icon={Users}
                    tone="info"
                />
                <StatCard
                    label="الحل دون تدخل بشري"
                    value={formatPercent(metrics.unattended_resolution_rate)}
                    caption={`${formatPercent(metrics.first_answer_resolution_rate)} حُلَّت من أول إجابة`}
                    icon={CheckCircle2}
                    tone={metrics.unattended_resolution_rate >= 70 ? 'success' : 'warning'}
                    progress={metrics.unattended_resolution_rate}
                />
                <StatCard
                    label="زمن أول رد"
                    value={formatLatency(metrics.avg_first_response_ms)}
                    caption={`متوسط الرد ${formatLatency(metrics.avg_response_ms)} · المحادثة ${formatDuration(metrics.avg_duration_seconds)}`}
                    icon={Timer}
                    tone={metrics.avg_first_response_ms <= 2500 ? 'success' : 'warning'}
                />
                <StatCard
                    label="تقييم المستخدم"
                    value={
                        metrics.avg_rating === null
                            ? '—'
                            : `${formatNumber(metrics.avg_rating, 1)} من 5`
                    }
                    caption={`${formatNumber(metrics.rated_count)} تقييمًا في الفترة`}
                    icon={Star}
                    tone={
                        metrics.avg_rating === null || metrics.avg_rating >= 4
                            ? 'success'
                            : 'warning'
                    }
                    progress={
                        metrics.avg_rating === null ? undefined : (metrics.avg_rating / 5) * 100
                    }
                />
            </section>

            <section aria-label="مؤشرات الجودة" className="grid gap-4 lg:grid-cols-3">
                <Card className="lg:col-span-1">
                    <CardHeader
                        title="مؤشرات الفهم"
                        description="أين يخفق المساعد قبل أن يخفق الحل"
                    />
                    <CardBody className="flex flex-col gap-4">
                        <QualityRow
                            label="عدم فهم السؤال"
                            value={metrics.misunderstanding_rate}
                            previous={hasComparison ? comparison.misunderstanding_rate : null}
                            goodWhenLow
                        />
                        <QualityRow
                            label="إعادة صياغة السؤال"
                            value={metrics.rephrase_rate}
                            previous={hasComparison ? comparison.rephrase_rate : null}
                            goodWhenLow
                        />
                        <QualityRow
                            label="انقطاع قبل الحل"
                            value={metrics.abandonment_rate}
                            previous={hasComparison ? comparison.abandonment_rate : null}
                            goodWhenLow
                        />
                        <QualityRow
                            label="الحل من أول إجابة"
                            value={metrics.first_answer_resolution_rate}
                            previous={
                                hasComparison ? comparison.first_answer_resolution_rate : null
                            }
                            goodWhenLow={false}
                        />
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader title="أكثر الأسئلة" description="النية المكتشفة ونسبة حلّها" />
                    <CardBody>
                        <ShareList
                            items={topIntents}
                            unit="محادثة"
                            emptyTitle="لا أسئلة في هذه الفترة"
                            emptyDescription="وسّع المدى الزمني أو أزل الفلاتر لرؤية النوايا الأكثر تكرارًا."
                        />
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader
                        title="نقاط التعثر"
                        description="الأقسام التي تنتهي محادثاتها بانقطاع أو تحويل"
                    />
                    <CardBody className="flex flex-col gap-5">
                        <ShareList
                            items={frictionPoints}
                            unit="متعثرة"
                            emptyTitle="لا تعثر مسجّل"
                            emptyDescription="كل المحادثات في هذه الفترة وصلت إلى حل."
                        />

                        {topSections.length > 0 ? (
                            <div className="border-t border-border-default pt-4">
                                <p className="mb-3 text-caption font-bold text-fg-muted">
                                    أكثر الأقسام نشاطًا
                                </p>
                                <ShareList
                                    items={topSections.slice(0, 3)}
                                    unit="محادثة"
                                    emptyTitle=""
                                    emptyDescription=""
                                />
                            </div>
                        ) : null}
                    </CardBody>
                </Card>
            </section>

            <Card>
                <CardHeader
                    title="سجل المحادثات"
                    description={`${formatNumber(conversations.total)} محادثة ضمن الفلاتر الحالية`}
                />

                <div className="flex flex-wrap items-end gap-3 border-b border-border-default px-5 py-4">
                    <form
                        className="flex min-w-[16rem] flex-1 items-center gap-2 rounded-control border border-border-strong bg-surface-raised px-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            applyFilter({ search });
                        }}
                    >
                        <Search aria-hidden className="size-4 shrink-0 text-fg-subtle" />
                        <input
                            type="search"
                            value={search}
                            onChange={(event) => {
                                setSearch(event.target.value);
                            }}
                            placeholder="ابحث برقم المحادثة أو المستخدم أو النية"
                            aria-label="بحث في المحادثات"
                            className="h-10 w-full bg-transparent text-body text-fg-default outline-none placeholder:text-fg-subtle"
                        />
                    </form>

                    <FilterSelect
                        label="القسم"
                        value={filters.section}
                        options={sectionOptions.map((section) => ({
                            value: section,
                            label: section,
                        }))}
                        onChange={(value) => {
                            applyFilter({ section: value });
                        }}
                    />
                    <FilterSelect
                        label="الحالة"
                        value={filters.outcome}
                        options={outcomeOptions}
                        onChange={(value) => {
                            applyFilter({ outcome: value });
                        }}
                    />
                    <FilterSelect
                        label="المزود"
                        value={filters.provider}
                        options={Object.entries(providerOptions).map(([slug, name]) => ({
                            value: slug,
                            label: name,
                        }))}
                        onChange={(value) => {
                            applyFilter({ provider: value });
                        }}
                    />
                </div>

                {conversations.data.length === 0 ? (
                    <EmptyState
                        icon={AlertTriangle}
                        title="لا محادثات مطابقة"
                        description="جرّب مدى زمنيًا أوسع أو أزل أحد الفلاتر."
                    />
                ) : (
                    <>
                        <DataTable columns={COLUMNS} caption="سجل المحادثات في الفترة المحددة">
                            {conversations.data.map((row) => (
                                <tr key={row.id} className="hover:bg-surface-sunken">
                                    <Td className="whitespace-nowrap">
                                        <Link
                                            href={`/conversations/${String(row.id)}`}
                                            className="group inline-flex items-center gap-1 font-bold text-fg-default tabular-nums hover:text-accent"
                                        >
                                            {row.reference}
                                            <ArrowLeft
                                                aria-hidden
                                                className="size-3.5 text-fg-subtle opacity-0 transition-opacity group-hover:opacity-100"
                                            />
                                            <span className="sr-only">— افتح التفاصيل</span>
                                        </Link>
                                        <span className="mt-0.5 block text-micro text-fg-subtle">
                                            {row.user_label ?? 'مستخدم غير معروف'}
                                        </span>
                                    </Td>
                                    <Td className="whitespace-nowrap">{row.section}</Td>
                                    <Td className="whitespace-nowrap">
                                        {row.assistant ?? '—'}
                                        <Badge tone={row.level.tone} className="ms-2">
                                            {row.level.label}
                                        </Badge>
                                    </Td>
                                    <Td className="whitespace-nowrap">
                                        {row.provider ?? '—'}
                                        <span className="mt-0.5 block text-micro text-fg-subtle">
                                            {row.model ?? '—'}
                                        </span>
                                    </Td>
                                    <Td className="whitespace-nowrap tabular-nums">
                                        {formatDuration(row.duration_seconds)}
                                        <span className="mt-0.5 block text-micro text-fg-subtle">
                                            {formatRelative(row.started_at)}
                                        </span>
                                    </Td>
                                    <Td className="tabular-nums">
                                        {formatNumber(row.message_count)}
                                    </Td>
                                    <Td className="whitespace-nowrap tabular-nums">
                                        {formatNumber(row.total_tokens)}
                                        <span className="mt-0.5 block text-micro text-fg-subtle">
                                            {formatMoney(row.cost, 'SAR', 3)}
                                        </span>
                                    </Td>
                                    <Td className="whitespace-nowrap">
                                        <Badge tone={row.outcome.tone} dot>
                                            {row.outcome.label}
                                        </Badge>
                                    </Td>
                                    <Td className="whitespace-nowrap">
                                        {row.escalation === null ? (
                                            <span className="text-fg-subtle">—</span>
                                        ) : (
                                            <Badge tone={row.escalation.tone}>
                                                {row.escalation.label}
                                            </Badge>
                                        )}
                                    </Td>
                                    <Td className="whitespace-nowrap tabular-nums">
                                        {row.rating === null ? (
                                            <span className="text-fg-subtle">—</span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1">
                                                <Star
                                                    aria-hidden
                                                    className="size-3.5 fill-current text-warning"
                                                />
                                                {formatNumber(row.rating, 1)}
                                            </span>
                                        )}
                                    </Td>
                                </tr>
                            ))}
                        </DataTable>

                        <Pagination
                            links={conversations.links}
                            from={conversations.from}
                            to={conversations.to}
                            total={conversations.total}
                            unit="محادثة"
                        />
                    </>
                )}
            </Card>
        </AdminLayout>
    );
}

function QualityRow({
    label,
    value,
    previous,
    goodWhenLow,
}: {
    label: string;
    value: number;
    /** null حين لا توجد فترة سابقة يُقاس عليها. */
    previous: number | null;
    goodWhenLow: boolean;
}) {
    const improved = previous !== null && (goodWhenLow ? value < previous : value > previous);
    const same = value === previous;

    return (
        <div className="flex flex-col gap-1.5">
            <div className="flex items-baseline justify-between gap-3">
                <p className="text-body text-fg-muted">{label}</p>
                <p className="flex items-baseline gap-2">
                    <span className="text-title font-bold text-fg-default tabular-nums">
                        {formatPercent(value)}
                    </span>
                    <span
                        className={cn(
                            'text-micro font-bold tabular-nums',
                            previous === null || same
                                ? 'text-fg-subtle'
                                : improved
                                  ? 'text-success'
                                  : 'text-danger',
                        )}
                    >
                        {previous === null
                            ? 'لا مقارنة'
                            : same
                              ? 'بلا تغيّر'
                              : `${formatChange(Math.round((value - previous) * 10) / 10)} نقطة`}
                    </span>
                </p>
            </div>
            <div className="h-1.5 w-full overflow-hidden rounded-pill bg-surface-track">
                <div
                    className={cn('h-full rounded-pill', goodWhenLow ? 'bg-warning' : 'bg-success')}
                    style={{ width: `${String(Math.min(100, value))}%` }}
                />
            </div>
        </div>
    );
}

function FilterSelect({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
}) {
    return (
        <label className="flex flex-col gap-1">
            <span className="text-micro font-bold text-fg-muted">{label}</span>
            <select
                value={value}
                onChange={(event) => {
                    onChange(event.target.value);
                }}
                className="h-10 min-w-[9rem] rounded-control border border-border-strong bg-surface-raised px-3 text-body text-fg-default"
            >
                <option value="">الكل</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </label>
    );
}
