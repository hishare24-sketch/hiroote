import { Head } from '@inertiajs/react';
import { LayoutDashboard } from 'lucide-react';
import { AdminLayout } from '@/Layouts/AdminLayout';
import { Card, CardBody, CardHeader } from '@/Components/ui/Card';
import { EmptyState } from '@/Components/ui/EmptyState';

/**
 * شاشة النظرة العامة — وثيقة التصميم §4.
 *
 * Placeholder until المرحلة 2 wires real metrics: the layout, navigation,
 * theming and permission gating are the deliverable of المرحلة 1.
 */
export default function Index() {
    return (
        <AdminLayout title="نظرة عامة">
            <Head title="نظرة عامة" />

            <Card>
                <CardHeader
                    title="مؤشرات التشغيل"
                    description="تظهر البطاقات الإحصائية هنا بعد ربط محرك الاستهلاك والمحادثات في المرحلة الثانية."
                />
                <CardBody>
                    <EmptyState
                        icon={LayoutDashboard}
                        title="لا توجد بيانات بعد"
                        description="لم يتم تسجيل أي محادثات أو استهلاك. ستظهر المؤشرات فور تفعيل أول مزود وربط التكامل مع Hi-Share."
                    />
                </CardBody>
            </Card>
        </AdminLayout>
    );
}
