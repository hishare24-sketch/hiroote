import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Check, Info, Lock, Pencil, Star, X } from 'lucide-react';
import type { StatusTone } from '@/types';
import type {
    AssistantFunctionToggle,
    AssistantLevelCard,
    AssistantProfile,
    SelectOptionPayload,
} from '@/types/assistants';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Alert } from '@/Components/ui/Alert';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Select } from '@/Components/ui/Select';
import { Switch } from '@/Components/ui/Switch';
import { usePermissions } from '@/Hooks/usePermissions';
import { formatMoney, formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/cn';

interface Props {
    systemStatus: { label: string; tone: StatusTone };
    project: { id: number; name: string };
    levels: AssistantLevelCard[];
    profile: AssistantProfile;
    functions: AssistantFunctionToggle[];
    models: SelectOptionPayload[];
    levelOptions: SelectOptionPayload[];
}

/** شاشة إعدادات وسلوك المساعد — وثيقة 06 §12 و§13. */
export default function AssistantsIndex({
    systemStatus,
    project,
    levels,
    profile,
    functions,
    models,
    levelOptions,
}: Props) {
    const { can } = usePermissions();
    const manage = can('assistants.manage');
    const [editing, setEditing] = useState<AssistantLevelCard | null>(null);

    return (
        <AdminLayout>
            <Head title="إعدادات وسلوك المساعد" />

            <PageHeader
                title="إعدادات وسلوك المساعد"
                description={`كيف يجيب المساعد في ${project.name} وما يُسمح له بفعله`}
                systemStatus={systemStatus}
            />

            {manage ? null : (
                <Alert tone="neutral" title="عرض فقط">
                    دورك في هذا المشروع يرى الإعدادات ولا يغيّرها.
                </Alert>
            )}

            <section
                aria-label="مستويات المساعد"
                className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
            >
                {levels.map((level) => (
                    <LevelCard
                        key={level.id}
                        level={level}
                        manage={manage}
                        onEdit={() => {
                            setEditing(level);
                        }}
                    />
                ))}
            </section>

            <div className="grid items-start gap-4 lg:grid-cols-[1fr_1.35fr]">
                <div className="lg:sticky lg:top-6">
                    <ProfileCard profile={profile} levelOptions={levelOptions} manage={manage} />
                </div>

                <Card>
                    <CardHeader
                        title="وظائف المساعد"
                        description="كل وظيفة سويتش مستقل — إطفاء الأصل يطفئ ما يعتمد عليه"
                    />
                    <CardBody className="flex flex-col divide-y divide-border-default">
                        {functions.map((fn) => (
                            <FunctionRow
                                key={fn.key}
                                fn={fn}
                                functions={functions}
                                manage={manage}
                            />
                        ))}
                    </CardBody>
                </Card>
            </div>

            {editing === null ? null : (
                <LevelEditor
                    level={editing}
                    models={models}
                    onClose={() => {
                        setEditing(null);
                    }}
                />
            )}
        </AdminLayout>
    );
}

function LevelCard({
    level,
    manage,
    onEdit,
}: {
    level: AssistantLevelCard;
    manage: boolean;
    onEdit: () => void;
}) {
    return (
        <Card className={cn('flex flex-col', level.is_available ? '' : 'opacity-70')}>
            <CardBody className="flex flex-1 flex-col gap-4">
                <div className="flex items-start justify-between gap-2">
                    <div className="min-w-0">
                        <p className="truncate text-title font-extrabold text-fg-default">
                            {level.label}
                        </p>
                        <p className="mt-1 text-caption text-fg-muted">{level.description}</p>
                    </div>
                    {level.is_default ? (
                        <span
                            title="المستوى الافتراضي"
                            className="flex size-7 shrink-0 items-center justify-center rounded-control bg-accent-soft text-accent"
                        >
                            <Star aria-hidden className="size-4 fill-current" />
                            <span className="sr-only">المستوى الافتراضي</span>
                        </span>
                    ) : null}
                </div>

                <dl className="flex flex-col gap-2 text-caption">
                    <Row label="طول الرد" value={level.response_length} />
                    <Row label="حد التوكن" value={formatNumber(level.token_limit)} />
                    <Row
                        label="التكلفة المتوقعة"
                        value={formatMoney(level.expected_cost, 'SAR', 2)}
                    />
                    <Row label="عتبة التصعيد" value={formatPercent(level.confidence_threshold)} />
                    <Row label="النموذج" value={level.model ?? 'نموذج المشروع'} />
                </dl>

                <div className="flex flex-col gap-2.5">
                    <Meter label="درجة الذكاء" value={level.intelligence} max={5} />
                    <Meter label="المبادرة" value={level.initiative} max={5} />
                    <Meter label="الإبداع" value={level.creativity} max={100} />
                    <Meter label="التفصيل" value={level.detail} max={5} />
                    <Meter label="الرسمية" value={level.formality} max={5} />
                </div>

                <div className="flex flex-wrap gap-1.5">
                    <Capability enabled={level.reads_attachments} label="قراءة المرفقات" />
                    <Capability enabled={level.calls_data} label="استدعاء البيانات" />
                    <Capability enabled={level.executes_actions} label="تنفيذ الإجراءات" />
                </div>

                <div className="mt-auto flex items-center justify-between gap-2 border-t border-border-default pt-3">
                    <Badge tone={level.is_available ? 'success' : 'neutral'}>
                        {level.is_available ? 'متاح للمستخدم' : 'غير متاح'}
                    </Badge>

                    {manage ? (
                        <Button variant="ghost" size="sm" onClick={onEdit}>
                            <Pencil aria-hidden className="size-3.5" />
                            تحرير
                        </Button>
                    ) : null}
                </div>
            </CardBody>
        </Card>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return (
        <div className="flex items-baseline justify-between gap-2">
            <dt className="text-fg-subtle">{label}</dt>
            <dd className="truncate font-bold text-fg-default tabular-nums">{value}</dd>
        </div>
    );
}

function Meter({ label, value, max }: { label: string; value: number; max: number }) {
    return (
        <div className="flex flex-col gap-1">
            <div className="flex items-baseline justify-between gap-2 text-caption">
                <span className="text-fg-subtle">{label}</span>
                <span className="font-bold text-fg-muted tabular-nums">
                    {max === 100
                        ? formatPercent(value)
                        : `${formatNumber(value)}/${formatNumber(max)}`}
                </span>
            </div>
            <div className="h-1.5 w-full overflow-hidden rounded-pill bg-surface-track">
                <div
                    className="h-full rounded-pill bg-accent"
                    style={{ width: `${String((value / max) * 100)}%` }}
                />
            </div>
        </div>
    );
}

function Capability({ enabled, label }: { enabled: boolean; label: string }) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1 rounded-pill px-2 py-0.5 text-micro font-semibold',
                enabled ? 'bg-success-soft text-success' : 'bg-neutral-soft text-neutral',
            )}
        >
            {enabled ? (
                <Check aria-hidden className="size-3" />
            ) : (
                <X aria-hidden className="size-3" />
            )}
            {label}
        </span>
    );
}

function FunctionRow({
    fn,
    functions,
    manage,
}: {
    fn: AssistantFunctionToggle;
    functions: AssistantFunctionToggle[];
    manage: boolean;
}) {
    const parent = fn.depends_on === null ? null : functions.find((f) => f.key === fn.depends_on);
    const blockedByParent = parent !== undefined && parent !== null && !parent.enabled;
    const locked = !manage || fn.awaits_implementation || blockedByParent;

    return (
        <div className="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">
            <div className="min-w-0">
                <p className="flex flex-wrap items-center gap-2 text-body font-semibold text-fg-default">
                    {fn.label}
                    {fn.sensitive ? <Badge tone="warning">حساسة</Badge> : null}
                    {fn.awaits_implementation ? <Badge tone="info">بانتظار التنفيذ</Badge> : null}
                </p>
                <p className="mt-0.5 text-caption text-fg-muted">{fn.description}</p>

                {fn.awaits_implementation ? (
                    <p className="mt-1 flex items-center gap-1.5 text-caption text-fg-subtle">
                        <Info aria-hidden className="size-3.5 shrink-0" />
                        تعريفها مُعتمد — نمط موازين — وتُفعَّل حين تُبنى الميزة في موجتها.
                    </p>
                ) : null}

                {blockedByParent ? (
                    <p className="mt-1 flex items-center gap-1.5 text-caption text-warning">
                        <Lock aria-hidden className="size-3.5 shrink-0" />
                        تحتاج تفعيل «{fn.depends_on_label}» أولًا.
                    </p>
                ) : null}
            </div>

            <Switch
                aria-label={fn.label}
                checked={fn.enabled}
                disabled={locked}
                onChange={(enabled) => {
                    router.post(
                        '/assistants/functions',
                        { key: fn.key, enabled },
                        { preserveScroll: true },
                    );
                }}
            />
        </div>
    );
}

function ProfileCard({
    profile,
    levelOptions,
    manage,
}: {
    profile: AssistantProfile;
    levelOptions: SelectOptionPayload[];
    manage: boolean;
}) {
    const form = useForm({
        default_level: profile.default_level,
        allow_level_change: profile.allow_level_change,
        level_scope: profile.level_scope,
        availability: profile.availability,
        availability_key: profile.availability_key ?? '',
    });

    return (
        <Card>
            <CardHeader
                title="تحكم المستخدم بالمستوى"
                description="ما يستطيع مستخدم المشروع تغييره بنفسه"
            />
            <CardBody>
                <form
                    className="flex flex-col gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put('/assistants/profile', { preserveScroll: true });
                    }}
                >
                    <Select
                        label="المستوى الافتراضي"
                        options={levelOptions}
                        value={form.data.default_level}
                        disabled={!manage}
                        onChange={(event) => {
                            form.setData(
                                'default_level',
                                event.target.value as AssistantProfile['default_level'],
                            );
                        }}
                    />

                    <div className="flex items-center justify-between gap-4 rounded-control border border-border-default px-3 py-2.5">
                        <span className="text-body text-fg-default">
                            السماح بتغيير المستوى داخل التطبيق
                        </span>
                        <Switch
                            aria-label="السماح بتغيير المستوى داخل التطبيق"
                            checked={form.data.allow_level_change}
                            disabled={!manage}
                            onChange={(value) => {
                                form.setData('allow_level_change', value);
                            }}
                        />
                    </div>

                    <Select
                        label="نطاق الاختيار"
                        options={[
                            { value: 'persistent', label: 'دائم عبر المحادثات' },
                            { value: 'conversation', label: 'للمحادثة الحالية فقط' },
                        ]}
                        value={form.data.level_scope}
                        disabled={!manage || !form.data.allow_level_change}
                        onChange={(event) => {
                            form.setData(
                                'level_scope',
                                event.target.value as AssistantProfile['level_scope'],
                            );
                        }}
                    />

                    <Select
                        label="التوفر"
                        options={[
                            { value: 'all', label: 'كل المستخدمين' },
                            { value: 'membership', label: 'حسب العضوية' },
                            { value: 'role', label: 'حسب الدور' },
                        ]}
                        value={form.data.availability}
                        disabled={!manage}
                        onChange={(event) => {
                            form.setData(
                                'availability',
                                event.target.value as AssistantProfile['availability'],
                            );
                        }}
                    />

                    {form.data.availability === 'all' ? null : (
                        <Input
                            label={
                                form.data.availability === 'membership'
                                    ? 'اسم العضوية'
                                    : 'اسم الدور'
                            }
                            value={form.data.availability_key}
                            disabled={!manage}
                            error={form.errors.availability_key}
                            onChange={(event) => {
                                form.setData('availability_key', event.target.value);
                            }}
                        />
                    )}

                    {manage ? (
                        <Button type="submit" loading={form.processing} className="self-start">
                            حفظ
                        </Button>
                    ) : null}
                </form>
            </CardBody>
        </Card>
    );
}

function LevelEditor({
    level,
    models,
    onClose,
}: {
    level: AssistantLevelCard;
    models: SelectOptionPayload[];
    onClose: () => void;
}) {
    const form = useForm({
        label: level.label,
        description: level.description,
        response_length: level.response_length,
        token_limit: level.token_limit,
        intelligence: level.intelligence,
        initiative: level.initiative,
        creativity: level.creativity,
        detail: level.detail,
        formality: level.formality,
        reads_attachments: level.reads_attachments,
        calls_data: level.calls_data,
        executes_actions: level.executes_actions,
        confidence_threshold: level.confidence_threshold,
        model_id: level.model_id === null ? '' : String(level.model_id),
        expected_cost: level.expected_cost,
        is_available: level.is_available,
    });

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label={`تحرير مستوى ${level.label}`}
            className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-fg-default/40 p-4 py-10"
        >
            <Card className="w-full max-w-2xl">
                <CardHeader
                    title={`تحرير: ${level.label}`}
                    description="القيم تسري على هذا المشروع وحده"
                    actions={
                        <Button variant="ghost" size="sm" onClick={onClose}>
                            <X aria-hidden className="size-4" />
                            <span className="sr-only">إغلاق</span>
                        </Button>
                    }
                />
                <CardBody>
                    <form
                        className="grid gap-4 sm:grid-cols-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.put(`/assistants/levels/${String(level.id)}`, {
                                preserveScroll: true,
                                onSuccess: onClose,
                            });
                        }}
                    >
                        <Input
                            label="الاسم"
                            value={form.data.label}
                            error={form.errors.label}
                            onChange={(event) => {
                                form.setData('label', event.target.value);
                            }}
                        />
                        <Input
                            label="طول الرد"
                            value={form.data.response_length}
                            error={form.errors.response_length}
                            onChange={(event) => {
                                form.setData('response_length', event.target.value);
                            }}
                        />

                        <div className="sm:col-span-2">
                            <Input
                                label="الوصف"
                                value={form.data.description}
                                error={form.errors.description}
                                onChange={(event) => {
                                    form.setData('description', event.target.value);
                                }}
                            />
                        </div>

                        <NumberField
                            label="حد التوكن"
                            value={form.data.token_limit}
                            min={100}
                            max={32000}
                            error={form.errors.token_limit}
                            onChange={(value) => {
                                form.setData('token_limit', value);
                            }}
                        />
                        <NumberField
                            label="عتبة التصعيد %"
                            value={form.data.confidence_threshold}
                            min={0}
                            max={100}
                            error={form.errors.confidence_threshold}
                            onChange={(value) => {
                                form.setData('confidence_threshold', value);
                            }}
                        />
                        <NumberField
                            label="درجة الذكاء (1–5)"
                            value={form.data.intelligence}
                            min={1}
                            max={5}
                            onChange={(value) => {
                                form.setData('intelligence', value);
                            }}
                        />
                        <NumberField
                            label="المبادرة (1–5)"
                            value={form.data.initiative}
                            min={1}
                            max={5}
                            onChange={(value) => {
                                form.setData('initiative', value);
                            }}
                        />
                        <NumberField
                            label="الإبداع (0–100)"
                            value={form.data.creativity}
                            min={0}
                            max={100}
                            onChange={(value) => {
                                form.setData('creativity', value);
                            }}
                        />
                        <NumberField
                            label="التفصيل (1–5)"
                            value={form.data.detail}
                            min={1}
                            max={5}
                            onChange={(value) => {
                                form.setData('detail', value);
                            }}
                        />
                        <NumberField
                            label="الرسمية (1–5)"
                            value={form.data.formality}
                            min={1}
                            max={5}
                            onChange={(value) => {
                                form.setData('formality', value);
                            }}
                        />
                        <NumberField
                            label="التكلفة المتوقعة"
                            value={form.data.expected_cost}
                            min={0}
                            max={999}
                            step={0.01}
                            onChange={(value) => {
                                form.setData('expected_cost', value);
                            }}
                        />

                        <div className="sm:col-span-2">
                            <Select
                                label="النموذج"
                                options={models}
                                placeholder="نموذج المشروع الافتراضي"
                                value={form.data.model_id}
                                onChange={(event) => {
                                    form.setData('model_id', event.target.value);
                                }}
                            />
                        </div>

                        <div className="flex flex-col gap-2 sm:col-span-2">
                            <ToggleRow
                                label="قراءة المرفقات"
                                checked={form.data.reads_attachments}
                                onChange={(value) => {
                                    form.setData('reads_attachments', value);
                                }}
                            />
                            <ToggleRow
                                label="استدعاء البيانات"
                                checked={form.data.calls_data}
                                onChange={(value) => {
                                    form.setData('calls_data', value);
                                }}
                            />
                            <ToggleRow
                                label="تنفيذ الإجراءات"
                                checked={form.data.executes_actions}
                                onChange={(value) => {
                                    form.setData('executes_actions', value);
                                }}
                            />
                            <ToggleRow
                                label="متاح للمستخدم"
                                checked={form.data.is_available}
                                onChange={(value) => {
                                    form.setData('is_available', value);
                                }}
                            />
                        </div>

                        <div className="flex gap-2 sm:col-span-2">
                            <Button type="submit" loading={form.processing}>
                                حفظ
                            </Button>
                            <Button type="button" variant="secondary" onClick={onClose}>
                                إلغاء
                            </Button>
                        </div>
                    </form>
                </CardBody>
            </Card>
        </div>
    );
}

function NumberField({
    label,
    value,
    min,
    max,
    step,
    error,
    onChange,
}: {
    label: string;
    value: number;
    min: number;
    max: number;
    step?: number;
    error?: string;
    onChange: (value: number) => void;
}) {
    return (
        <Input
            label={label}
            type="number"
            min={min}
            max={max}
            step={step}
            value={String(value)}
            error={error}
            onChange={(event) => {
                onChange(Number(event.target.value));
            }}
        />
    );
}

function ToggleRow({
    label,
    checked,
    onChange,
}: {
    label: string;
    checked: boolean;
    onChange: (value: boolean) => void;
}) {
    return (
        <div className="flex items-center justify-between gap-4 rounded-control border border-border-default px-3 py-2">
            <span className="text-body text-fg-default">{label}</span>
            <Switch aria-label={label} checked={checked} onChange={onChange} />
        </div>
    );
}
