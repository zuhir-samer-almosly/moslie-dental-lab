import { Head, Link, router } from '@inertiajs/react';
import { HandCoins, Pencil, Plus, Trash2, UserCog } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Employee } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'الموظفون',
        href: '/employees',
    },
];

export default function EmployeesIndex({
    employees,
}: {
    employees: Employee[];
}) {
    const handleDelete = (id: number) => {
        if (confirm('هل أنت متأكد من حذف هذا الموظف؟ سيتم حذف رواتبه أيضاً.')) {
            router.delete(`/employees/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="الموظفون" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">
                            الموظفون
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {employees.length} موظف في المختبر
                        </p>
                    </div>
                    <Button asChild size="lg" className="gap-2 sm:w-auto">
                        <Link href="/employees/create">
                            <Plus className="size-4" />
                            إضافة موظف
                        </Link>
                    </Button>
                </div>

                {/* Table */}
                <Card className="gap-0 overflow-hidden py-0">
                    <div className="flex items-center justify-between border-b p-5">
                        <div className="space-y-0.5">
                            <h2 className="font-semibold">قائمة الموظفين</h2>
                            <p className="text-xs text-muted-foreground">
                                جميع الموظفين وإجمالي ما دُفع لكل منهم
                            </p>
                        </div>
                    </div>
                    <CardContent className="p-0">
                        {employees.length === 0 ? (
                            <EmptyState
                                icon={UserCog}
                                text="لا يوجد موظفون بعد"
                            />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>الاسم</TableHead>
                                        <TableHead>المسمى الوظيفي</TableHead>
                                        <TableHead>الهاتف</TableHead>
                                        <TableHead>الحالة</TableHead>
                                        <TableHead>إجمالي المدفوع</TableHead>
                                        <TableHead className="text-end">
                                            الإجراءات
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {employees.map((employee) => (
                                        <TableRow key={employee.id}>
                                            <TableCell className="font-medium">
                                                {employee.name}
                                            </TableCell>
                                            <TableCell>
                                                {employee.role || '-'}
                                            </TableCell>
                                            <TableCell>
                                                {employee.phone || '-'}
                                            </TableCell>
                                            <TableCell>
                                                {employee.is_active ? (
                                                    <Badge variant="secondary">
                                                        نشط
                                                    </Badge>
                                                ) : (
                                                    <Badge variant="outline">
                                                        غير نشط
                                                    </Badge>
                                                )}
                                            </TableCell>
                                            <TableCell className="font-semibold tabular-nums text-rose-600 dark:text-rose-400">
                                                {(
                                                    employee.payments_sum_amount ??
                                                    0
                                                ).toLocaleString('en-US')}
                                            </TableCell>
                                            <TableCell className="text-end">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                        title="تسجيل راتب"
                                                    >
                                                        <Link
                                                            href={`/employee-payments/create?employee_id=${employee.id}`}
                                                        >
                                                            <HandCoins className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        <Link
                                                            href={`/employees/${employee.id}/edit`}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleDelete(
                                                                employee.id,
                                                            )
                                                        }
                                                    >
                                                        <Trash2 className="h-4 w-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}

function EmptyState({
    icon: Icon,
    text,
}: {
    icon: typeof UserCog;
    text: string;
}) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 py-16 text-center">
            <span className="flex size-10 items-center justify-center rounded-full bg-muted text-muted-foreground">
                <Icon className="size-5" />
            </span>
            <p className="text-sm text-muted-foreground">{text}</p>
        </div>
    );
}
