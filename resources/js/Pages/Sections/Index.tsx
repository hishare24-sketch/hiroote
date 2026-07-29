import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Check, Lock, Pencil, Plus, Trash2, X } from 'lucide-react';
import type { StatusTone } from '@/types';
import type { CapabilityColumn, SectionRow, SelectOptionPayload } from '@/types/assistants';
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
import { formatNumber, formatPercent, formatRelative } from '@/lib/format';
import { cn } from '@/lib/cn';

interface Props {
    systemStatus: { label: string; tone: StatusTone };
    project: { id: number; name: string };
    capabilities: CapabilityColumn[];
    sections: SectionRow[];
    models: SelectOptionPayload[];
    levelOptions: SelectOptionPayload[];
}

/** مصفوفة تكامل أقسام المشروع — وثيقة 06 §14. */
export default function SectionsIndex({
    systemStatus,
    project,
    capabilities,
    sections,
    models,
    levelOptions,
}: Props) {
    const { can } = usePermissions();
    const manage = can('integrations.manage');
    const [editing, setEditing] = useState<SectionRow | null>(null);
    const [adding, setAdding] = useState(false);

    return (
        <AdminLayout>
            <Head title="تكامل أقسام المشروع" />

            <PageHeader
                title="تكامل أقسام المشروع"
                description={`ما يستطيع المساعد فعله داخل كل قسم في ${project.name}`}
                systemStatus={systemStatus}
                actions={
                    manage ? (
                        <Button
                            size="sm"
                            onClick={() => {
                                setAdding(true);
                            }}
                        >
                            <Plus aria-hidden className="size-4" />
                            قسم جديد
                        </Button>
                    ) : undefined
                }
            />

            {manage ? null : (
                <Alert tone="neutral" title="عرض فقط">
                    دورك في هذا المشروع يرى المصفوفة ولا يغيّرها.
                </Alert>
            )}

            <Card>
                <CardHeader
                    title="المصفوفة"
                    description={`${formatNumber(sections.length)} قسمًا · ${formatNumber(capabilities.length)} قدرة لكل قسم`}
                />

                {sections.length === 0 ? (
                    <EmptyState
                        title="لا أقسام بعد"
                        description="أضف أقسام هذا المشروع لتبدأ بضبط ما يفعله المساعد في كل قسم."
                    />
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[72rem] border-collapse">
                            <caption className="sr-only">
                                مصفوفة قدرات المساعد لكل قسم من أقسام المشروع
                            </caption>
                            <thead>
                                <tr className="border-b border-border-default bg-surface-sunken">
                                    <th
                                        scope="col"
                                        className="sticky start-0 z-10 bg-surface-sunken px-3 py-3 text-start text-micro font-bold whitespace-nowrap text-fg-muted"
                                    >
                                        القسم
                                    </th>
                                    {capabilities.map((capability) => (
                                        <th
                                            key={capability.key}
                                            scope="col"
                                            title={capability.description}
                                            className="px-1 py-3 text-center text-micro font-bold whitespace-nowrap text-fg-muted"
                                        >
                                            {capability.short_label}
                                            {capability.sensitive ? (
                                                <span aria-hidden className="ms-0.5 text-warning">
                                                    •
                                                </span>
                                            ) : null}
                                        </th>
                                    ))}
                                    <th
                                        scope="col"
                                        className="px-3 py-3 text-start text-micro font-bold whitespace-nowrap text-fg-muted"
                                    >
                                        المستوى والنموذج
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-3 py-3 text-start text-micro font-bold whitespace-nowrap text-fg-muted"
                                    >
                                        الاستخدام والحل والتصعيد
                                    </th>
                                    <th
                                        scope="col"
                                        className="px-3 py-3 text-start text-micro font-bold whitespace-nowrap text-fg-muted"
                                    >
                                        آخر تحديث
                                    </th>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-border-default">
                                {sections.map((section) => (
                                    <tr key={section.id} className="group hover:bg-surface-sunken">
                                        <th
                                            scope="row"
                                            className="sticky start-0 z-10 bg-surface-raised px-3 py-2.5 text-start group-hover:bg-surface-sunken"
                                        >
                                            <span className="flex items-center gap-2">
                                                <span className="min-w-0">
                                                    <span className="block truncate text-caption font-bold text-fg-default">
                                                        {section.name}
                                                    </span>
                                                    {section.capabilities.ai_enabled ? null : (
                                                        <span className="block text-micro text-fg-subtle">
                                                            الذكاء معطّل
                                                        </span>
                                                    )}
                                                </span>

                                                {manage ? (
                                                    <span className="ms-auto flex shrink-0 gap-0.5">
                                                        <button
                                                            type="button"
                                                            aria-label={`تحرير ${section.name}`}
                                                            onClick={() => {
                                                                setEditing(section);
                                                            }}
                                                            className="rounded-control p-1 text-fg-subtle hover:bg-accent-soft hover:text-accent"
                                                        >
                                                            <Pencil
                                                                aria-hidden
                                                                className="size-3.5"
                                                            />
                                                        </button>
                                                        <button
                                                            type="button"
                                                            aria-label={`حذف ${section.name}`}
                                                            onClick={() => {
                                                                if (
                                                                    confirm(
                                                                        `حذف «${section.name}»؟ لا يؤثر على المحادثات السابقة.`,
                                                                    )
                                                                ) {
                                                                    router.delete(
                                                                        `/integrations/sections/${String(section.id)}`,
                                                                        { preserveScroll: true },
                                                                    );
                                                                }
                                                            }}
                                                            className="rounded-control p-1 text-fg-subtle hover:bg-danger-soft hover:text-danger"
                                                        >
                                                            <Trash2
                                                                aria-hidden
                                                                className="size-3.5"
                                                            />
                                                        </button>
                                                    </span>
                                                ) : null}
                                            </span>
                                        </th>

                                        {capabilities.map((capability) => (
                                            <td
                                                key={capability.key}
                                                className="px-1 py-2.5 text-center"
                                            >
                                                <CapabilityCell
                                                    section={section}
                                                    capability={capability}
                                                    manage={manage}
                                                />
                                            </td>
                                        ))}

                                        <td className="px-3 py-2.5 whitespace-nowrap">
                                            {section.level === null ? (
                                                <span className="text-caption text-fg-subtle">
                                                    مستوى المشروع
                                                </span>
                                            ) : (
                                                <Badge tone={section.level.tone}>
                                                    {section.level.label}
                                                </Badge>
                                            )}
                                            <span className="mt-0.5 block text-micro text-fg-subtle">
                                                {section.model ?? 'نموذج المشروع'}
                                            </span>
                                        </td>

                                        <td className="px-3 py-2.5 whitespace-nowrap tabular-nums">
                                            {section.conversations === 0 ? (
                                                <span className="text-caption text-fg-subtle">
                                                    لا محادثات
                                                </span>
                                            ) : (
                                                <>
                                                    <span className="text-caption font-bold text-fg-default">
                                                        {formatNumber(section.conversations)} محادثة
                                                    </span>
                                                    <span className="mt-0.5 block text-micro text-fg-subtle">
                                                        حل{' '}
                                                        {formatPercent(
                                                            section.resolution_rate ?? 0,
                                                        )}{' '}
                                                        · تصعيد{' '}
                                                        {formatPercent(
                                                            section.escalation_rate ?? 0,
                                                        )}
                                                    </span>
                                                </>
                                            )}
                                        </td>

                                        <td className="px-3 py-2.5 text-caption whitespace-nowrap text-fg-muted">
                                            {formatRelative(section.updated_at)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="flex flex-wrap items-center gap-x-5 gap-y-1.5 border-t border-border-default px-5 py-3 text-micro text-fg-subtle">
                    <span className="flex items-center gap-1.5">
                        <span aria-hidden className="text-warning">
                            •
                        </span>
                        قدرة حساسة
                    </span>
                    <span className="flex items-center gap-1.5">
                        <Lock aria-hidden className="size-3" />
                        معطّلة لأن ما تعتمد عليه مطفأ
                    </span>
                    <span>ترويسة كل عمود تحمل شرحه عند المرور عليها.</span>
                </div>
            </Card>

            {adding ? (
                <SectionDialog
                    title="قسم جديد"
                    onClose={() => {
                        setAdding(false);
                    }}
                />
            ) : null}

            {editing === null ? null : (
                <SectionDialog
                    title={`تحرير: ${editing.name}`}
                    section={editing}
                    models={models}
                    levelOptions={levelOptions}
                    onClose={() => {
                        setEditing(null);
                    }}
                />
            )}
        </AdminLayout>
    );
}

function CapabilityCell({
    section,
    capability,
    manage,
}: {
    section: SectionRow;
    capability: CapabilityColumn;
    manage: boolean;
}) {
    const enabled = section.capabilities[capability.key] === true;
    const aiOff = capability.key !== 'ai_enabled' && !section.capabilities.ai_enabled;
    const parentOff =
        capability.depends_on !== null && section.capabilities[capability.depends_on] !== true;
    const blocked = aiOff || parentOff;

    const title = blocked
        ? aiOff
            ? 'يحتاج تفعيل الذكاء في هذا القسم'
            : `يحتاج تفعيل «${capability.depends_on_label ?? 'القدرة التي تعتمد عليها'}»`
        : `${capability.label} — ${enabled ? 'مفعّلة' : 'مطفأة'}`;

    return (
        <button
            type="button"
            title={title}
            aria-label={`${capability.label} في ${section.name}`}
            aria-pressed={enabled && !blocked}
            disabled={!manage || blocked}
            onClick={() => {
                router.post(
                    `/integrations/sections/${String(section.id)}/toggle`,
                    { capability: capability.key, enabled: !enabled },
                    { preserveScroll: true },
                );
            }}
            className={cn(
                'inline-flex size-7 items-center justify-center rounded-control transition-colors',
                blocked
                    ? 'cursor-not-allowed bg-surface-sunken text-fg-subtle'
                    : enabled
                      ? capability.sensitive
                          ? 'bg-warning-soft text-warning'
                          : 'bg-success-soft text-success'
                      : 'bg-surface-track text-fg-subtle',
                manage && !blocked ? 'hover:brightness-95' : 'cursor-default',
            )}
        >
            {blocked ? (
                <Lock aria-hidden className="size-3" />
            ) : enabled ? (
                <Check aria-hidden className="size-4" />
            ) : (
                <X aria-hidden className="size-3.5" />
            )}
        </button>
    );
}

function SectionDialog({
    title,
    section,
    models,
    levelOptions,
    onClose,
}: {
    title: string;
    section?: SectionRow;
    models?: SelectOptionPayload[];
    levelOptions?: SelectOptionPayload[];
    onClose: () => void;
}) {
    const editing = section !== undefined;

    const form = useForm({
        name: section?.name ?? '',
        description: section?.description ?? '',
        level: section?.level?.value ?? '',
        model_id:
            section?.model_id === null || section === undefined ? '' : String(section.model_id),
        sort_order: section?.sort_order ?? 0,
    });

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label={title}
            className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-fg-default/40 p-4 py-16"
        >
            <Card className="w-full max-w-lg">
                <CardHeader
                    title={title}
                    actions={
                        <Button variant="ghost" size="sm" onClick={onClose}>
                            <X aria-hidden className="size-4" />
                            <span className="sr-only">إغلاق</span>
                        </Button>
                    }
                />
                <CardBody>
                    <form
                        className="flex flex-col gap-4"
                        onSubmit={(event) => {
                            event.preventDefault();

                            if (editing) {
                                form.put(`/integrations/sections/${String(section.id)}`, {
                                    preserveScroll: true,
                                    onSuccess: onClose,
                                });

                                return;
                            }

                            form.post('/integrations/sections', {
                                preserveScroll: true,
                                onSuccess: onClose,
                            });
                        }}
                    >
                        <Input
                            label="اسم القسم"
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
                            hint="يظهر للمشغّل فقط، ويشرح ما يغطيه هذا القسم."
                            onChange={(event) => {
                                form.setData('description', event.target.value);
                            }}
                        />

                        {editing ? (
                            <>
                                <Select
                                    label="المستوى"
                                    options={levelOptions ?? []}
                                    placeholder="مستوى المشروع الافتراضي"
                                    value={form.data.level}
                                    onChange={(event) => {
                                        form.setData('level', event.target.value);
                                    }}
                                />

                                <Select
                                    label="النموذج"
                                    options={models ?? []}
                                    placeholder="نموذج المشروع الافتراضي"
                                    value={form.data.model_id}
                                    onChange={(event) => {
                                        form.setData('model_id', event.target.value);
                                    }}
                                />

                                <Input
                                    label="الترتيب"
                                    type="number"
                                    min={0}
                                    max={999}
                                    value={String(form.data.sort_order)}
                                    error={form.errors.sort_order}
                                    onChange={(event) => {
                                        form.setData('sort_order', Number(event.target.value));
                                    }}
                                />
                            </>
                        ) : (
                            <p className="text-caption text-fg-muted">
                                يبدأ القسم بالقدرات الافتراضية، وتُضبط كل قدرة من المصفوفة بعد
                                الإضافة.
                            </p>
                        )}

                        <div className="flex gap-2">
                            <Button type="submit" loading={form.processing}>
                                {editing ? 'حفظ' : 'إضافة'}
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
