import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BookOpen, Check, FileWarning, Layers, X } from 'lucide-react';
import type { StatusTone } from '@/types';
import type { SectionKnowledgeRow } from '@/types/knowledge';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Badge } from '@/Components/ui/Badge';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { PageHeader } from '@/Components/ui/PageHeader';
import { StatCard } from '@/Components/ui/StatCard';
import { formatNumber, formatPercent, formatRelative } from '@/lib/format';
import { cn } from '@/lib/cn';

interface Props {
    systemStatus: { label: string; tone: StatusTone };
    project: { id: number; name: string };
    criteria: Record<string, string>;
    sections: SectionKnowledgeRow[];
}

const BARS: Record<string, string> = {
    danger: 'bg-danger',
    warning: 'bg-warning',
    info: 'bg-info',
    success: 'bg-success',
};

/** شاشة قاعدة المعرفة — وثيقة 06 §15. */
export default function KnowledgeIndex({ systemStatus, project, criteria, sections }: Props) {
    const total = sections.length;
    const complete = sections.filter((section) => section.completion === 100).length;
    const openNotes = sections.reduce((sum, section) => sum + section.open_notes, 0);
    const published = sections.reduce((sum, section) => sum + section.published, 0);

    const average =
        total === 0
            ? 0
            : Math.round(sections.reduce((sum, section) => sum + section.completion, 0) / total);

    return (
        <AdminLayout>
            <Head title="قاعدة المعرفة" />

            <PageHeader
                title="قاعدة المعرفة"
                description={`ما يعرفه المساعد عن كل قسم في ${project.name}`}
                systemStatus={systemStatus}
            />

            <section aria-label="ملخص التغطية" className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="متوسط الاكتمال"
                    value={formatPercent(average)}
                    caption={`${formatNumber(complete)} من ${formatNumber(total)} قسمًا مكتمل`}
                    icon={Layers}
                    tone={average >= 75 ? 'success' : average >= 40 ? 'warning' : 'danger'}
                    progress={average}
                />
                <StatCard
                    label="عناصر منشورة"
                    value={formatNumber(published)}
                    caption="وحدها تصل إلى المساعد"
                    icon={BookOpen}
                    tone="accent"
                />
                <StatCard
                    label="ملاحظات مفتوحة"
                    value={formatNumber(openNotes)}
                    caption={openNotes === 0 ? 'لا شيء ينتظر معالجة' : 'أسئلة بلا إجابة واقتراحات'}
                    icon={FileWarning}
                    tone={openNotes === 0 ? 'success' : openNotes > 5 ? 'danger' : 'warning'}
                />
                <StatCard
                    label="أقسام لم تبدأ"
                    value={formatNumber(sections.filter((s) => s.completion === 0).length)}
                    caption="بلا معرفة ولا شاشات ولا مصادر"
                    icon={X}
                    tone={sections.some((s) => s.completion === 0) ? 'warning' : 'success'}
                />
            </section>

            <Card>
                <CardHeader
                    title="الأقسام"
                    description="نسبة الاكتمال تقيس التغطية لا عدد العناصر"
                />

                {sections.length === 0 ? (
                    <EmptyState
                        title="لا أقسام في هذا المشروع"
                        description="أضف أقسام المشروع من شاشة التكامل ثم ابنِ معرفتها هنا."
                    />
                ) : (
                    <CardBody className="grid gap-3 lg:grid-cols-2">
                        {sections.map((section) => (
                            <Link
                                key={section.id}
                                href={`/knowledge/sections/${String(section.id)}`}
                                className="group flex flex-col gap-3 rounded-card border border-border-default p-4 transition-colors hover:border-accent hover:bg-surface-sunken"
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="flex items-center gap-2 text-body font-bold text-fg-default">
                                            {section.name}
                                            {section.ai_enabled ? null : (
                                                <Badge tone="neutral">الذكاء معطّل</Badge>
                                            )}
                                        </p>
                                        <p className="mt-0.5 truncate text-caption text-fg-muted">
                                            {section.description ?? 'بلا وصف'}
                                        </p>
                                    </div>

                                    <span className="flex shrink-0 items-center gap-2">
                                        <Badge tone={section.tone}>{section.status_label}</Badge>
                                        <ArrowLeft
                                            aria-hidden
                                            className="size-4 text-fg-subtle opacity-0 transition-opacity group-hover:opacity-100"
                                        />
                                    </span>
                                </div>

                                <div className="flex flex-col gap-1.5">
                                    <div className="flex items-baseline justify-between gap-2 text-caption">
                                        <span className="text-fg-subtle">نسبة الاكتمال</span>
                                        <span className="font-bold text-fg-default tabular-nums">
                                            {formatPercent(section.completion)}
                                        </span>
                                    </div>
                                    <div className="h-1.5 w-full overflow-hidden rounded-pill bg-surface-track">
                                        <div
                                            className={cn(
                                                'h-full rounded-pill',
                                                BARS[section.tone] ?? 'bg-neutral',
                                            )}
                                            style={{ width: `${String(section.completion)}%` }}
                                        />
                                    </div>
                                </div>

                                <ul className="flex flex-wrap gap-x-4 gap-y-1 text-caption">
                                    {Object.entries(criteria).map(([key, label]) => (
                                        <li
                                            key={key}
                                            className={cn(
                                                'flex items-center gap-1',
                                                section.met[key] === true
                                                    ? 'text-success'
                                                    : 'text-fg-subtle',
                                            )}
                                        >
                                            {section.met[key] === true ? (
                                                <Check aria-hidden className="size-3" />
                                            ) : (
                                                <X aria-hidden className="size-3" />
                                            )}
                                            {label}
                                        </li>
                                    ))}
                                </ul>

                                <dl className="flex flex-wrap gap-x-4 gap-y-1 border-t border-border-default pt-2.5 text-caption text-fg-muted tabular-nums">
                                    <Fact label="عناصر" value={section.items} />
                                    <Fact label="منشور" value={section.published} />
                                    <Fact label="شاشات" value={section.screens} />
                                    <Fact label="مصادر" value={section.sources} />
                                    <Fact
                                        label="ملاحظات مفتوحة"
                                        value={section.open_notes}
                                        danger={section.open_notes > 0}
                                    />
                                    <span className="ms-auto text-fg-subtle">
                                        آخر تحديث {formatRelative(section.updated_at)}
                                    </span>
                                </dl>
                            </Link>
                        ))}
                    </CardBody>
                )}
            </Card>
        </AdminLayout>
    );
}

function Fact({ label, value, danger }: { label: string; value: number; danger?: boolean }) {
    return (
        <span className="flex items-baseline gap-1">
            <dt className="text-fg-subtle">{label}</dt>
            <dd className={cn('font-bold', danger === true ? 'text-danger' : 'text-fg-default')}>
                {formatNumber(value)}
            </dd>
        </span>
    );
}
