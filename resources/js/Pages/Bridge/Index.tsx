import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Calculator, Link2, Plug, RefreshCw, Settings2 } from 'lucide-react';
import type { StatusTone, Tone } from '@/types';
import type { ConnectionMethod } from './MethodCards';
import { MethodCards } from './MethodCards';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Alert } from '@/Components/ui/Alert';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Input } from '@/Components/ui/Input';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Select } from '@/Components/ui/Select';
import { usePermissions } from '@/Hooks/usePermissions';
import { formatCompact, formatDateTime, formatNumber, formatRelative } from '@/lib/format';

interface BridgeRow {
    id: number;
    driver: string;
    base_url: string;
    auth_mode: string;
    is_enabled: boolean;
    configured: boolean;
    status: string;
    tone: Tone;
    last_synced_at: string | null;
    last_error: string | null;
    last_error_at: string | null;
}

interface Transport {
    ok: boolean;
    error: string | null;
    ms: number;
}

interface Assistant {
    provider: string | null;
    model: string | null;
    assistant_provider: string | null;
    assistant_model: string | null;
    embedding_provider: string | null;
    embedding_model: string | null;
    embeddings_enabled: boolean | null;
    system_prompt: string | null;
    assistant_level: number | null;
    allow_user_level_override: boolean | null;
    level_tokens: Record<string, number>;
    tools_enabled: boolean | null;
    write_tools_enabled: boolean | null;
    doc_max_reads: number | null;
    async_queue_enabled: boolean | null;
    async_queue_min_pages: number | null;
    reading_pipelines: number | null;
}

interface PlanRow {
    plan: string;
    label: string;
    max_per_request: number | null;
    daily: number | null;
    weekly: number | null;
    monthly: number | null;
    unlimited: boolean;
}

interface Derived {
    label: string;
    value: number;
    unit: string;
    note: string;
}

interface Snapshot {
    transport: Record<string, Transport>;
    assistant: Assistant | null;
    plans: PlanRow[];
    analytics: {
        since: string | null;
        total_cost_usd: number | null;
        by_kind: Record<string, unknown>[];
        daily: Record<string, unknown>[];
        by_provider_model: Record<string, unknown>[];
        top_users: Record<string, unknown>[];
        cache: Record<string, unknown> | null;
    } | null;
    derived: Derived[];
}

interface Props {
    systemStatus: { label: string; tone: StatusTone };
    project: { id: number; name: string };
    bridge: BridgeRow | null;
    methods: ConnectionMethod[];
    snapshot: Snapshot | null;
    fetchedAt: string;
}

const ENDPOINT_LABELS: Record<string, string> = {
    settings: 'إعداد المساعد',
    analytics: 'تحليل الاستهلاك',
    health: 'صحة البنية',
    quotas: 'استثناءات الحصص',
};

/**
 * لوحة المشروع الخارجي — عرضٌ حيّ بلا تحكّم.
 *
 * كل رقم هنا يقول من أين جاء: **مقروء** من موازين كما هو، أو **محسوب** في هاي
 * روت من أرقامه. والخلط بينهما يجعل تقديرًا يُقرأ كقياس.
 */
export default function BridgeIndex({
    systemStatus,
    project,
    bridge,
    methods,
    snapshot,
    fetchedAt,
}: Props) {
    const { can } = usePermissions();
    const manage = can('integrations.manage');
    const [editing, setEditing] = useState(bridge === null);

    return (
        <AdminLayout>
            <Head title="الربط والتكامل" />

            <PageHeader
                title="الربط والتكامل"
                description={`كل مسار ممكن بين هاي روت و${project.name} — وما يصل منه الآن`}
                systemStatus={systemStatus}
                actions={
                    <span className="flex items-center gap-2">
                        <Button
                            variant="ghost"
                            onClick={() => {
                                window.location.reload();
                            }}
                        >
                            <RefreshCw aria-hidden className="size-4" />
                            أعد الجلب
                        </Button>
                        {manage ? (
                            <Button
                                variant="ghost"
                                onClick={() => {
                                    setEditing((value) => !value);
                                }}
                            >
                                <Settings2 aria-hidden className="size-4" />
                                إعداد الاتصال
                            </Button>
                        ) : null}
                    </span>
                }
            />

            <Alert tone="neutral" title="قراءة فقط">
                هذه الشاشة تعرض ما في {project.name} ولا تغيّره. أي تعديل يبقى في لوحة المشروع نفسه
                — والكتابة من هنا قرارٌ مؤجَّل لم يُبنَ له مسار.
            </Alert>

            <MethodCards methods={methods} />

            {bridge === null ? null : (
                <Card>
                    <CardHeader title="الاتصال" description={`المحرّك: ${bridge.driver}`} />
                    <CardBody className="flex flex-wrap items-center gap-x-6 gap-y-2">
                        <Badge tone={bridge.tone}>{bridge.status}</Badge>
                        <span dir="ltr" className="text-start text-caption text-fg-muted">
                            {bridge.base_url}
                        </span>
                        <span className="text-caption text-fg-muted">
                            المصادقة:{' '}
                            {bridge.auth_mode === 'service_account' ? 'حساب خدمة' : 'رمز جاهز'}
                        </span>
                        <span className="text-caption text-fg-muted">
                            آخر نجاح:{' '}
                            {bridge.last_synced_at === null
                                ? 'لا شيء'
                                : formatRelative(bridge.last_synced_at)}
                        </span>
                        <span className="ms-auto text-caption text-fg-subtle">
                            جُلبت {formatDateTime(fetchedAt)}
                        </span>

                        {bridge.last_error === null ? null : (
                            <p className="w-full text-caption text-danger">{bridge.last_error}</p>
                        )}
                    </CardBody>
                </Card>
            )}

            {!editing || !manage ? null : <BridgeForm bridge={bridge} project={project} />}

            {snapshot === null ? (
                <EmptyState
                    icon={Plug}
                    title="لا اتصال بعد"
                    description="اضبط عنوان المشروع وبيانات حساب الخدمة ليبدأ الجلب."
                />
            ) : (
                <>
                    <Card>
                        <CardHeader
                            title="النقاط المقروءة"
                            description="كل نداء مستقل — ما وصل يُعرض وما أخفق يُقال"
                        />
                        <CardBody className="grid gap-2 md:grid-cols-2 xl:grid-cols-4">
                            {Object.entries(snapshot.transport).map(([key, value]) => (
                                <div
                                    key={key}
                                    className="flex items-center justify-between gap-2 rounded-card border border-border-default px-3 py-2"
                                >
                                    <span className="text-caption text-fg-default">
                                        {ENDPOINT_LABELS[key] ?? key}
                                    </span>
                                    {value.ok ? (
                                        <Badge tone="success">{formatNumber(value.ms)} م.ث</Badge>
                                    ) : (
                                        <Badge tone="danger">أخفق</Badge>
                                    )}
                                </div>
                            ))}
                        </CardBody>
                    </Card>

                    {snapshot.derived.length === 0 ? null : (
                        <Card>
                            <CardHeader
                                title="مشتقّات هاي روت"
                                description="محسوبة هنا من أرقام موازين — لا يعرضها موازين لنفسه"
                            />
                            <CardBody className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                {snapshot.derived.map((row) => (
                                    <div
                                        key={row.label}
                                        className="flex flex-col gap-1 rounded-card border border-accent/30 bg-accent-soft/30 p-3"
                                    >
                                        <span className="flex items-center gap-1.5 text-caption text-fg-muted">
                                            <Calculator aria-hidden className="size-3.5" />
                                            {row.label}
                                        </span>
                                        <span className="text-metric font-bold text-fg-default tabular-nums">
                                            {row.unit === 'percent'
                                                ? `${formatNumber(row.value, 1)}%`
                                                : row.unit === 'usd'
                                                  ? `$${formatNumber(row.value, 2)}`
                                                  : formatCompact(row.value)}
                                        </span>
                                        <span className="text-micro text-fg-subtle">
                                            {row.note}
                                        </span>
                                    </div>
                                ))}
                            </CardBody>
                        </Card>
                    )}

                    {snapshot.assistant === null ? null : (
                        <AssistantCard assistant={snapshot.assistant} />
                    )}

                    {snapshot.plans.length === 0 ? null : <PlansCard plans={snapshot.plans} />}

                    {snapshot.analytics === null ? null : (
                        <Card>
                            <CardHeader
                                title="تحليل الاستهلاك"
                                description={
                                    snapshot.analytics.since === null
                                        ? 'مقروء من موازين'
                                        : `منذ ${snapshot.analytics.since}`
                                }
                            />
                            <CardBody className="flex flex-wrap gap-x-8 gap-y-3">
                                <Fact
                                    label="الكلفة الكلية"
                                    value={
                                        snapshot.analytics.total_cost_usd === null
                                            ? '—'
                                            : `$${formatNumber(snapshot.analytics.total_cost_usd, 2)}`
                                    }
                                />
                                <Fact
                                    label="أنواع الاستخدام"
                                    value={formatNumber(snapshot.analytics.by_kind.length)}
                                />
                                <Fact
                                    label="أيام فيها حركة"
                                    value={formatNumber(snapshot.analytics.daily.length)}
                                />
                                <Fact
                                    label="تركيبات مزود/نموذج"
                                    value={formatNumber(
                                        snapshot.analytics.by_provider_model.length,
                                    )}
                                />
                            </CardBody>
                        </Card>
                    )}
                </>
            )}
        </AdminLayout>
    );
}

function AssistantCard({ assistant }: { assistant: Assistant }) {
    const rows: { label: string; value: string }[] = [
        { label: 'مزود المنصة', value: assistant.provider ?? 'الافتراضي' },
        { label: 'نموذج المنصة', value: assistant.model ?? 'الافتراضي' },
        { label: 'مزود المساعد', value: assistant.assistant_provider ?? 'يتبع المنصة' },
        { label: 'نموذج المساعد', value: assistant.assistant_model ?? 'يتبع المنصة' },
        { label: 'مزود التوليد المتّجه', value: assistant.embedding_provider ?? 'أول قادر' },
        {
            label: 'التوليد المتّجه',
            value: assistant.embeddings_enabled === true ? 'مفعّل' : 'متوقف',
        },
        {
            label: 'مستوى المساعد',
            value:
                assistant.assistant_level === null ? '—' : formatNumber(assistant.assistant_level),
        },
        {
            label: 'اختيار المشترك لمستواه',
            value: assistant.allow_user_level_override === true ? 'مسموح' : 'ممنوع',
        },
        { label: 'أدوات القراءة', value: assistant.tools_enabled === true ? 'مفعّلة' : 'متوقفة' },
        {
            label: 'أدوات الكتابة المحروسة',
            value: assistant.write_tools_enabled === true ? 'مفعّلة' : 'متوقفة',
        },
        {
            label: 'أقصى قراءات المستند',
            value: assistant.doc_max_reads === null ? '—' : formatNumber(assistant.doc_max_reads),
        },
        {
            label: 'الطابور غير المتزامن',
            value:
                assistant.async_queue_enabled === true
                    ? `مفعّل فوق ${formatNumber(assistant.async_queue_min_pages ?? 0)} صفحة`
                    : 'متوقف',
        },
        {
            label: 'مسارات القراءة',
            value:
                assistant.reading_pipelines === null
                    ? '—'
                    : `${formatNumber(assistant.reading_pipelines)} مسارًا`,
        },
    ];

    return (
        <Card>
            <CardHeader
                title="إعداد المساعد في موازين"
                description="مقروء كما هو — التعديل من لوحة موازين"
            />
            <CardBody className="flex flex-col gap-4">
                <dl className="grid gap-x-6 gap-y-2 md:grid-cols-2 xl:grid-cols-3">
                    {rows.map((row) => (
                        <div
                            key={row.label}
                            className="flex items-baseline justify-between gap-2 border-b border-border-default pb-1.5"
                        >
                            <dt className="text-caption text-fg-muted">{row.label}</dt>
                            <dd className="text-caption font-bold text-fg-default">{row.value}</dd>
                        </div>
                    ))}
                </dl>

                {Object.keys(assistant.level_tokens).length === 0 ? null : (
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="text-caption text-fg-muted">سقف التوكن لكل مستوى:</span>
                        {Object.entries(assistant.level_tokens).map(([level, tokens]) => (
                            <Badge key={level} tone="info">
                                مستوى {level}: {formatCompact(tokens)}
                            </Badge>
                        ))}
                    </div>
                )}

                {assistant.system_prompt === null ? null : (
                    <div className="flex flex-col gap-1">
                        <span className="text-caption font-bold text-fg-muted">
                            شخصية المساعد المخصّصة
                        </span>
                        <pre className="max-h-48 overflow-auto rounded-control bg-surface-sunken p-3 text-caption whitespace-pre-wrap text-fg-default">
                            {assistant.system_prompt}
                        </pre>
                    </div>
                )}
            </CardBody>
        </Card>
    );
}

function PlansCard({ plans }: { plans: PlanRow[] }) {
    return (
        <Card>
            <CardHeader
                title="حصص الباقات"
                description="الصفر يعني بلا سقف لا منعًا — كما يفسّره موازين"
            />
            <CardBody className="overflow-x-auto">
                <table className="w-full text-caption">
                    <thead>
                        <tr className="border-b border-border-default text-fg-muted">
                            <th className="p-2 text-start font-medium">الباقة</th>
                            <th className="p-2 text-start font-medium">أقصى للطلب</th>
                            <th className="p-2 text-start font-medium">يومي</th>
                            <th className="p-2 text-start font-medium">أسبوعي</th>
                            <th className="p-2 text-start font-medium">شهري</th>
                        </tr>
                    </thead>
                    <tbody>
                        {plans.map((plan) => (
                            <tr key={plan.plan} className="border-b border-border-default">
                                <td className="p-2 font-bold text-fg-default">
                                    {plan.label}
                                    {plan.unlimited ? (
                                        <Badge tone="accent" className="ms-2">
                                            بلا سقف
                                        </Badge>
                                    ) : null}
                                </td>
                                <td className="p-2 text-fg-default tabular-nums">
                                    {plan.max_per_request === null
                                        ? '—'
                                        : formatCompact(plan.max_per_request)}
                                </td>
                                <td className="p-2 text-fg-default tabular-nums">
                                    {plan.daily === null || plan.daily === 0
                                        ? '∞'
                                        : formatCompact(plan.daily)}
                                </td>
                                <td className="p-2 text-fg-default tabular-nums">
                                    {plan.weekly === null || plan.weekly === 0
                                        ? '∞'
                                        : formatCompact(plan.weekly)}
                                </td>
                                <td className="p-2 text-fg-default tabular-nums">
                                    {plan.monthly === null || plan.monthly === 0
                                        ? '∞'
                                        : formatCompact(plan.monthly)}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </CardBody>
        </Card>
    );
}

function BridgeForm({
    bridge,
    project,
}: {
    bridge: BridgeRow | null;
    project: { id: number; name: string };
}) {
    const form = useForm({
        base_url: bridge?.base_url ?? '',
        auth_mode: bridge?.auth_mode ?? 'service_account',
        email: '',
        password: '',
        token: '',
        is_enabled: bridge?.is_enabled ?? true,
    });

    return (
        <Card>
            <CardHeader
                title="إعداد الاتصال"
                description={`سيُربط بالمشروع الحالي: ${project.name} — بدّل المشروع أولًا إن أردت غيره`}
            />
            <CardBody>
                <form
                    className="flex flex-col gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post('/bridge', { preserveScroll: true });
                    }}
                >
                    <Alert tone="info" title="حساب خدمة بصلاحية قراءة فقط">
                        أنشئ في المشروع الخارجي حسابًا مخصّصًا لهذا الربط بنطاقَي القراءة (ai:read و
                        health:read) — لا تستعمل حسابك الشخصي.
                    </Alert>

                    <Input
                        label="عنوان الواجهة"
                        dir="ltr"
                        required
                        placeholder="https://api.mawazin.example/api"
                        value={form.data.base_url}
                        error={form.errors.base_url}
                        hint="الجذر الذي تليه /ai/settings و /auth/login."
                        onChange={(event) => {
                            form.setData('base_url', event.target.value);
                        }}
                    />

                    <Select
                        label="نمط المصادقة"
                        options={[
                            { value: 'service_account', label: 'حساب خدمة (يُجدَّد تلقائيًا)' },
                            { value: 'bearer', label: 'رمز جاهز' },
                        ]}
                        value={form.data.auth_mode}
                        onChange={(event) => {
                            form.setData('auth_mode', event.target.value);
                        }}
                    />

                    {form.data.auth_mode === 'bearer' ? (
                        <Input
                            label="الرمز"
                            dir="ltr"
                            type="password"
                            value={form.data.token}
                            error={form.errors.token}
                            hint="اتركه فارغًا للإبقاء على المحفوظ."
                            onChange={(event) => {
                                form.setData('token', event.target.value);
                            }}
                        />
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Input
                                label="بريد حساب الخدمة"
                                dir="ltr"
                                type="email"
                                value={form.data.email}
                                error={form.errors.email}
                                hint="اتركه فارغًا للإبقاء على المحفوظ."
                                onChange={(event) => {
                                    form.setData('email', event.target.value);
                                }}
                            />
                            <Input
                                label="كلمة المرور"
                                type="password"
                                value={form.data.password}
                                error={form.errors.password}
                                hint="مشفَّرة في قاعدة البيانات، ولا تُعرض بعد الحفظ."
                                onChange={(event) => {
                                    form.setData('password', event.target.value);
                                }}
                            />
                        </div>
                    )}

                    <div className="flex items-center justify-end gap-2">
                        <Link2 aria-hidden className="size-4 text-fg-subtle" />
                        <Button type="submit" disabled={form.processing}>
                            احفظ واتصل
                        </Button>
                    </div>
                </form>
            </CardBody>
        </Card>
    );
}

function Fact({ label, value }: { label: string; value: string }) {
    return (
        <span className="flex flex-col gap-0.5">
            <span className="text-caption text-fg-muted">{label}</span>
            <span className="text-title font-bold text-fg-default tabular-nums">{value}</span>
        </span>
    );
}
