import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { AlertTriangle, ArrowLeftRight, ScrollText, Search, SlidersHorizontal } from 'lucide-react';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { Input } from '@/Components/ui/Input';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Select } from '@/Components/ui/Select';
import { StatCard } from '@/Components/ui/StatCard';
import type { StatusTone } from '@/types';

interface AuditEntry {
    id: number;
    ulid: string;
    action: string;
    section: string | null;
    category: string;
    category_tone: string;
    actor: string;
    actor_role: string | null;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    reason: string | null;
    ip_address: string | null;
    request_id: string | null;
    created_at: string;
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface Paginated<T> {
    data: T[];
    links: PaginationLink[];
    total: number;
    from: number | null;
    to: number | null;
}

interface Filters {
    search: string;
    action: string;
    section: string;
    from: string | null;
    to: string | null;
}

interface AuditStats {
    today: number;
    settingsChanges: number;
    failovers: number;
    failures: number;
}

interface AuditPageProps {
    systemStatus: { label: string; tone: StatusTone };
    stats: AuditStats;
    logs: Paginated<AuditEntry>;
    filters: Filters;
    availableActions: string[];
    availableSections: string[];
}

function formatDate(value: string): string {
    return new Date(value).toLocaleString('ar', { dateStyle: 'medium', timeStyle: 'short' });
}

/** Audited values are arbitrary JSON, so every shape has to render readably. */
function stringifyValue(value: unknown): string {
    if (value === null || value === undefined) {
        return '—';
    }
    if (typeof value === 'string') {
        return value;
    }
    if (typeof value === 'number' || typeof value === 'boolean') {
        return String(value);
    }
    return JSON.stringify(value);
}

function ValuePreview({ values }: { values: Record<string, unknown> | null }) {
    if (values === null || Object.keys(values).length === 0) {
        return <span className="text-fg-subtle">—</span>;
    }

    return (
        <ul className="space-y-0.5">
            {Object.entries(values).map(([key, value]) => (
                <li key={key} className="text-caption">
                    <span className="text-fg-subtle">{key}:</span>{' '}
                    <span className="text-fg-default">{stringifyValue(value)}</span>
                </li>
            ))}
        </ul>
    );
}

function categoryClass(tone: string): string {
    return CATEGORY_TONES[tone] ?? 'bg-neutral-soft text-neutral';
}

const CATEGORY_TONES: Record<string, string> = {
    accent: 'bg-accent-soft text-accent',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
    info: 'bg-info-soft text-info',
    neutral: 'bg-neutral-soft text-neutral',
};

export default function Index({
    systemStatus,
    stats,
    logs,
    filters,
    availableActions,
    availableSections,
}: AuditPageProps) {
    const [search, setSearch] = useState(filters.search);
    const [action, setAction] = useState(filters.action);
    const [section, setSection] = useState(filters.section);
    const [from, setFrom] = useState(filters.from ?? '');
    const [to, setTo] = useState(filters.to ?? '');
    const [expanded, setExpanded] = useState<number | null>(null);

    const apply = () => {
        router.get(
            '/audit',
            { search, action, section, from, to },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const reset = () => {
        setSearch('');
        setAction('');
        setSection('');
        setFrom('');
        setTo('');
        router.get('/audit', {}, { preserveState: true, replace: true });
    };

    return (
        <AdminLayout>
            <Head title="سجل التشغيل والتدقيق" />

            <PageHeader
                title="سجل التشغيل والتدقيق"
                description="تتبع تغييرات الإعدادات وعمليات المزودين والتنبيهات والمعرفة"
                systemStatus={systemStatus}
                period="آخر 7 أيام"
            />

            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    label="أحداث اليوم"
                    value={stats.today.toLocaleString('ar')}
                    icon={ScrollText}
                    tone="accent"
                />
                <StatCard
                    label="تغييرات إعدادات"
                    value={stats.settingsChanges.toLocaleString('ar')}
                    icon={SlidersHorizontal}
                    tone="info"
                />
                <StatCard
                    label="تحويلات مزود"
                    value={stats.failovers.toLocaleString('ar')}
                    icon={ArrowLeftRight}
                    tone="warning"
                />
                <StatCard
                    label="محاولات فاشلة"
                    value={stats.failures.toLocaleString('ar')}
                    icon={AlertTriangle}
                    tone={stats.failures > 0 ? 'danger' : 'success'}
                />
            </div>

            <Card>
                <CardHeader
                    title="الفلاتر"
                    description="السجل غير قابل للتعديل أو الحذف — محمي على مستوى قاعدة البيانات."
                />
                <CardBody>
                    <form
                        className="grid gap-4 sm:grid-cols-2 lg:grid-cols-5"
                        onSubmit={(event) => {
                            event.preventDefault();
                            apply();
                        }}
                    >
                        <Input
                            label="بحث"
                            placeholder="إجراء، مستخدم، سبب…"
                            value={search}
                            onChange={(event) => {
                                setSearch(event.target.value);
                            }}
                        />
                        <Select
                            label="نوع العملية"
                            placeholder="الكل"
                            options={availableActions.map((value) => ({ value, label: value }))}
                            value={action}
                            onChange={(event) => {
                                setAction(event.target.value);
                            }}
                        />
                        <Select
                            label="القسم"
                            placeholder="الكل"
                            options={availableSections.map((value) => ({ value, label: value }))}
                            value={section}
                            onChange={(event) => {
                                setSection(event.target.value);
                            }}
                        />
                        <Input
                            label="من تاريخ"
                            type="date"
                            value={from}
                            onChange={(event) => {
                                setFrom(event.target.value);
                            }}
                        />
                        <Input
                            label="إلى تاريخ"
                            type="date"
                            value={to}
                            onChange={(event) => {
                                setTo(event.target.value);
                            }}
                        />

                        <div className="flex items-end gap-2 sm:col-span-2 lg:col-span-5">
                            <Button type="submit">
                                <Search aria-hidden className="size-4" />
                                تطبيق
                            </Button>
                            <Button type="button" variant="ghost" onClick={reset}>
                                إعادة تعيين
                            </Button>
                        </div>
                    </form>
                </CardBody>
            </Card>

            <Card>
                <CardHeader
                    title="السجلات"
                    description={
                        logs.total === 0
                            ? 'لا توجد سجلات مطابقة'
                            : `عرض ${String(logs.from ?? 0)}–${String(logs.to ?? 0)} من ${String(logs.total)}`
                    }
                />
                <CardBody className="p-0">
                    {logs.data.length === 0 ? (
                        <EmptyState
                            icon={ScrollText}
                            title="لا توجد سجلات"
                            description="سيظهر هنا كل تغيير حساس: تسجيل الدخول، تعديل المزودين، حفظ المفاتيح، والتحويل بين المزودين."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full text-body">
                                <thead className="border-b border-border-default bg-surface-sunken">
                                    <tr className="text-fg-muted">
                                        <th className="px-4 py-3 text-start font-medium">
                                            العملية
                                        </th>
                                        <th className="px-4 py-3 text-start font-medium">النوع</th>
                                        <th className="px-4 py-3 text-start font-medium">الموظف</th>
                                        <th className="px-4 py-3 text-start font-medium">الوقت</th>
                                        <th className="px-4 py-3 text-start font-medium">
                                            التفاصيل
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {logs.data.map((entry) => (
                                        <tr
                                            key={entry.id}
                                            className="border-b border-border-default align-top last:border-0"
                                        >
                                            <td className="px-4 py-3">
                                                <span
                                                    className="font-mono text-caption text-fg-default"
                                                    dir="ltr"
                                                >
                                                    {entry.action}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <span
                                                    className={`inline-flex rounded-pill px-2.5 py-1 text-caption font-bold ${categoryClass(entry.category_tone)}`}
                                                >
                                                    {entry.category}
                                                </span>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="text-fg-default">{entry.actor}</div>
                                                {entry.actor_role !== null ? (
                                                    <div className="text-caption text-fg-subtle">
                                                        {entry.actor_role}
                                                    </div>
                                                ) : null}
                                            </td>
                                            <td className="px-4 py-3 whitespace-nowrap text-fg-muted">
                                                {formatDate(entry.created_at)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setExpanded(
                                                            expanded === entry.id ? null : entry.id,
                                                        );
                                                    }}
                                                    className="text-caption font-medium text-brand-600 hover:underline dark:text-brand-300"
                                                >
                                                    {expanded === entry.id ? 'إخفاء' : 'عرض'}
                                                </button>

                                                {expanded === entry.id ? (
                                                    <div className="mt-2 space-y-2 rounded-control bg-surface-sunken p-3">
                                                        <div>
                                                            <p className="text-caption font-semibold text-fg-default">
                                                                القيمة السابقة
                                                            </p>
                                                            <ValuePreview
                                                                values={entry.old_values}
                                                            />
                                                        </div>
                                                        <div>
                                                            <p className="text-caption font-semibold text-fg-default">
                                                                القيمة الجديدة
                                                            </p>
                                                            <ValuePreview
                                                                values={entry.new_values}
                                                            />
                                                        </div>
                                                        {entry.reason !== null ? (
                                                            <div>
                                                                <p className="text-caption font-semibold text-fg-default">
                                                                    السبب
                                                                </p>
                                                                <p className="text-caption text-fg-muted">
                                                                    {entry.reason}
                                                                </p>
                                                            </div>
                                                        ) : null}
                                                        <div
                                                            className="font-mono text-micro text-fg-subtle"
                                                            dir="ltr"
                                                        >
                                                            {entry.ip_address ?? '—'} ·{' '}
                                                            {entry.request_id ?? '—'}
                                                        </div>
                                                    </div>
                                                ) : null}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </CardBody>
            </Card>

            {logs.links.length > 3 ? (
                <nav aria-label="ترقيم الصفحات" className="flex flex-wrap justify-center gap-1">
                    {logs.links.map((link) =>
                        link.url === null ? (
                            <span
                                key={link.label}
                                className="rounded-control px-3 py-1.5 text-body text-fg-subtle"
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ) : (
                            <Link
                                key={link.label}
                                href={link.url}
                                preserveScroll
                                aria-current={link.active ? 'page' : undefined}
                                className={
                                    link.active
                                        ? 'rounded-control bg-brand-600 px-3 py-1.5 text-body text-white'
                                        : 'rounded-control px-3 py-1.5 text-body text-fg-muted hover:bg-surface-sunken'
                                }
                                dangerouslySetInnerHTML={{ __html: link.label }}
                            />
                        ),
                    )}
                </nav>
            ) : null}
        </AdminLayout>
    );
}
