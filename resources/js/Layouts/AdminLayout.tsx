import { Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowLeftRight,
    Bell,
    BookOpen,
    Bot,
    Coins,
    LayoutDashboard,
    LogOut,
    MessagesSquare,
    Moon,
    Plug,
    ScrollText,
    Server,
    Sun,
    Users,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { Permission, SharedProps } from '@/types';
import { usePermissions } from '@/Hooks/usePermissions';
import { useTheme } from '@/Hooks/useTheme';
import { Alert } from '@/Components/ui/Alert';
import { cn } from '@/lib/cn';

interface NavItem {
    label: string;
    href: string;
    icon: LucideIcon;
    permission: Permission;
}

/** هيكل التنقل الرئيسي — وثيقة التصميم §3. */
const NAV_ITEMS: NavItem[] = [
    { label: 'نظرة عامة', href: '/', icon: LayoutDashboard, permission: 'overview.view' },
    {
        label: 'الأداء والمحادثات',
        href: '/conversations',
        icon: MessagesSquare,
        permission: 'conversations.view',
    },
    { label: 'الاستهلاك والتكلفة', href: '/usage', icon: Coins, permission: 'usage.view' },
    { label: 'المزودون والنماذج', href: '/providers', icon: Server, permission: 'providers.view' },
    {
        label: 'التحويل والتصعيد',
        href: '/escalations',
        icon: ArrowLeftRight,
        permission: 'escalations.view',
    },
    {
        label: 'إعدادات وسلوك المساعد',
        href: '/assistants',
        icon: Bot,
        permission: 'assistants.view',
    },
    {
        label: 'تكامل أقسام المنصة',
        href: '/integrations',
        icon: Plug,
        permission: 'integrations.view',
    },
    { label: 'قاعدة المعرفة', href: '/knowledge', icon: BookOpen, permission: 'knowledge.view' },
    { label: 'سجل التشغيل والتدقيق', href: '/audit', icon: ScrollText, permission: 'audit.view' },
    { label: 'التنبيهات', href: '/alerts', icon: Bell, permission: 'alerts.view' },
    { label: 'المستخدمون والصلاحيات', href: '/users', icon: Users, permission: 'users.view' },
];

export interface AdminLayoutProps {
    title: string;
    children: React.ReactNode;
    /** Header controls rendered next to the page title (filters, refresh…). */
    headerActions?: React.ReactNode;
}

export function AdminLayout({ title, children, headerActions }: AdminLayoutProps) {
    const { auth, flash } = usePage<SharedProps>().props;
    const { can } = usePermissions();
    const { theme, toggle } = useTheme();
    const { url } = usePage();

    const visibleItems = NAV_ITEMS.filter((item) => can(item.permission));

    return (
        <div className="flex min-h-screen">
            {/* Sidebar — وثيقة التصميم §19 */}
            <aside className="hidden w-64 shrink-0 flex-col border-e border-border-default bg-surface-raised lg:flex">
                <div className="flex h-16 items-center gap-2 border-b border-border-default px-6">
                    <Activity aria-hidden className="size-6 text-brand-600" />
                    <span className="text-lg font-bold text-fg-default">Hiroote AI</span>
                </div>

                <nav aria-label="التنقل الرئيسي" className="flex-1 space-y-1 overflow-y-auto p-3">
                    {visibleItems.map((item) => {
                        const active = item.href === '/' ? url === '/' : url.startsWith(item.href);

                        return (
                            <Link
                                key={item.href}
                                href={item.href}
                                aria-current={active ? 'page' : undefined}
                                className={cn(
                                    'flex items-center gap-3 rounded-control px-3 py-2 text-sm font-medium transition-colors',
                                    active
                                        ? 'bg-brand-600/10 text-brand-600 dark:text-brand-300'
                                        : 'text-fg-muted hover:bg-surface-sunken hover:text-fg-default',
                                )}
                            >
                                <item.icon aria-hidden className="size-4 shrink-0" />
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>

                <div className="border-t border-border-default p-4">
                    <p className="truncate text-sm font-medium text-fg-default">
                        {auth.user?.name}
                    </p>
                    <p className="truncate text-xs text-fg-muted">{auth.user?.role_label}</p>
                </div>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                {/* Top bar — وثيقة التصميم §4 */}
                <header className="flex h-16 items-center justify-between gap-4 border-b border-border-default bg-surface-raised px-6">
                    <h1 className="truncate text-lg font-semibold text-fg-default">{title}</h1>

                    <div className="flex items-center gap-2">
                        {headerActions}

                        <button
                            type="button"
                            onClick={toggle}
                            aria-label={
                                theme === 'dark'
                                    ? 'التبديل إلى الوضع الفاتح'
                                    : 'التبديل إلى الوضع الداكن'
                            }
                            className="flex size-10 items-center justify-center rounded-control text-fg-muted hover:bg-surface-sunken hover:text-fg-default"
                        >
                            {theme === 'dark' ? (
                                <Sun aria-hidden className="size-5" />
                            ) : (
                                <Moon aria-hidden className="size-5" />
                            )}
                        </button>

                        <Link
                            href="/logout"
                            method="post"
                            as="button"
                            aria-label="تسجيل الخروج"
                            className="flex size-10 items-center justify-center rounded-control text-fg-muted hover:bg-surface-sunken hover:text-danger"
                        >
                            <LogOut aria-hidden className="size-5" />
                        </Link>
                    </div>
                </header>

                <main className="flex-1 space-y-6 p-6">
                    {flash.success !== null ? <Alert tone="success" title={flash.success} /> : null}
                    {flash.error !== null ? <Alert tone="danger" title={flash.error} /> : null}

                    {children}
                </main>
            </div>
        </div>
    );
}
