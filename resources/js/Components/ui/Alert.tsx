import { AlertTriangle, CheckCircle2, Info, XCircle } from 'lucide-react';
import type { StatusTone } from '@/types';
import { cn } from '@/lib/cn';

const TONES: Record<StatusTone, { wrapper: string; icon: typeof Info; srLabel: string }> = {
    success: { wrapper: 'bg-success-soft text-success', icon: CheckCircle2, srLabel: 'نجاح' },
    warning: { wrapper: 'bg-warning-soft text-warning', icon: AlertTriangle, srLabel: 'تحذير' },
    danger: { wrapper: 'bg-danger-soft text-danger', icon: XCircle, srLabel: 'خطأ' },
    info: { wrapper: 'bg-info-soft text-info', icon: Info, srLabel: 'معلومة' },
    neutral: { wrapper: 'bg-neutral-soft text-neutral', icon: Info, srLabel: 'ملاحظة' },
};

export interface AlertProps {
    tone?: StatusTone;
    title: string;
    children?: React.ReactNode;
    actions?: React.ReactNode;
    className?: string;
}

export function Alert({ tone = 'info', title, children, actions, className }: AlertProps) {
    const { wrapper, icon: Icon, srLabel } = TONES[tone];

    return (
        <div
            role={tone === 'danger' ? 'alert' : 'status'}
            className={cn('flex gap-3 rounded-card px-4 py-3', wrapper, className)}
        >
            <Icon aria-hidden className="mt-0.5 size-5 shrink-0" />
            <div className="min-w-0 flex-1">
                {/* Carries the tone for assistive tech, since the colour cannot. */}
                <span className="sr-only">{srLabel}: </span>
                <p className="text-body font-semibold">{title}</p>
                {children !== undefined ? (
                    <div className="mt-1 text-body opacity-90">{children}</div>
                ) : null}
            </div>
            {actions !== undefined ? <div className="shrink-0">{actions}</div> : null}
        </div>
    );
}
