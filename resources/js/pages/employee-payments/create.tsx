import { Head, useForm } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Employee } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'الرواتب',
        href: '/employee-payments',
    },
    {
        title: 'تسجيل راتب',
        href: '/employee-payments/create',
    },
];

export default function EmployeePaymentsCreate({
    employees,
}: {
    employees: Employee[];
}) {
    const presetEmployeeId =
        typeof window !== 'undefined'
            ? new URLSearchParams(window.location.search).get('employee_id')
            : null;

    const { data, setData, post, processing, errors } = useForm({
        employee_id: presetEmployeeId ?? '',
        amount: '',
        payment_date: new Date().toISOString().split('T')[0],
        notes: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/employee-payments');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="تسجيل راتب" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-col gap-3">
                    <Button
                        variant="ghost"
                        size="sm"
                        className="w-fit gap-2 text-muted-foreground"
                        onClick={() => window.history.back()}
                    >
                        <ArrowRight className="size-4" />
                        رجوع
                    </Button>
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">
                            تسجيل راتب جديد
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            سجّل راتباً أو سلفة لأحد الموظفين
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="max-w-2xl">
                    <Card className="gap-0 py-0">
                        <CardContent className="space-y-6 p-5 md:p-6">
                            <div className="grid gap-2">
                                <Label htmlFor="employee_id">الموظف</Label>
                                <Select
                                    value={data.employee_id}
                                    onValueChange={(value) =>
                                        setData('employee_id', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="اختر الموظف" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {employees.map((employee) => (
                                            <SelectItem
                                                key={employee.id}
                                                value={employee.id.toString()}
                                            >
                                                {employee.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.employee_id} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="amount">المبلغ</Label>
                                <Input
                                    id="amount"
                                    type="number"
                                    min="1"
                                    value={data.amount}
                                    onChange={(e) =>
                                        setData('amount', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.amount} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="payment_date">التاريخ</Label>
                                <Input
                                    id="payment_date"
                                    type="date"
                                    value={data.payment_date}
                                    onChange={(e) =>
                                        setData('payment_date', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.payment_date} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="notes">ملاحظات</Label>
                                <Textarea
                                    id="notes"
                                    value={data.notes}
                                    onChange={(e) =>
                                        setData('notes', e.target.value)
                                    }
                                />
                                <InputError message={errors.notes} />
                            </div>

                            <Button type="submit" disabled={processing}>
                                حفظ
                            </Button>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
