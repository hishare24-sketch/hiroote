import { Link } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { formatNumber } from '@/lib/format';
import { cn } from '@/lib/cn';

export interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export interface PaginationProps {
    links: PaginationLink[];
    from: number | null;
    to: number | null;
    total: number;
    /** اسم ما يُعَدّ — «محادثة»، «حالة»… */
    unit: string;
}

/**
 * ترقيم الصفحات — روابط Laravel كما هي.
 *
 * السهمان يقلبان في RTL: «السابق» يشير يمينًا لأن القراءة تسير يسارًا.
 */
export function Pagination({ links, from, to, total, unit }: PaginationProps) {
    const previous = links.at(0);
    const next = links.at(-1);

    // Laravel يرسل «السابق» و«التالي» دائمًا؛ الحارس هنا للحالة الفارغة فقط.
    if (total === 0 || previous === undefined || next === undefined) {
        return null;
    }

    const numbered = links.slice(1, -1);

    return (
        <nav
            aria-label="ترقيم الصفحات"
            className="flex flex-wrap items-center justify-between gap-3 border-t border-border-default px-5 py-3"
        >
            <p className="text-caption text-fg-muted">
                عرض {formatNumber(from ?? 0)}–{formatNumber(to ?? 0)} من {formatNumber(total)}{' '}
                {unit}
            </p>

            <div className="flex items-center gap-1">
                <PageButton url={previous.url} label="الصفحة السابقة">
                    <ChevronRight aria-hidden className="size-4" />
                </PageButton>

                {numbered.map((link, index) => (
                    <PageButton
                        key={`${link.label}-${String(index)}`}
                        url={link.url}
                        active={link.active}
                        label={`الصفحة ${link.label}`}
                    >
                        {link.label}
                    </PageButton>
                ))}

                <PageButton url={next.url} label="الصفحة التالية">
                    <ChevronLeft aria-hidden className="size-4" />
                </PageButton>
            </div>
        </nav>
    );
}

function PageButton({
    url,
    label,
    active = false,
    children,
}: {
    url: string | null;
    label: string;
    active?: boolean;
    children: React.ReactNode;
}) {
    const classes = cn(
        'inline-flex h-8 min-w-8 items-center justify-center rounded-control px-2 text-caption font-semibold transition-colors',
        active
            ? 'bg-accent text-on-accent'
            : 'border border-border-strong text-fg-muted hover:bg-surface-sunken',
    );

    if (url === null) {
        return (
            <span aria-hidden className={cn(classes, 'cursor-not-allowed opacity-40')}>
                {children}
            </span>
        );
    }

    return (
        <Link
            href={url}
            aria-label={label}
            aria-current={active ? 'page' : undefined}
            preserveScroll
            className={classes}
        >
            {children}
        </Link>
    );
}
