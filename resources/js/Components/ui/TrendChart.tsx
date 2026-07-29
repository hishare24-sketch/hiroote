import { useId, useState } from 'react';
import { formatCompact, formatDayMonth } from '@/lib/format';
import { EmptyState } from '@/Components/ui/EmptyState';

export interface TrendPoint {
    date: string;
    value: number;
    /** قيمة ثانية تُعرض في التلميح فقط — التكلفة بجانب التوكن مثلًا. */
    secondary?: string;
}

export interface TrendChartProps {
    points: TrendPoint[];
    label: string;
    emptyTitle: string;
    emptyDescription: string;
}

const VIEW_WIDTH = 720;
const VIEW_HEIGHT = 200;
const PADDING_Y = 12;

/**
 * منحنى الاستهلاك عبر الزمن — SVG مكتوب يدويًا لا مكتبة رسم.
 *
 * السبب: الألوان تأتي من متغيرات التصميم مباشرة فيعمل الوضع الداكن دون
 * تكرار، والاتجاه يبقى تحت السيطرة في RTL — أحدث نقطة على اليسار كما يقرأ
 * الزمن في العربية.
 */
export function TrendChart({ points, label, emptyTitle, emptyDescription }: TrendChartProps) {
    const gradientId = useId();
    const [hovered, setHovered] = useState<number | null>(null);

    const oldest = points.at(0);
    const newest = points.at(-1);

    if (oldest === undefined || newest === undefined) {
        return <EmptyState title={emptyTitle} description={emptyDescription} />;
    }

    const max = Math.max(...points.map((point) => point.value), 1);
    const step = points.length > 1 ? VIEW_WIDTH / (points.length - 1) : 0;

    const coordinates = points.map((point, index) => {
        // المحور مقلوب أفقيًا: الأقدم يمينًا والأحدث يسارًا، اتساقًا مع RTL.
        const x = VIEW_WIDTH - index * step;
        const usable = VIEW_HEIGHT - PADDING_Y * 2;
        const y = VIEW_HEIGHT - PADDING_Y - (point.value / max) * usable;

        return { x, y };
    });

    const line = coordinates
        .map((point, index) => `${index === 0 ? 'M' : 'L'}${String(point.x)},${String(point.y)}`)
        .join(' ');

    const area = `${line} L0,${String(VIEW_HEIGHT)} L${String(VIEW_WIDTH)},${String(VIEW_HEIGHT)} Z`;
    const active = hovered === null ? undefined : points[hovered];
    const activePoint = hovered === null ? undefined : coordinates[hovered];

    return (
        <figure className="flex flex-col gap-3">
            <div className="relative">
                <svg
                    viewBox={`0 0 ${String(VIEW_WIDTH)} ${String(VIEW_HEIGHT)}`}
                    preserveAspectRatio="none"
                    role="img"
                    aria-label={`${label} — ${String(points.length)} يومًا`}
                    className="h-48 w-full"
                >
                    <defs>
                        <linearGradient id={gradientId} x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="var(--color-accent)" stopOpacity="0.22" />
                            <stop offset="100%" stopColor="var(--color-accent)" stopOpacity="0" />
                        </linearGradient>
                    </defs>

                    {[0.25, 0.5, 0.75].map((ratio) => (
                        <line
                            key={ratio}
                            x1="0"
                            x2={VIEW_WIDTH}
                            y1={VIEW_HEIGHT * ratio}
                            y2={VIEW_HEIGHT * ratio}
                            stroke="var(--color-border-default)"
                            strokeWidth="1"
                            vectorEffect="non-scaling-stroke"
                        />
                    ))}

                    <path d={area} fill={`url(#${gradientId})`} />
                    <path
                        d={line}
                        fill="none"
                        stroke="var(--color-accent)"
                        strokeWidth="2"
                        strokeLinejoin="round"
                        strokeLinecap="round"
                        vectorEffect="non-scaling-stroke"
                    />

                    {activePoint === undefined ? null : (
                        <circle
                            cx={activePoint.x}
                            cy={activePoint.y}
                            r="4"
                            fill="var(--color-accent)"
                            stroke="var(--color-surface-raised)"
                            strokeWidth="2"
                            vectorEffect="non-scaling-stroke"
                        />
                    )}
                </svg>

                {/* طبقة تفاعل منفصلة: أعمدة بعرض متساوٍ أسهل في الإصابة من نقاط المنحنى. */}
                <div className="absolute inset-0 flex flex-row-reverse">
                    {points.map((point, index) => (
                        <button
                            key={point.date}
                            type="button"
                            tabIndex={-1}
                            aria-hidden
                            onMouseEnter={() => {
                                setHovered(index);
                            }}
                            onMouseLeave={() => {
                                setHovered(null);
                            }}
                            className="h-full flex-1"
                        />
                    ))}
                </div>
            </div>

            <figcaption className="flex items-center justify-between gap-3 text-micro text-fg-subtle">
                <span>{formatDayMonth(newest.date)}</span>
                <span
                    aria-live="polite"
                    className="rounded-pill bg-surface-sunken px-3 py-1 font-bold text-fg-default"
                >
                    {active === undefined
                        ? `الذروة ${formatCompact(max)}`
                        : `${formatDayMonth(active.date)} · ${formatCompact(active.value)}${
                              active.secondary === undefined ? '' : ` · ${active.secondary}`
                          }`}
                </span>
                <span>{formatDayMonth(oldest.date)}</span>
            </figcaption>
        </figure>
    );
}
