import type { Tone } from '@/types';
import { formatNumber, formatPercent } from '@/lib/format';
import { cn } from '@/lib/cn';
import { EmptyState } from '@/Components/ui/EmptyState';

const BARS: Record<Tone, string> = {
    accent: 'bg-accent',
    success: 'bg-success',
    warning: 'bg-warning',
    danger: 'bg-danger',
    info: 'bg-info',
    neutral: 'bg-neutral',
};

export interface ShareListItem {
    label: string;
    caption?: string | null;
    count: number;
    share: number;
    tone: Tone;
}

export interface ShareListProps {
    items: ShareListItem[];
    /** وحدة العدّ في نهاية كل صف — «محادثة»، «حالة»… */
    unit: string;
    emptyTitle: string;
    emptyDescription: string;
}

/**
 * قائمة مرتّبة بنسبة مرئية — «أكثر الأسئلة» و«الأقسام» و«أسباب التحويل».
 *
 * الشريط يقيس حصة البند من المجموع لا قيمته المطلقة، ولذلك يُكتب العدد
 * والنسبة معًا: الشريط وحده لا يخبر بكم.
 */
export function ShareList({ items, unit, emptyTitle, emptyDescription }: ShareListProps) {
    if (items.length === 0) {
        return <EmptyState title={emptyTitle} description={emptyDescription} />;
    }

    const widest = Math.max(...items.map((item) => item.share), 1);

    return (
        <ul className="flex flex-col gap-4">
            {items.map((item) => (
                <li key={item.label} className="flex flex-col gap-1.5">
                    <div className="flex items-baseline justify-between gap-3">
                        <p className="min-w-0 truncate text-body font-semibold text-fg-default">
                            {item.label}
                        </p>
                        <p className="flex shrink-0 items-baseline gap-2 text-caption font-bold text-fg-muted tabular-nums">
                            <span className="whitespace-nowrap">
                                {formatNumber(item.count)} {unit}
                            </span>
                            <span aria-hidden className="text-fg-subtle">
                                ·
                            </span>
                            <span className="font-medium text-fg-subtle">
                                {formatPercent(item.share)}
                            </span>
                        </p>
                    </div>

                    <div
                        role="progressbar"
                        aria-valuenow={Math.round(item.share)}
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-label={item.label}
                        className="h-1.5 w-full overflow-hidden rounded-pill bg-surface-track"
                    >
                        <div
                            className={cn('h-full rounded-pill', BARS[item.tone])}
                            style={{ width: `${String((item.share / widest) * 100)}%` }}
                        />
                    </div>

                    {item.caption !== null && item.caption !== undefined ? (
                        <p className="text-micro text-fg-subtle">{item.caption}</p>
                    ) : null}
                </li>
            ))}
        </ul>
    );
}
