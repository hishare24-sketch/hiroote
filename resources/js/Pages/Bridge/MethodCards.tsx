import { Link } from '@inertiajs/react';
import {
    ArrowLeftRight,
    BellRing,
    KeyRound,
    Shield,
    Ticket,
    UserCog,
    Webhook,
    type LucideIcon,
} from 'lucide-react';
import type { Tone } from '@/types';
import { Badge } from '@/Components/ui/Badge';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';

export interface ConnectionMethod {
    key: string;
    icon: string;
    title: string;
    direction: string;
    summary: string;
    status: 'ready' | 'available' | 'planned';
    status_note: string;
    needs: string[];
    endpoints: string[];
    where: string;
    route: string | null;
}

const ICONS: Record<string, LucideIcon> = {
    key: KeyRound,
    'bell-ring': BellRing,
    'user-cog': UserCog,
    ticket: Ticket,
    webhook: Webhook,
    shield: Shield,
};

const STATUS: Record<ConnectionMethod['status'], { label: string; tone: Tone }> = {
    ready: { label: 'مُهيّأ', tone: 'success' },
    available: { label: 'متاح — غير مُهيّأ', tone: 'warning' },
    planned: { label: 'لم يُبنَ بعد', tone: 'neutral' },
};

/**
 * طرق الربط الممكنة بين الطرفين — وثيقة 07.
 *
 * الطريقة غير المبنيّة تُعرض ولا تُخفى: من لا يرى الـ Webhooks مذكورةً يظنّها
 * موجودة ويبحث عنها، أو يظنّها مستحيلة فيبني بديلًا لا يحتاجه. وإعلانها
 * «لم يُبنَ» يوفّر الحالتين.
 */
export function MethodCards({ methods }: { methods: ConnectionMethod[] }) {
    return (
        <Card>
            <CardHeader
                title="طرق الربط"
                description="كل مسار ممكن بين هاي روت والمشروع — وحالته الآن"
            />
            <CardBody className="grid gap-3 xl:grid-cols-2">
                {methods.map((method) => {
                    const Icon = ICONS[method.icon] ?? ArrowLeftRight;
                    const status = STATUS[method.status];

                    return (
                        <article
                            key={method.key}
                            className="flex flex-col gap-3 rounded-card border border-border-default p-4"
                        >
                            <div className="flex items-start gap-3">
                                <span
                                    aria-hidden
                                    className="flex size-10 shrink-0 items-center justify-center rounded-control bg-accent-soft text-accent"
                                >
                                    <Icon className="size-5" />
                                </span>

                                <div className="min-w-0 flex-1">
                                    <p className="flex flex-wrap items-center gap-2 text-body font-bold text-fg-default">
                                        {method.title}
                                        <Badge tone={status.tone}>{status.label}</Badge>
                                    </p>
                                    <p className="mt-0.5 flex items-center gap-1.5 text-caption text-fg-muted">
                                        <ArrowLeftRight aria-hidden className="size-3.5" />
                                        {method.direction}
                                    </p>
                                </div>
                            </div>

                            <p className="text-caption text-fg-default">{method.summary}</p>

                            <div className="flex flex-col gap-1.5">
                                <span className="text-caption font-bold text-fg-muted">
                                    ما يلزمه
                                </span>
                                <ul className="flex flex-col gap-1">
                                    {method.needs.map((need) => (
                                        <li
                                            key={need}
                                            className="flex gap-2 text-caption text-fg-default"
                                        >
                                            <span
                                                aria-hidden
                                                className="mt-2 size-1.5 shrink-0 rounded-full bg-border-strong"
                                            />
                                            {need}
                                        </li>
                                    ))}
                                </ul>
                            </div>

                            <ul className="flex flex-col gap-1">
                                {method.endpoints.map((endpoint) => (
                                    <li
                                        key={endpoint}
                                        dir="ltr"
                                        className="overflow-x-auto rounded-control bg-surface-sunken px-2.5 py-1 text-start text-caption text-fg-muted"
                                    >
                                        {endpoint}
                                    </li>
                                ))}
                            </ul>

                            <p className="mt-auto flex items-center justify-between gap-2 border-t border-border-default pt-2.5 text-caption">
                                <span className="text-fg-subtle">
                                    مكانه: <span className="text-fg-default">{method.where}</span>
                                </span>
                                <span className="text-fg-muted">{method.status_note}</span>
                            </p>

                            {method.route === null ? null : (
                                <Link
                                    href={method.route}
                                    className="rounded-control bg-surface-sunken px-3 py-1.5 text-center text-caption font-bold text-accent hover:bg-accent-soft"
                                >
                                    اذهب إلى {method.where}
                                </Link>
                            )}
                        </article>
                    );
                })}
            </CardBody>
        </Card>
    );
}
