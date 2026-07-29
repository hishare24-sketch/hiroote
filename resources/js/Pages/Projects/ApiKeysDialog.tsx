import { useForm, usePage } from '@inertiajs/react';
import { router } from '@inertiajs/react';
import { Copy, KeyRound, ShieldOff, X } from 'lucide-react';
import { useState } from 'react';
import { Alert } from '@/Components/ui/Alert';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';
import { formatRelative } from '@/lib/format';

export interface ApiKeyRow {
    id: number;
    name: string;
    prefix: string;
    status: string;
    usable: boolean;
    last_used_at: string | null;
    expires_at: string | null;
}

/**
 * مفاتيح جسر المشروع — وثيقة 02 §5.
 *
 * المفتاح يُعرض مرة واحدة عند إصداره ولا يُسترجع، لأنه مخزَّن مجزّأً لا مشفَّرًا.
 * والشاشة تقول ذلك صراحةً قبل الإصدار لا بعده: من يغلق النافذة ظنًّا أنه
 * سيجده لاحقًا يفقده.
 */
export function ApiKeysDialog({
    project,
    keys,
    onClose,
}: {
    project: { id: number; name: string };
    keys: ApiKeyRow[];
    onClose: () => void;
}) {
    const page = usePage<{ flash?: { issued_api_key?: string } }>();
    const issued = page.props.flash?.issued_api_key ?? null;
    const [copied, setCopied] = useState(false);

    const form = useForm({ name: '', expires_at: '' });

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label={`مفاتيح ${project.name}`}
            className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-fg-default/40 p-4 py-10"
        >
            <Card className="w-full max-w-xl">
                <CardHeader
                    title={`مفاتيح الوصول — ${project.name}`}
                    description="ما يستخدمه المشروع الخارجي ليسأل هاي روت عن سياق شاشاته"
                    actions={
                        <Button variant="ghost" size="sm" onClick={onClose}>
                            <X aria-hidden className="size-4" />
                            <span className="sr-only">إغلاق</span>
                        </Button>
                    }
                />

                <CardBody className="flex flex-col gap-4">
                    {issued === null ? null : (
                        <Alert tone="success" title="انسخه الآن — لن يُعرض ثانيةً">
                            <code
                                dir="ltr"
                                className="mt-1 block overflow-x-auto rounded-control bg-surface-sunken p-2 text-start text-caption"
                            >
                                {issued}
                            </code>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="mt-2"
                                onClick={() => {
                                    void navigator.clipboard.writeText(issued).then(() => {
                                        setCopied(true);
                                    });
                                }}
                            >
                                <Copy aria-hidden className="size-3.5" />
                                {copied ? 'نُسخ' : 'انسخ'}
                            </Button>
                        </Alert>
                    )}

                    <form
                        className="flex flex-col gap-3 rounded-card border border-border-default p-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post(`/projects/${String(project.id)}/keys`, {
                                preserveScroll: true,
                                onSuccess: () => {
                                    form.reset();
                                },
                            });
                        }}
                    >
                        <Input
                            label="اسم المفتاح"
                            required
                            placeholder="جسر هاي شير — الإنتاج"
                            value={form.data.name}
                            error={form.errors.name}
                            hint="يميّزه عند الإبطال؛ المفتاح نفسه لن يظهر بعد إصداره."
                            onChange={(event) => {
                                form.setData('name', event.target.value);
                            }}
                        />

                        <Input
                            label="ينتهي في (اختياري)"
                            type="date"
                            value={form.data.expires_at}
                            error={form.errors.expires_at}
                            onChange={(event) => {
                                form.setData('expires_at', event.target.value);
                            }}
                        />

                        <div className="flex justify-end">
                            <Button type="submit" size="sm" disabled={form.processing}>
                                <KeyRound aria-hidden className="size-3.5" />
                                أصدر مفتاحًا
                            </Button>
                        </div>
                    </form>

                    {keys.length === 0 ? (
                        <p className="text-caption text-fg-muted">لا مفاتيح بعد لهذا المشروع.</p>
                    ) : (
                        <ul className="flex flex-col divide-y divide-border-default">
                            {keys.map((key) => (
                                <li
                                    key={key.id}
                                    className="flex items-center justify-between gap-3 py-2.5 first:pt-0"
                                >
                                    <div className="min-w-0">
                                        <p className="flex items-center gap-2 text-body font-bold text-fg-default">
                                            {key.name}
                                            <Badge tone={key.usable ? 'success' : 'neutral'}>
                                                {key.status}
                                            </Badge>
                                        </p>
                                        <p
                                            dir="ltr"
                                            className="text-start text-caption text-fg-subtle"
                                        >
                                            {key.prefix}…
                                        </p>
                                        <p className="text-caption text-fg-muted">
                                            {key.last_used_at === null
                                                ? 'لم يُستخدم بعد'
                                                : `آخر استخدام ${formatRelative(key.last_used_at)}`}
                                        </p>
                                    </div>

                                    {key.usable ? (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => {
                                                router.delete(
                                                    `/projects/${String(project.id)}/keys/${String(key.id)}`,
                                                    { preserveScroll: true },
                                                );
                                            }}
                                        >
                                            <ShieldOff
                                                aria-hidden
                                                className="size-3.5 text-danger"
                                            />
                                            أبطِل
                                        </Button>
                                    ) : null}
                                </li>
                            ))}
                        </ul>
                    )}
                </CardBody>
            </Card>
        </div>
    );
}
