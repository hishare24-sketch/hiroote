import { useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { Plus, X } from 'lucide-react';
import type { AlertOptions, AlertRuleRow, MetricOption } from '@/types/alerts';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';
import { Select } from '@/Components/ui/Select';
import { Switch } from '@/Components/ui/Switch';
import { cn } from '@/lib/cn';

interface RecipientDraft {
    user_id: number | null;
    email: string | null;
    channel: string;
}

/** الفترات المعروضة بدل حقل دقائق حر — «١٤٤٠ دقيقة» لا تُقرأ يومًا. */
const WINDOWS = [
    { value: '60', label: 'آخر ساعة' },
    { value: '360', label: 'آخر ٦ ساعات' },
    { value: '720', label: 'آخر ١٢ ساعة' },
    { value: '1440', label: 'آخر يوم' },
    { value: '4320', label: 'آخر ٣ أيام' },
    { value: '10080', label: 'آخر أسبوع' },
    { value: '43200', label: 'آخر ٣٠ يومًا' },
];

const COOLDOWNS = [
    { value: '0', label: 'بلا تهدئة' },
    { value: '60', label: 'ساعة' },
    { value: '240', label: '٤ ساعات' },
    { value: '720', label: '١٢ ساعة' },
    { value: '1440', label: 'يوم' },
    { value: '2880', label: 'يومان' },
    { value: '10080', label: 'أسبوع' },
];

/** منشئ قاعدة التنبيه — وثيقة 06 §11. */
export function RuleDialog({
    rule,
    metrics,
    options,
    onClose,
}: {
    rule?: AlertRuleRow;
    metrics: MetricOption[];
    options: AlertOptions;
    onClose: () => void;
}) {
    const editing = rule !== undefined;
    const fallback = metrics[0];

    const form = useForm({
        name: rule?.name ?? '',
        description: rule?.description ?? '',
        metric: rule?.metric.value ?? fallback?.value ?? '',
        comparison: rule?.comparison.value ?? fallback?.suggested_comparison ?? 'gt',
        threshold: String(rule?.threshold ?? fallback?.suggested_threshold ?? 0),
        window_minutes: String(
            rule?.window_minutes === undefined || rule.window_minutes === 0
                ? 1440
                : rule.window_minutes,
        ),
        severity: rule?.severity.value ?? 'warning',
        cooldown_minutes: String(rule?.cooldown_minutes ?? 60),
        auto_action: rule?.auto_action.value ?? 'notify_only',
        is_enabled: rule?.is_enabled ?? true,
        section_ids: rule?.section_ids ?? [],
        provider_ids: rule?.provider_ids ?? [],
        recipients: (rule?.recipients ?? []).map((recipient): RecipientDraft => ({
            user_id: recipient.user_id,
            email: recipient.email,
            channel: recipient.channel.value,
        })),
    });

    const [externalEmail, setExternalEmail] = useState('');

    const metric = useMemo(
        () => metrics.find((option) => option.value === form.data.metric) ?? fallback,
        [metrics, form.data.metric, fallback],
    );

    const grouped = useMemo(() => {
        const map = new Map<string, { label: string; items: MetricOption[] }>();

        for (const option of metrics) {
            const bucket = map.get(option.family) ?? { label: option.family_label, items: [] };
            bucket.items.push(option);
            map.set(option.family, bucket);
        }

        return [...map.values()];
    }, [metrics]);

    /** تغيير المؤشر يعيد ضبط الشرط والحد: حدُّ نسبةٍ على مبلغ لا معنى له. */
    const pickMetric = (value: string): void => {
        const picked = metrics.find((option) => option.value === value);

        if (picked === undefined) {
            return;
        }

        form.setData((data) => ({
            ...data,
            metric: value,
            comparison: picked.suggested_comparison,
            threshold: String(picked.suggested_threshold),
            section_ids: picked.supports_sections ? data.section_ids : [],
        }));
    };

    const toggleId = (key: 'section_ids' | 'provider_ids', id: number): void => {
        const current = form.data[key];

        form.setData(
            key,
            current.includes(id) ? current.filter((value) => value !== id) : [...current, id],
        );
    };

    const addRecipient = (draft: RecipientDraft): void => {
        const exists = form.data.recipients.some(
            (item) =>
                item.channel === draft.channel &&
                item.user_id === draft.user_id &&
                item.email === draft.email,
        );

        if (exists) {
            return;
        }

        form.setData('recipients', [...form.data.recipients, draft]);
    };

    const submit = (event: React.SyntheticEvent): void => {
        event.preventDefault();

        const payload = { preserveScroll: true, onSuccess: onClose };

        if (editing) {
            form.put(`/alerts/${String(rule.id)}`, payload);

            return;
        }

        form.post('/alerts', payload);
    };

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label={editing ? 'تعديل قاعدة تنبيه' : 'قاعدة تنبيه جديدة'}
            className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-fg-default/40 p-4 py-10"
        >
            <Card className="w-full max-w-2xl">
                <CardHeader
                    title={editing ? 'تعديل القاعدة' : 'قاعدة تنبيه جديدة'}
                    description="ما يُراقَب، ومتى يُعدّ تجاوزًا، ومن يُخبَر"
                    actions={
                        <Button variant="ghost" size="sm" onClick={onClose}>
                            <X aria-hidden className="size-4" />
                            <span className="sr-only">إغلاق</span>
                        </Button>
                    }
                />

                <CardBody>
                    <form className="flex flex-col gap-4" onSubmit={submit}>
                        <Input
                            label="اسم القاعدة"
                            required
                            value={form.data.name}
                            error={form.errors.name}
                            onChange={(event) => {
                                form.setData('name', event.target.value);
                            }}
                        />

                        <Input
                            label="الوصف"
                            value={form.data.description}
                            error={form.errors.description}
                            hint="يظهر في بطاقة القاعدة وفي الإشعار."
                            onChange={(event) => {
                                form.setData('description', event.target.value);
                            }}
                        />

                        <div className="flex flex-col gap-1.5">
                            <label
                                htmlFor="alert-metric"
                                className="text-body font-medium text-fg-default"
                            >
                                المؤشر
                            </label>
                            <select
                                id="alert-metric"
                                value={form.data.metric}
                                onChange={(event) => {
                                    pickMetric(event.target.value);
                                }}
                                className="bg-surface-default rounded-control border border-border-default px-3 py-2 text-body text-fg-default"
                            >
                                {grouped.map((group) => (
                                    <optgroup key={group.label} label={group.label}>
                                        {group.items.map((option) => (
                                            <option key={option.value} value={option.value}>
                                                {option.label}
                                            </option>
                                        ))}
                                    </optgroup>
                                ))}
                            </select>
                            {metric === undefined ? null : (
                                <p className="text-caption text-fg-muted">{metric.hint}</p>
                            )}
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <Select
                                label="الشرط"
                                options={options.comparisons.map((comparison) => ({
                                    value: comparison.value,
                                    label: comparison.label,
                                }))}
                                value={form.data.comparison}
                                onChange={(event) => {
                                    form.setData('comparison', event.target.value);
                                }}
                            />

                            <Input
                                label={`الحد (${metric?.unit_label ?? ''})`}
                                type="number"
                                step="0.1"
                                min={0}
                                max={metric?.ceiling ?? undefined}
                                required
                                value={form.data.threshold}
                                error={form.errors.threshold}
                                onChange={(event) => {
                                    form.setData('threshold', event.target.value);
                                }}
                            />
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            {metric?.windowed === true ? (
                                <Select
                                    label="الفترة"
                                    options={WINDOWS}
                                    value={form.data.window_minutes}
                                    onChange={(event) => {
                                        form.setData('window_minutes', event.target.value);
                                    }}
                                />
                            ) : (
                                <div className="flex flex-col justify-end gap-1.5">
                                    <span className="text-body font-medium text-fg-default">
                                        الفترة
                                    </span>
                                    <p className="rounded-control bg-surface-sunken px-3 py-2 text-caption text-fg-muted">
                                        هذا المؤشر قيمة لحظية لا مجموع فترة.
                                    </p>
                                </div>
                            )}

                            <Select
                                label="التهدئة بين الإشعارات"
                                options={COOLDOWNS}
                                value={form.data.cooldown_minutes}
                                onChange={(event) => {
                                    form.setData('cooldown_minutes', event.target.value);
                                }}
                            />
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <Select
                                label="مستوى الخطورة"
                                options={options.severities.map((severity) => ({
                                    value: severity.value,
                                    label: severity.label,
                                }))}
                                value={form.data.severity}
                                onChange={(event) => {
                                    form.setData('severity', event.target.value);
                                }}
                            />

                            <Select
                                label="الإجراء التلقائي"
                                options={options.actions.map((action) => ({
                                    value: action.value,
                                    label: action.awaits
                                        ? `${action.label} (بانتظار التنفيذ)`
                                        : action.label,
                                }))}
                                value={form.data.auto_action}
                                onChange={(event) => {
                                    form.setData('auto_action', event.target.value);
                                }}
                            />
                        </div>

                        {metric?.supports_sections === true && options.sections.length > 0 ? (
                            <ChipPicker
                                label="الأقسام المشمولة"
                                hint="الفارغ يعني كل الأقسام."
                                items={options.sections}
                                selected={form.data.section_ids}
                                onToggle={(id) => {
                                    toggleId('section_ids', id);
                                }}
                            />
                        ) : null}

                        {options.providers.length > 0 ? (
                            <ChipPicker
                                label="المزودون المشمولون"
                                hint="الفارغ يعني كل المزودين."
                                items={options.providers}
                                selected={form.data.provider_ids}
                                onToggle={(id) => {
                                    toggleId('provider_ids', id);
                                }}
                            />
                        ) : null}

                        <fieldset className="flex flex-col gap-2 rounded-card border border-border-default p-3">
                            <legend className="px-1 text-caption font-bold text-fg-default">
                                المستلمون
                            </legend>

                            {form.data.recipients.length === 0 ? (
                                <p className="text-caption text-warning">
                                    بلا مستلمين لن يعلم أحد بالتفعيل.
                                </p>
                            ) : (
                                <ul className="flex flex-wrap gap-1.5">
                                    {form.data.recipients.map((recipient, index) => {
                                        const member = options.members.find(
                                            (candidate) => candidate.id === recipient.user_id,
                                        );
                                        const channel = options.channels.find(
                                            (candidate) => candidate.value === recipient.channel,
                                        );

                                        return (
                                            <li key={`${String(index)}-${recipient.channel}`}>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        form.setData(
                                                            'recipients',
                                                            form.data.recipients.filter(
                                                                (_, position) => position !== index,
                                                            ),
                                                        );
                                                    }}
                                                    className="inline-flex items-center gap-1.5 rounded-pill bg-surface-sunken px-2.5 py-1 text-micro text-fg-default hover:bg-danger-soft"
                                                >
                                                    {member?.name ?? recipient.email ?? '—'} ·{' '}
                                                    {channel?.label ?? recipient.channel}
                                                    <X aria-hidden className="size-3" />
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}

                            <div className="grid gap-2 sm:grid-cols-[1fr_auto]">
                                <Select
                                    label="أضف عضوًا"
                                    options={options.members.map((member) => ({
                                        value: String(member.id),
                                        label: member.name,
                                    }))}
                                    placeholder="اختر عضوًا"
                                    value=""
                                    onChange={(event) => {
                                        if (event.target.value === '') {
                                            return;
                                        }

                                        addRecipient({
                                            user_id: Number(event.target.value),
                                            email: null,
                                            channel: 'in_app',
                                        });
                                    }}
                                />

                                <div className="flex items-end gap-2">
                                    <Input
                                        label="أو بريد خارجي"
                                        type="email"
                                        value={externalEmail}
                                        onChange={(event) => {
                                            setExternalEmail(event.target.value);
                                        }}
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => {
                                            if (externalEmail === '') {
                                                return;
                                            }

                                            addRecipient({
                                                user_id: null,
                                                email: externalEmail,
                                                channel: 'email',
                                            });
                                            setExternalEmail('');
                                        }}
                                    >
                                        <Plus aria-hidden className="size-4" />
                                        إضافة
                                    </Button>
                                </div>
                            </div>

                            {options.channels.some((channel) => !channel.wired) ? (
                                <p className="text-micro text-fg-muted">
                                    {options.channels
                                        .filter((channel) => !channel.wired)
                                        .map(
                                            (channel) =>
                                                `${channel.label}: ${channel.pending_reason ?? ''}`,
                                        )
                                        .join(' · ')}
                                </p>
                            ) : null}
                        </fieldset>

                        <label className="flex items-center justify-between gap-3 rounded-card border border-border-default px-3 py-2">
                            <span className="text-body text-fg-default">القاعدة مفعّلة</span>
                            <Switch
                                aria-label="تفعيل القاعدة"
                                checked={form.data.is_enabled}
                                onChange={(checked) => {
                                    form.setData('is_enabled', checked);
                                }}
                            />
                        </label>

                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="ghost" onClick={onClose}>
                                إلغاء
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {editing ? 'حفظ' : 'إضافة القاعدة'}
                            </Button>
                        </div>
                    </form>
                </CardBody>
            </Card>
        </div>
    );
}

function ChipPicker({
    label,
    hint,
    items,
    selected,
    onToggle,
}: {
    label: string;
    hint: string;
    items: { id: number; name: string }[];
    selected: number[];
    onToggle: (id: number) => void;
}) {
    return (
        <div className="flex flex-col gap-1.5">
            <span className="text-body font-medium text-fg-default">{label}</span>
            <div className="flex flex-wrap gap-1.5">
                {items.map((item) => (
                    <button
                        key={item.id}
                        type="button"
                        onClick={() => {
                            onToggle(item.id);
                        }}
                        className={cn(
                            'rounded-pill px-2.5 py-1 text-micro font-bold',
                            selected.includes(item.id)
                                ? 'bg-accent text-on-accent'
                                : 'bg-surface-sunken text-fg-muted hover:text-fg-default',
                        )}
                    >
                        {item.name}
                    </button>
                ))}
            </div>
            <p className="text-micro text-fg-subtle">
                {selected.length === 0 ? (
                    hint
                ) : (
                    <Badge tone="accent">{selected.length} مختار</Badge>
                )}
            </p>
        </div>
    );
}
