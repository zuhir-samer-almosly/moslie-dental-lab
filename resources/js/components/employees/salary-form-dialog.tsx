import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Combobox } from '@/components/ui/combobox';
import { DateInput } from '@/components/ui/date-input';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Employee, EmployeePayment } from '@/types';

const today = () => new Date().toISOString().split('T')[0];

export function SalaryFormDialog({
    open,
    onOpenChange,
    employees,
    payment,
    presetEmployeeId,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    employees: Employee[];
    payment?: EmployeePayment | null;
    presetEmployeeId?: number | null;
}) {
    const isEdit = Boolean(payment);

    // In edit mode show every employee (the paid one may be inactive);
    // otherwise only active employees can receive a new salary.
    const options = isEdit ? employees : employees.filter((e) => e.is_active);

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm({
            employee_id: '',
            amount: '',
            payment_date: today(),
            notes: '',
        });

    // Sync the form each time the dialog opens.
    useEffect(() => {
        if (!open) return;
        clearErrors();
        if (payment) {
            setData({
                employee_id: payment.employee_id.toString(),
                amount: payment.amount.toString(),
                payment_date: payment.payment_date ?? today(),
                notes: payment.notes ?? '',
            });
        } else {
            reset();
            if (presetEmployeeId) {
                setData('employee_id', presetEmployeeId.toString());
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, payment, presetEmployeeId]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const submitOptions = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        };
        if (isEdit && payment) {
            put(`/employee-payments/${payment.id}`, submitOptions);
        } else {
            post('/employee-payments', submitOptions);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'تعديل الراتب' : 'تسجيل راتب جديد'}
                    </DialogTitle>
                    <DialogDescription>
                        سجّل راتباً أو سلفة لأحد الموظفين
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-5">
                    <div className="grid gap-2">
                        <Label htmlFor="employee_id">الموظف</Label>
                        <Combobox
                            id="employee_id"
                            value={data.employee_id}
                            onChange={(value) =>
                                setData('employee_id', value ?? '')
                            }
                            options={options.map((employee) => ({
                                value: employee.id.toString(),
                                label: employee.name,
                            }))}
                            placeholder="اختر الموظف"
                            aria-invalid={!!errors.employee_id}
                        />
                        <InputError message={errors.employee_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="amount">المبلغ</Label>
                        <Input
                            id="amount"
                            type="number"
                            min="1"
                            value={data.amount}
                            onChange={(e) => setData('amount', e.target.value)}
                            required
                        />
                        <InputError message={errors.amount} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="payment_date">التاريخ</Label>
                        <DateInput
                            id="payment_date"
                            value={data.payment_date}
                            onChange={(value) => setData('payment_date', value)}
                            required
                        />
                        <InputError message={errors.payment_date} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="notes">ملاحظات</Label>
                        <Textarea
                            id="notes"
                            value={data.notes}
                            onChange={(e) => setData('notes', e.target.value)}
                        />
                        <InputError message={errors.notes} />
                    </div>

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            إلغاء
                        </Button>
                        <Button type="submit" disabled={processing}>
                            حفظ
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
