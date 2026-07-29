import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ImageUp, Trash2, X } from 'lucide-react';
import type { ScreenRow } from '@/types/knowledge';
import { Alert } from '@/Components/ui/Alert';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';

/** قوائم العناصر والإجراءات والحالات تُكتب سطرًا لكل بند — أسهل من حقول متكررة. */
function toLines(values: string[]): string {
    return values.join('\n');
}

function fromLines(value: string): string[] {
    return value
        .split('\n')
        .map((line) => line.trim())
        .filter((line) => line !== '');
}

/**
 * محرّر شاشة القسم — وثيقة 06 §15.
 *
 * الصورة توثيق للمشغّل يكتب أمامها الوصف؛ ما يصل المساعد هو النص. لذلك يظهر
 * تنبيهٌ حين تُرفع صورة بلا وصف: قسمٌ يبدو موصوفًا وهو ليس كذلك أسوأ من قسم
 * فارغ يعلن فراغه.
 */
export function ScreenDialog({
    sectionId,
    screen,
    onClose,
}: {
    sectionId: number;
    screen?: ScreenRow;
    onClose: () => void;
}) {
    const editing = screen !== undefined;
    const [preview, setPreview] = useState<string | null>(screen?.image_url ?? null);

    const form = useForm<{
        name: string;
        key: string;
        path: string;
        description: string;
        elements: string;
        actions: string;
        states: string;
        image: File | null;
        remove_image: boolean;
    }>({
        name: screen?.name ?? '',
        key: screen?.key ?? '',
        path: screen?.path ?? '',
        description: screen?.description ?? '',
        elements: toLines(screen?.elements ?? []),
        actions: toLines(screen?.actions ?? []),
        states: toLines(screen?.states ?? []),
        image: null,
        remove_image: false,
    });

    const submit = (event: React.SyntheticEvent): void => {
        event.preventDefault();

        const url = editing
            ? `/knowledge/screens/${String(screen.id)}`
            : `/knowledge/sections/${String(sectionId)}/screens`;

        form.transform((data) => ({
            ...data,
            elements: fromLines(data.elements),
            actions: fromLines(data.actions),
            states: fromLines(data.states),
        }));

        // POST في الحالتين: رفع الملف يحتاج multipart، ولا يحمله PUT في المتصفح.
        form.post(url, { forceFormData: true, preserveScroll: true, onSuccess: onClose });
    };

    return (
        <div
            role="dialog"
            aria-modal="true"
            aria-label={editing ? 'تعديل شاشة' : 'شاشة جديدة'}
            className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-fg-default/40 p-4 py-10"
        >
            <Card className="w-full max-w-2xl">
                <CardHeader
                    title={editing ? 'تعديل الشاشة' : 'شاشة جديدة'}
                    description="ما يراه المستخدم أمامه، بعناصره وإجراءاته وحالاته"
                    actions={
                        <Button variant="ghost" size="sm" onClick={onClose}>
                            <X aria-hidden className="size-4" />
                            <span className="sr-only">إغلاق</span>
                        </Button>
                    }
                />

                <CardBody>
                    <form className="flex flex-col gap-4" onSubmit={submit}>
                        <Input
                            label="اسم الشاشة"
                            required
                            value={form.data.name}
                            error={form.errors.name}
                            hint="كما يعرفها المستخدم، مثل: طلب سحب."
                            onChange={(event) => {
                                form.setData('name', event.target.value);
                            }}
                        />

                        <div className="grid gap-3 sm:grid-cols-2">
                            <Input
                                label="مفتاح الشاشة"
                                dir="ltr"
                                placeholder="wallet.withdraw"
                                value={form.data.key}
                                error={form.errors.key}
                                hint="ما يرسله المشروع عند فتح الشات من هذه الشاشة."
                                onChange={(event) => {
                                    form.setData('key', event.target.value);
                                }}
                            />

                            <Input
                                label="المسار"
                                dir="ltr"
                                placeholder="/wallet/withdraw"
                                value={form.data.path}
                                error={form.errors.path}
                                hint="للتوثيق فقط — المطابقة تتم بالمفتاح."
                                onChange={(event) => {
                                    form.setData('path', event.target.value);
                                }}
                            />
                        </div>

                        <Field
                            label="وصف الشاشة واستخدامها"
                            rows={4}
                            required
                            value={form.data.description}
                            error={form.errors.description}
                            hint="ما تفعله هذه الشاشة، ومتى يصل إليها المستخدم، وما الذي يلتبس عليه فيها."
                            onChange={(value) => {
                                form.setData('description', value);
                            }}
                        />

                        <div className="grid gap-3 md:grid-cols-3">
                            <Field
                                label="العناصر"
                                rows={5}
                                value={form.data.elements}
                                hint="بند لكل سطر"
                                onChange={(value) => {
                                    form.setData('elements', value);
                                }}
                            />
                            <Field
                                label="الإجراءات"
                                rows={5}
                                value={form.data.actions}
                                hint="بند لكل سطر"
                                onChange={(value) => {
                                    form.setData('actions', value);
                                }}
                            />
                            <Field
                                label="الحالات"
                                rows={5}
                                value={form.data.states}
                                hint="بند لكل سطر"
                                onChange={(value) => {
                                    form.setData('states', value);
                                }}
                            />
                        </div>

                        <div className="flex flex-col gap-2 rounded-card border border-border-default p-3">
                            <span className="text-body font-medium text-fg-default">
                                صورة الشاشة
                            </span>

                            {preview === null ? null : (
                                <img
                                    src={preview}
                                    alt="معاينة صورة الشاشة"
                                    className="max-h-64 w-full rounded-control object-contain"
                                />
                            )}

                            <div className="flex flex-wrap items-center gap-2">
                                <label className="inline-flex cursor-pointer items-center gap-1.5 rounded-control bg-surface-sunken px-3 py-1.5 text-caption font-bold text-fg-default hover:bg-accent-soft">
                                    <ImageUp aria-hidden className="size-4" />
                                    اختر صورة
                                    <input
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp"
                                        className="sr-only"
                                        onChange={(event) => {
                                            const file = event.target.files?.[0] ?? null;
                                            form.setData('image', file);
                                            form.setData('remove_image', false);
                                            setPreview(
                                                file === null ? null : URL.createObjectURL(file),
                                            );
                                        }}
                                    />
                                </label>

                                {preview === null ? null : (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        onClick={() => {
                                            form.setData('image', null);
                                            form.setData('remove_image', true);
                                            setPreview(null);
                                        }}
                                    >
                                        <Trash2 aria-hidden className="size-3.5 text-danger" />
                                        إزالة
                                    </Button>
                                )}
                            </div>

                            {form.errors.image === undefined ? null : (
                                <p className="text-caption text-danger">{form.errors.image}</p>
                            )}

                            <p className="text-caption text-fg-muted">
                                PNG أو JPG أو WebP، حتى ٤ ميغابايت.
                            </p>
                        </div>

                        {preview !== null && form.data.description.trim() === '' ? (
                            <Alert tone="warning" title="الصورة وحدها لا تصل المساعد">
                                المساعد يقرأ الوصف لا الصورة. شاشةٌ بصورة وبلا وصف تبدو موثّقة وهي
                                ليست كذلك.
                            </Alert>
                        ) : null}

                        <div className="flex justify-end gap-2">
                            <Button type="button" variant="ghost" onClick={onClose}>
                                إلغاء
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {editing ? 'حفظ' : 'إضافة الشاشة'}
                            </Button>
                        </div>
                    </form>
                </CardBody>
            </Card>
        </div>
    );
}

function Field({
    label,
    rows,
    value,
    hint,
    error,
    required,
    onChange,
}: {
    label: string;
    rows: number;
    value: string;
    hint?: string;
    error?: string;
    required?: boolean;
    onChange: (value: string) => void;
}) {
    return (
        <label className="flex flex-col gap-1.5">
            <span className="text-body font-medium text-fg-default">{label}</span>
            <textarea
                rows={rows}
                required={required}
                value={value}
                onChange={(event) => {
                    onChange(event.target.value);
                }}
                className="bg-surface-default rounded-control border border-border-default px-3 py-2 text-body text-fg-default"
            />
            {error === undefined ? null : <span className="text-caption text-danger">{error}</span>}
            {hint === undefined ? null : <span className="text-caption text-fg-muted">{hint}</span>}
        </label>
    );
}
