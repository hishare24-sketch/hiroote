import { forwardRef } from 'react';
import { Loader2 } from 'lucide-react';
import { cn } from '@/lib/cn';

type Variant = 'primary' | 'secondary' | 'ghost' | 'danger';
type Size = 'sm' | 'md' | 'lg';

const VARIANTS: Record<Variant, string> = {
    primary: 'bg-brand-600 text-white hover:bg-brand-700 disabled:bg-brand-300',
    secondary:
        'bg-surface-raised text-fg-default border border-border-strong hover:bg-surface-sunken',
    ghost: 'bg-transparent text-fg-muted hover:bg-surface-sunken hover:text-fg-default',
    danger: 'bg-danger text-white hover:brightness-110',
};

const SIZES: Record<Size, string> = {
    sm: 'h-8 px-3 text-sm gap-1.5',
    md: 'h-10 px-4 text-sm gap-2',
    lg: 'h-12 px-6 text-base gap-2',
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
                'inline-flex items-center justify-center rounded-control font-medium transition-colors',
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
