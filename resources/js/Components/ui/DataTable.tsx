import { cn } from '@/lib/cn';

export interface DataTableProps {
    /** عناوين الأعمدة بالترتيب. */
    columns: string[];
    children: React.ReactNode;
    /** وصف مقروء للقارئ الشاشي — الجدول بلا عنوان يظل مبهمًا. */
    caption: string;
    className?: string;
}

/**
 * جدول موحّد — إطار وتمرير أفقي وترويسة لاصقة.
 *
 * التمرير داخل الجدول لا في الصفحة: عمود إضافي في شاشة ضيقة يجب ألا يدفع
 * التخطيط كله جانبًا.
 */
export function DataTable({ columns, children, caption, className }: DataTableProps) {
    return (
        <div className={cn('overflow-x-auto', className)}>
            <table className="w-full min-w-[68rem] border-collapse text-start">
                <caption className="sr-only">{caption}</caption>
                <thead>
                    <tr className="border-b border-border-default bg-surface-sunken">
                        {columns.map((column) => (
                            <th
                                key={column}
                                scope="col"
                                className="px-3 py-3 text-start text-micro font-bold whitespace-nowrap text-fg-muted"
                            >
                                {column}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-border-default">{children}</tbody>
            </table>
        </div>
    );
}

export function Td({ children, className }: { children: React.ReactNode; className?: string }) {
    return <td className={cn('px-3 py-3 text-caption text-fg-muted', className)}>{children}</td>;
}
