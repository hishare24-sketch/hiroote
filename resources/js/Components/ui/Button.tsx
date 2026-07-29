import { forwardRef } from 'react';
import { Loader2 } from 'lucide-react';
import { cn } from '@/lib/cn';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
type Size = 'sm' | 'md' | 'lg';

const VARIANTS: Record<Variant, string> = {
    primary: 'bg-accent text-on-accent hover:brightness-110',
    secondary:
        'border border-border-strong bg-surface-raised text-fg-default hover:bg-surface-sunken',
    ghost: 'bg-transparent text-fg-muted hover:bg-accent-soft hover:text-accent',
    danger: 'bg-danger text-fg-inverted hover:brightness-110',
};

const SIZES: Record<Size, string> = {
    sm: 'h-8 px-3 text-caption gap-1.5',
    md: 'h-10 px-4 text-body gap-2',
    lg: 'h-12 px-6 text-body gap-2',
};

export interface ButtonProps extends React.ButtonHTMLAttributes<HTMLButtonElement> {
    variant?: Variant;
    size?: Size;
    loading?: boolean;
    /** Required when the button renders an icon only, so it stays reachable by screen readers. */
    'aria-label'?: string;
}

export const Button = forwardRef<HTMLButtonElement, ButtonProps>(function Button(
    { variant = 'primary', size = 'md', loading = false, disabled, className, children, ...props },
    ref,
) {
    return (
        <button
            ref={ref}
            type={props.type ?? 'button'}
            disabled={disabled === true || loading}
            aria-busy={loading || undefined}
            className={cn(
                'inline-flex items-center justify-center rounded-control font-bold transition-colors',
                'disabled:cursor-not-allowed disabled:opacity-60',
                VARIANTS[variant],
                SIZES[size],
                className,
            )}
            {...props}
        >
            {loading ? <Loader2 aria-hidden className="size-4 animate-spin" /> : null}
            {children}
        </button>
    );
});
