import { Head, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, Check, FlaskConical, Minus, Send } from 'lucide-react';
import type { StatusTone } from '@/types';
import type { SelectOptionPayload } from '@/types/assistants';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Alert } from '@/Components/ui/Alert';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { PageHeader } from '@/Components/ui/PageHeader';
import { Select } from '@/Components/ui/Select';
import { formatNumber } from '@/lib/format';

interface Readiness {
    provider: string | null;
    has_driver: boolean;
    has_model: boolean;
    has_credential: boolean;
    supported: string[];
}

interface Outcome {
    ok: boolean;
    text: string;
    error: string | null;
    input_tokens: number | null;
    output_tokens: number | null;
    cost: number | null;
    latency_ms: number;
    provider: string | null;
    model: string | null;
    failed_over: boolean;
    conversation_id: number | null;
}

interface Props {
    systemStatus: { label: string; tone: StatusTone };
    project: { id: number; name: string };
    readiness: Readiness;
    screens: SelectOptionPayload[];
    sections: SelectOptionPayload[];
    levels: SelectOptionPayload[];
}

/**
 * الاختبار والتجربة — بالمسار الحقيقي لا بمسارٍ خاصّ.
 *
 * نفس الطبقة التي يسلكها كل نداء: نفس المزود والمفتاح والمعرفة والمحاسبة.
 * شاشةُ تجربةٍ بمسارٍ خاصّ تُطمئن على ما لا يعمل في الإنتاج.
 */
export default function PlaygroundIndex({
    systemStatus,
    project,
    readiness,
    screens,
    sections,
    levels,
}: Props) {
    const flash = usePage().props.flash as { playground?: Outcome } | undefined;
    const outcome = flash?.playground ?? null;

    const form = useForm({
        message: '',
        screen: screens[0]?.value ?? '',
        section: sections[0]?.value ?? '',
        level: levels[1]?.value ?? levels[0]?.value ?? '',
    });

    const ready =
        readiness.provider !== null &&
        readiness.has_driver &&
        readiness.has_model &&
        readiness.has_credential;

    return (
        <AdminLayout>
            <Head title="الاختبار والتجربة" />

            <PageHeader
                title="الاختبار والتجربة"
                description={`اسأل مساعد ${project.name} بالمسار الحقيقي`}
                systemStatus={systemStatus}
            />

            <Alert tone="warning" title="هذه ليست محاكاة">
                ما تجرّبه هنا يمرّ بنفس الطبقة التي يمرّ بها كل نداء — فيُسجَّل محادثةً، وتُحتسب
                رموزه وكلفته في الاستهلاك. جرّب بما يكفي لا أكثر.
            </Alert>

            <ReadinessCard readiness={readiness} />

            <Card>
                <CardHeader
                    title="السؤال"
                    description="اختر الموضع الذي تفترض أن المستخدم يقف فيه — السياق يُبنى منه"
                />
                <CardBody>
                    <form
                        className="flex flex-col gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/playground', { preserveScroll: true });
                        }}
                    >
                        <div className="grid gap-3 md:grid-cols-3">
                            <Select
                                label="الشاشة"
                                options={[{ value: '', label: 'بلا شاشة' }, ...screens]}
                                value={form.data.screen}
                                error={form.errors.screen}
                                onChange={(event) => {
                                    form.setData('screen', event.target.value);
                                }}
                            />
                            <Select
                                label="القسم"
                                options={[{ value: '', label: 'بلا قسم' }, ...sections]}
                                value={form.data.section}
                                error={form.errors.section}
                                onChange={(event) => {
                                    form.setData('section', event.target.value);
                                }}
                            />
                            <Select
                                label="المستوى"
                                options={levels}
                                value={form.data.level}
                                error={form.errors.level}
                                onChange={(event) => {
                                    form.setData('level', event.target.value);
                                }}
                            />
                        </div>

                        <label className="flex flex-col gap-1.5">
                            <span className="text-caption font-medium text-fg-default">
                                نصّ المستخدم
                            </span>
                            <textarea
                                required
                                rows={4}
                                className="bg-surface-default rounded-control border border-border-default p-3 text-body text-fg-default outline-none focus:border-accent"
                                value={form.data.message}
                                onChange={(event) => {
                                    form.setData('message', event.target.value);
                                }}
                            />
                            {form.errors.message === undefined ? null : (
                                <span className="text-caption text-danger">
                                    {form.errors.message}
                                </span>
                            )}
                        </label>

                        <div className="flex justify-end">
                            <Button type="submit" disabled={form.processing || !ready}>
                                <Send aria-hidden className="size-4" />
                                {form.processing ? 'ينتظر المزود…' : 'أرسل'}
                            </Button>
                        </div>
                    </form>
                </CardBody>
            </Card>

            {outcome === null ? null : <OutcomeCard outcome={outcome} />}
        </AdminLayout>
    );
}

/** ثلاثة شروط، كلٌّ يُقال على حدة: «غير جاهز» وحدها تترك المشغّل يجرّب عشوائيًّا. */
function ReadinessCard({ readiness }: { readiness: Readiness }) {
    const rows: { label: string; ok: boolean; note: string }[] = [
        {
            label: 'مزود نشط',
            ok: readiness.provider !== null,
            note: readiness.provider ?? 'فعّل واحدًا من شاشة المزودين',
        },
        {
            label: 'مهايئ يخدمه',
            ok: readiness.has_driver,
            note: `المدعوم في هذه النسخة: ${readiness.supported.join(' · ')}`,
        },
        {
            label: 'نموذج مفعَّل',
            ok: readiness.has_model,
            note: 'من شاشة المزودين ← النماذج',
        },
        {
            label: 'مفتاح فعّال',
            ok: readiness.has_credential,
            note: 'مخزَّن مشفَّرًا ولا يُعرض بعد الحفظ',
        },
    ];

    return (
        <Card>
            <CardHeader title="الجاهزية" description="ما ينقص النداءَ الحقيقي — كلٌّ على حدة" />
            <CardBody className="grid gap-2 md:grid-cols-2 xl:grid-cols-4">
                {rows.map((row) => (
                    <div
                        key={row.label}
                        className="flex flex-col gap-1 rounded-card border border-border-default p-3"
                    >
                        <span className="flex items-center gap-2 text-caption font-bold text-fg-default">
                            {row.ok ? (
                                <Check aria-hidden className="size-4 text-success" />
                            ) : (
                                <Minus aria-hidden className="size-4 text-danger" />
                            )}
                            {row.label}
                        </span>
                        <span className="text-micro text-fg-subtle">{row.note}</span>
                    </div>
                ))}
            </CardBody>
        </Card>
    );
}

function OutcomeCard({ outcome }: { outcome: Outcome }) {
    return (
        <Card>
            <CardHeader
                title="النتيجة"
                description={
                    outcome.ok
                        ? `${outcome.provider ?? '—'} · ${outcome.model ?? '—'}`
                        : 'أخفق النداء'
                }
            />
            <CardBody className="flex flex-col gap-3">
                {outcome.failed_over ? (
                    <Alert tone="warning" title="حُوِّل إلى مزود آخر">
                        أخفق المزود النشط، ونجح المرشّح التالي. راجع شاشة المزودين.
                    </Alert>
                ) : null}

                {outcome.ok ? (
                    <p className="rounded-card bg-surface-sunken p-4 text-body whitespace-pre-wrap text-fg-default">
                        {outcome.text}
                    </p>
                ) : (
                    <p className="flex items-start gap-2 rounded-card border border-danger/40 p-4 text-body text-danger">
                        <AlertTriangle aria-hidden className="mt-0.5 size-4 shrink-0" />
                        {outcome.error}
                    </p>
                )}

                <div className="flex flex-wrap gap-x-6 gap-y-2 border-t border-border-default pt-3">
                    <Fact
                        label="رموز الإدخال"
                        // المزود الذي لم يرسل محاسبته لا تُقدَّر رموزه.
                        value={
                            outcome.input_tokens === null
                                ? 'لم يرسلها المزود'
                                : formatNumber(outcome.input_tokens)
                        }
                    />
                    <Fact
                        label="رموز الإخراج"
                        value={
                            outcome.output_tokens === null
                                ? 'لم يرسلها المزود'
                                : formatNumber(outcome.output_tokens)
                        }
                    />
                    <Fact
                        label="الكلفة"
                        // نموذجٌ بلا تسعير: صفرٌ يُقرأ «مجاني» لا «غير مُسعَّر».
                        value={
                            outcome.cost === null
                                ? 'النموذج غير مُسعَّر'
                                : `$${outcome.cost.toFixed(6)}`
                        }
                    />
                    <Fact label="الزمن" value={`${formatNumber(outcome.latency_ms)} م.ث`} />
                    {outcome.conversation_id === null ? null : (
                        <span className="flex items-center gap-2">
                            <FlaskConical aria-hidden className="size-4 text-fg-subtle" />
                            <Badge tone="neutral">
                                سُجّلت محادثةً رقم {formatNumber(outcome.conversation_id)}
                            </Badge>
                        </span>
                    )}
                </div>
            </CardBody>
        </Card>
    );
}

function Fact({ label, value }: { label: string; value: string }) {
    return (
        <span className="flex flex-col gap-0.5">
            <span className="text-caption text-fg-muted">{label}</span>
            <span className="text-title font-bold text-fg-default tabular-nums">{value}</span>
        </span>
    );
}
