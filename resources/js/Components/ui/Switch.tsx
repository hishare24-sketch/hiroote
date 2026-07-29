import { cn } from '@/lib/cn';

export interface SwitchProps {
    checked: boolean;
    onChange: (checked: boolean) => void;
    /** Required: the control carries no visible text of its own. */
    'aria-label': string;
    disabled?: boolean;
    describedBy?: string;
    className?: string;
}

/**
 * سويتش التحكم — العنصر وحده دون تسمية مرئية.
 *
 * Built on a real checkbox so keyboard focus, Space to toggle and the
 * screen-reader state come from the platform (وثيقة 03 §6). The knob is
 * positioned with flex justification rather than a transform, so it travels
 * the correct way in both RTL and LTR without a direction-specific rule.
 */
export function Switch({
    checked,
    onChange,
    disabled,
    describedBy,
    className,
    'aria-label': ariaLabel,
}: SwitchProps) {
    return (
        <span className={cn('relative inline-flex shrink-0', className)}>
            <input
                type="checkbox"
                role="switch"
                checked={checked}
                disabled={disabled}
                aria-label={ariaLabel}
                aria-describedby={describedBy}
                onChange={(event) => {
                    onChange(event.target.checked);
                }}
                className="peer absolute inset-0 z-10 size-full cursor-pointer opacity-0 disabled:cursor-not-allowed"
            />
            <span
                aria-hidden
                className={cn(
                    'pointer-events-none flex h-[22px] w-10 items-center rounded-pill p-0.5 transition-colors',
                    'peer-focus-visible:shadow-[0_0_0_3px_color-mix(in_srgb,var(--color-focus-ring)_25%,transparent)]',
                    checked ? 'justify-end bg-accent' : 'justify-start bg-surface-track',
                )}
            >
                <span className="size-[18px] rounded-full bg-white shadow-sm transition-all" />
            </span>
        </span>
    );
}
