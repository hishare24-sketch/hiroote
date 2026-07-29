import { Head, useForm } from '@inertiajs/react';
import { Activity } from 'lucide-react';
import { Alert } from '@/Components/ui/Alert';
import { Button } from '@/Components/ui/Button';
import { Card, CardBody } from '@/Components/ui/Card';
import { Input } from '@/Components/ui/Input';

interface LoginProps {
    status: string | null;
}

export default function Login({ status }: LoginProps) {
    const { data, setData, post, processing, errors } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    return (
        <>
            <Head title="تسجيل الدخول" />

            <div className="flex min-h-screen items-center justify-center bg-surface-base p-4">
                <div className="w-full max-w-sm space-y-6">
                    <div className="flex flex-col items-center gap-2">
                        <Activity aria-hidden className="size-10 text-brand-600" />
                        <h1 className="text-xl font-bold text-fg-default">Hiroote AI</h1>
                        <p className="text-sm text-fg-muted">لوحة إدارة المساعد الذكي</p>
                    </div>

                    {status !== null ? <Alert tone="info" title={status} /> : null}

                    <Card>
                        <CardBody>
                            <form
                                className="space-y-4"
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    post('/login');
                                }}
                            >
                                <Input
                                    label="البريد الإلكتروني"
                                    type="email"
                                    autoComplete="username"
                                    required
                                    dir="ltr"
                                    value={data.email}
                                    onChange={(event) => {
                                        setData('email', event.target.value);
                                    }}
                                    error={errors.email}
                                />

                                <Input
                                    label="كلمة المرور"
                                    type="password"
                                    autoComplete="current-password"
                                    required
                                    dir="ltr"
                                    value={data.password}
                                    onChange={(event) => {
                                        setData('password', event.target.value);
                                    }}
                                    error={errors.password}
                                />

                                <label className="flex items-center gap-2 text-sm text-fg-muted">
                                    <input
                                        type="checkbox"
                                        checked={data.remember}
                                        onChange={(event) => {
                                            setData('remember', event.target.checked);
                                        }}
                                        className="size-4 rounded border-border-strong"
                                    />
                                    تذكرني
                                </label>

                                <Button type="submit" loading={processing} className="w-full">
                                    تسجيل الدخول
                                </Button>
                            </form>
                        </CardBody>
                    </Card>
                </div>
            </div>
        </>
    );
}
