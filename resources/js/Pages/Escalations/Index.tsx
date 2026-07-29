import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Bot, ClipboardList, Clock, Headset, ShieldAlert, Ticket } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { StatusTone } from '@/types';
import type {
    EscalationJourneyStep,
    EscalationPathSummary,
    EscalationRow,
    EscalationRule,
    EscalationTarget,
    RankedItem,
} from '@/types/conversations';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Badge } from '@/Components/ui/Badge';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { DataTable, Td } from '@/Components/ui/DataTable';
import { EmptyState } from '@/Components/ui/EmptyState';
import { PageHeader } from '@/Components/ui/PageHeader';
import { PeriodFilter, type PeriodOption } from '@/Components/ui/PeriodFilter';
import { ShareList } from '@/Components/ui/ShareList';
import { StatCard } from '@/Components/ui/StatCard';
import { formatDuration, formatNumber, formatPercent, formatRelative } from '@/lib/format';
import { cn } from '@/lib/cn';

interface Props {
    systemStatus: { label: string; tone: StatusTone };
    period: { key: string; label: string; from: string; to: string };
    periodOptions: PeriodOption[];
    totals: {
        escalated: number;
        escalation_rate: number;
        open: number;
        avg_wait_seconds: number | null;
        resolved_without_escalation: number;
    };
    paths: EscalationPathSummary[];
    journey: EscalationJourneyStep[];
    reasons: RankedItem[];
    rules: EscalationRule[];
    openCases: EscalationRow[];
}

const PATH_ICONS: Record<EscalationTarget, LucideIcon> = {
    specialist_assistant: Bot,
    human_agent: Headset,
    ticket: Ticket,
};

/** شاشة التحويل والتصعيد — وثيقة 06 §10. */
export default function EscalationsIndex({
    systemStatus,
    period,
    periodOptions,
    totals,
    paths,
    journey,
    reasons,
    rules,
    openCases,
}: Props) {
    return (
        <AdminLayout>
            <Head title="التحويل والتصعيد" />

            <PageHeader
                title="التحويل والتصعيد"
                description="متى يخرج السؤال من يد المساعد، وإلى أين يذهب"
                systemStatus={systemStatus}
                period={period.label}
            />

            <PeriodFilter
                options={periodOptions}
                active={period.key}
                from={null}
                to={null}
                url="/escalations"
            />

            <section aria-label="ملخص التحويل" className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="نسبة التحويل"
                    value={formatPercent(totals.escalation_rate)}
                    caption={`${formatNumber(totals.escalated)} محادثة خرجت من المساعد`}
                    icon={ClipboardList}
                    tone={totals.escalation_rate <= 25 ? 'success' : 'warning'}
                    progress={totals.escalation_rate}
                />
                <StatCard
                    label="حُلَّت دون تحويل"
                    value={formatPercent(totals.resolved_without_escalation)}
                    caption="أُغلقت داخل المساعد نفسه"
                    icon={Bot}
                    tone={totals.resolved_without_escalation >= 60 ? 'success' : 'warning'}
                    progress={totals.resolved_without_escalation}
                />
                <StatCard
                    label="الحالات المفتوحة الآن"
                    value={formatNumber(totals.open)}
                    caption={
                        totals.open === 0 ? 'لا حالة تنتظر متابعة' : 'تحتاج متابعة قبل الإغلاق'
                    }
                    icon={ShieldAlert}
                    tone={totals.open === 0 ? 'success' : totals.open > 5 ? 'danger' : 'warning'}
                />
                <StatCard
                    label="متوسط الانتظار"
                    value={formatDuration(totals.avg_wait_seconds)}
                    caption="من لحظة التحويل إلى بدء المعالجة"
                    icon={Clock}
                    tone="info"
                />
            </section>

            <section aria-label="مسارات التحويل الثلاثة" className="grid gap-4 lg:grid-cols-3">
                {paths.map((path) => {
                    const Icon = PATH_ICONS[path.target.value];

                    return (
                        <Card key={path.target.value}>
                            <CardBody className="flex flex-col gap-4">
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="text-caption font-medium text-fg-muted">
                                            التحويل إلى
                                        </p>
                                        <p className="mt-0.5 text-title font-extrabold text-fg-default">
                                            {path.target.label}
                                        </p>
                                    </div>
                                    <span
                                        aria-hidden
                                        className={cn(
                                            'flex size-9 shrink-0 items-center justify-center rounded-control',
                                            path.target.value === 'specialist_assistant'
                                                ? 'bg-accent-soft text-accent'
                                                : path.target.value === 'human_agent'
                                                  ? 'bg-info-soft text-info'
                                                  : 'bg-warning-soft text-warning',
                                        )}
                                    >
                                        <Icon className="size-[18px]" />
                                    </span>
                                </div>

                                <div className="flex items-baseline gap-2">
                                    <p className="text-metric font-bold text-fg-default tabular-nums">
                                        {formatNumber(path.count)}
                                    </p>
                                    <p className="text-caption font-bold text-fg-subtle">
                                        {formatPercent(path.share)} من التحويلات
                                    </p>
                                </div>

                                <dl className="grid grid-cols-2 gap-3 border-t border-border-default pt-3 text-caption">
                                    <div>
                                        <dt className="text-fg-subtle">متوسط الانتظار</dt>
                                        <dd className="mt-0.5 font-bold text-fg-default tabular-nums">
                                            {formatDuration(path.avg_wait_seconds)}
                                        </dd>
                                    </div>
                                    <div>
                                        <dt className="text-fg-subtle">متوسط المعالجة</dt>
                                        <dd className="mt-0.5 font-bold text-fg-default tabular-nums">
                                            {formatDuration(path.avg_handling_seconds)}
                                        </dd>
                                    </div>
                                </dl>

                                {path.open_count > 0 ? (
                                    <p className="rounded-control bg-warning-soft px-3 py-1.5 text-caption font-bold text-warning">
                                        {formatNumber(path.open_count)} ما زالت مفتوحة
                                    </p>
                                ) : (
                                    <p className="rounded-control bg-success-soft px-3 py-1.5 text-caption font-bold text-success">
                                        لا حالات مفتوحة في هذا المسار
                                    </p>
                                )}
                            </CardBody>
                        </Card>
                    );
                })}
            </section>

            <div className="grid gap-4 lg:grid-cols-[1fr_1fr]">
                <Card>
                    <CardHeader
                        title="رحلة التحويل"
                        description="من كل المحادثات إلى ما بقي مفتوحًا"
                    />
                    <CardBody>
                        <ol className="flex flex-col gap-4">
                            {journey.map((step, index) => (
                                <li key={step.label} className="flex gap-3">
                                    <span
                                        aria-hidden
                                        className="mt-0.5 flex size-6 shrink-0 items-center justify-center rounded-full bg-accent-soft text-micro font-bold text-accent tabular-nums"
                                    >
                                        {index + 1}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-baseline justify-between gap-3">
                                            <p className="text-body font-semibold text-fg-default">
                                                {step.label}
                                            </p>
                                            <p className="flex shrink-0 items-baseline gap-2 text-caption font-bold text-fg-muted tabular-nums">
                                                <span>{formatNumber(step.count)}</span>
                                                <span aria-hidden className="text-fg-subtle">
                                                    ·
                                                </span>
                                                <span className="font-medium text-fg-subtle">
                                                    {formatPercent(step.share)}
                                                </span>
                                            </p>
                                        </div>
                                        <p className="mt-0.5 text-caption text-fg-muted">
                                            {step.detail}
                                        </p>
                                        <div className="mt-2 h-1.5 w-full overflow-hidden rounded-pill bg-surface-track">
                                            <div
                                                className="h-full rounded-pill bg-accent"
                                                style={{
                                                    width: `${String(Math.min(100, step.share))}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader
                        title="أسباب التحويل الأكثر تكرارًا"
                        description="ما الذي يخرج المحادثة من يد المساعد"
                    />
                    <CardBody>
                        <ShareList
                            items={reasons}
                            unit="حالة"
                            emptyTitle="لا تحويلات في هذه الفترة"
                            emptyDescription="أُغلقت كل المحادثات داخل المساعد."
                        />
                    </CardBody>
                </Card>
            </div>

            <Card>
                <CardHeader
                    title="قواعد تحديد النية والتصعيد"
                    description="الشروط النافذة الآن — تصبح قابلة للتحرير مع شاشة مستويات المساعد"
                />
                <DataTable
                    columns={['الشرط', 'الإجراء', 'درجة الخطورة']}
                    caption="قواعد التحويل والتصعيد النافذة"
                    className="[&_table]:min-w-[42rem]"
                >
                    {rules.map((rule) => (
                        <tr key={rule.condition} className="hover:bg-surface-sunken">
                            <Td className="font-semibold text-fg-default">{rule.condition}</Td>
                            <Td>{rule.action}</Td>
                            <Td>
                                {rule.severity === null ? (
                                    <span className="text-fg-subtle">—</span>
                                ) : (
                                    <Badge tone={rule.severity.tone}>{rule.severity.label}</Badge>
                                )}
                            </Td>
                        </tr>
                    ))}
                </DataTable>
            </Card>

            <Card>
                <CardHeader
                    title="الحالات المفتوحة"
                    description="الأحرج أولًا ثم الأقدم انتظارًا"
                />
                {openCases.length === 0 ? (
                    <EmptyState
                        title="لا حالات مفتوحة"
                        description="كل ما حُوِّل في هذه الفترة أُغلق."
                    />
                ) : (
                    <DataTable
                        columns={[
                            'المرجع',
                            'الموضوع',
                            'القسم',
                            'المسار',
                            'الخطورة',
                            'سبب التحويل',
                            'الانتظار',
                            'منذ',
                            '',
                        ]}
                        caption="الحالات المفتوحة التي تحتاج متابعة"
                    >
                        {openCases.map((item) => (
                            <tr key={item.id} className="hover:bg-surface-sunken">
                                <Td className="font-bold whitespace-nowrap text-fg-default tabular-nums">
                                    {item.reference}
                                </Td>
                                <Td>{item.subject}</Td>
                                <Td className="whitespace-nowrap">{item.section}</Td>
                                <Td>
                                    <Badge tone={item.target.tone}>{item.target.label}</Badge>
                                </Td>
                                <Td>
                                    <Badge tone={item.severity.tone} dot>
                                        {item.severity.label}
                                    </Badge>
                                </Td>
                                <Td>{item.reason}</Td>
                                <Td className="whitespace-nowrap tabular-nums">
                                    {formatDuration(item.wait_seconds)}
                                </Td>
                                <Td className="whitespace-nowrap">
                                    {formatRelative(item.created_at)}
                                </Td>
                                <Td>
                                    {item.conversation_id === null ? (
                                        <span className="text-fg-subtle">—</span>
                                    ) : (
                                        <Link
                                            href={`/conversations/${String(item.conversation_id)}`}
                                            className="inline-flex items-center gap-1 rounded-control px-2 py-1 text-caption font-bold text-accent hover:bg-accent-soft"
                                        >
                                            المحادثة
                                            <ArrowLeft aria-hidden className="size-3.5" />
                                        </Link>
                                    )}
                                </Td>
                            </tr>
                        ))}
                    </DataTable>
                )}
            </Card>
        </AdminLayout>
    );
}
