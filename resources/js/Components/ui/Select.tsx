import { forwardRef, useId } from 'react';
import { ChevronDown } from 'lucide-react';
import { cn } from '@/lib/cn';

export interface SelectOption {
    value: string;
    label: string;
    disabled?: boolean;
}

export interface SelectProps extends Omit<React.SelectHTMLAttributes<HTMLSelectElement>, 'id'> {
    label: string;
    options: SelectOption[];
    error?: string;
    placeholder?: string;
}

export const Select = forwardRef<HTMLSelectElement, SelectProps>(function Select(
    { label, options, error, placeholder, className, ...props },
    ref,
) {
    const id = useId();
    const errorId = `${id}-error`;

    return (
        <div className="flex flex-col gap-1.5">
            <label htmlFor={id} className="text-sm font-medium text-fg-default">
                {label}
            </label>

            <div className="relative">
                <select
                    ref={ref}
                    id={id}
                    aria-invalid={error !== undefined || undefined}
                    aria-describedby={error !== undefined ? errorId : undefined}
                    className={cn(
                        'h-10 w-full appearance-none rounded-control border bg-surface-raised ps-3 pe-9 text-sm text-fg-default',
                        'disabled:cursor-not-allowed disabled:opacity-60',
                        error !== undefined ? 'border-danger' : 'border-border-strong',
                        className,
                    )}
                    {...props}
                >
                    {placeholder !== undefined ? <option value="">{placeholder}</option> : null}
                    {options.map((option) => (
                        <option key={option.value} value={option.value} disabled={option.disabled}>
                            {option.label}
                        </option>
                    ))}
                </select>

                <ChevronDown
                    aria-hidden
                    className="pointer-events-none absolute end-3 top-1/2 size-4 -translate-y-1/2 text-fg-subtle"
                />
            </div>

            {error !== undefined ? (
                <p id={errorId} role="alert" className="text-xs font-medium text-danger">
                    {error}
                </p>
            ) : null}
        </div>
    );
});
