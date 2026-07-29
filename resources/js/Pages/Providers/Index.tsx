import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowDown, ArrowUp, KeyRound, RefreshCw, Server, ShieldCheck, Trash2 } from 'lucide-react';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Alert } from '@/Components/ui/Alert';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Input } from '@/Components/ui/Input';
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
    models: ProviderModel[];
    credentials: ProviderCredential[];
}

interface FailoverEvent {
    id: number;
    from: string | null;
    to: string | null;
    reason: string;
    triggered_by: string | null;
    created_at: string;
}

interface ProvidersPageProps {
    providers: Provider[];
    recentFailovers: FailoverEvent[];
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

function CredentialForm({ provider }: { provider: Provider }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        label: '',
        api_key: '',
    });

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

function ProviderCard({
    provider,
    index,
    total,
    order,
}: {
    provider: Provider;
    index: number;
    total: number;
    order: number[];
}) {
    const { can } = usePermissions();
    const [showCredentialForm, setShowCredentialForm] = useState(false);

    const move = (direction: -1 | 1) => {
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

    const activeCredential = provider.credentials.find((credential) => credential.is_active);

    return (
        <Card className={provider.is_active ? 'border-brand-500 ring-1 ring-brand-500/40' : ''}>
            <CardHeader
                title={provider.name}
                description={
                    provider.models.find((model) => model.is_default)?.display_name ??
                    'لا يوجد نموذج افتراضي'
                }
                actions={
                    <div className="flex items-center gap-2">
                        {provider.is_active ? (
                            <Badge tone="info" dot>
                                المزود النشط
                            </Badge>
                        ) : null}
                        <Badge tone={STATUS_TONES[provider.status]} dot>
                            {provider.status_label}
                        </Badge>
                    </div>
                }
            />
            <CardBody className="space-y-4">
                <dl className="grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div>
                        <dt className="text-fg-muted">الأولوية</dt>
                        <dd className="mt-0.5 flex items-center gap-1 font-medium text-fg-default">
                            {provider.priority}
                            {can('providers.manage') ? (
                                <span className="ms-1 inline-flex gap-0.5">
                                    <button
                                        type="button"
                                        aria-label={`رفع أولوية ${provider.name}`}
                                        disabled={index === 0}
                                        onClick={() => {
                                            move(-1);
                                        }}
                                        className="rounded p-0.5 text-fg-subtle hover:bg-surface-sunken disabled:opacity-30"
                                    >
                                        <ArrowUp aria-hidden className="size-3.5" />
                                    </button>
                                    <button
                                        type="button"
                                        aria-label={`خفض أولوية ${provider.name}`}
                                        disabled={index === total - 1}
                                        onClick={() => {
                                            move(1);
                                        }}
                                        className="rounded p-0.5 text-fg-subtle hover:bg-surface-sunken disabled:opacity-30"
                                    >
                                        <ArrowDown aria-hidden className="size-3.5" />
                                    </button>
                                </span>
                            ) : null}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-fg-muted">آخر فحص</dt>
                        <dd className="mt-0.5 font-medium text-fg-default">
                            {formatDate(provider.last_checked_at)}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-fg-muted">فشل متتالٍ</dt>
                        <dd className="mt-0.5 font-medium text-fg-default">
                            {provider.consecutive_failures}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-fg-muted">المفتاح الفعال</dt>
                        <dd
                            className="mt-0.5 font-mono text-xs font-medium text-fg-default"
                            dir="ltr"
                        >
                            {activeCredential ? `••••${activeCredential.key_hint}` : 'لا يوجد'}
                        </dd>
                    </div>
                </dl>

                {provider.models.length > 0 ? (
                    <div className="flex flex-wrap gap-2">
                        {provider.models.map((model) => (
                            <Badge key={model.id} tone={model.is_default ? 'info' : 'neutral'}>
                                {model.display_name}
                            </Badge>
                        ))}
                    </div>
                ) : null}

                <div className="flex flex-wrap items-center gap-2 border-t border-border-default pt-4">
                    {can('providers.manage') ? (
                        <>
                            <label className="flex cursor-pointer items-center gap-2 text-sm text-fg-default">
                                <input
                                    type="checkbox"
                                    role="switch"
                                    checked={provider.is_enabled}
                                    onChange={(event) => {
                                        router.post(
                                            `/providers/${String(provider.id)}/toggle`,
                                            { enabled: event.target.checked },
                                            { preserveScroll: true },
                                        );
                                    }}
                                    className="size-4 rounded border-border-strong"
                                />
                                مفعل
                            </label>

                            <Button
                                variant="secondary"
                                size="sm"
                                onClick={() => {
                                    router.post(
                                        `/providers/${String(provider.id)}/check`,
                                        {},
                                        { preserveScroll: true },
                                    );
                                }}
                            >
                                <RefreshCw aria-hidden className="size-4" />
                                فحص الآن
                            </Button>
                        </>
                    ) : null}

                    {can('providers.failover') && !provider.is_active && provider.is_enabled ? (
                        <Button
                            variant="secondary"
                            size="sm"
                            onClick={() => {
                                router.post(
                                    `/providers/${String(provider.id)}/activate`,
                                    {},
                                    { preserveScroll: true },
                                );
                            }}
                        >
                            <ShieldCheck aria-hidden className="size-4" />
                            تحويل إليه
                        </Button>
                    ) : null}

                    {can('providers.manage_credentials') ? (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setShowCredentialForm((value) => !value);
                            }}
                        >
                            <KeyRound aria-hidden className="size-4" />
                            {showCredentialForm ? 'إخفاء نموذج المفتاح' : 'إدارة المفاتيح'}
                        </Button>
                    ) : null}
                </div>

                {showCredentialForm && can('providers.manage_credentials') ? (
                    <div className="space-y-3 rounded-card bg-surface-sunken p-4">
                        <CredentialForm provider={provider} />

                        {provider.credentials.length > 0 ? (
                            <ul className="space-y-1 text-sm">
                                {provider.credentials.map((credential) => (
                                    <li
                                        key={credential.id}
                                        className="flex items-center justify-between gap-2 rounded-control bg-surface-raised px-3 py-2"
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
                                            {credential.is_active ? (
                                                <Badge tone="success">فعال</Badge>
                                            ) : (
                                                <Badge tone="neutral">مبطل</Badge>
                                            )}
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
                                                    <Trash2 aria-hidden className="size-4" />
                                                </button>
                                            ) : null}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        ) : null}
                    </div>
                ) : null}
            </CardBody>
        </Card>
    );
}

export default function Index({ providers, recentFailovers }: ProvidersPageProps) {
    const order = providers.map((provider) => provider.id);
    const noKeys = providers.every((provider) =>
        provider.credentials.every((credential) => !credential.is_active),
    );

    return (
        <AdminLayout title="المزودون والنماذج">
            <Head title="المزودون والنماذج" />

            {noKeys && providers.length > 0 ? (
                <Alert
                    tone="warning"
                    title="لا توجد مفاتيح فعالة"
                    children="أضف مفتاح API لمزود واحد على الأقل من زر «إدارة المفاتيح» حتى يبدأ الفحص الذاتي والتشغيل."
                />
            ) : null}

            {providers.length === 0 ? (
                <Card>
                    <CardBody>
                        <EmptyState
                            icon={Server}
                            title="لا يوجد مزودون"
                            description="شغّل بذر قاعدة البيانات (php artisan db:seed) لإضافة المزودين الافتراضيين."
                        />
                    </CardBody>
                </Card>
            ) : (
                <div className="space-y-4">
                    {providers.map((provider, index) => (
                        <ProviderCard
                            key={provider.id}
                            provider={provider}
                            index={index}
                            total={providers.length}
                            order={order}
                        />
                    ))}
                </div>
            )}

            <Card>
                <CardHeader
                    title="آخر أحداث التحويل"
                    description="سجل التحويل بين المزودين — يدويًا أو تلقائيًا عند فشل الفحص."
                />
                <CardBody>
                    {recentFailovers.length === 0 ? (
                        <EmptyState
                            title="لا توجد أحداث تحويل"
                            description="سيظهر هنا كل تحويل بين المزودين مع سببه ومن نفذه."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-border-default text-start text-fg-muted">
                                        <th className="pe-4 pb-2 text-start font-medium">من</th>
                                        <th className="pe-4 pb-2 text-start font-medium">إلى</th>
                                        <th className="pe-4 pb-2 text-start font-medium">السبب</th>
                                        <th className="pe-4 pb-2 text-start font-medium">المنفذ</th>
                                        <th className="pb-2 text-start font-medium">الوقت</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {recentFailovers.map((event) => (
                                        <tr
                                            key={event.id}
                                            className="border-b border-border-default last:border-0"
                                        >
                                            <td className="py-2 pe-4 text-fg-default">
                                                {event.from ?? '—'}
                                            </td>
                                            <td className="py-2 pe-4 font-medium text-fg-default">
                                                {event.to ?? '—'}
                                            </td>
                                            <td className="py-2 pe-4 text-fg-muted">
                                                {event.reason}
                                            </td>
                                            <td className="py-2 pe-4 text-fg-muted">
                                                {event.triggered_by ?? 'تلقائي'}
                                            </td>
                                            <td className="py-2 text-fg-muted">
                                                {formatDate(event.created_at)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardBody>
            </Card>
        </AdminLayout>
    );
}
