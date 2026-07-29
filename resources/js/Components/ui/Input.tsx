import { forwardRef, useId } from 'react';
import { cn } from '@/lib/cn';

export interface InputProps extends Omit<React.InputHTMLAttributes<HTMLInputElement>, 'id'> {
    /** Always required: every input carries a real label (وثيقة 03 §6). */
    label: string;
    error?: string;
    hint?: string;
}

export const Input = forwardRef<HTMLInputElement, InputProps>(function Input(
    { label, error, hint, className, ...props },
    ref,
) {
    const id = useId();
    const hintId = `${id}-hint`;
    const errorId = `${id}-error`;

    return (
        <div className="flex flex-col gap-1.5">
            <label htmlFor={id} className="text-sm font-medium text-fg-default">
                {label}
                {props.required === true ? (
                    <span aria-hidden className="ms-1 text-danger">
                        *
                    </span>
                ) : null}
            </label>

            <input
                ref={ref}
                id={id}
                aria-invalid={error !== undefined || undefined}
                aria-describedby={
                    cn(hint !== undefined && hintId, error !== undefined && errorId) || undefined
                }
                className={cn(
                    'h-10 w-full rounded-control border bg-surface-raised px-3 text-sm text-fg-default',
                    'placeholder:text-fg-subtle disabled:cursor-not-allowed disabled:opacity-60',
                    error !== undefined ? 'border-danger' : 'border-border-strong',
                    className,
                )}
                {...props}
            />

            {hint !== undefined ? (
                <p id={hintId} className="text-xs text-fg-muted">
                    {hint}
                </p>
            ) : null}

            {error !== undefined ? (
                <p id={errorId} role="alert" className="text-xs font-medium text-danger">
                    {error}
                </p>
            ) : null}
        </div>
    );
});
