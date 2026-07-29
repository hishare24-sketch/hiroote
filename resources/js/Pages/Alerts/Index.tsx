import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Activity,
    AlertTriangle,
    BellRing,
    CheckCircle2,
    FlaskConical,
    Pencil,
    Play,
    Plus,
    Trash2,
} from 'lucide-react';
import type { StatusTone } from '@/types';
import type {
    AlertEventRow,
    AlertOptions,
    AlertRuleRow,
    MetricOption,
    MetricUnit,
} from '@/types/alerts';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Alert } from '@/Components/ui/Alert';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { RuleDialog } from './RuleDialog';
import { usePermissions } from '@/Hooks/usePermissions';
import {
    formatDateTime,
    formatLatency,
    formatMoney,
    formatNumber,
    formatRelative,
} from '@/lib/format';
import { cn } from '@/lib/cn';

interface Props {
    systemStatus: { label: string; tone: StatusTone };
    project: { id: number; name: string };
    rules: AlertRuleRow[];
    events: AlertEventRow[];
    metrics: MetricOption[];
    options: AlertOptions;
}

/** تنسيق قيمة المؤشر بوحدتها — العدد وحده لا يقول إن كان نسبةً أو ريالًا. */
export function formatMetric(value: number, unit: MetricUnit): string {
    switch (unit) {
        case 'percent':
            return `${formatNumber(value, Number.isInteger(value) ? 0 : 1)}%`;
        case 'money':
            return formatMoney(value);
        case 'milliseconds':
            return formatLatency(value);
        case 'rating':
            return `${formatNumber(value, 1)} من 5`;
        default:
            return formatNumber(value);
    }
}

export function formatWindow(minutes: number): string {
    if (minutes === 0) {
        return 'قيمة لحظية';
    }

    if (minutes < 60) {
        return `آخر ${formatNumber(minutes)} دقيقة`;
    }

    if (minutes < 1440) {
        return `آخر ${formatNumber(minutes / 60)} ساعة`;
    }

    return `آخر ${formatNumber(minutes / 1440)} يوم`;
}

/** شاشة التنبيهات — وثيقة 06 §11. */
export default function AlertsIndex({
    systemStatus,
    project,
    rules,
    events,
    metrics,
    options,
}: Props) {
    const { can } = usePermissions();
    const manage = can('alerts.manage');

    const [dialog, setDialog] = useState<{ rule?: AlertRuleRow } | null>(null);

    const active = rules.filter((rule) => rule.is_enabled).length;
    const breached = rules.filter((rule) => rule.is_enabled && rule.breached).length;
    const unmeasurable = rules.filter(
        (rule) => rule.is_enabled && rule.current_value === null,
    ).length;
    const open = events.filter((event) => event.status.value !== 'resolved').length;

    return (
        <AdminLayout>
            <Head title="التنبيهات" />

            <PageHeader
                title="التنبيهات"
                description={`ما يُراقَب في ${project.name} وما تجاوز حدَّه`}
                systemStatus={systemStatus}
                actions={
                    manage ? (
                        <span className="flex items-center gap-2">
                            <Button
                                variant="ghost"
                                onClick={() => {
                                    router.post('/alerts/evaluate', {}, { preserveScroll: true });
                                }}
                            >
                                <Play aria-hidden className="size-4" />
                                قيّم الآن
                            </Button>
                            <Button
                                onClick={() => {
                                    setDialog({});
                                }}
                            >
                                <Plus aria-hidden className="size-4" />
                                قاعدة جديدة
                            </Button>
                        </span>
                    ) : undefined
                }
            />

            <section
                aria-label="ملخص التنبيهات"
                className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
            >
                <StatCard
                    label="قواعد مفعّلة"
                    value={formatNumber(active)}
                    caption={`من ${formatNumber(rules.length)} قاعدة`}
                    icon={BellRing}
                    tone="accent"
                />
                <StatCard
                    label="متجاوزة الآن"
                    value={formatNumber(breached)}
                    caption={breached === 0 ? 'كل المؤشرات ضمن حدودها' : 'تحتاج نظرة'}
                    icon={AlertTriangle}
                    tone={breached === 0 ? 'success' : 'danger'}
                />
                <StatCard
                    label="أحداث مفتوحة"
                    value={formatNumber(open)}
                    caption={open === 0 ? 'لا شيء ينتظر إقرارًا' : 'لم تُقَرّ ولم تعد للطبيعي'}
                    icon={Activity}
                    tone={open === 0 ? 'success' : 'warning'}
                />
                <StatCard
                    label="تعذّر قياسها"
                    value={formatNumber(unmeasurable)}
                    caption="لا بيانات كافية في الفترة"
                    icon={FlaskConical}
                    tone={unmeasurable === 0 ? 'success' : 'neutral'}
                />
            </section>

            {unmeasurable > 0 ? (
                <Alert tone="neutral" title="قاعدة لا تُقاس ليست قاعدة تطمئن">
                    {formatNumber(unmeasurable)} قاعدة مفعّلة لا تجد بيانات في فترتها، فلا تُقيَّم
                    ولا تُفعَّل. وسّع فترتها أو انتظر تراكم البيانات — بقاؤها هكذا يعني أن أحدًا لا
                    يراقب مؤشرها.
                </Alert>
            ) : null}

            <Card>
                <CardHeader
                    title="القواعد"
                    description="القيمة المعروضة محسوبة الآن لا محفوظة من آخر تقييم"
                />

                {rules.length === 0 ? (
                    <EmptyState
                        title="لا قواعد تنبيه في هذا المشروع"
                        description="أضف قاعدة تراقب مؤشرًا واحدًا وتخبر من يهمّه الأمر عند تجاوزه."
                    />
                ) : (
                    <CardBody className="grid gap-3 xl:grid-cols-2">
                        {rules.map((rule) => (
                            <RuleCard
                                key={rule.id}
                                rule={rule}
                                manage={manage}
                                onEdit={() => {
                                    setDialog({ rule });
                                }}
                            />
                        ))}
                    </CardBody>
                )}
            </Card>

            <Card>
                <CardHeader title="سجل الأحداث" description="آخر ٤٠ حدثًا — المفتوح أولًا" />

                {events.length === 0 ? (
                    <EmptyState
                        title="لا أحداث بعد"
                        description="لم يتجاوز أي مؤشر حدَّه منذ إنشاء القواعد."
                        icon={CheckCircle2}
                    />
                ) : (
                    <CardBody className="flex flex-col gap-3">
                        {events.map((event) => (
                            <EventCard key={event.id} event={event} manage={manage} />
                        ))}
                    </CardBody>
                )}
            </Card>

            {dialog === null ? null : (
                <RuleDialog
                    rule={dialog.rule}
                    metrics={metrics}
                    options={options}
                    onClose={() => {
                        setDialog(null);
                    }}
                />
            )}
        </AdminLayout>
    );
}

function RuleCard({
    rule,
    manage,
    onEdit,
}: {
    rule: AlertRuleRow;
    manage: boolean;
    onEdit: () => void;
}) {
    const measurable = rule.current_value !== null;

    return (
        <article
            className={cn(
                'flex flex-col gap-3 rounded-card border p-4',
                rule.breached ? 'border-danger bg-danger-soft/30' : 'border-border-default',
                rule.is_enabled ? '' : 'opacity-60',
            )}
        >
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="flex flex-wrap items-center gap-2 text-body font-bold text-fg-default">
                        {rule.name}
                        <Badge tone={rule.severity.tone}>{rule.severity.label}</Badge>
                        {rule.is_enabled ? null : <Badge tone="neutral">موقوفة</Badge>}
                        {rule.cooling_down ? <Badge tone="info">تهدئة</Badge> : null}
                    </p>
                    <p className="mt-0.5 text-caption text-fg-muted">
                        {rule.description ?? rule.metric_hint}
                    </p>
                </div>

                {manage ? (
                    <span className="flex shrink-0 items-center gap-1">
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                router.post(
                                    `/alerts/${String(rule.id)}/test`,
                                    {},
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            <FlaskConical aria-hidden className="size-3.5" />
                            تجربة
                        </Button>
                        <Button variant="ghost" size="sm" onClick={onEdit}>
                            <Pencil aria-hidden className="size-3.5" />
                            <span className="sr-only">تعديل</span>
                        </Button>
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                router.delete(`/alerts/${String(rule.id)}`, {
                                    preserveScroll: true,
                                });
                            }}
                        >
                            <Trash2 aria-hidden className="size-3.5 text-danger" />
                            <span className="sr-only">حذف</span>
                        </Button>
                    </span>
                ) : null}
            </div>

            <div className="flex flex-wrap items-baseline gap-x-3 gap-y-1 rounded-control bg-surface-sunken px-3 py-2">
                <span className="text-caption text-fg-muted">{rule.metric.label}</span>
                <span
                    className={cn(
                        'text-title font-bold tabular-nums',
                        measurable
                            ? rule.breached
                                ? 'text-danger'
                                : 'text-fg-default'
                            : 'text-fg-subtle',
                    )}
                >
                    {measurable ? formatMetric(rule.current_value ?? 0, rule.unit) : 'لا قياس'}
                </span>
                <span className="text-caption text-fg-subtle tabular-nums">
                    الحد {rule.comparison.label} {formatMetric(rule.threshold, rule.unit)}
                </span>
                <span className="ms-auto text-caption text-fg-subtle">{rule.current_sample}</span>
            </div>

            <dl className="flex flex-wrap gap-x-4 gap-y-1 text-caption text-fg-muted">
                <Fact label="الفترة" value={formatWindow(rule.window_minutes)} />
                <Fact
                    label="التهدئة"
                    value={
                        rule.cooldown_minutes === 0 ? 'بلا' : formatWindow(rule.cooldown_minutes)
                    }
                />
                <Fact label="مرات التفعيل" value={formatNumber(rule.trigger_count)} />
                <Fact
                    label="آخر تقييم"
                    value={
                        rule.last_evaluated_at === null
                            ? 'لم تُقيَّم'
                            : formatRelative(rule.last_evaluated_at)
                    }
                />
                <Fact
                    label="آخر تفعيل"
                    value={
                        rule.last_triggered_at === null
                            ? 'لم تُفعَّل'
                            : formatRelative(rule.last_triggered_at)
                    }
                />
            </dl>

            <div className="flex flex-wrap items-center gap-1.5 border-t border-border-default pt-2.5">
                {rule.recipients.length === 0 ? (
                    <span className="text-caption text-warning">بلا مستلمين — لن يعلم أحد</span>
                ) : (
                    rule.recipients.map((recipient, index) => (
                        <Badge
                            key={`${String(recipient.user_id ?? 0)}-${recipient.email ?? ''}-${String(index)}`}
                            tone={recipient.wired ? 'neutral' : 'warning'}
                        >
                            {recipient.name} · {recipient.channel.label}
                        </Badge>
                    ))
                )}

                {rule.auto_action.value === 'notify_only' ? null : (
                    <Badge tone={rule.auto_action.awaits ? 'warning' : 'neutral'}>
                        {rule.auto_action.label}
                        {rule.auto_action.awaits ? ' (بانتظار التنفيذ)' : ''}
                    </Badge>
                )}
            </div>
        </article>
    );
}

function EventCard({ event, manage }: { event: AlertEventRow; manage: boolean }) {
    const open = event.status.value !== 'resolved';

    return (
        <article
            className={cn(
                'flex flex-col gap-2 rounded-card border p-4',
                open ? 'border-border-strong' : 'border-border-default opacity-75',
            )}
        >
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <p className="flex flex-wrap items-center gap-2 text-body font-bold text-fg-default">
                        {event.rule_name}
                        <Badge tone={event.status.tone}>{event.status.label}</Badge>
                        <Badge tone={event.severity.tone}>{event.severity.label}</Badge>
                    </p>
                    <p className="mt-0.5 text-caption text-fg-muted tabular-nums">
                        {event.metric.label}: بلغ{' '}
                        <span className="font-bold text-danger">
                            {formatMetric(event.peak_value, event.unit)}
                        </span>{' '}
                        والحد {event.comparison} {formatMetric(event.threshold, event.unit)}
                        {event.sample === null ? '' : ` · ${event.sample}`}
                    </p>
                </div>

                {manage && open ? (
                    <span className="flex shrink-0 items-center gap-1">
                        {event.status.value === 'triggered' ? (
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    router.post(
                                        `/alerts/events/${String(event.id)}`,
                                        { status: 'acknowledged' },
                                        { preserveScroll: true },
                                    );
                                }}
                            >
                                أقرّ
                            </Button>
                        ) : null}
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                router.post(
                                    `/alerts/events/${String(event.id)}`,
                                    { status: 'resolved' },
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            أغلِق
                        </Button>
                    </span>
                ) : null}
            </div>

            <dl className="flex flex-wrap gap-x-4 gap-y-1 text-caption text-fg-subtle">
                <Fact label="بدأ" value={formatDateTime(event.triggered_at)} />
                {event.resolved_at === null ? null : (
                    <Fact label="عاد للطبيعي" value={formatRelative(event.resolved_at)} />
                )}
                {event.acknowledged_by === null ? null : (
                    <Fact label="أقرّه" value={event.acknowledged_by} />
                )}
                <Fact label="الفترة" value={formatWindow(event.window_minutes)} />
            </dl>

            {event.deliveries.length === 0 ? null : (
                <ul className="flex flex-wrap gap-1.5">
                    {event.deliveries.map((delivery, index) => (
                        <li key={`${delivery.target}-${String(index)}`}>
                            <Badge tone={delivery.status.tone}>
                                {delivery.channel.label} · {delivery.target} ·{' '}
                                {delivery.status.label}
                            </Badge>
                        </li>
                    ))}
                </ul>
            )}
        </article>
    );
}

function Fact({ label, value }: { label: string; value: string }) {
    return (
        <span className="flex items-baseline gap-1">
            <dt className="text-fg-subtle">{label}</dt>
            <dd className="font-bold text-fg-default">{value}</dd>
        </span>
    );
}
