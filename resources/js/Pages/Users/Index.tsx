import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Check, Minus, Plus, ShieldCheck, UserCog, UserMinus, UserPlus, X } from 'lucide-react';
import type { StatusTone } from '@/types';
import type { SelectOptionPayload } from '@/types/assistants';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Alert } from '@/Components/ui/Alert';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Select } from '@/Components/ui/Select';
import { Switch } from '@/Components/ui/Switch';
import { formatNumber, formatRelative } from '@/lib/format';

interface Membership {
    project_id: number;
    project: string;
    role: string;
}

interface UserRow {
    id: number;
    name: string;
    email: string;
    role: string;
    role_label: string;
    is_active: boolean;
    is_platform_admin: boolean;
    last_login_at: string | null;
    memberships: Membership[];
}

interface MatrixRow {
    permission: string;
    label: string;
    group: string;
    roles: Record<string, boolean>;
}

interface Props {
    systemStatus: { label: string; tone: StatusTone };
    canManage: boolean;
    actorId: number;
    users: UserRow[];
    projects: SelectOptionPayload[];
    roles: SelectOptionPayload[];
    matrix: MatrixRow[];
}

/**
 * المستخدمون والصلاحيات — آخر شاشة إدارية.
 *
 * الدور في صفّ المستخدم افتراضٌ للعضوية القادمة لا صلاحية نافذة، والشاشة تقولها
 * صراحةً: من يمنح دورًا ثم ينتظر أثرًا لا يأتي يظنّ البوابة معطّلة.
 */
export default function UsersIndex({
    systemStatus,
    canManage,
    actorId,
    users,
    projects,
    roles,
    matrix,
}: Props) {
    const [creating, setCreating] = useState(false);
    const [editing, setEditing] = useState<UserRow | null>(null);
    const [membersFor, setMembersFor] = useState<UserRow | null>(null);

    const roleLabel = (value: string) => roles.find((r) => r.value === value)?.label ?? value;

    return (
        <AdminLayout>
            <Head title="المستخدمون والصلاحيات" />

            <PageHeader
                title="المستخدمون والصلاحيات"
                description="حسابات المشغّلين وأدوارهم لكل مشروع"
                systemStatus={systemStatus}
                actions={
                    canManage ? (
                        <Button
                            size="sm"
                            onClick={() => {
                                setCreating(true);
                            }}
                        >
                            <UserPlus aria-hidden className="size-4" />
                            حساب جديد
                        </Button>
                    ) : null
                }
            />

            <Alert tone="neutral" title="الدور يُحلّ لكل مشروع">
                الدور في صفّ الحساب هو افتراضه عند إضافته لمشروع، لا صلاحيته النافذة. حسابٌ بدور
                «مدير النظام» بلا عضوية لا يرى شيئًا — والعضويات مذكورة تحت كل اسم.
            </Alert>

            <Card>
                <CardHeader
                    title={`الحسابات (${formatNumber(users.length)})`}
                    description="التعطيل يسحب الصلاحيات ويُبقي التاريخ — لا حذف"
                />
                <CardBody className="flex flex-col gap-2">
                    {users.map((user) => (
                        <article
                            key={user.id}
                            className="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-card border border-border-default p-3"
                        >
                            <div className="min-w-48 flex-1">
                                <p className="flex flex-wrap items-center gap-2 text-body font-bold text-fg-default">
                                    {user.name}
                                    {user.is_platform_admin ? (
                                        <Badge tone="accent">مدير منصة</Badge>
                                    ) : null}
                                    {user.is_active ? null : <Badge tone="neutral">معطَّل</Badge>}
                                </p>
                                <p dir="ltr" className="text-start text-caption text-fg-muted">
                                    {user.email}
                                </p>
                            </div>

                            <span className="text-caption text-fg-muted">
                                الافتراضي:{' '}
                                <span className="text-fg-default">{user.role_label}</span>
                            </span>

                            <span className="text-caption text-fg-muted">
                                آخر دخول:{' '}
                                <span className="text-fg-default">
                                    {/* الرقم الذي لا يُقاس ليس صفرًا. */}
                                    {user.last_login_at === null
                                        ? 'لم يدخل بعد'
                                        : formatRelative(user.last_login_at)}
                                </span>
                            </span>

                            <div className="flex w-full flex-wrap items-center gap-1.5">
                                {user.memberships.length === 0 ? (
                                    <span className="text-caption text-fg-subtle">
                                        بلا عضوية في أي مشروع — لا صلاحية تشغيلية
                                    </span>
                                ) : (
                                    user.memberships.map((membership) => (
                                        <Badge key={membership.project_id} tone="neutral">
                                            {membership.project} · {roleLabel(membership.role)}
                                        </Badge>
                                    ))
                                )}
                            </div>

                            {canManage ? (
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => {
                                            setEditing(user);
                                        }}
                                    >
                                        <UserCog aria-hidden className="size-4" />
                                        تعديل
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => {
                                            setMembersFor(user);
                                        }}
                                    >
                                        <ShieldCheck aria-hidden className="size-4" />
                                        العضويات
                                    </Button>
                                    <span className="flex items-center gap-2 text-caption text-fg-muted">
                                        {user.is_active ? 'فعّال' : 'معطَّل'}
                                        <Switch
                                            aria-label={`تفعيل حساب ${user.name}`}
                                            checked={user.is_active}
                                            // من يعطّل نفسه يخرج من اللوحة ولا يملك إعادة فتحها.
                                            disabled={user.id === actorId}
                                            onChange={(next) => {
                                                router.post(
                                                    `/users/${String(user.id)}/active`,
                                                    { is_active: next },
                                                    { preserveScroll: true },
                                                );
                                            }}
                                        />
                                    </span>
                                </div>
                            ) : null}
                        </article>
                    ))}
                </CardBody>
            </Card>

            <MatrixCard matrix={matrix} roles={roles} />

            {creating ? (
                <UserDialog
                    user={null}
                    roles={roles}
                    onClose={() => {
                        setCreating(false);
                    }}
                />
            ) : null}

            {editing === null ? null : (
                <UserDialog
                    user={editing}
                    roles={roles}
                    onClose={() => {
                        setEditing(null);
                    }}
                />
            )}

            {membersFor === null ? null : (
                <MembershipsDialog
                    user={membersFor}
                    projects={projects}
                    roles={roles}
                    onClose={() => {
                        setMembersFor(null);
                    }}
                />
            )}
        </AdminLayout>
    );
}

/**
 * مصفوفة الصلاحيات — مقروءة من `Role::permissions()` لحظةَ العرض.
 *
 * قراءةٌ فقط عمدًا: المنح يُعدَّل في الكود فيمرّ بمراجعة، لا يُنقر في شاشة.
 */
function MatrixCard({ matrix, roles }: { matrix: MatrixRow[]; roles: SelectOptionPayload[] }) {
    const groups = matrix.reduce<Record<string, MatrixRow[]>>((carry, row) => {
        carry[row.group] = [...(carry[row.group] ?? []), row];

        return carry;
    }, {});

    return (
        <Card>
            <CardHeader
                title="مصفوفة الصلاحيات"
                description="مقروءة من مصدرها الوحيد لحظةَ العرض — تُعدَّل في الكود لا من هنا"
            />
            <CardBody className="overflow-x-auto">
                <table className="w-full text-caption">
                    <thead>
                        <tr className="border-b border-border-default text-fg-muted">
                            <th className="p-2 text-start font-medium">الصلاحية</th>
                            {roles.map((role) => (
                                <th key={role.value} className="p-2 text-center font-medium">
                                    {role.label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {Object.entries(groups).map(([group, rows]) => (
                            <>
                                <tr key={group} className="bg-surface-sunken">
                                    <td
                                        colSpan={roles.length + 1}
                                        className="p-2 text-caption font-bold text-fg-muted"
                                    >
                                        {group}
                                    </td>
                                </tr>
                                {rows.map((row) => (
                                    <tr
                                        key={row.permission}
                                        className="border-b border-border-default/60"
                                    >
                                        <td className="p-2 text-fg-default">{row.label}</td>
                                        {roles.map((role) => (
                                            <td key={role.value} className="p-2 text-center">
                                                {row.roles[role.value] === true ? (
                                                    <Check
                                                        aria-label="يملكها"
                                                        className="mx-auto size-4 text-success"
                                                    />
                                                ) : (
                                                    <Minus
                                                        aria-label="لا يملكها"
                                                        className="mx-auto size-4 text-fg-subtle"
                                                    />
                                                )}
                                            </td>
                                        ))}
                                    </tr>
                                ))}
                            </>
                        ))}
                    </tbody>
                </table>
            </CardBody>
        </Card>
    );
}

function UserDialog({
    user,
    roles,
    onClose,
}: {
    user: UserRow | null;
    roles: SelectOptionPayload[];
    onClose: () => void;
}) {
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        role: user?.role ?? roles[0]?.value ?? '',
        password: '',
        is_platform_admin: user?.is_platform_admin ?? false,
    });

    return (
        <Dialog title={user === null ? 'حساب جديد' : `تعديل ${user.name}`} onClose={onClose}>
            <form
                className="flex flex-col gap-3"
                onSubmit={(event) => {
                    event.preventDefault();

                    if (user === null) {
                        form.post('/users', { preserveScroll: true, onSuccess: onClose });

                        return;
                    }

                    form.put(`/users/${String(user.id)}`, {
                        preserveScroll: true,
                        onSuccess: onClose,
                    });
                }}
            >
                <Input
                    label="الاسم"
                    required
                    value={form.data.name}
                    error={form.errors.name}
                    onChange={(event) => {
                        form.setData('name', event.target.value);
                    }}
                />

                <Input
                    label="البريد"
                    type="email"
                    dir="ltr"
                    required
                    value={form.data.email}
                    error={form.errors.email}
                    onChange={(event) => {
                        form.setData('email', event.target.value);
                    }}
                />

                <Select
                    label="الدور الافتراضي"
                    options={roles}
                    value={form.data.role}
                    error={form.errors.role}
                    onChange={(event) => {
                        form.setData('role', event.target.value);
                    }}
                />
                <p className="-mt-2 text-micro text-fg-subtle">
                    يُستعمل عند إضافة الحساب إلى مشروع — ولا يمنح صلاحية بذاته.
                </p>

                <Input
                    label="كلمة المرور"
                    type="password"
                    required={user === null}
                    value={form.data.password}
                    error={form.errors.password}
                    hint={
                        user === null
                            ? 'ثمانية أحرف فأكثر. تُسلَّم لصاحبها يدًا بيد — لا بريد يرسلها.'
                            : 'اتركها فارغة للإبقاء على الحالية.'
                    }
                    onChange={(event) => {
                        form.setData('password', event.target.value);
                    }}
                />

                <div className="flex items-start justify-between gap-3 rounded-control border border-border-default p-3">
                    <span className="flex flex-col gap-0.5">
                        <span className="text-caption font-bold text-fg-default">مدير منصة</span>
                        <span className="text-micro text-fg-subtle">
                            عضوية ضمنية بدور مدير النظام في كل مشروع — لا استثناء من البوابة.
                        </span>
                    </span>
                    <Switch
                        aria-label="مدير منصة"
                        checked={form.data.is_platform_admin}
                        onChange={(next) => {
                            form.setData('is_platform_admin', next);
                        }}
                    />
                </div>

                <div className="flex justify-end gap-2 pt-1">
                    <Button type="button" variant="ghost" onClick={onClose}>
                        إلغاء
                    </Button>
                    <Button type="submit" disabled={form.processing}>
                        حفظ
                    </Button>
                </div>
            </form>
        </Dialog>
    );
}

function MembershipsDialog({
    user,
    projects,
    roles,
    onClose,
}: {
    user: UserRow;
    projects: SelectOptionPayload[];
    roles: SelectOptionPayload[];
    onClose: () => void;
}) {
    const form = useForm({
        project_id: projects[0]?.value ?? '',
        role: roles[0]?.value ?? '',
    });

    return (
        <Dialog title={`عضويات ${user.name}`} onClose={onClose}>
            <div className="flex flex-col gap-3">
                {user.memberships.length === 0 ? (
                    <p className="text-caption text-fg-subtle">
                        بلا عضوية — لا صلاحية تشغيلية له في أي مشروع.
                    </p>
                ) : (
                    <ul className="flex flex-col gap-1.5">
                        {user.memberships.map((membership) => (
                            <li
                                key={membership.project_id}
                                className="flex items-center justify-between gap-2 rounded-control border border-border-default px-3 py-2"
                            >
                                <span className="text-caption text-fg-default">
                                    {membership.project} ·{' '}
                                    {roles.find((r) => r.value === membership.role)?.label ??
                                        membership.role}
                                </span>
                                <Button
                                    size="sm"
                                    variant="ghost"
                                    onClick={() => {
                                        router.delete(`/users/${String(user.id)}/memberships`, {
                                            data: { project_id: membership.project_id },
                                            preserveScroll: true,
                                        });
                                    }}
                                >
                                    <UserMinus aria-hidden className="size-4" />
                                    سحب
                                </Button>
                            </li>
                        ))}
                    </ul>
                )}

                <form
                    className="flex flex-col gap-3 border-t border-border-default pt-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/users/${String(user.id)}/memberships`, {
                            preserveScroll: true,
                        });
                    }}
                >
                    <Select
                        label="المشروع"
                        options={projects}
                        value={form.data.project_id}
                        error={form.errors.project_id}
                        onChange={(event) => {
                            form.setData('project_id', event.target.value);
                        }}
                    />
                    <Select
                        label="الدور فيه"
                        options={roles}
                        value={form.data.role}
                        error={form.errors.role}
                        onChange={(event) => {
                            form.setData('role', event.target.value);
                        }}
                    />
                    <Button type="submit" disabled={form.processing}>
                        <Plus aria-hidden className="size-4" />
                        أضف أو غيّر الدور
                    </Button>
                </form>
            </div>
        </Dialog>
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
        <div className="fixed inset-0 z-40 flex items-start justify-center overflow-y-auto bg-fg-default/20 p-4 backdrop-blur-sm">
            <div className="w-full max-w-lg rounded-card border border-border-default bg-surface-raised shadow-lg">
                <div className="flex items-center justify-between border-b border-border-default p-4">
                    <h2 className="text-title font-bold text-fg-default">{title}</h2>
                    <button
                        type="button"
                        aria-label="إغلاق"
                        className="rounded-control p-1 text-fg-muted hover:bg-surface-sunken"
                        onClick={onClose}
                    >
                        <X aria-hidden className="size-4" />
                    </button>
                </div>
                <div className="p-4">{children}</div>
            </div>
        </div>
    );
}
