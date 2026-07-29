import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { Activity, LogOut, Menu, Moon, Sun, X } from 'lucide-react';
import type { Permission, SharedProps } from '@/types';
import { usePermissions } from '@/Hooks/usePermissions';
import { useTheme } from '@/Hooks/useTheme';
import { Alert } from '@/Components/ui/Alert';
import { ProjectSwitcher } from '@/Components/ui/ProjectSwitcher';
import { cn } from '@/lib/cn';

interface NavItem {
    label: string;
    href: string;
    permission: Permission;
    /** Screens whose real implementation lands in a later phase (وثيقة 01 §9). */
    planned?: boolean;
}

/** هيكل التنقل الرئيسي — وثيقة التصميم §3. */
const NAV_ITEMS: NavItem[] = [
    { label: 'نظرة عامة', href: '/', permission: 'overview.view' },
    { label: 'المشاريع', href: '/projects', permission: 'project.view' },
    { label: 'الأداء والمحادثات', href: '/conversations', permission: 'conversations.view' },
    { label: 'الاستهلاك والتكلفة', href: '/usage', permission: 'usage.view' },
    { label: 'المزودون والنماذج', href: '/providers', permission: 'providers.view' },
    { label: 'التحويل والتصعيد', href: '/escalations', permission: 'escalations.view' },
    { label: 'إعدادات وسلوك المساعد', href: '/assistants', permission: 'assistants.view' },
    { label: 'تكامل أقسام المشروع', href: '/integrations', permission: 'integrations.view' },
    { label: 'قاعدة المعرفة', href: '/knowledge', permission: 'knowledge.view' },
    { label: 'الربط والتكامل', href: '/bridge', permission: 'integrations.view' },
    { label: 'التنبيهات', href: '/alerts', permission: 'alerts.view' },
    { label: 'سجل التشغيل', href: '/audit', permission: 'audit.view' },
    {
        label: 'المستخدمون والصلاحيات',
        href: '/users',
        permission: 'users.view',
        planned: true,
    },
];

export interface AdminLayoutProps {
    children: React.ReactNode;
}

function NavLinks({ onNavigate }: { onNavigate?: () => void }) {
    const { can } = usePermissions();
    const { url } = usePage();

    return (
        <>
            {NAV_ITEMS.filter((item) => can(item.permission)).map((item) => {
                const active = item.href === '/' ? url === '/' : url.startsWith(item.href);

                return (
                    <Link
                        key={item.href}
                        href={item.href}
                        onClick={onNavigate}
                        aria-current={active ? 'page' : undefined}
                        title={item.planned === true ? `${item.label} — قريبًا` : item.label}
                        className={cn(
                            'flex items-center gap-2.5 rounded-control px-3 py-2.5 text-body transition-colors',
                            active
                                ? 'bg-accent-soft font-bold text-accent'
                                : item.planned === true
                                  ? 'text-fg-subtle hover:bg-surface-sunken hover:text-fg-strong'
                                  : 'text-fg-strong hover:bg-surface-sunken',
                        )}
                    >
                        <span
                            aria-hidden
                            className={cn(
                                'size-2 shrink-0 rounded-full',
                                active
                                    ? 'bg-accent'
                                    : item.planned === true
                                      ? 'bg-border-default'
                                      : 'bg-border-strong',
                            )}
                        />
                        <span className="min-w-0 flex-1 leading-snug">
                            {item.label}
                            {item.planned === true ? (
                                <span className="sr-only"> — قريبًا</span>
                            ) : null}
                        </span>
                    </Link>
                );
            })}
        </>
    );
}

export function AdminLayout({ children }: AdminLayoutProps) {
    const { auth, flash } = usePage<SharedProps>().props;
    const { theme, toggle } = useTheme();
    const [mobileNavOpen, setMobileNavOpen] = useState(false);

    const sidebar = (
        <>
            <div className="px-6 pt-6 pb-4">
                <p className="text-display font-bold text-accent">Hiroote AI</p>
                <p className="mt-0.5 text-caption text-fg-muted">مركز تحكم المشاريع</p>
            </div>

            {/* المبدّل فوق التنقل لا تحته: كل رابط أسفله يقرأ بيانات هذا المشروع. */}
            <div className="px-3 pb-4">
                <ProjectSwitcher />
            </div>

            <nav
                aria-label="التنقل الرئيسي"
                className="flex-1 space-y-0.5 overflow-y-auto px-3 pb-4"
            >
                <NavLinks
                    onNavigate={() => {
                        setMobileNavOpen(false);
                    }}
                />
            </nav>

            <div className="border-t border-border-default px-6 py-4">
                <p className="truncate text-body font-medium text-fg-default">{auth.user?.name}</p>
                <p className="truncate text-caption text-fg-muted">
                    {auth.user?.is_platform_admin === true
                        ? 'مدير المنصة'
                        : (auth.user?.role_label ?? 'بلا دور في هذا المشروع')}
                </p>

                <div className="mt-3 flex items-center gap-1">
                    <button
                        type="button"
                        onClick={toggle}
                        aria-label={
                            theme === 'dark'
                                ? 'التبديل إلى الوضع الفاتح'
                                : 'التبديل إلى الوضع الداكن'
                        }
                        className="flex size-9 items-center justify-center rounded-control text-fg-muted hover:bg-surface-sunken hover:text-fg-default"
                    >
                        {theme === 'dark' ? (
                            <Sun aria-hidden className="size-4" />
                        ) : (
                            <Moon aria-hidden className="size-4" />
                        )}
                    </button>

                    <Link
                        href="/logout"
                        method="post"
                        as="button"
                        aria-label="تسجيل الخروج"
                        className="flex size-9 items-center justify-center rounded-control text-fg-muted hover:bg-surface-sunken hover:text-danger"
                    >
                        <LogOut aria-hidden className="size-4" />
                    </Link>
                </div>
            </div>
        </>
    );

    return (
        <div className="flex min-h-screen bg-surface-base">
            {/* الشريط الجانبي — 260px كما في الفيجما، ويطوى تحت lg */}
            <aside className="hidden w-[276px] shrink-0 flex-col border-s border-border-default bg-surface-raised lg:flex">
                {sidebar}
            </aside>

            {mobileNavOpen ? (
                <div className="fixed inset-0 z-40 lg:hidden">
                    <button
                        type="button"
                        aria-label="إغلاق القائمة"
                        onClick={() => {
                            setMobileNavOpen(false);
                        }}
                        className="absolute inset-0 bg-black/40"
                    />
                    <aside className="absolute inset-y-0 end-0 flex w-[280px] flex-col bg-surface-raised shadow-xl">
                        {sidebar}
                    </aside>
                </div>
            ) : null}

            <div className="flex min-w-0 flex-1 flex-col">
                <button
                    type="button"
                    onClick={() => {
                        setMobileNavOpen((open) => !open);
                    }}
                    className="m-4 flex w-fit items-center gap-2 rounded-control border border-border-default bg-surface-raised px-4 py-2 text-body text-fg-default lg:hidden"
                >
                    {mobileNavOpen ? (
                        <X aria-hidden className="size-4" />
                    ) : (
                        <Menu aria-hidden className="size-4" />
                    )}
                    <Activity aria-hidden className="size-4 text-accent" />
                    Hiroote AI
                </button>

                <main className="mx-auto w-full max-w-[1440px] flex-1 space-y-5 p-4 sm:p-6">
                    {flash.success !== null ? <Alert tone="success" title={flash.success} /> : null}
                    {flash.error !== null ? <Alert tone="danger" title={flash.error} /> : null}

                    {children}
                </main>
            </div>
        </div>
    );
}
