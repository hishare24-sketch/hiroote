import { router } from '@inertiajs/react';
import { useState } from 'react';
import { CalendarRange } from 'lucide-react';
import { cn } from '@/lib/cn';

export interface PeriodOption {
    value: string;
    label: string;
}

export interface PeriodFilterProps {
    options: PeriodOption[];
    active: string;
    from: string | null;
    to: string | null;
    /** المسار الذي يُعاد تحميله؛ بقية الفلاتر تُحفظ عبر `preserveState`. */
    url: string;
}

/**
 * الفلاتر الزمنية العامة — وثيقة 06 §5.
 *
 * تعمل بروابط لا بحالة محلية: الفترة تبقى في الـ URL فتُشارَك وتُحفَظ في
 * التاريخ، ولا تعود الشاشة إلى «آخر 30 يومًا» عند التحديث.
 */
export function PeriodFilter({ options, active, from, to, url }: PeriodFilterProps) {
    const [customFrom, setCustomFrom] = useState(from ?? '');
    const [customTo, setCustomTo] = useState(to ?? '');

    const apply = (period: string, nextFrom?: string, nextTo?: string) => {
        router.get(
            url,
            {
                period,
                ...(period === 'custom' ? { from: nextFrom ?? '', to: nextTo ?? '' } : {}),
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <div className="flex flex-wrap items-center gap-2">
            <div
                role="group"
                aria-label="الفترة الزمنية"
                className="flex flex-wrap items-center gap-1 rounded-pill bg-surface-sunken p-1"
            >
                {options
                    .filter((option) => option.value !== 'custom')
                    .map((option) => (
                        <button
                            key={option.value}
                            type="button"
                            aria-pressed={option.value === active}
                            onClick={() => {
                                apply(option.value);
                            }}
                            className={cn(
                                'rounded-pill px-3.5 py-1.5 text-caption font-semibold transition-colors',
                                option.value === active
                                    ? 'bg-surface-raised text-accent shadow-card'
                                    : 'text-fg-muted hover:text-fg-default',
                            )}
                        >
                            {option.label}
                        </button>
                    ))}
            </div>

            <div className="flex items-center gap-1.5 rounded-pill border border-border-strong bg-surface-raised px-3 py-1">
                <CalendarRange aria-hidden className="size-4 shrink-0 text-fg-subtle" />
                <input
                    type="date"
                    aria-label="من تاريخ"
                    value={customFrom}
                    onChange={(event) => {
                        setCustomFrom(event.target.value);
                    }}
                    className="w-[8.5rem] bg-transparent text-caption text-fg-default outline-none"
                />
                <span aria-hidden className="text-fg-subtle">
                    —
                </span>
                <input
                    type="date"
                    aria-label="إلى تاريخ"
                    value={customTo}
                    onChange={(event) => {
                        setCustomTo(event.target.value);
                    }}
                    className="w-[8.5rem] bg-transparent text-caption text-fg-default outline-none"
                />
                <button
                    type="button"
                    disabled={customFrom === '' || customTo === ''}
                    onClick={() => {
                        apply('custom', customFrom, customTo);
                    }}
                    className={cn(
                        'rounded-pill px-3 py-1 text-micro font-bold transition-colors',
                        active === 'custom'
                            ? 'bg-accent text-on-accent'
                            : 'bg-accent-soft text-accent hover:bg-accent hover:text-on-accent',
                        'disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-accent-soft disabled:hover:text-accent',
                    )}
                >
                    تطبيق
                </button>
            </div>
        </div>
    );
}
