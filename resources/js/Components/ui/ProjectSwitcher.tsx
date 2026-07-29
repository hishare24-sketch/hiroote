import { router, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Check, ChevronDown, LayoutGrid } from 'lucide-react';
import type { ProjectSummary, SharedProps } from '@/types';
import { cn } from '@/lib/cn';

/**
 * مبدّل المشاريع — ADR-0003 §4.
 *
 * يعرض المشاريع التي يملك المستخدم عضوية فيها فقط؛ القائمة تأتي من الخادم
 * مصفّاة أصلًا، فالإخفاء هنا عرضٌ لا حماية.
 */
export function ProjectSwitcher() {
    const { projectSwitcher } = usePage<SharedProps>().props;
    const [open, setOpen] = useState(false);
    const container = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        const close = (event: MouseEvent) => {
            if (!container.current?.contains(event.target as Node)) {
                setOpen(false);
            }
        };

        document.addEventListener('mousedown', close);

        return () => {
            document.removeEventListener('mousedown', close);
        };
    }, [open]);

    if (projectSwitcher.current === null) {
        return null;
    }

    const current = projectSwitcher.current;
    const only = projectSwitcher.available.length <= 1;

    const switchTo = (project: ProjectSummary) => {
        setOpen(false);

        if (project.id !== current.id) {
            router.post(`/projects/${String(project.id)}/switch`, {}, { preserveScroll: false });
        }
    };

    return (
        <div ref={container} className="relative">
            <button
                type="button"
                disabled={only}
                aria-haspopup="listbox"
                aria-expanded={open}
                onClick={() => {
                    setOpen((value) => !value);
                }}
                className={cn(
                    'flex w-full items-center gap-2.5 rounded-control border border-border-strong bg-surface-raised px-3 py-2.5 text-start transition-colors',
                    only ? 'cursor-default' : 'hover:bg-surface-sunken',
                )}
            >
                <span
                    aria-hidden
                    className="flex size-8 shrink-0 items-center justify-center rounded-control bg-accent-soft text-accent"
                >
                    <LayoutGrid className="size-4" />
                </span>

                <span className="min-w-0 flex-1">
                    <span className="block text-micro text-fg-subtle">المشروع</span>
                    <span className="block truncate text-body font-bold text-fg-default">
                        {current.name}
                    </span>
                </span>

                {only ? null : (
                    <ChevronDown
                        aria-hidden
                        className={cn(
                            'size-4 shrink-0 text-fg-subtle transition-transform',
                            open && 'rotate-180',
                        )}
                    />
                )}
            </button>

            {open ? (
                <ul
                    role="listbox"
                    aria-label="المشاريع المتاحة"
                    className="absolute inset-x-0 top-full z-20 mt-1.5 overflow-hidden rounded-card border border-border-strong bg-surface-raised py-1 shadow-raised"
                >
                    {projectSwitcher.available.map((project) => (
                        <li key={project.id}>
                            <button
                                type="button"
                                role="option"
                                aria-selected={project.id === current.id}
                                onClick={() => {
                                    switchTo(project);
                                }}
                                className="flex w-full items-center gap-2 px-3 py-2 text-start text-body text-fg-default hover:bg-surface-sunken"
                            >
                                <Check
                                    aria-hidden
                                    className={cn(
                                        'size-4 shrink-0 text-accent',
                                        project.id === current.id ? 'opacity-100' : 'opacity-0',
                                    )}
                                />
                                <span className="min-w-0 truncate">{project.name}</span>
                            </button>
                        </li>
                    ))}
                </ul>
            ) : null}
        </div>
    );
}
