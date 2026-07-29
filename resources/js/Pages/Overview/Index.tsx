import { Head, Link, router } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ArrowLeft,
    Check,
    CircleGauge,
    KeyRound,
    ScrollText,
    Server,
    ShieldCheck,
} from 'lucide-react';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Badge } from '@/Components/ui/Badge';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { Toggle } from '@/Components/ui/Toggle';
import { usePermissions } from '@/Hooks/usePermissions';
import type { StatusTone } from '@/types';

type Tone = StatusTone | 'accent';

interface Stat {
    key: string;
    label: string;
    value: string;
    caption: string;
    tone: Tone;
    progress?: number;
}

interface SetupStep {
    title: string;
    detail: string;
    done: boolean;
    href: string | null;
    cta: string | null;
}

interface ProviderRow {
    id: number;
    name: string;
    model: string | null;
    is_active: boolean;
    is_enabled: boolean;
    status: 'operational' | 'degraded' | 'down' | 'unknown';
    status_label: string;
    has_key: boolean;
    latency_ms: number | null;
    balance: number;
    currency: string;
}

interface ActivityRow {
    id: number;
    action: string;
    category: string;
    tone: string;
    actor: string;
    created_at: string;
}

interface OverviewProps {
    systemStatus: { label: string; tone: StatusTone };
    stats: Stat[];
    setupSteps: SetupStep[];
    providers: ProviderRow[];
    quickControls: { key: string; label: string; enabled: boolean }[];
    recentActivity: ActivityRow[];
}

const STAT_ICONS: Record<string, LucideIcon> = {
    providers: Server,
    keys: KeyRound,
    health: CircleGauge,
    audit: ScrollText,
};

const STATUS_TONES: Record<ProviderRow['status'], StatusTone> = {
    operational: 'success',
    degraded: 'warning',
    down: 'danger',
    unknown: 'neutral',
};

const CATEGORY_TONES: Record<string, string> = {
    accent: 'bg-accent-soft text-accent',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
    info: 'bg-info-soft text-info',
    neutral: 'bg-neutral-soft text-neutral',
};

function categoryClass(tone: string): string {
    return CATEGORY_TONES[tone] ?? 'bg-neutral-soft text-neutral';
}

function formatTime(value: string): string {
    return new Date(value).toLocaleString('ar', { dateStyle: 'short', timeStyle: 'short' });
}

/** لوحة خطوات التشغيل — تختفي كليًا عند اكتمال ما يمكن إنجازه الآن. */
function SetupPanel({ steps }: { steps: SetupStep[] }) {
    const actionable = steps.filter((step) => step.href !== null);
    const done = actionable.filter((step) => step.done).length;
    const percent = actionable.length === 0 ? 100 : (done / actionable.length) * 100;

    return (
        <Card>
            <CardHeader
                title="خطوات التشغيل"
                description={`${String(done)} من ${String(actionable.length)} خطوة مكتملة — أكملها ليبدأ المساعد في استقبال الطلبات.`}
                actions={
                    <div className="flex items-center gap-3">
                        <div className="h-1.5 w-24 overflow-hidden rounded-pill bg-surface-track">
                            <div
                                className="h-full rounded-pill bg-accent transition-[width]"
                                style={{ width: `${String(percent)}%` }}
                            />
                        </div>
                        <span className="text-body font-bold text-fg-default">
                            {Math.round(percent)}%
                        </span>
                    </div>
                }
            />
            <CardBody className="p-0">
                <ol>
                    {steps.map((step, index) => (
                        <li
                            key={step.title}
                            className="flex flex-wrap items-center gap-4 border-b border-border-default px-6 py-4 last:border-0"
                        >
                            <span
                                aria-hidden
                                className={
                                    step.done
                                        ? 'flex size-8 shrink-0 items-center justify-center rounded-full bg-success text-white'
                                        : 'flex size-8 shrink-0 items-center justify-center rounded-full border-2 border-border-strong text-body font-bold text-fg-subtle'
                                }
                            >
                                {step.done ? <Check className="size-4" /> : index + 1}
                            </span>

                            <span className="min-w-0 flex-1">
                                <span
                                    className={
                                        step.done
                                            ? 'block text-body font-medium text-fg-muted line-through'
                                            : 'block text-body font-bold text-fg-default'
                                    }
                                >
                                    {step.title}
                                </span>
                                <span className="mt-0.5 block text-caption text-fg-muted">
                                    {step.detail}
                                </span>
                            </span>

                            {step.done ? (
                                <Badge tone="success">تم</Badge>
                            ) : step.href !== null && step.cta !== null ? (
                                <Link
                                    href={step.href}
                                    className="inline-flex shrink-0 items-center gap-1.5 rounded-control bg-accent px-4 py-2 text-caption font-bold text-white hover:brightness-110"
                                >
                                    {step.cta}
                                    <ArrowLeft aria-hidden className="size-3.5" />
                                </Link>
                            ) : (
                                <Badge tone="neutral">لاحقًا</Badge>
                            )}
                        </li>
                    ))}
                </ol>
            </CardBody>
        </Card>
    );
}

export default function Index({
    systemStatus,
    stats,
    setupSteps,
    providers,
    quickControls,
    recentActivity,
}: OverviewProps) {
    const { can } = usePermissions();
    const canToggle = can('maintenance.toggle');
    const setupIncomplete = setupSteps.some((step) => step.href !== null && !step.done);

    return (
        <AdminLayout>
            <Head title="نظرة عامة" />

            <PageHeader
                title="نظرة عامة"
                description="مركز تشغيل ومراقبة المساعد الذكي"
                systemStatus={systemStatus}
                period="آخر 7 أيام"
            />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {stats.map((stat) => (
                    <StatCard
                        key={stat.key}
                        label={stat.label}
                        value={stat.value}
                        caption={stat.caption}
                        progress={stat.progress}
                        tone={stat.tone}
                        icon={STAT_ICONS[stat.key] ?? Server}
                    />
                ))}
            </div>

            {setupIncomplete ? <SetupPanel steps={setupSteps} /> : null}

            <div className="grid gap-4 lg:grid-cols-5">
                {/* المزودون وحالة الطابور */}
                <Card className="lg:col-span-3">
                    <CardHeader
                        title="المزودون وحالة الطابور"
                        description="الترتيب يحدد من يستلم الطلبات عند تعطل السابق."
                        actions={
                            <Link
                                href="/providers"
                                className="text-caption font-bold text-accent hover:underline"
                            >
                                إدارة
                            </Link>
                        }
                    />
                    <CardBody className="p-0">
                        <ul>
                            {providers.map((provider, index) => (
                                <li
                                    key={provider.id}
                                    className="flex flex-wrap items-center gap-3 border-b border-border-default px-6 py-3.5 last:border-0"
                                >
                                    <span
                                        aria-hidden
                                        className="flex size-7 shrink-0 items-center justify-center rounded-control bg-surface-track text-caption font-bold text-fg-muted"
                                    >
                                        {index + 1}
                                    </span>

                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-center gap-2">
                                            <span className="text-body font-bold text-fg-default">
                                                {provider.name}
                                            </span>
                                            {provider.is_active ? (
                                                <Badge tone="success" dot>
                                                    نشط
                                                </Badge>
                                            ) : null}
                                            {!provider.is_enabled ? (
                                                <Badge tone="neutral">معطل</Badge>
                                            ) : null}
                                        </span>
                                        <span className="mt-0.5 block text-caption text-fg-muted">
                                            {provider.model ?? 'بلا نموذج افتراضي'} ·{' '}
                                            {provider.balance.toLocaleString('ar', {
                                                maximumFractionDigits: 0,
                                            })}{' '}
                                            {provider.currency}
                                        </span>
                                    </span>

                                    <span className="flex shrink-0 items-center gap-2">
                                        {provider.has_key ? (
                                            <Badge tone="success">مفتاح فعال</Badge>
                                        ) : (
                                            <Badge tone="danger">بلا مفتاح</Badge>
                                        )}
                                        <Badge tone={STATUS_TONES[provider.status]} dot>
                                            {provider.status_label}
                                        </Badge>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </CardBody>
                </Card>

                {/* مفاتيح التحكم السريعة */}
                <Card className="lg:col-span-2">
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

            {/* آخر النشاط */}
            <Card>
                <CardHeader
                    title="آخر النشاط"
                    description="كل تغيير حساس يُسجَّل في سجل غير قابل للتعديل."
                    actions={
                        <Link
                            href="/audit"
                            className="text-caption font-bold text-accent hover:underline"
                        >
                            السجل الكامل
                        </Link>
                    }
                />
                <CardBody className="p-0">
                    {recentActivity.length === 0 ? (
                        <p className="px-6 py-8 text-center text-body text-fg-muted">
                            لا يوجد نشاط مسجل بعد.
                        </p>
                    ) : (
                        <ul>
                            {recentActivity.map((entry) => (
                                <li
                                    key={entry.id}
                                    className="flex flex-wrap items-center gap-3 border-b border-border-default px-6 py-3 last:border-0"
                                >
                                    <span
                                        className={`shrink-0 rounded-pill px-2.5 py-1 text-caption font-bold ${categoryClass(entry.tone)}`}
                                    >
                                        {entry.category}
                                    </span>
                                    <span
                                        className="min-w-0 flex-1 truncate font-mono text-caption text-fg-default"
                                        dir="ltr"
                                    >
                                        {entry.action}
                                    </span>
                                    <span className="shrink-0 text-caption text-fg-muted">
                                        {entry.actor}
                                    </span>
                                    <span className="shrink-0 text-caption text-fg-muted">
                                        {formatTime(entry.created_at)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardBody>
            </Card>

            {/* ما يصل مع المرحلة الثانية — سطر واحد بدل بطاقات مجوّفة */}
            <p className="flex items-center justify-center gap-2 pb-2 text-center text-caption text-fg-muted">
                <ShieldCheck aria-hidden className="size-4 shrink-0" />
                مؤشرات التوكن والتكلفة والمحادثات تظهر هنا فور ربط محرك المحادثات في المرحلة
                الثانية.
            </p>
        </AdminLayout>
    );
}
