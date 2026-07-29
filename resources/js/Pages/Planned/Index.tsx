import { Head } from '@inertiajs/react';
import { CheckCircle2, Clock } from 'lucide-react';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Badge } from '@/Components/ui/Badge';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { PageHeader } from '@/Components/ui/PageHeader';
import type { StatusTone } from '@/types';

interface PlannedScreen {
    title: string;
    phase: number;
    summary: string;
    items: string[];
}

interface PlannedProps {
    screen: PlannedScreen;
    systemStatus: { label: string; tone: StatusTone };
}

export default function Index({ screen, systemStatus }: PlannedProps) {
    return (
        <AdminLayout>
            <Head title={screen.title} />

            <PageHeader
                title={screen.title}
                description={screen.summary}
                systemStatus={systemStatus}
            />

            <Card>
                <CardHeader
                    title={screen.title}
                    description={screen.summary}
                    actions={
                        <Badge tone="info" dot>
                            المرحلة {screen.phase}
                        </Badge>
                    }
                />
                <CardBody className="space-y-6">
                    <div className="flex items-start gap-3 rounded-card bg-info-soft px-4 py-3 text-info">
                        <Clock aria-hidden className="mt-0.5 size-5 shrink-0" />
                        <p className="text-sm">
                            هذه الشاشة مخططة ضمن <strong>المرحلة {screen.phase}</strong> ولم تُنفّذ
                            بعد. البنية الخلفية والصلاحيات الخاصة بها جاهزة، وسيظهر محتواها هنا فور
                            تنفيذ المرحلة.
                        </p>
                    </div>

                    <div>
                        <h3 className="text-sm font-semibold text-fg-default">ما ستحتويه الشاشة</h3>
                        <ul className="mt-3 space-y-2">
                            {screen.items.map((item) => (
                                <li
                                    key={item}
                                    className="flex items-start gap-2 text-sm text-fg-muted"
                                >
                                    <CheckCircle2
                                        aria-hidden
                                        className="mt-0.5 size-4 shrink-0 text-fg-subtle"
                                    />
                                    {item}
                                </li>
                            ))}
                        </ul>
                    </div>
                </CardBody>
            </Card>
        </AdminLayout>
    );
}
