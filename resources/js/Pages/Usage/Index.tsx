import { Head } from '@inertiajs/react';
import { Coins, Layers, TrendingUp, Wallet } from 'lucide-react';
import type { StatusTone } from '@/types';
import type {
    BudgetStatus,
    CostlyOperation,
    PeriodComparison,
    TokenBreakdownItem,
    UsageAverages,
    UsagePoint,
    UsageSlice,
    UsageTotals,
} from '@/types/usage';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Alert } from '@/Components/ui/Alert';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { DataTable, Td } from '@/Components/ui/DataTable';
import { EmptyState } from '@/Components/ui/EmptyState';
import { PageHeader } from '@/Components/ui/PageHeader';
import { PeriodFilter, type PeriodOption } from '@/Components/ui/PeriodFilter';
import { ShareList } from '@/Components/ui/ShareList';
import { StatCard } from '@/Components/ui/StatCard';
import { TrendChart } from '@/Components/ui/TrendChart';
import {
    formatChange,
    formatCompact,
    formatMoney,
    formatNumber,
    formatPercent,
} from '@/lib/format';
import { cn } from '@/lib/cn';

interface Props {
    systemStatus: { label: string; tone: StatusTone };
    period: { key: string; label: string; from: string; to: string };
    periodOptions: PeriodOption[];
    totals: UsageTotals;
    tokenBreakdown: TokenBreakdownItem[];
    series: UsagePoint[];
    comparison: PeriodComparison;
    byProvider: UsageSlice[];
    bySection: UsageSlice[];
    byModel: UsageSlice[];
    averages: UsageAverages;
    costlyOperations: CostlyOperation[];
    budget: BudgetStatus | null;
}

/** شاشة الاستهلاك والتكلفة — وثيقة 06 §7. */
export default function UsageIndex({
    systemStatus,
    period,
    periodOptions,
    totals,
    tokenBreakdown,
    series,
    comparison,
    byProvider,
    bySection,
    byModel,
    averages,
    costlyOperations,
    budget,
}: Props) {
    // التوقّع تحت السقف ليس تحذيرًا — البرتقالي الدائم يفقد معناه بالتعوّد.
    const projectionTone =
        budget === null
            ? 'neutral'
            : totals.projected_month_cost > budget.monthly_limit
              ? 'danger'
              : totals.projected_month_cost > budget.monthly_limit * (budget.warn_at_percent / 100)
                ? 'warning'
                : 'success';

    return (
        <AdminLayout>
            <Head title="الاستهلاك والتكلفة" />

            <PageHeader
                title="الاستهلاك والتكلفة"
                description="أين يذهب التوكن وكم يكلّف، وإلى أين تتجه الفاتورة"
                systemStatus={systemStatus}
                period={period.label}
            />

            <PeriodFilter
                options={periodOptions}
                active={period.key}
                from={null}
                to={null}
                url="/usage"
            />

            {budget === null ? null : (
                <Alert
                    tone={budget.tone === 'accent' ? 'info' : budget.tone}
                    title={`الميزانية الشهرية: ${formatMoney(budget.spent, budget.currency)} من ${formatMoney(budget.monthly_limit, budget.currency)} (${formatPercent(budget.consumed_percent)})`}
                >
                    {budget.message}
                    {budget.hard_stop
                        ? ' الإيقاف الصارم مفعّل — تتوقف الطلبات عند بلوغ السقف.'
                        : ''}
                </Alert>
            )}

            <section
                aria-label="البطاقات الرئيسية"
                className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
            >
                <StatCard
                    label="إجمالي التوكن"
                    value={formatCompact(totals.total_tokens)}
                    caption={`${formatChange(comparison.tokens_change)} عن الفترة السابقة`}
                    icon={Layers}
                    tone="accent"
                />
                <StatCard
                    label="إجمالي التكلفة"
                    value={formatMoney(totals.total_cost, totals.currency)}
                    caption={`${formatChange(comparison.cost_change)} عن الفترة السابقة`}
                    icon={Coins}
                    tone="info"
                />
                <StatCard
                    label="الرصيد المتبقي"
                    value={formatMoney(totals.remaining_balance, totals.currency, 0)}
                    caption="رصيد المزودين المشترك بين كل المشاريع"
                    icon={Wallet}
                    tone={
                        totals.remaining_balance > totals.projected_month_cost
                            ? 'success'
                            : 'danger'
                    }
                />
                <StatCard
                    label="المتوقع حتى نهاية الشهر"
                    value={formatMoney(totals.projected_month_cost, totals.currency, 0)}
                    caption={
                        budget === null
                            ? 'لا ميزانية شهرية محددة'
                            : `السقف ${formatMoney(budget.monthly_limit, budget.currency, 0)}`
                    }
                    icon={TrendingUp}
                    tone={projectionTone}
                    progress={
                        budget === null
                            ? undefined
                            : Math.min(
                                  100,
                                  (totals.projected_month_cost / budget.monthly_limit) * 100,
                              )
                    }
                />
            </section>

            <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                <Card>
                    <CardHeader
                        title="الاستهلاك عبر الزمن"
                        description={`${formatNumber(series.length)} يومًا · مرّر المؤشر لقراءة يوم بعينه`}
                    />
                    <CardBody>
                        <TrendChart
                            label="التوكن اليومي"
                            points={series.map((point) => ({
                                date: point.date,
                                value: point.tokens,
                                secondary: formatMoney(point.cost, totals.currency),
                            }))}
                            emptyTitle="لا استهلاك في هذه الفترة"
                            emptyDescription="وسّع المدى الزمني لرؤية المنحنى."
                        />
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader
                        title="تفصيل التوكن"
                        description="أين يُستهلك التوكن داخل المحادثة الواحدة"
                    />
                    <CardBody>
                        <ShareList
                            items={tokenBreakdown.map((item) => ({
                                label: item.label,
                                count: item.tokens,
                                share: item.share,
                                tone: item.tone,
                                caption: null,
                            }))}
                            unit="توكن"
                            emptyTitle="لا توكن مسجّل"
                            emptyDescription="لم تُسجَّل عمليات في هذه الفترة."
                        />
                    </CardBody>
                </Card>
            </div>

            <section aria-label="المقارنة والمتوسطات" className="grid gap-4 lg:grid-cols-2">
                <Card>
                    <CardHeader
                        title="مقارنة بالفترة السابقة"
                        description="نفس طول المدى، مباشرةً قبل الفترة الحالية"
                    />
                    <CardBody className="grid gap-4 sm:grid-cols-2">
                        <CompareBlock
                            label="التوكن"
                            current={formatCompact(comparison.current_tokens)}
                            previous={formatCompact(comparison.previous_tokens)}
                            change={comparison.tokens_change}
                        />
                        <CompareBlock
                            label="التكلفة"
                            current={formatMoney(comparison.current_cost, totals.currency)}
                            previous={formatMoney(comparison.previous_cost, totals.currency)}
                            change={comparison.cost_change}
                        />
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader
                        title="متوسط التكلفة"
                        description="ما يكلّفه كل عنصر في المتوسط داخل الفترة"
                    />
                    <CardBody className="grid gap-4 sm:grid-cols-3">
                        <AverageBlock
                            label="لكل محادثة"
                            value={formatMoney(averages.cost_per_conversation, totals.currency, 3)}
                        />
                        <AverageBlock
                            label="لكل رد"
                            value={formatMoney(averages.cost_per_response, totals.currency, 3)}
                        />
                        <AverageBlock
                            label="لكل مستخدم"
                            value={formatMoney(averages.cost_per_user, totals.currency, 3)}
                        />
                    </CardBody>
                </Card>
            </section>

            <section aria-label="التوزيعات" className="grid gap-4 lg:grid-cols-3">
                <Card>
                    <CardHeader title="التكلفة حسب المزود" description="من يستهلك الفاتورة" />
                    <CardBody>
                        <ShareList
                            items={byProvider.map((slice) => ({
                                label: slice.label,
                                count: Math.round(slice.cost),
                                share: slice.share,
                                tone: slice.tone,
                                caption: `${formatCompact(slice.tokens)} توكن`,
                            }))}
                            unit={totals.currency === 'SAR' ? 'ر.س' : totals.currency}
                            emptyTitle="لا تكلفة مسجّلة"
                            emptyDescription="لم تُسجَّل عمليات لأي مزود في هذه الفترة."
                        />
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader
                        title="التوكن حسب القسم"
                        description="أي أقسام Hi-Share تستهلك أكثر"
                    />
                    <CardBody>
                        <ShareList
                            items={bySection.map((slice) => ({
                                label: slice.label,
                                count: slice.tokens,
                                share: slice.share,
                                tone: slice.tone,
                                caption: null,
                            }))}
                            unit="توكن"
                            emptyTitle="لا استهلاك حسب القسم"
                            emptyDescription="لم تُسجَّل عمليات موسومة بقسم."
                        />
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader title="التوكن حسب النموذج" description="أي نموذج يحمل العبء" />
                    <CardBody>
                        <ShareList
                            items={byModel.map((slice) => ({
                                label: slice.label,
                                count: slice.tokens,
                                share: slice.share,
                                tone: slice.tone,
                                caption: null,
                            }))}
                            unit="توكن"
                            emptyTitle="لا استهلاك حسب النموذج"
                            emptyDescription="لم تُسجَّل عمليات مرتبطة بنموذج."
                        />
                    </CardBody>
                </Card>
            </section>

            <Card>
                <CardHeader
                    title="أكثر العمليات تكلفة"
                    description="العمليات مرتبة بإجمالي ما كلّفته في الفترة"
                />
                {costlyOperations.length === 0 ? (
                    <EmptyState
                        title="لا عمليات مسجّلة"
                        description="لم تُسجَّل أي تكلفة في المدى المحدد."
                    />
                ) : (
                    <DataTable
                        columns={[
                            'العملية',
                            'القسم',
                            'عدد المرات',
                            'إجمالي التكلفة',
                            'متوسط المرة',
                        ]}
                        caption="أكثر العمليات تكلفة في الفترة المحددة"
                        className="[&_table]:min-w-[40rem]"
                    >
                        {costlyOperations.map((operation) => (
                            <tr
                                key={`${operation.label}-${operation.section ?? 'none'}`}
                                className="hover:bg-surface-sunken"
                            >
                                <Td className="font-semibold text-fg-default">{operation.label}</Td>
                                <Td>{operation.section ?? '—'}</Td>
                                <Td className="tabular-nums">{formatNumber(operation.count)}</Td>
                                <Td className="font-bold text-fg-default tabular-nums">
                                    {formatMoney(operation.total_cost, totals.currency)}
                                </Td>
                                <Td className="tabular-nums">
                                    {formatMoney(operation.avg_cost, totals.currency, 3)}
                                </Td>
                            </tr>
                        ))}
                    </DataTable>
                )}
            </Card>
        </AdminLayout>
    );
}

function CompareBlock({
    label,
    current,
    previous,
    change,
}: {
    label: string;
    current: string;
    previous: string;
    change: number | null;
}) {
    return (
        <div className="rounded-card border border-border-default p-4">
            <p className="text-caption text-fg-muted">{label}</p>
            <p className="mt-1.5 text-metric font-bold text-fg-default tabular-nums">{current}</p>
            <p className="mt-1 flex items-center gap-2 text-caption text-fg-subtle tabular-nums">
                <span>السابق {previous}</span>
                <span
                    className={cn(
                        'rounded-pill px-2 py-0.5 text-micro font-bold',
                        change === null
                            ? 'bg-neutral-soft text-neutral'
                            : change > 0
                              ? 'bg-warning-soft text-warning'
                              : 'bg-success-soft text-success',
                    )}
                >
                    {formatChange(change)}
                </span>
            </p>
        </div>
    );
}

function AverageBlock({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-card border border-border-default p-4">
            <p className="text-caption text-fg-muted">{label}</p>
            <p className="mt-1.5 text-title font-bold text-fg-default tabular-nums">{value}</p>
        </div>
    );
}
