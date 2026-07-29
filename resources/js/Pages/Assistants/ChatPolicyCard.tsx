import { useForm } from '@inertiajs/react';
import { MessageSquare, ShieldAlert } from 'lucide-react';
import { Alert } from '@/Components/ui/Alert';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';
import { Switch } from '@/Components/ui/Switch';

export interface ChatPolicy {
    is_enabled: boolean;
    channel_kinds: string[];
    scopes: string[];
    assistant_participates: boolean;
    attachments_allowed: boolean;
    retention_days: number;
}

export interface ChatKindOption {
    value: string;
    label: string;
    about: string;
    human_to_human: boolean;
}

export interface ChatScopeOption {
    value: string;
    label: string;
    about: string;
    crosses_tenants: boolean;
}

/**
 * حوكمة الشات — **إذنٌ لا محتوى**.
 *
 * هاي روت لا يخزّن رسالةً واحدة من رسائل مستخدمي المشروع؛ هويّاتهم ومحادثاتهم
 * تبقى عنده (وثيقة 01 §6). ما يُضبط هنا يقرأه المشروع من جسر الوارد فيطبّقه في
 * واجهته — ومصدرٌ واحد للإذن يعني أن إطفاء المالك يسري فورًا.
 */
export function ChatPolicyCard({
    policy,
    kinds,
    scopes,
    canManage,
}: {
    policy: ChatPolicy;
    kinds: ChatKindOption[];
    scopes: ChatScopeOption[];
    canManage: boolean;
}) {
    const form = useForm({
        is_enabled: policy.is_enabled,
        channel_kinds: policy.channel_kinds,
        scopes: policy.scopes,
        assistant_participates: policy.assistant_participates,
        attachments_allowed: policy.attachments_allowed,
        retention_days: policy.retention_days,
    });

    const toggle = (field: 'channel_kinds' | 'scopes', value: string) => {
        const current = form.data[field];

        form.setData(
            field,
            current.includes(value) ? current.filter((v) => v !== value) : [...current, value],
        );
    };

    const opensHumanChannels = form.data.channel_kinds.some(
        (kind) => kinds.find((k) => k.value === kind)?.human_to_human === true,
    );
    const crossesTenants = form.data.scopes.some(
        (scope) => scopes.find((s) => s.value === scope)?.crosses_tenants === true,
    );

    return (
        <Card>
            <CardHeader
                title="حوكمة الشات"
                description="ما يُسمح به في المشروع — الواجهة يبنيها المشروع، والإذن من هنا"
            />
            <CardBody>
                <form
                    className="flex flex-col gap-4"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.put('/assistants/chat', { preserveScroll: true });
                    }}
                >
                    <Alert tone="neutral" title="إذنٌ لا محتوى">
                        هاي روت لا يخزّن رسائل مستخدمي المشروع — تبقى عنده. ما يُضبط هنا يقرأه
                        المشروع من <span dir="ltr">GET /api/v1/context</span> فيطبّقه.
                    </Alert>

                    <div className="flex items-start justify-between gap-3 rounded-control border border-border-default p-3">
                        <span className="flex flex-col gap-0.5">
                            <span className="flex items-center gap-2 text-caption font-bold text-fg-default">
                                <MessageSquare aria-hidden className="size-4" />
                                الشات مفعّل
                            </span>
                            <span className="text-micro text-fg-subtle">
                                مطفأ افتراضيًّا: الشات يفتح قناةً بين بشرٍ وبشر، وافتراض السماح
                                يفتحها بلا قرار من أحد.
                            </span>
                        </span>
                        <Switch
                            aria-label="تفعيل الشات"
                            checked={form.data.is_enabled}
                            disabled={!canManage}
                            onChange={(next) => {
                                form.setData('is_enabled', next);
                            }}
                        />
                    </div>

                    {form.errors.is_enabled === undefined ? null : (
                        <p className="text-caption text-danger">{form.errors.is_enabled}</p>
                    )}

                    <fieldset className="flex flex-col gap-2">
                        <legend className="text-caption font-bold text-fg-muted">
                            أنواع القنوات
                        </legend>
                        {kinds.map((kind) => (
                            <label
                                key={kind.value}
                                className="flex items-start gap-3 rounded-control border border-border-default p-2.5"
                            >
                                <input
                                    type="checkbox"
                                    className="mt-1 size-4 accent-accent"
                                    disabled={!canManage}
                                    checked={form.data.channel_kinds.includes(kind.value)}
                                    onChange={() => {
                                        toggle('channel_kinds', kind.value);
                                    }}
                                />
                                <span className="flex flex-col gap-0.5">
                                    <span className="flex flex-wrap items-center gap-2 text-caption font-bold text-fg-default">
                                        {kind.label}
                                        {kind.human_to_human ? (
                                            <Badge tone="warning">بشر إلى بشر</Badge>
                                        ) : null}
                                    </span>
                                    <span className="text-micro text-fg-subtle">{kind.about}</span>
                                </span>
                            </label>
                        ))}
                    </fieldset>

                    <fieldset className="flex flex-col gap-2">
                        <legend className="text-caption font-bold text-fg-muted">الدوائر</legend>
                        {scopes.map((scope) => (
                            <label
                                key={scope.value}
                                className="flex items-start gap-3 rounded-control border border-border-default p-2.5"
                            >
                                <input
                                    type="checkbox"
                                    className="mt-1 size-4 accent-accent"
                                    disabled={!canManage}
                                    checked={form.data.scopes.includes(scope.value)}
                                    onChange={() => {
                                        toggle('scopes', scope.value);
                                    }}
                                />
                                <span className="flex flex-col gap-0.5">
                                    <span className="flex flex-wrap items-center gap-2 text-caption font-bold text-fg-default">
                                        {scope.label}
                                        {scope.crosses_tenants ? (
                                            <Badge tone="danger">تعبر المشتركين</Badge>
                                        ) : null}
                                    </span>
                                    <span className="text-micro text-fg-subtle">{scope.about}</span>
                                </span>
                            </label>
                        ))}
                    </fieldset>

                    {opensHumanChannels || crossesTenants ? (
                        <Alert tone="warning" title="ما تفتحه هذه الاختيارات">
                            <ul className="flex list-inside list-disc flex-col gap-1">
                                {opensHumanChannels ? (
                                    <li>
                                        قنواتٌ بين بشرٍ وبشر: محتواها ليس إجابةَ مساعد، ولا تحكمه
                                        معرفةُ اللوحة ولا حدودُ المستوى.
                                    </li>
                                ) : null}
                                {crossesTenants ? (
                                    <li className="flex items-start gap-1.5">
                                        <ShieldAlert
                                            aria-hidden
                                            className="mt-0.5 size-3.5 shrink-0"
                                        />
                                        دائرة «عبر المشتركين» تُخرج المحادثة من حدود المشترك الواحد
                                        — راجعها مع سياسة الخصوصية قبل التفعيل.
                                    </li>
                                ) : null}
                            </ul>
                        </Alert>
                    ) : null}

                    <div className="grid gap-3 md:grid-cols-2">
                        <div className="flex items-start justify-between gap-3 rounded-control border border-border-default p-3">
                            <span className="flex flex-col gap-0.5">
                                <span className="text-caption font-bold text-fg-default">
                                    المساعد يشارك
                                </span>
                                <span className="text-micro text-fg-subtle">
                                    إطفاؤه يترك القنوات بشرية بحتة.
                                </span>
                            </span>
                            <Switch
                                aria-label="مشاركة المساعد"
                                checked={form.data.assistant_participates}
                                disabled={!canManage}
                                onChange={(next) => {
                                    form.setData('assistant_participates', next);
                                }}
                            />
                        </div>

                        <div className="flex items-start justify-between gap-3 rounded-control border border-border-default p-3">
                            <span className="flex flex-col gap-0.5">
                                <span className="text-caption font-bold text-fg-default">
                                    المرفقات
                                </span>
                                <span className="text-micro text-fg-subtle">
                                    السماح برفع الملفات داخل القنوات.
                                </span>
                            </span>
                            <Switch
                                aria-label="السماح بالمرفقات"
                                checked={form.data.attachments_allowed}
                                disabled={!canManage}
                                onChange={(next) => {
                                    form.setData('attachments_allowed', next);
                                }}
                            />
                        </div>
                    </div>

                    <Input
                        label="مدة الحفظ (أيام)"
                        type="number"
                        min={0}
                        max={3650}
                        disabled={!canManage}
                        value={String(form.data.retention_days)}
                        error={form.errors.retention_days}
                        hint="الصفر يعني بلا حدّ لا «لا تحفظ» — والفرق بينهما كامل."
                        onChange={(event) => {
                            form.setData('retention_days', Number(event.target.value));
                        }}
                    />

                    {canManage ? (
                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing}>
                                احفظ الإذن
                            </Button>
                        </div>
                    ) : null}
                </form>
            </CardBody>
        </Card>
    );
}
