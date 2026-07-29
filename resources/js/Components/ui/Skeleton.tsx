import { cn } from '@/lib/cn';

export interface SkeletonProps {
    className?: string;
}

/** Loading placeholder (حالة «تحميل» — وثيقة التصميم §18). */
export function Skeleton({ className }: SkeletonProps) {
    return (
        <div
            aria-hidden
            className={cn('skeleton-pulse rounded-control bg-surface-sunken', className)}
        />
    );
}
