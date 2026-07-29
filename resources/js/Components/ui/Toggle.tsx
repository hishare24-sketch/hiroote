import { useId } from 'react';
import { Switch } from '@/Components/ui/Switch';
import { cn } from '@/lib/cn';

export interface ToggleProps {
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    disabled?: boolean;
    /** Shown under the label — use it to say what flipping the switch does. */
    hint?: string;
    className?: string;
}

/** صف سويتش بتسمية — مفاتيح التحكم السريعة، وثيقة التصميم §4. */
export function Toggle({ label, checked, onChange, disabled, hint, className }: ToggleProps) {
    const id = useId();
    const hintId = `${id}-hint`;

    return (
        <div
            className={cn(
                'flex items-center gap-3 rounded-md bg-surface-sunken px-4 py-3',
                disabled === true ? 'opacity-60' : null,
                className,
            )}
        >
            <Switch
                checked={checked}
                onChange={onChange}
                disabled={disabled}
                aria-label={label}
                describedBy={hint !== undefined ? hintId : undefined}
            />

            <span className="min-w-0 flex-1">
                <span className="block text-body text-fg-default">{label}</span>
                {hint !== undefined ? (
                    <span id={hintId} className="mt-0.5 block text-caption text-fg-muted">
                        {hint}
                    </span>
                ) : null}
            </span>
        </div>
    );
}
