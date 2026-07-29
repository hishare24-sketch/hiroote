import { cn } from '@/lib/cn';

export interface CardProps {
    children: React.ReactNode;
    className?: string;
}

export function Card({ children, className }: CardProps) {
    return (
        <div
            className={cn(
                'rounded-card border border-border-default bg-surface-raised shadow-xs',
                className,
            )}
        >
            {children}
        </div>
    );
}

export interface CardHeaderProps {
    title: string;
    description?: string;
    /** Actions rendered at the inline-end of the header. */
    actions?: React.ReactNode;
    className?: string;
}

export function CardHeader({ title, description, actions, className }: CardHeaderProps) {
    return (
        <div
            className={cn(
                'flex items-start justify-between gap-4 border-b border-border-default px-6 py-4',
                className,
            )}
        >
            <div className="min-w-0">
                <h2 className="truncate text-base font-semibold text-fg-default">{title}</h2>
                {description !== undefined ? (
                    <p className="mt-1 text-sm text-fg-muted">{description}</p>
                ) : null}
            </div>
            {actions !== undefined ? <div className="shrink-0">{actions}</div> : null}
        </div>
    );
}

export function CardBody({ children, className }: CardProps) {
    return <div className={cn('px-6 py-5', className)}>{children}</div>;
}

export function CardFooter({ children, className }: CardProps) {
    return (
        <div className={cn('border-t border-border-default px-6 py-4', className)}>{children}</div>
    );
}
