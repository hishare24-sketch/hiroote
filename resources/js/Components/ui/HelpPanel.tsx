import { usePage } from '@inertiajs/react';
import { useEffect, useReducer, useState } from 'react';
import { AlertTriangle, HelpCircle, UserCog, X } from 'lucide-react';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';

interface HelpTopic {
    screen: string;
    title: string;
    purpose: string;
    reading: { heading: string; body: string }[];
    traps: string[];
    role_note: string | null;
    role_label: string | null;
}

/**
 * ذاكرة الجلسة: الشرح لا يتغيّر بين تنقّل وآخر، فجلبه مرة واحدة يكفي.
 * `null` تعني «طُلب ولا شرح لهذه الشاشة» — تُميَّز عن «لم يُطلب بعد».
 */
const cache = new Map<string, HelpTopic | null>();

/**
 * أيقونة شرح الشاشة — وثيقة 06 §18.
 *
 * موضعها في `PageHeader` لا في كل صفحة: شاشةٌ جديدة تحصل على أيقونتها بلا أن
 * يتذكّر أحد إضافتها، وشاشةٌ بلا شرح تُكشف باختبارٍ لا بعين قارئ.
 */
export function HelpPanel() {
    const { component } = usePage();
    const [open, setOpen] = useState(false);

    // الشرح مشتقٌّ من الذاكرة لا محفوظٌ في حالة موازية: نسختان لحقيقة واحدة
    // تتباعدان عند أول تنقّل بين شاشتين.
    const [, redraw] = useReducer((tick: number): number => tick + 1, 0);
    const topic: HelpTopic | null | undefined = cache.has(component)
        ? (cache.get(component) ?? null)
        : undefined;

    useEffect(() => {
        if (!open || cache.has(component)) {
            return;
        }

        void fetch(`/help/topic?screen=${encodeURIComponent(component)}`, {
            headers: { Accept: 'application/json' },
        })
            .then((response) => (response.ok ? (response.json() as Promise<HelpTopic>) : null))
            .then((data) => {
                cache.set(component, data);
                redraw();
            })
            .catch(() => {
                cache.set(component, null);
                redraw();
            });
    }, [open, component]);

    useEffect(() => {
        if (!open) {
            return;
        }

        const close = (event: KeyboardEvent): void => {
            if (event.key === 'Escape') {
                setOpen(false);
            }
        };

        window.addEventListener('keydown', close);

        return () => {
            window.removeEventListener('keydown', close);
        };
    }, [open]);

    return (
        <>
            <button
                type="button"
                aria-label="شرح هذه الشاشة"
                title="شرح هذه الشاشة"
                onClick={() => {
                    setOpen(true);
                }}
                className="inline-flex size-8 shrink-0 items-center justify-center rounded-control text-fg-subtle transition-colors hover:bg-accent-soft hover:text-accent"
            >
                <HelpCircle aria-hidden className="size-5" />
            </button>

            {!open ? null : (
                <div
                    role="dialog"
                    aria-modal="true"
                    aria-label="شرح الشاشة"
                    className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-fg-default/40 p-4 py-10"
                    onClick={(event) => {
                        if (event.target === event.currentTarget) {
                            setOpen(false);
                        }
                    }}
                >
                    <Card className="w-full max-w-xl">
                        <CardHeader
                            title={topic?.title ?? 'شرح الشاشة'}
                            description={topic === undefined ? 'جارٍ التحميل…' : undefined}
                            actions={
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => {
                                        setOpen(false);
                                    }}
                                >
                                    <X aria-hidden className="size-4" />
                                    <span className="sr-only">إغلاق</span>
                                </Button>
                            }
                        />

                        <CardBody className="flex flex-col gap-4">
                            {topic === undefined ? (
                                <p className="text-body text-fg-muted">جارٍ التحميل…</p>
                            ) : topic === null ? (
                                <p className="text-body text-fg-muted">
                                    لا شرح مسجَّل لهذه الشاشة بعد.
                                </p>
                            ) : (
                                <>
                                    <p className="text-body text-fg-default">{topic.purpose}</p>

                                    {topic.reading.length === 0 ? null : (
                                        <section className="flex flex-col gap-2">
                                            <h3 className="text-caption font-bold text-fg-muted">
                                                كيف تقرأ هذه الشاشة
                                            </h3>
                                            {topic.reading.map((part) => (
                                                <div
                                                    key={part.heading}
                                                    className="rounded-card border border-border-default p-3"
                                                >
                                                    <p className="text-body font-bold text-fg-default">
                                                        {part.heading}
                                                    </p>
                                                    <p className="mt-0.5 text-caption text-fg-muted">
                                                        {part.body}
                                                    </p>
                                                </div>
                                            ))}
                                        </section>
                                    )}

                                    {topic.traps.length === 0 ? null : (
                                        <section className="flex flex-col gap-2">
                                            <h3 className="flex items-center gap-1.5 text-caption font-bold text-warning">
                                                <AlertTriangle aria-hidden className="size-4" />
                                                ما قد يضلّلك
                                            </h3>
                                            <ul className="flex flex-col gap-1.5">
                                                {topic.traps.map((trap) => (
                                                    <li
                                                        key={trap}
                                                        className="flex gap-2 text-caption text-fg-default"
                                                    >
                                                        <span
                                                            aria-hidden
                                                            className="mt-2 size-1.5 shrink-0 rounded-full bg-warning"
                                                        />
                                                        {trap}
                                                    </li>
                                                ))}
                                            </ul>
                                        </section>
                                    )}

                                    {topic.role_note === null ? null : (
                                        <section className="flex flex-col gap-1.5 rounded-card bg-accent-soft p-3">
                                            <h3 className="flex items-center gap-1.5 text-caption font-bold text-accent">
                                                <UserCog aria-hidden className="size-4" />
                                                يخصّك بوصفك
                                                {topic.role_label === null ? null : (
                                                    <Badge tone="accent">{topic.role_label}</Badge>
                                                )}
                                            </h3>
                                            <p className="text-caption text-fg-default">
                                                {topic.role_note}
                                            </p>
                                        </section>
                                    )}
                                </>
                            )}
                        </CardBody>
                    </Card>
                </div>
            )}
        </>
    );
}
