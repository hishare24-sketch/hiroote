import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ArrowRight, RotateCcw } from 'lucide-react';
import type { StatusTone } from '@/types';
import type { VersionRow } from '@/types/knowledge';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Alert } from '@/Components/ui/Alert';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { PageHeader } from '@/Components/ui/PageHeader';
import { usePermissions } from '@/Hooks/usePermissions';
import { formatDateTime, formatNumber } from '@/lib/format';
import { cn } from '@/lib/cn';

interface Props {
    systemStatus: { label: string; tone: StatusTone };
    item: { id: number; title: string; version: number; section_id: number | null };
    versions: VersionRow[];
}

/** مقارنة الإصدارات والرجوع — وثيقة 06 §15. */
export default function KnowledgeVersions({ systemStatus, item, versions }: Props) {
    const { can } = usePermissions();
    const manage = can('knowledge.manage');

    const [left, setLeft] = useState<number>(versions[1]?.version ?? versions[0]?.version ?? 1);
    const [right, setRight] = useState<number>(versions[0]?.version ?? 1);

    // إصدار واحد لا يُقارن بنفسه — تُعرض النسخة وحدها بدل لوحتين متطابقتين.
    const single = versions.length < 2;
    const older = single ? undefined : versions.find((v) => v.version === Math.min(left, right));
    const newer = versions.find((version) => version.version === Math.max(left, right));

    return (
        <AdminLayout>
            <Head title={`إصدارات: ${item.title}`} />

            <PageHeader
                title={`إصدارات: ${item.title}`}
                description={`${formatNumber(versions.length)} إصدارًا · الحالي ${formatNumber(item.version)}`}
                systemStatus={systemStatus}
                actions={
                    item.section_id === null ? undefined : (
                        <Link
                            href={`/knowledge/sections/${String(item.section_id)}`}
                            className="inline-flex items-center gap-1.5 rounded-control px-3 py-1.5 text-caption font-bold text-accent hover:bg-accent-soft"
                        >
                            <ArrowRight aria-hidden className="size-4" />
                            عودة إلى القسم
                        </Link>
                    )
                }
            />

            <Alert tone="neutral" title="الرجوع لا يمحو ما بعده">
                استعادة إصدار سابق تُنشئ إصدارًا جديدًا بمحتواه، فيبقى تاريخ العنصر كاملًا ويظهر من
                رجع وإلى أي نسخة.
            </Alert>

            <div className="grid gap-4 lg:grid-cols-[1fr_2fr]">
                <Card>
                    <CardHeader title="السجل" description="الأحدث أولًا" />
                    <CardBody>
                        <ol className="flex flex-col divide-y divide-border-default">
                            {versions.map((version) => (
                                <li
                                    key={version.id}
                                    className="flex flex-col gap-1.5 py-3 first:pt-0"
                                >
                                    <div className="flex items-center justify-between gap-2">
                                        <p className="flex items-center gap-2 text-body font-bold text-fg-default">
                                            الإصدار {formatNumber(version.version)}
                                            {version.version === item.version ? (
                                                <Badge tone="accent">الحالي</Badge>
                                            ) : null}
                                        </p>
                                        <Badge tone={version.status.tone}>
                                            {version.status.label}
                                        </Badge>
                                    </div>

                                    <p className="text-caption text-fg-muted">
                                        {version.change_note ?? 'بلا سبب مسجّل'}
                                    </p>
                                    <p className="text-micro text-fg-subtle tabular-nums">
                                        {version.author ?? 'غير معروف'} ·{' '}
                                        {formatDateTime(version.created_at)}
                                    </p>

                                    <div className="mt-1 flex flex-wrap gap-1.5">
                                        {single ? null : (
                                            <>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setLeft(version.version);
                                                    }}
                                                    className={cn(
                                                        'rounded-pill px-2.5 py-1 text-micro font-bold',
                                                        left === version.version
                                                            ? 'bg-accent text-on-accent'
                                                            : 'bg-surface-sunken text-fg-muted hover:text-fg-default',
                                                    )}
                                                >
                                                    قارن كأقدم
                                                </button>
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setRight(version.version);
                                                    }}
                                                    className={cn(
                                                        'rounded-pill px-2.5 py-1 text-micro font-bold',
                                                        right === version.version
                                                            ? 'bg-accent text-on-accent'
                                                            : 'bg-surface-sunken text-fg-muted hover:text-fg-default',
                                                    )}
                                                >
                                                    قارن كأحدث
                                                </button>
                                            </>
                                        )}

                                        {manage && version.version !== item.version ? (
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                onClick={() => {
                                                    router.post(
                                                        `/knowledge/items/${String(item.id)}/versions/${String(version.id)}/restore`,
                                                        {},
                                                        { preserveScroll: true },
                                                    );
                                                }}
                                            >
                                                <RotateCcw aria-hidden className="size-3.5" />
                                                رجوع
                                            </Button>
                                        ) : null}
                                    </div>
                                </li>
                            ))}
                        </ol>
                    </CardBody>
                </Card>

                <Card>
                    <CardHeader
                        title={single ? 'النص الحالي' : 'المقارنة'}
                        description={
                            single
                                ? 'لا مقارنة — إصدار واحد فقط'
                                : older === undefined || newer === undefined
                                  ? 'اختر إصدارين'
                                  : `الإصدار ${formatNumber(older.version)} مقابل ${formatNumber(newer.version)}`
                        }
                    />
                    <CardBody>
                        {newer === undefined ? (
                            <p className="text-body text-fg-muted">اختر إصدارين من السجل.</p>
                        ) : older === undefined ? (
                            <VersionPane version={newer} label="الوحيد" />
                        ) : (
                            <div className="grid gap-4 md:grid-cols-2">
                                <VersionPane version={older} label="الأقدم" />
                                <VersionPane version={newer} label="الأحدث" />
                            </div>
                        )}
                    </CardBody>
                </Card>
            </div>
        </AdminLayout>
    );
}

function VersionPane({ version, label }: { version: VersionRow; label: string }) {
    return (
        <div className="flex flex-col gap-2 rounded-card border border-border-default p-4">
            <p className="flex items-center justify-between gap-2 text-caption font-bold text-fg-muted">
                {label} — الإصدار {formatNumber(version.version)}
                <Badge tone={version.status.tone}>{version.status.label}</Badge>
            </p>
            <p className="text-body font-bold text-fg-default">{version.title}</p>
            {version.summary === null ? null : (
                <p className="text-caption text-fg-muted">{version.summary}</p>
            )}
            <pre className="mt-1 max-h-96 overflow-auto rounded-control bg-surface-sunken p-3 text-caption whitespace-pre-wrap text-fg-default">
                {version.body}
            </pre>
        </div>
    );
}
