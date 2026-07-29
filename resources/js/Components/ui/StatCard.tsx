import type { LucideIcon } from 'lucide-react';
import type { StatusTone } from '@/types';
import { cn } from '@/lib/cn';

const ICON_TONES: Record<StatusTone | 'accent', string> = {
    accent: 'bg-accent-soft text-accent',
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
    info: 'bg-info-soft text-info',
    neutral: 'bg-neutral-soft text-neutral',
};

const BAR_TONES: Record<StatusTone | 'accent', string> = {
    accent: 'bg-accent',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    info: 'bg-info',
    neutral: 'bg-neutral',
};

export interface StatCardProps {
    label: string;
    value: string;
    caption?: string;
    icon: LucideIcon;
    tone?: StatusTone | 'accent';
    /** 0–100. Omit when the metric has no meaningful ceiling. */
    progress?: number;
    className?: string;
}

/** البطاقات الإحصائية العليا — وثيقة التصميم §4. */
export function StatCard({
    label,
    value,
    caption,
    icon: Icon,
    tone = 'accent',
    progress,
    className,
}: StatCardProps) {
    const clamped = progress === undefined ? undefined : Math.min(100, Math.max(0, progress));

    return (
        <div
            className={cn(
                'flex flex-col gap-4 rounded-card border border-border-default bg-surface-raised p-5 shadow-[0_1px_2px_rgba(16,24,40,0.04)]',
                className,
            )}
        >
            <div className="flex items-center gap-3">
                <span
                    aria-hidden
                    className={cn(
                        'flex size-10 shrink-0 items-center justify-center rounded-control',
                        ICON_TONES[tone],
                    )}
                >
                    <Icon className="size-5" />
                </span>
                <p className="min-w-0 flex-1 truncate text-body font-medium text-fg-muted">
                    {label}
                </p>
            </div>

            <div>
                <p className="text-metric font-bold text-fg-default">{value}</p>
                {caption !== undefined ? (
                    <p className="mt-1.5 text-caption text-fg-muted">{caption}</p>
                ) : null}
            </div>

            {clamped !== undefined ? (
                <div
                    role="progressbar"
                    aria-valuenow={Math.round(clamped)}
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-label={label}
                    className="h-[5px] w-full overflow-hidden rounded-pill bg-surface-track"
                >
                    <div
                        className={cn('h-full rounded-pill', BAR_TONES[tone])}
                        style={{ width: `${String(clamped)}%` }}
                    />
                </div>
            ) : null}
        </div>
    );
}
