import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    BookPlus,
    EyeOff,
    MousePointerClick,
    Route,
    Star,
    TicketPlus,
    Wrench,
} from 'lucide-react';
import type { StatusTone } from '@/types';
import type { ConversationDetail } from '@/types/conversations';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Alert } from '@/Components/ui/Alert';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';
import { PageHeader } from '@/Components/ui/PageHeader';
import {
    formatDateTime,
    formatDuration,
    formatLatency,
    formatMoney,
    formatNumber,
    formatPercent,
} from '@/lib/format';
import { cn } from '@/lib/cn';

interface Props {
    systemStatus: { label: string; tone: StatusTone };
    conversation: ConversationDetail;
}

/** شاشة تفاصيل المحادثة — وثيقة 06 §6. */
export default function ConversationShow({ systemStatus, conversation }: Props) {
    const escalation = conversation.escalation_detail;

    return (
        <AdminLayout>
            <Head title={`المحادثة ${conversation.reference}`} />

            <PageHeader
                title={`المحادثة ${conversation.reference}`}
                description={`${conversation.section} · ${conversation.assistant ?? 'المساعد العام'} · ${formatDateTime(conversation.started_at)}`}
                systemStatus={systemStatus}
                actions={
                    <Link
                        href="/conversations"
                        className="inline-flex items-center gap-1.5 rounded-control px-3 py-1.5 text-caption font-bold text-accent hover:bg-accent-soft"
                    >
                        <ArrowRight aria-hidden className="size-4" />
                        كل المحادثات
                    </Link>
                }
            />

            {escalation !== null ? (
                <Alert
                    tone={escalation.severity.tone === 'accent' ? 'info' : escalation.severity.tone}
                    title={`حُوِّلت إلى ${escalation.target.label} — ${escalation.severity.label}`}
                >
                    {`السبب: ${escalation.reason}. `}
                    {escalation.resolved_at === null
                        ? 'الحالة ما زالت مفتوحة وتحتاج متابعة.'
                        : `أُغلقت في ${formatDateTime(escalation.resolved_at)}.`}
                </Alert>
            ) : null}

            <section
                aria-label="ملخص المحادثة"
                className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
            >
                <SummaryCard label="الحالة النهائية">
                    <Badge tone={conversation.outcome.tone} dot>
                        {conversation.outcome.label}
                    </Badge>
                    <p className="mt-2 text-caption text-fg-muted">
                        {conversation.resolved_first_answer
                            ? 'حُلَّت من أول إجابة'
                            : 'احتاجت أكثر من إجابة'}
                    </p>
                </SummaryCard>

                <SummaryCard label="النية المكتشفة">
                    <p className="text-title font-bold text-fg-default">
                        {conversation.detected_intent ?? 'لم تُحدَّد'}
                    </p>
                    <p className="mt-2 text-caption text-fg-muted">
                        {conversation.confidence === null
                            ? 'بلا درجة ثقة مسجّلة'
                            : `الثقة ${formatPercent(conversation.confidence)} · ${conversation.understood_intent ? 'فُهم السؤال' : 'لم يُفهم السؤال'}`}
                    </p>
                </SummaryCard>

                <SummaryCard label="الزمن والحجم">
                    <p className="text-title font-bold text-fg-default tabular-nums">
                        {formatDuration(conversation.duration_seconds)}
                    </p>
                    <p className="mt-2 text-caption text-fg-muted tabular-nums">
                        {formatNumber(conversation.message_count)} رسالة · أول رد{' '}
                        {formatLatency(conversation.first_response_ms)}
                    </p>
                </SummaryCard>

                <SummaryCard label="التوكن والتكلفة">
                    <p className="text-title font-bold text-fg-default tabular-nums">
                        {formatNumber(conversation.total_tokens)}
                    </p>
                    <p className="mt-2 text-caption text-fg-muted tabular-nums">
                        {formatMoney(conversation.cost, 'SAR', 3)} · {conversation.provider ?? '—'}
                        {conversation.model === null ? '' : ` / ${conversation.model}`}
                    </p>
                </SummaryCard>
            </section>

            <div className="grid items-start gap-4 lg:grid-cols-[1.35fr_1fr]">
                <Card>
                    <CardHeader
                        title="الرسائل"
                        description={
                            conversation.can_view_content
                                ? 'نص المحادثة كما جرت، بزمن كل رد'
                                : 'المسار والأزمنة ظاهرة، والنص محجوب عن دورك'
                        }
                    />
                    <CardBody className="flex flex-col gap-4">
                        {conversation.can_view_content ? null : (
                            <Alert tone="neutral" title="نص الرسائل محجوب">
                                دورك يرى مقاييس المحادثة ومسارها دون نصّها الخام — صلاحية «قراءة
                                محتوى المحادثات» غير ممنوحة لك.
                            </Alert>
                        )}

                        {conversation.messages.length === 0 ? (
                            <EmptyState
                                title="لا رسائل محفوظة"
                                description="قد تكون المحادثة أُنشئت دون حفظ المحتوى وفق سياسة الخصوصية."
                            />
                        ) : (
                            <ol className="flex flex-col gap-3">
                                {conversation.messages.map((message) => {
                                    const isUser = message.role.value === 'user';

                                    return (
                                        <li
                                            key={message.id}
                                            className={cn(
                                                'flex flex-col gap-1.5 rounded-card border p-4',
                                                isUser
                                                    ? 'border-border-default bg-surface-sunken'
                                                    : 'border-accent-soft bg-accent-soft',
                                            )}
                                        >
                                            <div className="flex items-center justify-between gap-3">
                                                <Badge tone={message.role.tone}>
                                                    {message.role.label}
                                                </Badge>
                                                <span className="text-micro text-fg-subtle tabular-nums">
                                                    {formatDateTime(message.created_at)}
                                                    {message.latency_ms === null
                                                        ? ''
                                                        : ` · ${formatLatency(message.latency_ms)}`}
                                                </span>
                                            </div>
                                            {message.content === null ? (
                                                <p className="flex items-center gap-1.5 text-body text-fg-subtle italic">
                                                    <EyeOff aria-hidden className="size-4" />
                                                    محجوب
                                                </p>
                                            ) : (
                                                <p className="text-body text-fg-default">
                                                    {message.content}
                                                </p>
                                            )}
                                            <p className="text-micro text-fg-subtle tabular-nums">
                                                {formatNumber(message.tokens)} توكن
                                            </p>
                                        </li>
                                    );
                                })}
                            </ol>
                        )}
                    </CardBody>
                </Card>

                {/* يلاصق أثناء قراءة نص طويل: الخط الزمني هو المرجع الذي يُقرأ النص عليه. */}
                <div className="flex flex-col gap-4 lg:sticky lg:top-6">
                    <Card>
                        <CardHeader
                            title="الخط الزمني ومسار الحل"
                            description="ما جرى خارج نص الرسائل"
                        />
                        <CardBody>
                            <Timeline conversation={conversation} />
                        </CardBody>
                    </Card>

                    <Card>
                        <CardHeader
                            title="تقييم الجودة"
                            description="ما يُبنى عليه قرار إضافة معرفة أو مراجعة"
                        />
                        <CardBody className="flex flex-col gap-3">
                            <QualityFlag
                                label="تقييم المستخدم"
                                value={
                                    conversation.rating === null
                                        ? 'لم يُقيَّم'
                                        : `${formatNumber(conversation.rating, 1)} من 5`
                                }
                                tone={
                                    conversation.rating === null
                                        ? 'neutral'
                                        : conversation.rating >= 4
                                          ? 'success'
                                          : 'warning'
                                }
                                icon={Star}
                            />
                            <QualityFlag
                                label="فهم النية"
                                value={conversation.understood_intent ? 'فُهمت' : 'لم تُفهم'}
                                tone={conversation.understood_intent ? 'success' : 'danger'}
                                icon={Route}
                            />
                            <QualityFlag
                                label="إعادة الصياغة"
                                value={conversation.rephrased ? 'احتاجها المستخدم' : 'لم تُطلب'}
                                tone={conversation.rephrased ? 'warning' : 'success'}
                                icon={MousePointerClick}
                            />

                            <div className="flex flex-wrap gap-2 border-t border-border-default pt-4">
                                {/* الإجراءان يصلان بالمعرفة والتذاكر في الموجة الثالثة. */}
                                <Button variant="secondary" size="sm" disabled>
                                    <BookPlus aria-hidden className="size-4" />
                                    إضافة إلى قاعدة المعرفة
                                </Button>
                                <Button variant="ghost" size="sm" disabled>
                                    <TicketPlus aria-hidden className="size-4" />
                                    فتح تذكرة مراجعة
                                </Button>
                            </div>
                            <p className="text-micro text-fg-subtle">
                                الإجراءان يُفعَّلان مع شاشة قاعدة المعرفة.
                            </p>
                        </CardBody>
                    </Card>
                </div>
            </div>

            <Card>
                <CardHeader
                    title="الأدوات والبيانات المستدعاة"
                    description="ما طلبه المساعد من Hi-Share أثناء المحادثة"
                />
                <CardBody>
                    {conversation.tools.length === 0 ? (
                        <EmptyState
                            icon={Wrench}
                            title="لم تُستدعَ أي أداة"
                            description="أُجيب المستخدم من المعرفة المخزّنة دون قراءة بيانات حيّة."
                        />
                    ) : (
                        <ul className="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                            {conversation.tools.map((tool) => (
                                <li
                                    key={tool.id}
                                    className="flex items-start justify-between gap-3 rounded-card border border-border-default p-4"
                                >
                                    <div className="min-w-0">
                                        <p className="truncate text-body font-semibold text-fg-default">
                                            {tool.name}
                                        </p>
                                        <p className="mt-1 text-micro text-fg-subtle tabular-nums">
                                            {formatLatency(tool.duration_ms)}
                                        </p>
                                        {tool.error_message === null ? null : (
                                            <p className="mt-1 text-micro text-danger">
                                                {tool.error_message}
                                            </p>
                                        )}
                                    </div>
                                    <Badge tone={tool.outcome.tone}>{tool.outcome.label}</Badge>
                                </li>
                            ))}
                        </ul>
                    )}
                </CardBody>
            </Card>
        </AdminLayout>
    );
}

function Timeline({ conversation }: { conversation: ConversationDetail }) {
    const entries = [
        {
            key: 'start',
            label: 'بدأت المحادثة',
            detail: conversation.detected_intent ?? 'بلا نية محددة',
            at: conversation.started_at,
        },
        ...conversation.clicks.map((click) => ({
            key: `click-${String(click.id)}`,
            label: click.led_to_resolution ? 'نقرة أوصلت إلى الحل' : 'فتح شاشة داخل التطبيق',
            detail: click.screen,
            at: click.created_at,
        })),
        ...conversation.timeline.map((event) => ({
            key: `event-${String(event.id)}`,
            label: event.label,
            detail: event.detail ?? '',
            at: event.created_at,
        })),
    ].sort((a, b) => new Date(a.at).getTime() - new Date(b.at).getTime());

    if (entries.length === 0) {
        return <EmptyState title="لا أحداث" description="لم تُسجَّل أحداث خارج الرسائل." />;
    }

    return (
        <ol className="relative flex flex-col gap-5 border-s border-border-default ps-5">
            {entries.map((entry) => (
                <li key={entry.key} className="relative">
                    <span
                        aria-hidden
                        className="absolute -start-[1.575rem] top-1.5 size-2.5 rounded-full border-2 border-surface-raised bg-accent"
                    />
                    <p className="text-body font-semibold text-fg-default">{entry.label}</p>
                    {entry.detail === '' ? null : (
                        <p className="mt-0.5 text-caption text-fg-muted">{entry.detail}</p>
                    )}
                    <p className="mt-0.5 text-micro text-fg-subtle tabular-nums">
                        {formatDateTime(entry.at)}
                    </p>
                </li>
            ))}
        </ol>
    );
}

function SummaryCard({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="rounded-card border border-border-default bg-surface-raised p-5 shadow-card">
            <p className="text-caption font-medium text-fg-muted">{label}</p>
            <div className="mt-2">{children}</div>
        </div>
    );
}

function QualityFlag({
    label,
    value,
    tone,
    icon: Icon,
}: {
    label: string;
    value: string;
    tone: StatusTone;
    icon: React.ComponentType<{ className?: string; 'aria-hidden'?: boolean }>;
}) {
    return (
        <div className="flex items-center justify-between gap-3">
            <span className="inline-flex items-center gap-2 text-body text-fg-muted">
                <Icon aria-hidden className="size-4 text-fg-subtle" />
                {label}
            </span>
            <Badge tone={tone}>{value}</Badge>
        </div>
    );
}
