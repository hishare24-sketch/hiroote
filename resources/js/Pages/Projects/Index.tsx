import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import {
    KeyRound,
    LayoutGrid,
    Plus,
    Settings2,
    Shield,
    UserMinus,
    UserPlus,
    X,
} from 'lucide-react';
import type { StatusTone } from '@/types';
import type { SelectOptionPayload } from '@/types/assistants';
import type { ApiKeyRow } from './ApiKeysDialog';
import { ApiKeysDialog } from './ApiKeysDialog';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Alert } from '@/Components/ui/Alert';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Select } from '@/Components/ui/Select';
import { Switch } from '@/Components/ui/Switch';
import { formatMoney, formatNumber } from '@/lib/format';
import { cn } from '@/lib/cn';

interface Member {
    id: number;
    name: string;
    email: string;
    role: string;
    is_platform_admin: boolean;
}

interface ProjectRow {
    id: number;
    name: string;
    slug: string;
    description: string | null;
    api_base_url: string | null;
    is_active: boolean;
    sort_order: number;
    conversations: number;
    cost: number;
    sections: number;
    members: Member[];
    api_keys: ApiKeyRow[];
}

interface Props {
    systemStatus: { label: string; tone: StatusTone };
    isPlatformAdmin: boolean;
    currentProjectId: number | null;
    projects: ProjectRow[];
    roleOptions: SelectOptionPayload[];
    assignableUsers: SelectOptionPayload[];
}

/** شاشة إدارة المشاريع والعضوية — ADR-0003. */
export default function ProjectsIndex({
    systemStatus,
    isPlatformAdmin,
    currentProjectId,
    projects,
    roleOptions,
    assignableUsers,
}: Props) {
    const [creating, setCreating] = useState(false);
    const [settingsFor, setSettingsFor] = useState<ProjectRow | null>(null);
    const [membersFor, setMembersFor] = useState<ProjectRow | null>(null);
    const [keysFor, setKeysFor] = useState<ProjectRow | null>(null);

    return (
        <AdminLayout>
            <Head title="المشاريع" />

            <PageHeader
                title="المشاريع"
                description="كل مشروع لوحة مستقلة بأقسامه وسلوك مساعده وميزانيته"
                systemStatus={systemStatus}
                actions={
                    isPlatformAdmin ? (
                        <Button
                            size="sm"
                            onClick={() => {
                                setCreating(true);
                            }}
                        >
                            <Plus aria-hidden className="size-4" />
                            مشروع جديد
                        </Button>
                    ) : undefined
                }
            />

            {isPlatformAdmin ? null : (
                <Alert tone="neutral" title="مشاريعك وحدها">
                    ترى هنا المشاريع التي تملك عضوية فيها. إنشاء مشروع جديد صلاحية مدير المنصة.
                </Alert>
            )}

            <section aria-label="المشاريع" className="grid gap-4 lg:grid-cols-2">
                {projects.map((project) => (
                    <Card key={project.id} className={project.is_active ? '' : 'opacity-70'}>
                        <CardBody className="flex flex-col gap-4">
                            <div className="flex items-start justify-between gap-3">
                                <div className="flex min-w-0 items-start gap-3">
                                    <span
                                        aria-hidden
                                        className="flex size-10 shrink-0 items-center justify-center rounded-control bg-accent-soft text-accent"
                                    >
                                        <LayoutGrid className="size-5" />
                                    </span>
                                    <div className="min-w-0">
                                        <p className="flex flex-wrap items-center gap-2 text-title font-extrabold text-fg-default">
                                            {project.name}
                                            {project.id === currentProjectId ? (
                                                <Badge tone="accent">النشط الآن</Badge>
                                            ) : null}
                                            {project.is_active ? null : (
                                                <Badge tone="neutral">موقوف</Badge>
                                            )}
                                        </p>
                                        <p className="mt-0.5 text-caption text-fg-muted">
                                            {project.description ?? 'بلا وصف'}
                                        </p>
                                        <p
                                            dir="ltr"
                                            className="mt-1 truncate text-start text-caption text-fg-subtle"
                                        >
                                            {project.api_base_url ?? '— لم يُربط بـ API بعد'}
                                        </p>
                                    </div>
                                </div>

                                {project.id === currentProjectId ? null : (
                                    <Button
                                        variant="secondary"
                                        size="sm"
                                        onClick={() => {
                                            router.post(
                                                `/projects/${String(project.id)}/switch`,
                                                {},
                                            );
                                        }}
                                    >
                                        فتح
                                    </Button>
                                )}
                            </div>

                            <dl className="grid grid-cols-3 gap-3 border-y border-border-default py-3 text-caption">
                                <Stat label="الأقسام" value={formatNumber(project.sections)} />
                                <Stat
                                    label="المحادثات"
                                    value={formatNumber(project.conversations)}
                                />
                                <Stat label="التكلفة" value={formatMoney(project.cost, 'SAR', 0)} />
                            </dl>

                            <div className="flex flex-wrap items-center gap-2">
                                <span className="text-caption text-fg-muted">
                                    {formatNumber(project.members.length)} عضوًا
                                </span>
                                <span className="flex flex-wrap gap-1">
                                    {project.members.slice(0, 4).map((member) => (
                                        <span
                                            key={member.id}
                                            title={`${member.name} — ${member.email}`}
                                            className="flex size-6 items-center justify-center rounded-full bg-surface-sunken text-micro font-bold text-fg-muted"
                                        >
                                            {member.name.charAt(0)}
                                        </span>
                                    ))}
                                    {project.members.length > 4 ? (
                                        <span className="flex size-6 items-center justify-center rounded-full bg-surface-sunken text-micro font-bold text-fg-subtle">
                                            +{project.members.length - 4}
                                        </span>
                                    ) : null}
                                </span>

                                <div className="ms-auto flex gap-1.5">
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setMembersFor(project);
                                        }}
                                    >
                                        <UserPlus aria-hidden className="size-3.5" />
                                        الفريق
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setKeysFor(project);
                                        }}
                                    >
                                        <KeyRound aria-hidden className="size-3.5" />
                                        المفاتيح
                                    </Button>
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            setSettingsFor(project);
                                        }}
                                    >
                                        <Settings2 aria-hidden className="size-3.5" />
                                        الإعدادات
                                    </Button>
                                </div>
                            </div>
                        </CardBody>
                    </Card>
                ))}
            </section>

            {creating ? (
                <CreateDialog
                    onClose={() => {
                        setCreating(false);
                    }}
                />
            ) : null}

            {keysFor === null ? null : (
                <ApiKeysDialog
                    project={keysFor}
                    keys={projects.find((row) => row.id === keysFor.id)?.api_keys ?? []}
                    onClose={() => {
                        setKeysFor(null);
                    }}
                />
            )}

            {settingsFor === null ? null : (
                <SettingsDialog
                    project={settingsFor}
                    onClose={() => {
                        setSettingsFor(null);
                    }}
                />
            )}

            {membersFor === null ? null : (
                <MembersDialog
                    project={membersFor}
                    roleOptions={roleOptions}
                    assignableUsers={assignableUsers}
                    onClose={() => {
                        setMembersFor(null);
                    }}
                />
            )}
        </AdminLayout>
    );
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <div>
            <dt className="text-fg-subtle">{label}</dt>
            <dd className="mt-0.5 font-bold text-fg-default tabular-nums">{value}</dd>
        </div>
    );
}

function Dialog({
    title,
    onClose,
    children,
}: {
    title: string;
    onClose: () => void;
    children: React.ReactNode;
}) {
    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label={title}
            className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-fg-default/40 p-4 py-12"
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
                <CardBody>{children}</CardBody>
            </Card>
        </div>
    );
}

function CreateDialog({ onClose }: { onClose: () => void }) {
    const form = useForm({ name: '', description: '', api_base_url: '' });

    return (
        <Dialog title="مشروع جديد" onClose={onClose}>
            <form
                className="flex flex-col gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.post('/projects', { preserveScroll: true, onSuccess: onClose });
                }}
            >
                <Input
                    label="اسم المشروع"
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
                    onChange={(event) => {
                        form.setData('description', event.target.value);
                    }}
                />
                <Input
                    label="عنوان API"
                    dir="ltr"
                    placeholder="https://api.example.com/v1"
                    hint="الربط عبر REST فقط — لا اتصال بقاعدة بيانات المشروع."
                    value={form.data.api_base_url}
                    error={form.errors.api_base_url}
                    onChange={(event) => {
                        form.setData('api_base_url', event.target.value);
                    }}
                />

                <p className="text-caption text-fg-muted">
                    يُجهَّز المشروع بأربعة مستويات مساعد ووظائفه الأربع عشرة، ثم تُضاف أقسامه من
                    شاشة التكامل.
                </p>

                <div className="flex gap-2">
                    <Button type="submit" loading={form.processing}>
                        إنشاء
                    </Button>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        إلغاء
                    </Button>
                </div>
            </form>
        </Dialog>
    );
}

function SettingsDialog({ project, onClose }: { project: ProjectRow; onClose: () => void }) {
    const form = useForm({
        name: project.name,
        description: project.description ?? '',
        api_base_url: project.api_base_url ?? '',
        is_active: project.is_active,
        sort_order: project.sort_order,
    });

    return (
        <Dialog title={`إعدادات: ${project.name}`} onClose={onClose}>
            <form
                className="flex flex-col gap-4"
                onSubmit={(event) => {
                    event.preventDefault();
                    form.put(`/projects/${String(project.id)}`, {
                        preserveScroll: true,
                        onSuccess: onClose,
                    });
                }}
            >
                <Input
                    label="اسم المشروع"
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
                    onChange={(event) => {
                        form.setData('description', event.target.value);
                    }}
                />
                <Input
                    label="عنوان API"
                    dir="ltr"
                    value={form.data.api_base_url}
                    error={form.errors.api_base_url}
                    onChange={(event) => {
                        form.setData('api_base_url', event.target.value);
                    }}
                />
                <Input
                    label="الترتيب في المبدّل"
                    type="number"
                    min={0}
                    max={999}
                    value={String(form.data.sort_order)}
                    error={form.errors.sort_order}
                    onChange={(event) => {
                        form.setData('sort_order', Number(event.target.value));
                    }}
                />

                <div className="flex items-center justify-between gap-4 rounded-control border border-border-default px-3 py-2.5">
                    <span className="min-w-0">
                        <span className="block text-body text-fg-default">مشروع مفعّل</span>
                        <span className="block text-caption text-fg-subtle">
                            إيقافه يخفيه من المبدّل ويحتفظ ببياناته كاملة.
                        </span>
                    </span>
                    <Switch
                        aria-label="مشروع مفعّل"
                        checked={form.data.is_active}
                        onChange={(value) => {
                            form.setData('is_active', value);
                        }}
                    />
                </div>

                <div className="flex gap-2">
                    <Button type="submit" loading={form.processing}>
                        حفظ
                    </Button>
                    <Button type="button" variant="secondary" onClick={onClose}>
                        إلغاء
                    </Button>
                </div>
            </form>
        </Dialog>
    );
}

function MembersDialog({
    project,
    roleOptions,
    assignableUsers,
    onClose,
}: {
    project: ProjectRow;
    roleOptions: SelectOptionPayload[];
    assignableUsers: SelectOptionPayload[];
    onClose: () => void;
}) {
    const form = useForm({ user_id: '', role: roleOptions[0]?.value ?? '' });

    // رسالة سحب العضوية تخص زرًّا خارج هذا النموذج، فتُحفظ هنا بدل تعليقها على
    // حقلٍ لا وجود له فيه.
    const [removalError, setRemovalError] = useState<string | null>(null);

    const roleLabel = (value: string): string =>
        roleOptions.find((option) => option.value === value)?.label ?? value;

    return (
        <Dialog title={`فريق: ${project.name}`} onClose={onClose}>
            <div className="flex flex-col gap-5">
                {removalError === null ? null : (
                    <Alert tone="danger" title="تعذّر سحب العضوية">
                        {removalError}
                    </Alert>
                )}

                <ul className="flex flex-col divide-y divide-border-default">
                    {project.members.length === 0 ? (
                        <li className="py-3 text-caption text-fg-muted">
                            لا أعضاء بعد — مديرو المنصة يرون المشروع بحكم عضويتهم الضمنية.
                        </li>
                    ) : null}

                    {project.members.map((member) => (
                        <li
                            key={member.id}
                            className="flex items-center justify-between gap-3 py-2.5 first:pt-0"
                        >
                            <span className="min-w-0">
                                <span className="block truncate text-body font-semibold text-fg-default">
                                    {member.name}
                                </span>
                                <span
                                    dir="ltr"
                                    className="block truncate text-start text-caption text-fg-subtle"
                                >
                                    {member.email}
                                </span>
                            </span>

                            <span className="flex shrink-0 items-center gap-2">
                                {member.is_platform_admin ? (
                                    <Badge tone="accent">
                                        <Shield aria-hidden className="size-3" />
                                        مدير المنصة
                                    </Badge>
                                ) : (
                                    <Badge tone="neutral">{roleLabel(member.role)}</Badge>
                                )}

                                <button
                                    type="button"
                                    aria-label={`سحب عضوية ${member.name}`}
                                    onClick={() => {
                                        setRemovalError(null);
                                        router.delete(
                                            `/projects/${String(project.id)}/members/${String(member.id)}`,
                                            {
                                                preserveScroll: true,
                                                onError: (errors) => {
                                                    setRemovalError(
                                                        errors.member ?? 'تعذّر سحب العضوية.',
                                                    );
                                                },
                                            },
                                        );
                                    }}
                                    className="rounded-control p-1 text-fg-subtle hover:bg-danger-soft hover:text-danger"
                                >
                                    <UserMinus aria-hidden className="size-4" />
                                </button>
                            </span>
                        </li>
                    ))}
                </ul>

                <form
                    className={cn('flex flex-col gap-3 border-t border-border-default pt-4')}
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/projects/${String(project.id)}/members`, {
                            preserveScroll: true,
                            onSuccess: () => {
                                form.setData('user_id', '');
                            },
                        });
                    }}
                >
                    <Select
                        label="أضف عضوًا"
                        options={assignableUsers}
                        placeholder="اختر مستخدمًا"
                        value={form.data.user_id}
                        error={form.errors.user_id}
                        onChange={(event) => {
                            form.setData('user_id', event.target.value);
                        }}
                    />
                    <Select
                        label="دوره في هذا المشروع"
                        options={roleOptions}
                        value={form.data.role}
                        error={form.errors.role}
                        onChange={(event) => {
                            form.setData('role', event.target.value);
                        }}
                    />

                    <p className="text-caption text-fg-subtle">
                        الدور يخص هذا المشروع وحده؛ الشخص نفسه قد يحمل دورًا آخر في مشروع آخر.
                    </p>

                    <Button
                        type="submit"
                        loading={form.processing}
                        disabled={form.data.user_id === ''}
                        className="self-start"
                    >
                        <UserPlus aria-hidden className="size-4" />
                        إضافة
                    </Button>
                </form>
            </div>
        </Dialog>
    );
}
