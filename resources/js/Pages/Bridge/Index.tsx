import { Head, useForm } from '@inertiajs/react';
import { useRef, useState } from 'react';
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
import { Switch } from '@/Components/ui/Switch';
import { usePermissions } from '@/Hooks/usePermissions';
import {
    formatCompact,
    formatDate,
    formatDateTime,
    formatNumber,
    formatRelative,
} from '@/lib/format';

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

interface Governance {
    conversion: { enabled: boolean | null; formats: string[] };
    instructions: {
        enabled: boolean | null;
        allow_custom: boolean | null;
        predefined: number | null;
        max_retries: number | null;
    };
    read_confirm: { enabled: boolean | null; token_threshold: number | null };
    points: {
        enabled: boolean | null;
        base_cost: number | null;
        extra_page_cost: number | null;
        rescan_discount_pct: number | null;
        rollover_requires_activity: boolean | null;
        plan_monthly: Record<string, number>;
    };
    pipelines: { key: string; label: string; description: string | null }[];
}

interface Platform {
    users: number | null;
    projects: number | null;
    active_projects: number | null;
    archived_projects: number | null;
    memberships: number | null;
    transactions: number | null;
    income: number | null;
    expense: number | null;
    volume: number | null;
    open_receivables: number | null;
}

interface Service {
    status: string | null;
    database: string | null;
    uptime_seconds: number | null;
    error: string | null;
}

interface ComparisonRow {
    label: string;
    unit: string;
    remote: number | null;
    local: number | null;
    remote_currency?: string;
    local_currency?: string;
    note: string;
}

interface Snapshot {
    transport: Record<string, Transport>;
    assistant: Assistant | null;
    governance: Governance | null;
    platform: Platform | null;
    service: Service | null;
    comparison: ComparisonRow[];
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

interface WebhookRow {
    url: string;
    is_enabled: boolean;
    status: string;
    last_delivered_at: string | null;
    last_error: string | null;
}

interface Props {
    systemStatus: { label: string; tone: StatusTone };
    project: { id: number; name: string };
    bridge: BridgeRow | null;
    methods: ConnectionMethod[];
    webhook: WebhookRow | null;
    snapshot: Snapshot | null;
    fetchedAt: string;
}

/** ما يوقّع به هاي روت كل دفعة تنبيه — كما يقرأه المستقبِل حرفًا بحرف. */
const SIGNATURE_HEADERS: [string, string][] = [
    ['الطابع', 'X-Hiroote-Timestamp: 1753822800'],
    ['التوقيع', 'X-Hiroote-Signature: sha256=HMAC-SHA256(secret, "{timestamp}.{body}")'],
];

const ENDPOINT_LABELS: Record<string, string> = {
    settings: 'إعداد المساعد',
    analytics: 'تحليل الاستهلاك',
    health: 'صحة البنية',
    quotas: 'استثناءات الحصص',
    platform: 'إحصاءات المنصة',
    service: 'حالة الخدمة',
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
    webhook,
    snapshot,
    fetchedAt,
}: Props) {
    const { can } = usePermissions();
    const manage = can('integrations.manage');
    const [editing, setEditing] = useState(bridge === null);
    const formRef = useRef<HTMLDivElement>(null);

    /**
     * النموذج يقع تحت بطاقات طرق الربط، أي خارج الشاشة عند الفتح.
     *
     * زرٌّ يبدّل حالة شيء لا يراه الضاغط يقرأ عطلًا: مشروعٌ بلا جسر يفتح
     * نموذجه تلقائيًّا، فأول ضغطة كانت **تخفيه** بلا أثر ظاهر. فصار الاسم
     * يعلن الحالة، والفتح ينقل إليه.
     */
    const toggleForm = (): void => {
        if (editing) {
            setEditing(false);

            return;
        }

        setEditing(true);
        requestAnimationFrame(() => {
            formRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    };

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
                            <Button variant="ghost" onClick={toggleForm}>
                                <Settings2 aria-hidden className="size-4" />
                                {editing ? 'إخفاء إعداد الاتصال' : 'إعداد الاتصال'}
                            </Button>
                        ) : null}
                    </span>
                }
            />

            <Alert tone="neutral" title="قراءة فقط">
                ما يُعرض من {project.name} في هذه الشاشة مقروءٌ ولا يُغيَّر: أي تعديل يبقى في لوحة
                المشروع نفسه، والكتابة في بياناته قرارٌ مؤجَّل لم يُبنَ له مسار. المسار الصادر
                الوحيد هو دفع التنبيهات أدناه — يُرسل إنذارًا موقَّعًا ولا يمسّ بيانات المشروع.
            </Alert>

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

            {!editing || !manage ? null : (
                <div ref={formRef} className="scroll-mt-4">
                    <BridgeForm bridge={bridge} project={project} />
                </div>
            )}

            {manage ? <WebhookCard webhook={webhook} /> : null}

            {/*
             * الشرح بعد العمل لا قبله: بطاقات الطرق مرجعٌ يُقرأ مرة، وحالةُ
             * الاتصال ونموذجه هما ما يُفتح لأجله. تصديرُ خمس بطاقات فوقهما كان
             * يدفع النموذج خارج الشاشة فيبدو غائبًا.
             */}
            <MethodCards methods={methods} />

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

                    {snapshot.service === null && snapshot.platform === null ? null : (
                        <PlatformCard platform={snapshot.platform} service={snapshot.service} />
                    )}

                    {snapshot.comparison.length === 0 ? null : (
                        <ComparisonCard rows={snapshot.comparison} />
                    )}

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

                    {snapshot.governance === null ? null : (
                        <GovernanceCard governance={snapshot.governance} />
                    )}

                    {snapshot.plans.length === 0 ? null : <PlansCard plans={snapshot.plans} />}

                    {snapshot.analytics === null ? null : (
                        <Card>
                            <CardHeader
                                title="تحليل الاستهلاك"
                                description={
                                    snapshot.analytics.since === null
                                        ? 'مقروء من موازين'
                                        : // الطابع الزمني الخام يصل كما أرسله موازين؛ عرضه
                                          // نصًّا يجعل تاريخًا يُقرأ رمزًا تقنيًّا.
                                          `منذ ${formatDate(snapshot.analytics.since)}`
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

/**
 * وجهة دفع التنبيهات — المسار الصادر الوحيد من هاي روت إلى المشروع.
 *
 * الحالة تُقرأ من آخر محاولة فعلية لا من كون الحقول ممتلئة: عنوانٌ محفوظ
 * وسرٌّ محفوظ لا يعنيان أن شيئًا وصل، و«تعمل» بلا تسليمٍ ناجح تُقنع المشغّل
 * بأن إنذاره يصل.
 */
function WebhookCard({ webhook }: { webhook: WebhookRow | null }) {
    const form = useForm({
        url: webhook?.url ?? '',
        secret: '',
        is_enabled: webhook?.is_enabled ?? true,
    });

    const tone: Tone = webhookTone(webhook?.status);
    const lastError = webhook?.last_error ?? null;

    return (
        <Card>
            <CardHeader
                title="وجهة دفع التنبيهات"
                description="هاي روت يدفع التنبيه المفتوح إلى هذا العنوان موقَّعًا — والتوقيع وحده يميّز دفعتنا"
                actions={
                    webhook === null ? null : (
                        <span className="flex items-center gap-3">
                            <Badge tone={tone}>{webhook.status}</Badge>
                            <span className="text-caption text-fg-subtle">
                                آخر تسليم:{' '}
                                {webhook.last_delivered_at === null
                                    ? 'لا شيء'
                                    : formatRelative(webhook.last_delivered_at)}
                            </span>
                        </span>
                    )
                }
            />
            <CardBody>
                <form
                    className="flex flex-col gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put('/bridge/webhook', { preserveScroll: true });
                    }}
                >
                    {lastError === null ? null : (
                        <Alert tone="danger" title="آخر محاولة أخفقت">
                            {lastError}
                        </Alert>
                    )}

                    <Input
                        label="عنوان الوجهة"
                        dir="ltr"
                        required
                        placeholder="https://api.mawazin.example/api/hiroote/alerts"
                        value={form.data.url}
                        error={form.errors.url}
                        hint="مسار POST في المشروع يستقبل التنبيه ويتحقق من التوقيع."
                        onChange={(event) => {
                            form.setData('url', event.target.value);
                        }}
                    />

                    <Input
                        label="سرّ التوقيع"
                        dir="ltr"
                        type="password"
                        value={form.data.secret}
                        error={form.errors.secret}
                        hint={
                            webhook === null
                                ? 'ستّة عشر محرفًا فأكثر — يُشفَّر في قاعدة البيانات ولا يُعرض بعد الحفظ.'
                                : 'اتركه فارغًا للإبقاء على المحفوظ.'
                        }
                        onChange={(event) => {
                            form.setData('secret', event.target.value);
                        }}
                    />

                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <label className="flex items-center gap-2 text-caption text-fg-default">
                            <Switch
                                aria-label="تفعيل دفع التنبيهات"
                                checked={form.data.is_enabled}
                                onChange={(checked) => {
                                    form.setData('is_enabled', checked);
                                }}
                            />
                            مفعّلة
                        </label>

                        <Button type="submit" disabled={form.processing}>
                            احفظ الوجهة
                        </Button>
                    </div>

                    {/*
                     * كل ترويسة في سطرها بجهتها.
                     *
                     * سطرٌ واحد يخلط عربيةً بأسماء ترويسات ودالّة توقيع يعيد
                     * الثنائي الاتجاه ترتيبَه على الشاشة، فيُقرأ اسمُ ترويسةٍ
                     * ملتصقًا بقيمة أخرى — ومن ينسخه يوقّع على غير ما نوقّع.
                     */}
                    <div className="rounded-control bg-surface-sunken p-3">
                        <p className="text-caption text-fg-muted">ما يصل المشروع مع كل دفعة:</p>

                        <dl className="mt-2 flex flex-col gap-2">
                            {SIGNATURE_HEADERS.map(([label, value]) => (
                                <div key={label}>
                                    <dt className="text-micro text-fg-subtle">{label}</dt>
                                    <dd
                                        dir="ltr"
                                        className="overflow-x-auto font-mono text-micro text-fg-default"
                                    >
                                        {value}
                                    </dd>
                                </div>
                            ))}
                        </dl>

                        <p className="mt-3 text-micro text-fg-subtle">
                            احسب التوقيع على <strong className="text-fg-muted">الجسم الخام</strong>{' '}
                            قبل تحليله، وارفض ما تجاوز طابعه دقائق معدودة. وردٌّ غير ٢xx يُسجَّل
                            إخفاقًا في هذه البطاقة.
                        </p>
                    </div>
                </form>
            </CardBody>
        </Card>
    );
}

function webhookTone(status: string | undefined): Tone {
    switch (status) {
        case 'تعمل':
            return 'success';
        case 'أخفقت':
            return 'danger';
        case 'موقوفة':
        case 'غير مكتملة':
            return 'neutral';
        default:
            return 'warning';
    }
}

/** حالة المنصة التي يعمل فيها المساعد — مقروءة كما هي، بلا هوية شخص. */
function PlatformCard({
    platform,
    service,
}: {
    platform: Platform | null;
    service: Service | null;
}) {
    return (
        <Card>
            <CardHeader
                title="المنصة والخدمة"
                description="سياق المشروع الذي يعمل فيه المساعد — مقروء كما هو"
            />
            <CardBody className="flex flex-wrap gap-x-8 gap-y-3">
                {service === null ? null : (
                    <>
                        <Fact
                            label="الخدمة"
                            value={service.status === 'ok' ? 'تعمل' : (service.status ?? '—')}
                        />
                        <Fact
                            label="قاعدة البيانات"
                            value={service.database === 'up' ? 'متصلة' : (service.database ?? '—')}
                        />
                        <Fact
                            label="مدة التشغيل"
                            value={
                                service.uptime_seconds === null
                                    ? '—'
                                    : formatDuration(service.uptime_seconds)
                            }
                        />
                    </>
                )}

                {platform === null ? null : (
                    <>
                        <Fact label="المشتركون" value={countOrDash(platform.users)} />
                        <Fact
                            label="المشاريع"
                            value={
                                platform.projects === null
                                    ? '—'
                                    : `${formatNumber(platform.projects)} · ${formatNumber(platform.active_projects ?? 0)} نشط`
                            }
                        />
                        <Fact label="العضويات" value={countOrDash(platform.memberships)} />
                        <Fact label="الحركات" value={countOrDash(platform.transactions)} />
                        <Fact
                            label="حجم الحركة"
                            value={
                                platform.volume === null
                                    ? '—'
                                    : `${formatCompact(platform.volume)} ر.س`
                            }
                        />
                        <Fact label="ذمم مفتوحة" value={countOrDash(platform.open_receivables)} />
                    </>
                )}
            </CardBody>
        </Card>
    );
}

/**
 * ما يقوله المشروع عن نفسه بجانب ما سجّله هاي روت عنه.
 *
 * الفجوة معلومةٌ في ذاتها لا خطأ يُخفى: هاي روت لا يرى إلا ما رفعه إليه
 * المشروع، وموازين لا يعدّ ما لا يمرّ بوحدة ذكائه. ولذلك «لا يرصده» تُكتب
 * صراحةً بدل صفرٍ يُقرأ قياسًا.
 */
function ComparisonCard({ rows }: { rows: ComparisonRow[] }) {
    const money = (value: number, currency: string | undefined) =>
        currency === 'USD' ? `$${formatNumber(value, 2)}` : `${formatNumber(value, 2)} ر.س`;

    return (
        <Card>
            <CardHeader
                title="المصدران جنبًا إلى جنب"
                description="ما يقوله موازين عن نفسه، وما سجّله هاي روت عنه — والفجوة بينهما معلومة لا عطل"
            />
            <CardBody className="overflow-x-auto">
                <table className="w-full text-caption">
                    <thead>
                        <tr className="border-b border-border-default text-fg-muted">
                            <th className="p-2 text-start font-medium">المؤشر</th>
                            <th className="p-2 text-start font-medium">موازين يقول</th>
                            <th className="p-2 text-start font-medium">هاي روت سجّل</th>
                            <th className="p-2 text-start font-medium">ملاحظة</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.map((row) => (
                            <tr key={row.label} className="border-b border-border-default/60">
                                <td className="p-2 font-bold text-fg-default">{row.label}</td>
                                <td className="p-2 text-fg-default tabular-nums">
                                    {row.remote === null ? (
                                        <span className="text-fg-subtle">لا يرصده</span>
                                    ) : row.unit === 'money' ? (
                                        money(row.remote, row.remote_currency)
                                    ) : (
                                        formatCompact(row.remote)
                                    )}
                                </td>
                                <td className="p-2 text-fg-default tabular-nums">
                                    {row.local === null ? (
                                        <span className="text-fg-subtle">لا يرصده</span>
                                    ) : row.unit === 'money' ? (
                                        money(row.local, row.local_currency)
                                    ) : (
                                        formatCompact(row.local)
                                    )}
                                </td>
                                <td className="p-2 text-fg-subtle">{row.note}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </CardBody>
        </Card>
    );
}

/** حوكمة القراءة والنقاط — إعدادات ذكاءٍ متفرّقة في لوحة موازين، مجموعة هنا. */
function GovernanceCard({ governance }: { governance: Governance }) {
    const state = (value: boolean | null) =>
        value === null ? 'غير معلوم' : value ? 'مفعّل' : 'متوقف';

    return (
        <Card>
            <CardHeader
                title="حوكمة القراءة والنقاط"
                description="إعدادات ذكاءٍ يملكها موازين — مجموعة هنا في مكان واحد"
            />
            <CardBody className="flex flex-col gap-4">
                <div className="flex flex-wrap gap-x-8 gap-y-3">
                    <Fact label="تحويل المستندات" value={state(governance.conversion.enabled)} />
                    <Fact
                        label="الصيغ المسموحة"
                        value={
                            governance.conversion.formats.length === 0
                                ? 'لا شيء'
                                : `${formatNumber(governance.conversion.formats.length)} صيغة`
                        }
                    />
                    <Fact
                        label="التعليمات المسبقة"
                        value={state(governance.instructions.enabled)}
                    />
                    <Fact
                        label="تعليمات حرّة"
                        value={state(governance.instructions.allow_custom)}
                    />
                    <Fact
                        label="إعادة المسح شهريًّا"
                        value={
                            governance.instructions.max_retries === null
                                ? '—'
                                : // ‎-1 في موازين يعني «بلا حدّ» لا «ممنوع».
                                  governance.instructions.max_retries < 0
                                  ? 'بلا حدّ'
                                  : formatNumber(governance.instructions.max_retries)
                        }
                    />
                    <Fact
                        label="تأكيد القراءة الكبيرة"
                        value={state(governance.read_confirm.enabled)}
                    />
                    <Fact
                        label="عتبة التأكيد"
                        value={
                            governance.read_confirm.token_threshold === null
                                ? '—'
                                : `${formatCompact(governance.read_confirm.token_threshold)} رمز`
                        }
                    />
                    <Fact label="محرّك النقاط" value={state(governance.points.enabled)} />
                    <Fact
                        label="خصم إعادة المسح"
                        value={
                            governance.points.rescan_discount_pct === null
                                ? '—'
                                : `${formatNumber(governance.points.rescan_discount_pct)}%`
                        }
                    />
                </div>

                {governance.pipelines.length === 0 ? null : (
                    <div className="flex flex-col gap-2">
                        <span className="text-caption font-bold text-fg-muted">مسارات القراءة</span>
                        <ul className="grid gap-2 md:grid-cols-2">
                            {governance.pipelines.map((pipeline) => (
                                <li
                                    key={pipeline.key}
                                    className="rounded-card border border-border-default p-2.5"
                                >
                                    <p className="text-caption font-bold text-fg-default">
                                        {pipeline.label}
                                    </p>
                                    {pipeline.description === null ? null : (
                                        <p className="mt-0.5 text-micro text-fg-subtle">
                                            {pipeline.description}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                )}
            </CardBody>
        </Card>
    );
}

function countOrDash(value: number | null): string {
    return value === null ? '—' : formatNumber(value);
}

/** «٣ ساعات و١٢ دقيقة» — مدة تشغيل الخدمة تُقرأ زمنًا لا عدد ثوانٍ. */
function formatDuration(seconds: number): string {
    const days = Math.floor(seconds / 86400);
    const hours = Math.floor((seconds % 86400) / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);

    if (days > 0) {
        return `${formatNumber(days)} يوم · ${formatNumber(hours)} س`;
    }

    if (hours > 0) {
        return `${formatNumber(hours)} س · ${formatNumber(minutes)} د`;
    }

    return `${formatNumber(minutes)} د`;
}

function Fact({ label, value }: { label: string; value: string }) {
    return (
        <span className="flex flex-col gap-0.5">
            <span className="text-caption text-fg-muted">{label}</span>
            <span className="text-title font-bold text-fg-default tabular-nums">{value}</span>
        </span>
    );
}
