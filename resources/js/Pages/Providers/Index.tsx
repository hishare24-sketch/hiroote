import { Head, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { ArrowDown, ArrowUp, KeyRound, RefreshCw, Server, ShieldCheck, Trash2 } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Alert } from '@/Components/ui/Alert';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Input } from '@/Components/ui/Input';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Switch } from '@/Components/ui/Switch';
import { Toggle } from '@/Components/ui/Toggle';
import { usePermissions } from '@/Hooks/usePermissions';
import type { StatusTone } from '@/types';

interface ProviderModel {
    id: number;
    name: string;
    display_name: string;
    is_default: boolean;
}

interface ProviderCredential {
    id: number;
    label: string;
    key_hint: string;
    is_active: boolean;
    last_used_at: string | null;
}

interface Provider {
    id: number;
    name: string;
    slug: string;
    priority: number;
    is_enabled: boolean;
    is_active: boolean;
    status: 'operational' | 'degraded' | 'down' | 'unknown';
    status_label: string;
    consecutive_failures: number;
    last_checked_at: string | null;
    latency_ms: number | null;
    error_rate: number;
    balance: number;
    burn_rate: number;
    currency: string;
    default_model: string | null;
    models: ProviderModel[];
    credentials: ProviderCredential[];
}

interface ProvidersPageProps {
    systemStatus: { label: string; tone: StatusTone };
    providers: Provider[];
    activeProvider: Provider | null;
    healthCheck: {
        intervalMinutes: number;
        failureThreshold: number;
        nextCheckInSeconds: number | null;
    };
    failoverPolicies: { key: string; label: string; enabled: boolean }[];
    recentFailovers: {
        id: number;
        from: string | null;
        to: string | null;
        reason: string;
        triggered_by: string | null;
        created_at: string;
    }[];
}

const STATUS_TONES: Record<Provider['status'], StatusTone> = {
    operational: 'success',
    degraded: 'warning',
    down: 'danger',
    unknown: 'neutral',
};

function formatDate(value: string | null): string {
    if (value === null) {
        return '—';
    }
    return new Date(value).toLocaleString('ar', { dateStyle: 'short', timeStyle: 'short' });
}

function formatMoney(amount: number, currency: string): string {
    return `${amount.toLocaleString('ar', { maximumFractionDigits: 0 })} ${currency === 'SAR' ? 'ر.س' : currency}`;
}

function formatLatency(ms: number | null): string {
    return ms === null ? '—' : `${(ms / 1000).toFixed(1)} ث`;
}

function queueLabel(index: number): { text: string; tone: StatusTone } {
    if (index === 0) {
        return { text: 'أساسي', tone: 'success' };
    }
    return { text: `احتياطي ${String(index)}`, tone: index === 1 ? 'info' : 'warning' };
}

/**
 * عدّاد الفحص القادم — وثيقة التصميم §9.
 *
 * The parent remounts this with `key={seconds}` on every server refresh, so the
 * initial value comes straight from props and the effect only owns the ticker.
 */
function CheckCountdown({ seconds }: { seconds: number }) {
    const [remaining, setRemaining] = useState(seconds);

    useEffect(() => {
        const timer = setInterval(() => {
            setRemaining((value) => (value <= 0 ? 0 : value - 1));
        }, 1000);

        return () => {
            clearInterval(timer);
        };
    }, []);

    const pad = (value: number) => String(value).padStart(2, '0');

    return (
        <span className="font-mono text-2xl font-bold text-fg-default" dir="ltr">
            {pad(Math.floor(remaining / 3600))}:{pad(Math.floor((remaining % 3600) / 60))}:
            {pad(remaining % 60)}
        </span>
    );
}

/** زر إجراء بأيقونة فقط — التسمية تصل عبر aria-label و title. */
function IconAction({
    label,
    icon: Icon,
    onClick,
    active = false,
}: {
    label: string;
    icon: LucideIcon;
    onClick: () => void;
    active?: boolean;
}) {
    return (
        <button
            type="button"
            title={label}
            aria-label={label}
            aria-pressed={active}
            onClick={onClick}
            className={
                active
                    ? 'flex size-8 items-center justify-center rounded-control bg-accent-soft text-accent'
                    : 'flex size-8 items-center justify-center rounded-control text-fg-muted hover:bg-surface-sunken hover:text-fg-default'
            }
        >
            <Icon aria-hidden className="size-4" />
        </button>
    );
}

function MetricTile({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-control border border-border-default bg-surface-sunken px-4 py-3">
            <p className="text-xs text-fg-muted">{label}</p>
            <p className="mt-1 text-base font-bold text-fg-default">{value}</p>
        </div>
    );
}

function CredentialForm({ provider }: { provider: Provider }) {
    const { data, setData, post, processing, errors, reset } = useForm({ label: '', api_key: '' });

    return (
        <form
            className="flex flex-col gap-3 sm:flex-row sm:items-end"
            onSubmit={(event) => {
                event.preventDefault();
                post(`/providers/${String(provider.id)}/credentials`, {
                    preserveScroll: true,
                    onSuccess: () => {
                        reset();
                    },
                });
            }}
        >
            <div className="flex-1">
                <Input
                    label="اسم المفتاح"
                    placeholder="مثال: مفتاح الإنتاج"
                    value={data.label}
                    onChange={(event) => {
                        setData('label', event.target.value);
                    }}
                    error={errors.label}
                />
            </div>
            <div className="flex-[2]">
                <Input
                    label="قيمة المفتاح"
                    type="password"
                    dir="ltr"
                    placeholder="sk-..."
                    autoComplete="off"
                    hint="يحفظ مشفرًا ولن يظهر كاملًا مرة أخرى."
                    value={data.api_key}
                    onChange={(event) => {
                        setData('api_key', event.target.value);
                    }}
                    error={errors.api_key}
                />
            </div>
            <Button type="submit" loading={processing} className="sm:mb-6">
                <KeyRound aria-hidden className="size-4" />
                حفظ المفتاح
            </Button>
        </form>
    );
}

export default function Index({
    systemStatus,
    providers,
    activeProvider,
    healthCheck,
    failoverPolicies,
    recentFailovers,
}: ProvidersPageProps) {
    const { can } = usePermissions();
    const [openCredentials, setOpenCredentials] = useState<number | null>(null);

    const order = providers.map((provider) => provider.id);
    const noKeys = providers.every((provider) =>
        provider.credentials.every((credential) => !credential.is_active),
    );

    const move = (index: number, direction: -1 | 1) => {
        const next = [...order];
        const target = index + direction;
        const current = next[index];
        const swapped = next[target];
        if (current === undefined || swapped === undefined) {
            return;
        }
        next[index] = swapped;
        next[target] = current;
        router.post('/providers/reorder', { order: next }, { preserveScroll: true });
    };

    return (
        <AdminLayout>
            <Head title="المزودون والنماذج" />

            <PageHeader
                title="المزودون والنماذج"
                description="مركز تشغيل ومراقبة المساعد الذكي"
                systemStatus={systemStatus}
                period="آخر 7 أيام"
            />

            {noKeys && providers.length > 0 ? (
                <Alert tone="warning" title="لا توجد مفاتيح فعالة">
                    أضف مفتاح API لمزود واحد على الأقل من زر «إدارة المفاتيح» حتى يبدأ الفحص الذاتي
                    والتشغيل.
                </Alert>
            ) : null}

            {/* بطاقة المزود النشط — وثيقة التصميم §8 */}
            {activeProvider === null ? (
                <Card>
                    <CardBody>
                        <EmptyState
                            icon={Server}
                            title="لا يوجد مزود نشط"
                            description="فعّل مزودًا وحوّل إليه ليبدأ استقبال الطلبات."
                        />
                    </CardBody>
                </Card>
            ) : (
                <Card className="border-accent/40 ring-1 ring-accent/20">
                    <CardHeader
                        title={`${activeProvider.name} — ${activeProvider.default_model ?? 'بلا نموذج افتراضي'}`}
                        description={`آخر فحص: ${formatDate(activeProvider.last_checked_at)}`}
                        actions={
                            <Badge tone="success" dot>
                                المزود النشط
                            </Badge>
                        }
                    />
                    <CardBody className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
                        <MetricTile
                            label="زمن الاستجابة"
                            value={formatLatency(activeProvider.latency_ms)}
                        />
                        <MetricTile
                            label="معدل الأخطاء"
                            value={`${activeProvider.error_rate.toFixed(1)}%`}
                        />
                        <MetricTile
                            label="الرصيد المتبقي"
                            value={formatMoney(activeProvider.balance, activeProvider.currency)}
                        />
                        <MetricTile
                            label="معدل الاستهلاك"
                            value={`${activeProvider.burn_rate.toFixed(1)} ${activeProvider.currency === 'SAR' ? 'ر.س' : activeProvider.currency}/دقيقة`}
                        />
                    </CardBody>
                </Card>
            )}

            {/* ترتيب المزودين والطابور الاحتياطي */}
            <Card>
                <CardHeader
                    title="ترتيب المزودين والطابور الاحتياطي"
                    description="الترتيب يحدد من يستلم الطلبات عند تعطل السابق."
                />
                <CardBody className="p-0">
                    {providers.length === 0 ? (
                        <EmptyState
                            icon={Server}
                            title="لا يوجد مزودون"
                            description="شغّل بذر قاعدة البيانات لإضافة المزودين الافتراضيين."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[840px] text-sm">
                                <thead className="border-b border-border-default bg-surface-sunken text-fg-muted">
                                    <tr>
                                        <th className="px-4 py-3 text-start font-medium">
                                            الأولوية
                                        </th>
                                        <th className="px-4 py-3 text-start font-medium">مفعل</th>
                                        <th className="px-4 py-3 text-start font-medium">المزود</th>
                                        <th className="px-4 py-3 text-start font-medium">
                                            النموذج
                                        </th>
                                        <th className="px-4 py-3 text-start font-medium">الزمن</th>
                                        <th className="px-4 py-3 text-start font-medium">
                                            الأخطاء
                                        </th>
                                        <th className="px-4 py-3 text-start font-medium">الرصيد</th>
                                        <th className="px-4 py-3 text-start font-medium">الحالة</th>
                                        <th className="px-4 py-3 text-start font-medium">
                                            إجراءات
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {providers.map((provider, index) => {
                                        const queue = queueLabel(index);
                                        return (
                                            <tr
                                                key={provider.id}
                                                className="border-b border-border-default last:border-0"
                                            >
                                                <td className="px-4 py-3">
                                                    <span className="flex items-center gap-1">
                                                        <span className="flex size-7 items-center justify-center rounded-control bg-accent-soft text-xs font-bold text-accent">
                                                            {provider.priority}
                                                        </span>
                                                        {can('providers.manage') ? (
                                                            <span className="inline-flex flex-col">
                                                                <button
                                                                    type="button"
                                                                    aria-label={`رفع أولوية ${provider.name}`}
                                                                    disabled={index === 0}
                                                                    onClick={() => {
                                                                        move(index, -1);
                                                                    }}
                                                                    className="rounded p-0.5 text-fg-subtle hover:bg-surface-sunken disabled:opacity-30"
                                                                >
                                                                    <ArrowUp
                                                                        aria-hidden
                                                                        className="size-3"
                                                                    />
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    aria-label={`خفض أولوية ${provider.name}`}
                                                                    disabled={
                                                                        index ===
                                                                        providers.length - 1
                                                                    }
                                                                    onClick={() => {
                                                                        move(index, 1);
                                                                    }}
                                                                    className="rounded p-0.5 text-fg-subtle hover:bg-surface-sunken disabled:opacity-30"
                                                                >
                                                                    <ArrowDown
                                                                        aria-hidden
                                                                        className="size-3"
                                                                    />
                                                                </button>
                                                            </span>
                                                        ) : null}
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Switch
                                                        aria-label={`تفعيل ${provider.name}`}
                                                        checked={provider.is_enabled}
                                                        disabled={!can('providers.manage')}
                                                        onChange={(checked) => {
                                                            router.post(
                                                                `/providers/${String(provider.id)}/toggle`,
                                                                { enabled: checked },
                                                                { preserveScroll: true },
                                                            );
                                                        }}
                                                    />
                                                </td>
                                                <td className="px-4 py-3 font-bold text-fg-default">
                                                    {provider.name}
                                                </td>
                                                <td className="px-4 py-3 text-fg-muted">
                                                    {provider.default_model ?? '—'}
                                                </td>
                                                <td className="px-4 py-3 text-fg-muted">
                                                    {formatLatency(provider.latency_ms)}
                                                </td>
                                                <td className="px-4 py-3 text-fg-muted">
                                                    {provider.error_rate.toFixed(1)}%
                                                </td>
                                                <td className="px-4 py-3 text-fg-muted">
                                                    {formatMoney(
                                                        provider.balance,
                                                        provider.currency,
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="flex flex-col items-start gap-1">
                                                        <Badge tone={queue.tone}>
                                                            {queue.text}
                                                        </Badge>
                                                        <Badge
                                                            tone={STATUS_TONES[provider.status]}
                                                            dot
                                                        >
                                                            {provider.status_label}
                                                        </Badge>
                                                    </span>
                                                </td>
                                                <td className="px-4 py-3">
                                                    <span className="flex items-center gap-1">
                                                        {can('providers.manage') ? (
                                                            <IconAction
                                                                label={`فحص ${provider.name} الآن`}
                                                                icon={RefreshCw}
                                                                onClick={() => {
                                                                    router.post(
                                                                        `/providers/${String(provider.id)}/check`,
                                                                        {},
                                                                        { preserveScroll: true },
                                                                    );
                                                                }}
                                                            />
                                                        ) : null}

                                                        {can('providers.failover') &&
                                                        !provider.is_active &&
                                                        provider.is_enabled ? (
                                                            <IconAction
                                                                label={`التحويل إلى ${provider.name}`}
                                                                icon={ShieldCheck}
                                                                onClick={() => {
                                                                    router.post(
                                                                        `/providers/${String(provider.id)}/activate`,
                                                                        {},
                                                                        { preserveScroll: true },
                                                                    );
                                                                }}
                                                            />
                                                        ) : null}

                                                        {can('providers.manage_credentials') ? (
                                                            <IconAction
                                                                label={`مفاتيح ${provider.name}`}
                                                                icon={KeyRound}
                                                                active={
                                                                    openCredentials === provider.id
                                                                }
                                                                onClick={() => {
                                                                    setOpenCredentials((current) =>
                                                                        current === provider.id
                                                                            ? null
                                                                            : provider.id,
                                                                    );
                                                                }}
                                                            />
                                                        ) : null}
                                                    </span>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardBody>
            </Card>

            {/* نموذج المفاتيح للمزود المختار */}
            {openCredentials !== null && can('providers.manage_credentials')
                ? providers
                      .filter((provider) => provider.id === openCredentials)
                      .map((provider) => (
                          <Card key={provider.id}>
                              <CardHeader
                                  title={`مفاتيح ${provider.name}`}
                                  description="المفتاح الجديد يُبطل السابق تلقائيًا، ولا يظهر كاملًا بعد الحفظ."
                              />
                              <CardBody className="space-y-4">
                                  <CredentialForm provider={provider} />

                                  {provider.credentials.length > 0 ? (
                                      <ul className="space-y-1 text-sm">
                                          {provider.credentials.map((credential) => (
                                              <li
                                                  key={credential.id}
                                                  className="flex items-center justify-between gap-2 rounded-control border border-border-default bg-surface-sunken px-4 py-2.5"
                                              >
                                                  <span className="min-w-0 truncate text-fg-default">
                                                      {credential.label}{' '}
                                                      <span
                                                          className="font-mono text-xs text-fg-muted"
                                                          dir="ltr"
                                                      >
                                                          ••••{credential.key_hint}
                                                      </span>
                                                  </span>
                                                  <span className="flex shrink-0 items-center gap-2">
                                                      <Badge
                                                          tone={
                                                              credential.is_active
                                                                  ? 'success'
                                                                  : 'neutral'
                                                          }
                                                      >
                                                          {credential.is_active ? 'فعال' : 'مبطل'}
                                                      </Badge>
                                                      {credential.is_active ? (
                                                          <button
                                                              type="button"
                                                              aria-label={`إبطال ${credential.label}`}
                                                              onClick={() => {
                                                                  router.delete(
                                                                      `/providers/${String(provider.id)}/credentials/${String(credential.id)}`,
                                                                      { preserveScroll: true },
                                                                  );
                                                              }}
                                                              className="rounded p-1 text-fg-subtle hover:bg-danger-soft hover:text-danger"
                                                          >
                                                              <Trash2
                                                                  aria-hidden
                                                                  className="size-4"
                                                              />
                                                          </button>
                                                      ) : null}
                                                  </span>
                                              </li>
                                          ))}
                                      </ul>
                                  ) : null}
                              </CardBody>
                          </Card>
                      ))
                : null}

            <div className="grid gap-4 lg:grid-cols-2">
                {/* الفحص الذاتي والحالة — وثيقة التصميم §9 */}
                <Card>
                    <CardHeader title="الفحص الذاتي والحالة" />
                    <CardBody className="space-y-4">
                        <div className="flex items-center justify-between gap-3 rounded-control border border-border-default bg-surface-sunken px-4 py-3">
                            <span className="text-xs text-fg-muted">الفحص القادم بعد</span>
                            {healthCheck.nextCheckInSeconds === null ? (
                                <span className="text-sm text-fg-subtle">لم يُجرَ فحص بعد</span>
                            ) : (
                                <CheckCountdown
                                    key={healthCheck.nextCheckInSeconds}
                                    seconds={healthCheck.nextCheckInSeconds}
                                />
                            )}
                        </div>

                        <dl className="space-y-2 text-sm">
                            <div className="flex justify-between gap-3">
                                <dt className="text-fg-muted">دورة الفحص</dt>
                                <dd className="font-medium text-fg-default">
                                    {healthCheck.intervalMinutes >= 60
                                        ? 'كل ساعة'
                                        : `كل ${String(healthCheck.intervalMinutes)} دقيقة`}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-fg-muted">عتبة التحويل</dt>
                                <dd className="font-medium text-fg-default">
                                    عند {healthCheck.failureThreshold} أخطاء متتالية يتم التحويل
                                    فورًا
                                </dd>
                            </div>
                        </dl>

                        {can('providers.manage') && activeProvider !== null ? (
                            <Button
                                className="w-full"
                                onClick={() => {
                                    router.post(
                                        `/providers/${String(activeProvider.id)}/check`,
                                        {},
                                        { preserveScroll: true },
                                    );
                                }}
                            >
                                <RefreshCw aria-hidden className="size-4" />
                                تنفيذ فحص الآن
                            </Button>
                        ) : null}
                    </CardBody>
                </Card>

                {/* سياسات التحويل التلقائي — وثيقة التصميم §8 */}
                <Card>
                    <CardHeader
                        title="سياسات التحويل التلقائي"
                        description={
                            can('maintenance.toggle')
                                ? undefined
                                : 'تحتاج صلاحية التحكم لتعديل هذه السياسات.'
                        }
                    />
                    <CardBody className="space-y-2">
                        {failoverPolicies.map((policy) => (
                            <Toggle
                                key={policy.key}
                                label={policy.label}
                                checked={policy.enabled}
                                disabled={!can('maintenance.toggle')}
                                onChange={(checked) => {
                                    router.post(
                                        '/settings/toggle',
                                        { key: policy.key, enabled: checked },
                                        { preserveScroll: true },
                                    );
                                }}
                            />
                        ))}
                    </CardBody>
                </Card>
            </div>

            {/* سجل آخر التحويلات */}
            <Card>
                <CardHeader
                    title="سجل آخر التحويلات"
                    description="كل تحويل بين المزودين — يدويًا أو تلقائيًا عند فشل الفحص."
                />
                <CardBody className="p-0">
                    {recentFailovers.length === 0 ? (
                        <EmptyState
                            title="لا توجد أحداث تحويل"
                            description="سيظهر هنا كل تحويل مع سببه ومن نفذه."
                        />
                    ) : (
                        <ul>
                            {recentFailovers.map((event) => (
                                <li
                                    key={event.id}
                                    className="flex flex-wrap items-center justify-between gap-3 border-b border-border-default px-6 py-3 last:border-0"
                                >
                                    <span className="font-bold text-fg-default" dir="ltr">
                                        {event.from ?? '—'} ← {event.to ?? '—'}
                                    </span>
                                    <span className="flex items-center gap-4 text-xs text-fg-muted">
                                        <span>{event.reason}</span>
                                        <span>{event.triggered_by ?? 'تلقائي'}</span>
                                        <span>{formatDate(event.created_at)}</span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardBody>
            </Card>
        </AdminLayout>
    );
}
