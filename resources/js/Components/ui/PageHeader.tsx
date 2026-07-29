import type { StatusTone } from '@/types';
import { cn } from '@/lib/cn';

const PILL_TONES: Record<StatusTone, string> = {
    success: 'bg-success-soft text-success',
    warning: 'bg-warning-soft text-warning',
    danger: 'bg-danger-soft text-danger',
    info: 'bg-info-soft text-info',
    neutral: 'bg-neutral-soft text-neutral',
};

export interface PageHeaderProps {
    title: string;
    description: string;
    systemStatus: { label: string; tone: StatusTone };
    /** Currently applied period filter — الفلاتر العامة، وثيقة التصميم §5. */
    period?: string;
    actions?: React.ReactNode;
}

/** ترويسة الشاشة الموحدة — وثيقة التصميم §4. */
export function PageHeader({ title, description, systemStatus, period, actions }: PageHeaderProps) {
    return (
        <header className="flex flex-wrap items-center justify-between gap-4 rounded-card border border-border-default bg-surface-raised px-6 py-4">
            <div className="min-w-0">
                <h1 className="truncate text-xl font-bold text-fg-default">{title}</h1>
                <p className="mt-0.5 text-xs text-fg-muted">{description}</p>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                {actions}

                {period !== undefined ? (
                    <span className="rounded-pill bg-info-soft px-4 py-1.5 text-xs font-bold text-info">
                        {period}
                    </span>
                ) : null}

                <span
                    className={cn(
                        'inline-flex items-center gap-1.5 rounded-pill px-4 py-1.5 text-xs font-bold',
                        PILL_TONES[systemStatus.tone],
                    )}
                >
                    <span aria-hidden className="size-1.5 rounded-full bg-current" />
                    {systemStatus.label}
                </span>
            </div>
        </header>
    );
}
