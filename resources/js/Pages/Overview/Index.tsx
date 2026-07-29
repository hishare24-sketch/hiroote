import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowLeftRight,
    CircleGauge,
    Coins,
    MessagesSquare,
    ScrollText,
    Server,
    Timer,
} from 'lucide-react';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Badge } from '@/Components/ui/Badge';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { Toggle } from '@/Components/ui/Toggle';
import { usePermissions } from '@/Hooks/usePermissions';
import type { StatusTone } from '@/types';

interface Metric {
    value: string;
    caption: string;
    progress?: number;
}

interface OverviewProps {
    systemStatus: { label: string; tone: StatusTone };
    metrics: {
        tokens: Metric | null;
        conversations: Metric | null;
        avgDuration: Metric | null;
        autoResolutionRate: Metric | null;
    };
    escalations: { label: string; count: number; share: string; tone: StatusTone }[] | null;
    providers: {
        id: number;
        name: string;
        model: string | null;
        is_active: boolean;
        priority: number;
        status: string;
    }[];
    quickControls: { key: string; label: string; enabled: boolean }[];
    attentionAlerts: { title: string; detail: string; tone: StatusTone; href: string }[];
    recentActivity: { id: number; action: string; actor: string; created_at: string }[];
}

const ALERT_TONES: Record<StatusTone, string> = {
    success: 'bg-success-soft',
    warning: 'bg-warning-soft',
    danger: 'bg-danger-soft',
    info: 'bg-info-soft',
    neutral: 'bg-neutral-soft',
};

const ALERT_DOTS: Record<StatusTone, string> = {
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    info: 'bg-info',
    neutral: 'bg-neutral',
};

function queueLabel(index: number): { text: string; tone: StatusTone } {
    if (index === 0) {
        return { text: 'نشط', tone: 'success' };
    }
    return { text: `احتياطي ${String(index)}`, tone: index === 1 ? 'info' : 'warning' };
}

/** بطاقة مؤشر لم يُوصَل مصدرها بعد — تقول ذلك صراحةً بدل عرض صفر مضلل. */
function PendingMetric({ label }: { label: string }) {
    return (
        <div className="flex flex-col justify-between gap-3 rounded-card border border-dashed border-border-strong bg-surface-raised p-5">
            <p className="text-sm text-fg-muted">{label}</p>
            <p className="text-sm text-fg-subtle">بانتظار محرك القياس — المرحلة 2</p>
        </div>
    );
}

export default function Index({
    systemStatus,
    metrics,
    escalations,
    providers,
    quickControls,
    attentionAlerts,
    recentActivity,
}: OverviewProps) {
    const { can } = usePermissions();
    const canToggle = can('maintenance.toggle');

    return (
        <AdminLayout>
            <Head title="نظرة عامة" />

            <PageHeader
                title="نظرة عامة"
                description="مركز تشغيل ومراقبة المساعد الذكي"
                systemStatus={systemStatus}
                period="آخر 7 أيام"
            />

            {/* البطاقات الإحصائية العليا */}
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {metrics.tokens === null ? (
                    <PendingMetric label="استهلاك التوكن الشهري" />
                ) : (
                    <StatCard
                        label="استهلاك التوكن الشهري"
                        value={metrics.tokens.value}
                        caption={metrics.tokens.caption}
                        progress={metrics.tokens.progress}
                        icon={Coins}
                        tone="accent"
                    />
                )}

                {metrics.conversations === null ? (
                    <PendingMetric label="إجمالي المحادثات" />
                ) : (
                    <StatCard
                        label="إجمالي المحادثات"
                        value={metrics.conversations.value}
                        caption={metrics.conversations.caption}
                        progress={metrics.conversations.progress}
                        icon={MessagesSquare}
                        tone="warning"
                    />
                )}

                {metrics.avgDuration === null ? (
                    <PendingMetric label="متوسط مدة المحادثة" />
                ) : (
                    <StatCard
                        label="متوسط مدة المحادثة"
                        value={metrics.avgDuration.value}
                        caption={metrics.avgDuration.caption}
                        progress={metrics.avgDuration.progress}
                        icon={Timer}
                        tone="info"
                    />
                )}

                {metrics.autoResolutionRate === null ? (
                    <PendingMetric label="نسبة الحل التلقائي" />
                ) : (
                    <StatCard
                        label="نسبة الحل التلقائي"
                        value={metrics.autoResolutionRate.value}
                        caption={metrics.autoResolutionRate.caption}
                        progress={metrics.autoResolutionRate.progress}
                        icon={CircleGauge}
                        tone="success"
                    />
                )}
            </div>

            <div className="grid gap-4 lg:grid-cols-3">
                {/* رسم استهلاك التوكن */}
                <Card className="lg:col-span-2">
                    <CardHeader
                        title="حجم ومعدل استهلاك التوكن"
                        description="مقارنة بالفترة السابقة"
                    />
                    <CardBody>
                        <EmptyState
                            icon={Coins}
                            title="لا توجد قراءات بعد"
                            description="يبدأ الرسم بالتعبئة فور تسجيل أول محادثة عبر الـ Orchestrator في المرحلة الثانية."
                        />
                    </CardBody>
                </Card>

                {/* التحويل والتصعيد */}
                <Card>
                    <CardHeader title="التحويل والتصعيد" />
                    <CardBody className="p-0">
                        {escalations === null || escalations.length === 0 ? (
                            <EmptyState
                                icon={ArrowLeftRight}
                                title="لا توجد تحويلات"
                                description="ستظهر أنواع التحويل ونسبها هنا."
                            />
                        ) : (
                            <ul>
                                {escalations.map((row) => (
                                    <li
                                        key={row.label}
                                        className="flex items-center justify-between gap-3 border-b border-border-default px-6 py-3.5 last:border-0"
                                    >
                                        <span className="flex items-center gap-2 text-sm font-bold text-fg-strong">
                                            <span
                                                aria-hidden
                                                className={`size-2.5 rounded-full ${ALERT_DOTS[row.tone]}`}
                                            />
                                            {row.label}
                                        </span>
                                        <span className="flex items-center gap-4">
                                            <span className="text-sm font-bold text-fg-default">
                                                {row.count.toLocaleString('ar')}
                                            </span>
                                            <span className="text-xs text-fg-muted">
                                                {row.share}
                                            </span>
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardBody>
                </Card>
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                {/* المزودون وحالة الطابور */}
                <Card>
                    <CardHeader
                        title="المزودون وحالة الطابور"
                        actions={
                            <Link
                                href="/providers"
                                className="text-xs font-medium text-accent hover:underline"
                            >
                                إدارة
                            </Link>
                        }
                    />
                    <CardBody className="space-y-2">
                        {providers.length === 0 ? (
                            <EmptyState
                                icon={Server}
                                title="لا يوجد مزودون مفعلون"
                                description="فعّل مزودًا واحدًا على الأقل ليبدأ التشغيل."
                            />
                        ) : (
                            providers.map((provider, index) => {
                                const queue = queueLabel(index);
                                return (
                                    <div
                                        key={provider.id}
                                        className="flex items-center justify-between gap-3 rounded-control border border-border-default bg-surface-sunken px-4 py-2.5"
                                    >
                                        <Badge tone={queue.tone}>{queue.text}</Badge>
                                        <span className="flex min-w-0 flex-1 items-center justify-end gap-4">
                                            <span className="truncate text-xs text-fg-muted">
                                                {provider.model ?? '—'}
                                            </span>
                                            <span className="text-[15px] font-bold text-fg-default">
                                                {provider.name}
                                            </span>
                                        </span>
                                    </div>
                                );
                            })
                        )}
                    </CardBody>
                </Card>

                {/* مفاتيح التحكم السريعة */}
                <Card>
                    <CardHeader
                        title="مفاتيح التحكم السريعة"
                        description={
                            canToggle ? undefined : 'تحتاج صلاحية التحكم لتعديل هذه المفاتيح.'
                        }
                    />
                    <CardBody className="grid gap-2">
                        {quickControls.map((control) => (
                            <Toggle
                                key={control.key}
                                label={control.label}
                                checked={control.enabled}
                                disabled={!canToggle}
                                onChange={(checked) => {
                                    router.post(
                                        '/settings/toggle',
                                        { key: control.key, enabled: checked },
                                        { preserveScroll: true },
                                    );
                                }}
                            />
                        ))}
                    </CardBody>
                </Card>
            </div>

            {/* تنبيهات تحتاج إجراء */}
            <Card>
                <CardHeader title="تنبيهات تحتاج إجراء" />
                <CardBody>
                    {attentionAlerts.length === 0 ? (
                        <EmptyState
                            title="لا شيء يحتاج إجراء"
                            description="كل المزودين مفعّلون وبمفاتيح سارية، ولا تنبيهات ميزانية."
                        />
                    ) : (
                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {attentionAlerts.map((alert) => (
                                <Link
                                    key={alert.title}
                                    href={alert.href}
                                    className={`flex items-start gap-2.5 rounded-control px-4 py-3 transition-opacity hover:opacity-80 ${ALERT_TONES[alert.tone]}`}
                                >
                                    <span
                                        aria-hidden
                                        className={`mt-1.5 size-2.5 shrink-0 rounded-full ${ALERT_DOTS[alert.tone]}`}
                                    />
                                    <span className="min-w-0">
                                        <span className="block text-[13px] font-bold text-fg-strong">
                                            {alert.title}
                                        </span>
                                        <span className="mt-0.5 block text-xs text-fg-muted">
                                            {alert.detail}
                                        </span>
                                    </span>
                                </Link>
                            ))}
                        </div>
                    )}
                </CardBody>
            </Card>

            {/* آخر النشاط — إضافة على الفيجما: يربط النظرة العامة بسجل التدقيق */}
            {recentActivity.length > 0 ? (
                <Card>
                    <CardHeader
                        title="آخر النشاط"
                        actions={
                            <Link
                                href="/audit"
                                className="text-xs font-medium text-accent hover:underline"
                            >
                                السجل الكامل
                            </Link>
                        }
                    />
                    <CardBody className="p-0">
                        <ul>
                            {recentActivity.map((entry) => (
                                <li
                                    key={entry.id}
                                    className="flex items-center justify-between gap-3 border-b border-border-default px-6 py-3 last:border-0"
                                >
                                    <span className="flex items-center gap-2">
                                        <ScrollText
                                            aria-hidden
                                            className="size-4 shrink-0 text-fg-subtle"
                                        />
                                        <span
                                            className="font-mono text-xs text-fg-default"
                                            dir="ltr"
                                        >
                                            {entry.action}
                                        </span>
                                    </span>
                                    <span className="flex items-center gap-4 text-xs text-fg-muted">
                                        <span>{entry.actor}</span>
                                        <span>
                                            {new Date(entry.created_at).toLocaleString('ar', {
                                                dateStyle: 'short',
                                                timeStyle: 'short',
                                            })}
                                        </span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </CardBody>
                </Card>
            ) : null}
        </AdminLayout>
    );
}
