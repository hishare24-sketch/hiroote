import type { StatusTone } from '@/types';
import { cn } from '@/lib/cn';

const TONES: Record<StatusTone, string> = {
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
    info: 'bg-info-soft text-info',
    neutral: 'bg-neutral-soft text-neutral',
};

const DOTS: Record<StatusTone, string> = {
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    info: 'bg-info',
    neutral: 'bg-neutral',
};

export interface BadgeProps {
    tone?: StatusTone;
    /**
     * Renders a status dot next to the label. The label still carries the
     * meaning — colour alone never does (وثيقة 03 §6).
     */
    dot?: boolean;
    children: React.ReactNode;
    className?: string;
}

export function Badge({ tone = 'neutral', dot = false, children, className }: BadgeProps) {
    return (
        <span
            className={cn(
                'inline-flex items-center gap-1.5 rounded-pill px-2.5 py-1 text-micro font-semibold',
                TONES[tone],
                className,
            )}
        >
            {dot ? <span aria-hidden className={cn('size-1.5 rounded-full', DOTS[tone])} /> : null}
            {children}
        </span>
    );
}
