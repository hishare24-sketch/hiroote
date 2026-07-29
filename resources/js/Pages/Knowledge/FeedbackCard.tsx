import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ClipboardCheck, Hand, RotateCcw, ShieldCheck, X } from 'lucide-react';
import type { FeedbackRow, KindOption } from '@/types/knowledge';
import { Badge } from '@/Components/ui/Badge';
import { Button } from '@/Components/ui/Button';
import { Select } from '@/Components/ui/Select';
import { formatNumber, formatRelative } from '@/lib/format';
import { cn } from '@/lib/cn';

/**
 * بطاقة رصد واحد ودورته — وثيقة 06 §15.
 *
 * الدورة: رصد ← فرز ← **تحقق ميداني** ← تحرير ← إغلاق. خطوة التحقق ليست زينة:
 * ما يرفعه المساعد إشارةٌ إحصائية، ولا يصير أساسًا لتعديل المحتوى قبل أن
 * يجرّبه إنسان على الشاشة نفسها بوصفه مستخدمًا.
 */
export function FeedbackCard({
    entry,
    outcomes,
    manage,
}: {
    entry: FeedbackRow;
    outcomes: KindOption[];
    manage: boolean;
}) {
    const [verifying, setVerifying] = useState(false);

    const form = useForm({ outcome: outcomes[0]?.value ?? 'reproduced', steps: '', finding: '' });

    const close = (resolution: 'fixed' | 'dismissed' | 'reopen'): void => {
        router.post(
            `/knowledge/feedback/${String(entry.id)}/close`,
            { resolution },
            { preserveScroll: true },
        );
    };

    return (
        <li className={cn('flex flex-col gap-2 py-3 first:pt-0', entry.resolved && 'opacity-60')}>
            <div className="flex flex-wrap items-center gap-2">
                <Badge tone={entry.kind.tone}>{entry.kind.label}</Badge>
                <Badge tone={entry.source.tone}>{entry.source.label}</Badge>

                {entry.screen === null ? null : (
                    <Badge tone="neutral">شاشة: {entry.screen.name}</Badge>
                )}

                {entry.occurrences > 1 ? (
                    <span className="text-caption font-bold text-fg-muted tabular-nums">
                        تكرر {formatNumber(entry.occurrences)} مرات
                    </span>
                ) : null}

                {entry.resolved ? (
                    <Badge tone={entry.resolution === 'fixed' ? 'success' : 'neutral'}>
                        {entry.resolution === 'fixed' ? 'عولج بتعديل' : 'استُبعد'}
                    </Badge>
                ) : entry.actionable ? (
                    <Badge tone="success">تحقَّق منه — يجوز التعديل</Badge>
                ) : (
                    <Badge tone="warning">ينتظر تحققًا ميدانيًا</Badge>
                )}

                <span className="ms-auto text-caption text-fg-subtle">
                    {formatRelative(entry.created_at)}
                </span>
            </div>

            <p className={cn('text-body text-fg-default', entry.resolved && 'line-through')}>
                {entry.body}
            </p>

            {entry.assignee === null ? null : (
                <p className="text-caption text-fg-muted">المسؤول: {entry.assignee}</p>
            )}

            {entry.verifications.length === 0 ? null : (
                <ol className="flex flex-col gap-2 border-s-2 border-border-default ps-3">
                    {entry.verifications.map((check) => (
                        <li key={check.id} className="flex flex-col gap-0.5">
                            <p className="flex flex-wrap items-center gap-2 text-caption">
                                <Badge tone={check.outcome.tone}>{check.outcome.label}</Badge>
                                <span className="text-fg-muted">
                                    {check.verifier ?? 'غير معروف'}
                                    {check.screen === null ? '' : ` · ${check.screen}`}
                                </span>
                                <span className="text-fg-subtle">
                                    {formatRelative(check.created_at)}
                                </span>
                            </p>
                            <p className="text-caption whitespace-pre-wrap text-fg-default">
                                <span className="text-fg-subtle">ما جُرِّب: </span>
                                {check.steps}
                            </p>
                            {check.finding === null ? null : (
                                <p className="text-caption whitespace-pre-wrap text-fg-muted">
                                    <span className="text-fg-subtle">ما وُجد: </span>
                                    {check.finding}
                                </p>
                            )}
                        </li>
                    ))}
                </ol>
            )}

            {!manage ? null : entry.resolved ? (
                <div>
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            close('reopen');
                        }}
                    >
                        <RotateCcw aria-hidden className="size-3.5" />
                        أعد فتحه
                    </Button>
                </div>
            ) : (
                <div className="flex flex-wrap items-center gap-1.5">
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            router.post(
                                `/knowledge/feedback/${String(entry.id)}/assign`,
                                {},
                                { preserveScroll: true },
                            );
                        }}
                    >
                        <Hand aria-hidden className="size-3.5" />
                        {entry.assignee === null ? 'أسنِده لي' : 'ارفع يدك'}
                    </Button>

                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            setVerifying((value) => !value);
                        }}
                    >
                        <ClipboardCheck aria-hidden className="size-3.5" />
                        سجّل تحققًا
                    </Button>

                    <Button
                        variant="ghost"
                        size="sm"
                        disabled={!entry.actionable}
                        title={
                            entry.actionable
                                ? undefined
                                : 'يلزم تحقق ميداني يثبت الرصد قبل إغلاقه بوصفه معالَجًا.'
                        }
                        onClick={() => {
                            close('fixed');
                        }}
                    >
                        <ShieldCheck aria-hidden className="size-3.5 text-success" />
                        عولج بتعديل
                    </Button>

                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            close('dismissed');
                        }}
                    >
                        <X aria-hidden className="size-3.5" />
                        استبعده
                    </Button>
                </div>
            )}

            {!verifying || entry.resolved ? null : (
                <form
                    className="flex flex-col gap-2 rounded-card border border-border-default p-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        form.post(`/knowledge/feedback/${String(entry.id)}/verify`, {
                            preserveScroll: true,
                            onSuccess: () => {
                                setVerifying(false);
                                form.reset();
                            },
                        });
                    }}
                >
                    <p className="text-caption text-fg-muted">
                        ادخل هاي شير بوصفك مستخدمًا، جرّب ما وصفه الرصد على الشاشة نفسها، ثم سجّل ما
                        فعلتَه وما وجدتَه.
                    </p>

                    <Select
                        label="النتيجة"
                        options={outcomes.map((outcome) => ({
                            value: outcome.value,
                            label: outcome.label,
                        }))}
                        value={form.data.outcome}
                        onChange={(event) => {
                            form.setData('outcome', event.target.value);
                        }}
                    />

                    <label className="flex flex-col gap-1.5">
                        <span className="text-body font-medium text-fg-default">
                            ما فعلتُه بوصفي مستخدمًا
                        </span>
                        <textarea
                            rows={3}
                            required
                            value={form.data.steps}
                            onChange={(event) => {
                                form.setData('steps', event.target.value);
                            }}
                            className="bg-surface-default rounded-control border border-border-default px-3 py-2 text-body text-fg-default"
                        />
                        {form.errors.steps === undefined ? null : (
                            <span className="text-caption text-danger">{form.errors.steps}</span>
                        )}
                    </label>

                    <label className="flex flex-col gap-1.5">
                        <span className="text-body font-medium text-fg-default">ما وجدتُه</span>
                        <textarea
                            rows={2}
                            value={form.data.finding}
                            onChange={(event) => {
                                form.setData('finding', event.target.value);
                            }}
                            className="bg-surface-default rounded-control border border-border-default px-3 py-2 text-body text-fg-default"
                        />
                    </label>

                    <div className="flex justify-end gap-2">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            onClick={() => {
                                setVerifying(false);
                            }}
                        >
                            إلغاء
                        </Button>
                        <Button type="submit" size="sm" disabled={form.processing}>
                            احفظ المحضر
                        </Button>
                    </div>
                </form>
            )}
        </li>
    );
}
