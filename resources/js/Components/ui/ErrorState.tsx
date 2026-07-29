import { AlertTriangle } from 'lucide-react';
import { Button } from '@/Components/ui/Button';

export interface ErrorStateProps {
    title?: string;
    description?: string;
    /** The request id shown so a user report can be matched to server logs. */
    requestId?: string;
    onRetry?: () => void;
}

/** حالة «خطأ» — وثيقة التصميم §18. */
export function ErrorState({
    title = 'تعذر تحميل البيانات',
    description = 'حدث خطأ غير متوقع. حاول مرة أخرى أو تواصل مع فريق التشغيل.',
    requestId,
    onRetry,
}: ErrorStateProps) {
    return (
        <div
            role="alert"
            className="flex flex-col items-center justify-center gap-3 px-6 py-12 text-center"
        >
            <div className="flex size-12 items-center justify-center rounded-full bg-danger-soft">
                <AlertTriangle aria-hidden className="size-6 text-danger" />
            </div>
            <div>
                <p className="text-sm font-semibold text-fg-default">{title}</p>
                <p className="mt-1 max-w-sm text-sm text-fg-muted">{description}</p>
                {requestId !== undefined ? (
                    <p className="mt-2 font-mono text-xs text-fg-subtle" dir="ltr">
                        request_id: {requestId}
                    </p>
                ) : null}
            </div>
            {onRetry !== undefined ? (
                <Button variant="secondary" size="sm" onClick={onRetry}>
                    إعادة المحاولة
                </Button>
            ) : null}
        </div>
    );
}
