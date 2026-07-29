import type { LucideIcon } from 'lucide-react';
import { Inbox } from 'lucide-react';

export interface EmptyStateProps {
    title: string;
    description?: string;
    icon?: LucideIcon;
    action?: React.ReactNode;
}

/** حالة «فارغ» — وثيقة التصميم §18. */
export function EmptyState({ title, description, icon: Icon = Inbox, action }: EmptyStateProps) {
    return (
        <div className="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center">
            <div className="flex size-12 items-center justify-center rounded-full bg-surface-sunken">
                <Icon aria-hidden className="size-6 text-fg-subtle" />
            </div>
            <div>
                <p className="text-body font-semibold text-fg-default">{title}</p>
                {description !== undefined ? (
                    <p className="mt-1 max-w-sm text-body text-fg-muted">{description}</p>
                ) : null}
            </div>
            {action}
        </div>
    );
}
