import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    ArrowRight,
    FileText,
    History,
    Link2,
    MessageSquareWarning,
    MonitorSmartphone,
    Pencil,
    Plus,
    StickyNote,
    Trash2,
    X,
} from 'lucide-react';
import type { StatusTone } from '@/types';
import type {
    FeedbackRow,
    KindOption,
    KnowledgeItemRow,
    KnowledgeKind,
    KnowledgeStatus,
    ScreenRow,
    SectionDetail,
    SourceRow,
} from '@/types/knowledge';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Alert } from '@/Components/ui/Alert';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Input } from '@/Components/ui/Input';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Select } from '@/Components/ui/Select';
import { FeedbackCard } from './FeedbackCard';
import { ScreenDialog } from './ScreenDialog';
import { usePermissions } from '@/Hooks/usePermissions';
import { formatNumber, formatPercent, formatRelative } from '@/lib/format';
import { cn } from '@/lib/cn';

interface Props {
    systemStatus: { label: string; tone: StatusTone };
    section: SectionDetail;
    items: KnowledgeItemRow[];
    screens: ScreenRow[];
    sources: SourceRow[];
    feedback: FeedbackRow[];
    kindOptions: KindOption[];
    statusOptions: KindOption[];
    feedbackKinds: KindOption[];
    verificationOutcomes: KindOption[];
}

const SOURCE_ICONS: Record<string, typeof Link2> = {
    link: Link2,
    file: FileText,
    note: StickyNote,
};

/** صفحة تفاصيل القسم — وثيقة 06 §15. */
export default function KnowledgeShow({
    systemStatus,
    section,
    items,
    screens,
    sources,
    feedback,
    kindOptions,
    statusOptions,
    verificationOutcomes,
}: Props) {
    const { can } = usePermissions();
    const manage = can('knowledge.manage');
    const [editing, setEditing] = useState<KnowledgeItemRow | null>(null);
    const [creating, setCreating] = useState(false);
    const [screenDialog, setScreenDialog] = useState<{ screen?: ScreenRow } | null>(null);

    const open = feedback.filter((entry) => !entry.resolved);

    return (
        <AdminLayout>
            <Head title={`معرفة: ${section.name}`} />

            <PageHeader
                title={section.name}
                description={section.description ?? 'قسم بلا وصف'}
                systemStatus={systemStatus}
                actions={
                    <span className="flex items-center gap-2">
                        {manage ? (
                            <Button
                                size="sm"
                                onClick={() => {
                                    setCreating(true);
                                }}
                            >
                                <Plus aria-hidden className="size-4" />
                                عنصر معرفة
                            </Button>
                        ) : null}
                        <Link
                            href="/knowledge"
                            className="inline-flex items-center gap-1.5 rounded-control px-3 py-1.5 text-caption font-bold text-accent hover:bg-accent-soft"
                        >
                            <ArrowRight aria-hidden className="size-4" />
                            كل الأقسام
                        </Link>
                    </span>
                }
            />

            {section.knowledge_enabled ? null : (
                <Alert tone="warning" title="المعرفة معطّلة لهذا القسم">
                    ما تكتبه هنا يُحفظ ولا يصل المساعد. فعّل قدرة «معرفة» للقسم من شاشة تكامل
                    الأقسام.
                </Alert>
            )}

            <section aria-label="حالة المعرفة" className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <Metric
                    label="نسبة الاكتمال"
                    value={formatPercent(section.completion)}
                    caption={section.status_label}
                    tone={section.tone}
                    progress={section.completion}
                />
                <Metric
                    label="عناصر المعرفة"
                    value={formatNumber(section.items)}
                    caption={`${formatNumber(section.published)} منشور`}
                    tone="accent"
                />
                <Metric
                    label="الشاشات الموصوفة"
                    value={formatNumber(section.screens)}
                    caption="بعناصرها وإجراءاتها وحالاتها"
                    tone="info"
                />
                <Metric
                    label="ملاحظات مفتوحة"
                    value={formatNumber(section.open_notes)}
                    caption={section.open_notes === 0 ? 'لا شيء ينتظر' : 'تحتاج معالجة'}
                    tone={section.open_notes === 0 ? 'success' : 'warning'}
                />
            </section>

            <div className="grid gap-4 lg:grid-cols-[1.4fr_1fr]">
                <Card>
                    <CardHeader title="عناصر المعرفة" description="المنشور وحده يصل إلى المساعد" />
                    <CardBody>
                        {items.length === 0 ? (
                            <EmptyState
                                title="لا عناصر بعد"
                                description="ابدأ بسؤال متكرر أو سياسة — أكثر ما يُسأل عنه في هذا القسم."
                            />
                        ) : (
                            <ul className="flex flex-col divide-y divide-border-default">
                                {items.map((item) => (
                                    <li
                                        key={item.id}
                                        className="flex flex-col gap-2 py-4 first:pt-0"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="flex flex-wrap items-center gap-2 text-body font-bold text-fg-default">
                                                    {item.title}
                                                    <Badge tone={item.status.tone}>
                                                        {item.status.label}
                                                    </Badge>
                                                    <Badge tone={item.kind.tone}>
                                                        {item.kind.label}
                                                    </Badge>
                                                </p>
                                                {item.summary === null ? null : (
                                                    <p className="mt-1 text-caption text-fg-muted">
                                                        {item.summary}
                                                    </p>
                                                )}
                                            </div>

                                            <span className="flex shrink-0 gap-1">
                                                <Link
                                                    href={`/knowledge/items/${String(item.id)}/versions`}
                                                    aria-label={`إصدارات ${item.title}`}
                                                    className="rounded-control p-1.5 text-fg-subtle hover:bg-surface-sunken hover:text-fg-default"
                                                >
                                                    <History aria-hidden className="size-4" />
                                                </Link>
                                                {manage ? (
                                                    <button
                                                        type="button"
                                                        aria-label={`تحرير ${item.title}`}
                                                        onClick={() => {
                                                            setEditing(item);
                                                        }}
                                                        className="rounded-control p-1.5 text-fg-subtle hover:bg-accent-soft hover:text-accent"
                                                    >
                                                        <Pencil aria-hidden className="size-4" />
                                                    </button>
                                                ) : null}
                                            </span>
                                        </div>

                                        {item.tags.length === 0 ? null : (
                                            <ul className="flex flex-wrap gap-1.5">
                                                {item.tags.map((tag) => (
                                                    <li
                                                        key={tag}
                                                        className="rounded-pill bg-surface-sunken px-2 py-0.5 text-micro text-fg-muted"
                                                    >
                                                        {tag}
                                                    </li>
                                                ))}
                                            </ul>
                                        )}

                                        <p className="text-micro text-fg-subtle tabular-nums">
                                            الإصدار {formatNumber(item.version)} ·{' '}
                                            {item.editor ?? 'غير معروف'} ·{' '}
                                            {formatRelative(item.updated_at)}
                                        </p>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardBody>
                </Card>

                <div className="flex flex-col gap-4">
                    <Card>
                        <CardHeader
                            title="الملاحظات والأسئلة"
                            description="رصدٌ يُفرز ثم يُتحقق منه ميدانيًا قبل أي تعديل"
                        />
                        <CardBody>
                            {feedback.length === 0 ? (
                                <EmptyState
                                    title="لا ملاحظات"
                                    description="لم يصل سؤال بلا إجابة ولا اقتراح لهذا القسم."
                                />
                            ) : (
                                <ul className="flex flex-col divide-y divide-border-default">
                                    {feedback.map((entry) => (
                                        <FeedbackCard
                                            key={entry.id}
                                            entry={entry}
                                            outcomes={verificationOutcomes}
                                            manage={manage}
                                        />
                                    ))}
                                </ul>
                            )}
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader title="المصادر" description="الروابط والملفات والملاحظات" />
                        <CardBody>
                            {sources.length === 0 ? (
                                <EmptyState
                                    title="لا مصادر"
                                    description="أرفق الرابط أو الملف الذي بُنيت عليه هذه المعرفة."
                                />
                            ) : (
                                <ul className="flex flex-col gap-2.5">
                                    {sources.map((source) => {
                                        const Icon = SOURCE_ICONS[source.kind.value] ?? StickyNote;

                                        return (
                                            <li
                                                key={source.id}
                                                className="flex items-start gap-2.5 rounded-control border border-border-default p-3"
                                            >
                                                <Icon
                                                    aria-hidden
                                                    className="mt-0.5 size-4 shrink-0 text-fg-subtle"
                                                />
                                                <div className="min-w-0">
                                                    <p className="truncate text-caption font-semibold text-fg-default">
                                                        {source.label}
                                                    </p>
                                                    {source.url === null ? null : (
                                                        <p
                                                            dir="ltr"
                                                            className="truncate text-start text-micro text-fg-subtle"
                                                        >
                                                            {source.url}
                                                        </p>
                                                    )}
                                                    {source.note === null ? null : (
                                                        <p className="text-micro text-fg-muted">
                                                            {source.note}
                                                        </p>
                                                    )}
                                                </div>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </CardBody>
                    </Card>
                </div>
            </div>

            <Card>
                <CardHeader
                    title="شاشات القسم"
                    description="ما يراه المستخدم، بعناصره وإجراءاته وحالاته"
                    actions={
                        manage ? (
                            <Button
                                size="sm"
                                onClick={() => {
                                    setScreenDialog({});
                                }}
                            >
                                <Plus aria-hidden className="size-4" />
                                شاشة
                            </Button>
                        ) : undefined
                    }
                />
                <CardBody>
                    {screens.length === 0 ? (
                        <EmptyState
                            icon={MonitorSmartphone}
                            title="لا شاشات موصوفة"
                            description="وصف الشاشة يمكّن المساعد من توجيه المستخدم إليها بدقة."
                        />
                    ) : (
                        <ul className="grid gap-4 lg:grid-cols-2">
                            {screens.map((screen) => (
                                <li
                                    key={screen.id}
                                    className="flex flex-col gap-3 rounded-card border border-border-default p-4"
                                >
                                    {screen.image_url === null ? null : (
                                        <img
                                            src={screen.image_url}
                                            alt={`صورة شاشة ${screen.name}`}
                                            loading="lazy"
                                            className="max-h-56 w-full rounded-control border border-border-default object-contain"
                                        />
                                    )}

                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <p className="text-body font-bold text-fg-default">
                                                {screen.name}
                                            </p>
                                            {screen.key === null ? (
                                                <p className="text-caption text-warning">
                                                    بلا مفتاح — لن يعرفها المشروع عند فتح الشات
                                                </p>
                                            ) : (
                                                <p
                                                    dir="ltr"
                                                    className="text-start text-caption text-accent"
                                                >
                                                    {screen.key}
                                                </p>
                                            )}
                                            {screen.path === null ? null : (
                                                <p
                                                    dir="ltr"
                                                    className="text-start text-caption text-fg-subtle"
                                                >
                                                    {screen.path}
                                                </p>
                                            )}
                                            {screen.description === null ? (
                                                <p className="mt-1 text-caption text-warning">
                                                    بلا وصف — المساعد يقرأ الوصف لا الصورة
                                                </p>
                                            ) : (
                                                <p className="mt-1 text-caption text-fg-muted">
                                                    {screen.description}
                                                </p>
                                            )}
                                        </div>

                                        {manage ? (
                                            <span className="flex shrink-0 items-center gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => {
                                                        setScreenDialog({ screen });
                                                    }}
                                                >
                                                    <Pencil aria-hidden className="size-3.5" />
                                                    <span className="sr-only">تعديل</span>
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    onClick={() => {
                                                        router.delete(
                                                            `/knowledge/screens/${String(screen.id)}`,
                                                            { preserveScroll: true },
                                                        );
                                                    }}
                                                >
                                                    <Trash2
                                                        aria-hidden
                                                        className="size-3.5 text-danger"
                                                    />
                                                    <span className="sr-only">حذف</span>
                                                </Button>
                                            </span>
                                        ) : null}
                                    </div>

                                    <ScreenFacet label="العناصر" values={screen.elements} />
                                    <ScreenFacet label="الإجراءات" values={screen.actions} />
                                    <ScreenFacet label="الحالات" values={screen.states} />
                                </li>
                            ))}
                        </ul>
                    )}
                </CardBody>
            </Card>

            {creating ? (
                <ItemDialog
                    title="عنصر معرفة جديد"
                    sectionId={section.id}
                    kindOptions={kindOptions}
                    statusOptions={statusOptions}
                    onClose={() => {
                        setCreating(false);
                    }}
                />
            ) : null}

            {editing === null ? null : (
                <ItemDialog
                    title={`تحرير: ${editing.title}`}
                    sectionId={section.id}
                    item={editing}
                    kindOptions={kindOptions}
                    statusOptions={statusOptions}
                    onClose={() => {
                        setEditing(null);
                    }}
                />
            )}

            {screenDialog === null ? null : (
                <ScreenDialog
                    sectionId={section.id}
                    screen={screenDialog.screen}
                    onClose={() => {
                        setScreenDialog(null);
                    }}
                />
            )}

            {open.length === 0 ? null : (
                <p className="flex items-center gap-2 text-caption text-fg-subtle">
                    <MessageSquareWarning aria-hidden className="size-4" />
                    {formatNumber(open.length)} ملاحظة مفتوحة تمنع اكتمال هذا القسم.
                </p>
            )}
        </AdminLayout>
    );
}

function Metric({
    label,
    value,
    caption,
    tone,
    progress,
}: {
    label: string;
    value: string;
    caption: string;
    tone: string;
    progress?: number;
}) {
    const bar: Record<string, string> = {
        danger: 'bg-danger',
        warning: 'bg-warning',
        info: 'bg-info',
        success: 'bg-success',
        accent: 'bg-accent',
    };

    return (
        <div className="flex flex-col gap-3 rounded-card border border-border-default bg-surface-raised p-5 shadow-card">
            <p className="text-body font-medium text-fg-muted">{label}</p>
            <p className="text-metric font-bold text-fg-default tabular-nums">{value}</p>
            <p className="text-caption text-fg-muted">{caption}</p>
            {progress === undefined ? null : (
                <div className="h-[5px] w-full overflow-hidden rounded-pill bg-surface-track">
                    <div
                        className={cn('h-full rounded-pill', bar[tone] ?? 'bg-neutral')}
                        style={{ width: `${String(progress)}%` }}
                    />
                </div>
            )}
        </div>
    );
}

function ScreenFacet({ label, values }: { label: string; values: string[] }) {
    if (values.length === 0) {
        return null;
    }

    return (
        <div>
            <p className="text-micro font-bold text-fg-subtle">{label}</p>
            <ul className="mt-1 flex flex-wrap gap-1.5">
                {values.map((value) => (
                    <li
                        key={value}
                        className="rounded-pill bg-surface-sunken px-2 py-0.5 text-micro text-fg-muted"
                    >
                        {value}
                    </li>
                ))}
            </ul>
        </div>
    );
}

function ItemDialog({
    title,
    sectionId,
    item,
    kindOptions,
    statusOptions,
    onClose,
}: {
    title: string;
    sectionId: number;
    item?: KnowledgeItemRow;
    kindOptions: KindOption[];
    statusOptions: KindOption[];
    onClose: () => void;
}) {
    const editing = item !== undefined;

    const form = useForm({
        title: item?.title ?? '',
        summary: item?.summary ?? '',
        body: item?.body ?? '',
        kind: item?.kind.value ?? 'faq',
        status: item?.status.value ?? 'draft',
        tags: item?.tags.join('، ') ?? '',
        change_note: '',
    });

    const submit = (event: React.SyntheticEvent) => {
        event.preventDefault();

        const payload = {
            ...form.data,
            tags: form.data.tags
                .split(/[،,]/)
                .map((tag) => tag.trim())
                .filter((tag) => tag !== ''),
        };

        const options = { preserveScroll: true, onSuccess: onClose };

        if (editing) {
            router.put(`/knowledge/items/${String(item.id)}`, payload, options);

            return;
        }

        router.post(`/knowledge/sections/${String(sectionId)}/items`, payload, options);
    };

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label={title}
            className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-fg-default/40 p-4 py-10"
        >
            <Card className="w-full max-w-2xl">
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
                    <form className="grid gap-4 sm:grid-cols-2" onSubmit={submit}>
                        <div className="sm:col-span-2">
                            <Input
                                label="العنوان"
                                required
                                value={form.data.title}
                                error={form.errors.title}
                                onChange={(event) => {
                                    form.setData('title', event.target.value);
                                }}
                            />
                        </div>

                        <Select
                            label="النوع"
                            options={kindOptions}
                            value={form.data.kind}
                            onChange={(event) => {
                                form.setData('kind', event.target.value as KnowledgeKind);
                            }}
                        />
                        <Select
                            label="الحالة"
                            options={statusOptions}
                            value={form.data.status}
                            onChange={(event) => {
                                form.setData('status', event.target.value as KnowledgeStatus);
                            }}
                        />

                        <div className="sm:col-span-2">
                            <Input
                                label="الملخص"
                                hint="سطر واحد يظهر في القائمة قبل فتح العنصر."
                                value={form.data.summary}
                                error={form.errors.summary}
                                onChange={(event) => {
                                    form.setData('summary', event.target.value);
                                }}
                            />
                        </div>

                        <div className="flex flex-col gap-1.5 sm:col-span-2">
                            <label
                                htmlFor="knowledge-body"
                                className="text-body font-medium text-fg-default"
                            >
                                المحتوى
                            </label>
                            <textarea
                                id="knowledge-body"
                                rows={10}
                                required
                                value={form.data.body}
                                onChange={(event) => {
                                    form.setData('body', event.target.value);
                                }}
                                className="w-full rounded-control border border-border-strong bg-surface-raised p-3 text-body text-fg-default"
                            />
                            {form.errors.body === undefined ? null : (
                                <p role="alert" className="text-caption font-medium text-danger">
                                    {form.errors.body}
                                </p>
                            )}
                        </div>

                        <Input
                            label="الوسوم"
                            hint="افصل بينها بفاصلة."
                            value={form.data.tags}
                            onChange={(event) => {
                                form.setData('tags', event.target.value);
                            }}
                        />
                        <Input
                            label="سبب التعديل"
                            hint="يظهر في سجل الإصدارات."
                            value={form.data.change_note}
                            error={form.errors.change_note}
                            onChange={(event) => {
                                form.setData('change_note', event.target.value);
                            }}
                        />

                        <div className="flex gap-2 sm:col-span-2">
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
