import { Head, router } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    HandCoins,
    Pencil,
    Plus,
    Trash2,
    UserCog,
} from 'lucide-react';
import { useState } from 'react';
import { EmployeeFormDialog } from '@/components/employees/employee-form-dialog';
import { SalaryFormDialog } from '@/components/employees/salary-form-dialog';
import ForeignOrigin from '@/components/money/foreign-origin';
import { MonthPicker } from '@/components/month-picker';
import { formatDate } from '@/components/order-display';
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
import { formatSyp } from '@/lib/money';
import type { BreadcrumbItem, Employee, EmployeePayment } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'الموظفون',
        href: '/employees',
    },
];

const MONTH_LABEL = (month: string) =>
    new Date(`${month}-01T00:00:00`).toLocaleDateString('ar-SY', {
        month: 'long',
        year: 'numeric',
    });

function shiftMonth(month: string, delta: number) {
    const [y, m] = month.split('-').map(Number);
    const date = new Date(y, m - 1 + delta, 1);
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
}

export default function EmployeesIndex({
    employees,
    payments,
    month,
    total,
    todayRate,
}: {
    employees: Employee[];
    payments: EmployeePayment[];
    month: string;
    total: number;
    todayRate: string | null;
}) {
    const [employeeDialogOpen, setEmployeeDialogOpen] = useState(false);
    const [editingEmployee, setEditingEmployee] = useState<Employee | null>(
        null,
    );

    const [salaryDialogOpen, setSalaryDialogOpen] = useState(false);
    const [editingPayment, setEditingPayment] =
        useState<EmployeePayment | null>(null);
    const [salaryPresetId, setSalaryPresetId] = useState<number | null>(null);

    const openCreateEmployee = () => {
        setEditingEmployee(null);
        setEmployeeDialogOpen(true);
    };

    const openEditEmployee = (employee: Employee) => {
        setEditingEmployee(employee);
        setEmployeeDialogOpen(true);
    };

    const openCreateSalary = (presetEmployeeId: number | null = null) => {
        setEditingPayment(null);
        setSalaryPresetId(presetEmployeeId);
        setSalaryDialogOpen(true);
    };

    const openEditSalary = (payment: EmployeePayment) => {
        setSalaryPresetId(null);
        setEditingPayment(payment);
        setSalaryDialogOpen(true);
    };

    const goToMonth = (next: string) => {
        router.get(
            '/employees',
            { month: next },
            { preserveState: true, preserveScroll: true },
        );
    };

    const handleDeleteEmployee = (id: number) => {
        if (confirm('هل أنت متأكد من حذف هذا الموظف؟')) {
            router.delete(`/employees/${id}`, { preserveScroll: true });
        }
    };

    const handleDeletePayment = (id: number) => {
        if (confirm('هل أنت متأكد من حذف هذا الراتب؟')) {
            router.delete(`/employee-payments/${id}`, { preserveScroll: true });
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="الموظفون والرواتب" />

            <div className="flex h-full flex-1 flex-col gap-8 p-4 md:p-6">
                {/* Header */}
                <div className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">
                        الموظفون والرواتب
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        {employees.length} موظف في المختبر
                    </p>
                </div>

                {/* Employees */}
                <Card className="gap-0 overflow-hidden py-0">
                    <div className="flex items-center justify-between gap-4 border-b p-5">
                        <div className="space-y-0.5">
                            <h2 className="font-semibold">قائمة الموظفين</h2>
                            <p className="text-xs text-muted-foreground">
                                جميع الموظفين وإجمالي ما دُفع لكل منهم
                            </p>
                        </div>
                        <Button className="gap-2" onClick={openCreateEmployee}>
                            <Plus className="size-4" />
                            إضافة موظف
                        </Button>
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
                                            <TableCell className="font-semibold text-rose-600 tabular-nums dark:text-rose-400">
                                                {(
                                                    employee.payments_sum_amount ??
                                                    0
                                                ).toLocaleString('en-US')}
                                            </TableCell>
                                            <TableCell className="text-end">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        title="تسجيل راتب"
                                                        onClick={() =>
                                                            openCreateSalary(
                                                                employee.id,
                                                            )
                                                        }
                                                    >
                                                        <HandCoins className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        title="تعديل"
                                                        onClick={() =>
                                                            openEditEmployee(
                                                                employee,
                                                            )
                                                        }
                                                    >
                                                        <Pencil className="h-4 w-4" />
                                                    </Button>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        title="حذف"
                                                        onClick={() =>
                                                            handleDeleteEmployee(
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

                {/* Salaries */}
                <div className="space-y-4">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="space-y-0.5">
                            <h2 className="text-lg font-semibold">الرواتب</h2>
                            <p className="text-xs text-muted-foreground">
                                رواتب وسلف الموظفين حسب الشهر
                            </p>
                        </div>
                        <Button
                            className="gap-2 sm:w-auto"
                            onClick={() => openCreateSalary()}
                        >
                            <Plus className="size-4" />
                            تسجيل راتب
                        </Button>
                    </div>

                    {/* Month picker + total */}
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-2">
                            <Button
                                variant="outline"
                                size="icon"
                                onClick={() => goToMonth(shiftMonth(month, -1))}
                                title="الشهر السابق"
                            >
                                <ChevronRight className="size-4" />
                            </Button>
                            <MonthPicker value={month} onChange={goToMonth} />
                            <Button
                                variant="outline"
                                size="icon"
                                onClick={() => goToMonth(shiftMonth(month, 1))}
                                title="الشهر التالي"
                            >
                                <ChevronLeft className="size-4" />
                            </Button>
                        </div>
                        <Card className="py-0">
                            <CardContent className="flex items-center gap-3 px-5 py-3">
                                <span className="text-sm text-muted-foreground">
                                    إجمالي رواتب {MONTH_LABEL(month)}
                                </span>
                                <span className="text-lg font-bold text-rose-600 tabular-nums dark:text-rose-400">
                                    {total.toLocaleString('en-US')}
                                </span>
                            </CardContent>
                        </Card>
                    </div>

                    <Card className="gap-0 overflow-hidden py-0">
                        <CardContent className="p-0">
                            {payments.length === 0 ? (
                                <EmptyState
                                    icon={HandCoins}
                                    text="لا توجد رواتب مسجّلة في هذا الشهر"
                                />
                            ) : (
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>الموظف</TableHead>
                                            <TableHead>المبلغ</TableHead>
                                            <TableHead>التاريخ</TableHead>
                                            <TableHead>ملاحظات</TableHead>
                                            <TableHead className="text-end">
                                                الإجراءات
                                            </TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {payments.map((payment) => (
                                            <TableRow key={payment.id}>
                                                <TableCell className="font-medium">
                                                    {payment.employee?.name}
                                                </TableCell>
                                                <TableCell className="font-semibold text-rose-600 tabular-nums dark:text-rose-400">
                                                    {formatSyp(payment.amount)}
                                                    <ForeignOrigin
                                                        money={payment}
                                                        className="text-rose-700/70 dark:text-rose-400/70"
                                                    />
                                                </TableCell>
                                                <TableCell className="whitespace-nowrap text-muted-foreground">
                                                    {formatDate(
                                                        payment.payment_date ||
                                                            payment.created_at,
                                                    )}
                                                </TableCell>
                                                <TableCell className="text-muted-foreground">
                                                    {payment.notes || '-'}
                                                </TableCell>
                                                <TableCell className="text-end">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            title="تعديل"
                                                            onClick={() =>
                                                                openEditSalary(
                                                                    payment,
                                                                )
                                                            }
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            variant="destructive"
                                                            size="sm"
                                                            title="حذف"
                                                            onClick={() =>
                                                                handleDeletePayment(
                                                                    payment.id,
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
            </div>

            <EmployeeFormDialog
                open={employeeDialogOpen}
                onOpenChange={setEmployeeDialogOpen}
                employee={editingEmployee}
            />
            <SalaryFormDialog
                open={salaryDialogOpen}
                onOpenChange={setSalaryDialogOpen}
                employees={employees}
                payment={editingPayment}
                presetEmployeeId={salaryPresetId}
                todayRate={todayRate}
            />
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
