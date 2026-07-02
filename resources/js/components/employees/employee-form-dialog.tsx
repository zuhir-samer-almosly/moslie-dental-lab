import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import type { Employee } from '@/types';

export function EmployeeFormDialog({
    open,
    onOpenChange,
    employee,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    employee?: Employee | null;
}) {
    const isEdit = Boolean(employee);

    const { data, setData, post, put, processing, errors, reset, clearErrors } =
        useForm({
            name: '',
            role: '',
            phone: '',
            notes: '',
            is_active: true,
        });

    // Sync the form to the selected employee whenever the dialog opens.
    useEffect(() => {
        if (!open) return;
        clearErrors();
        if (employee) {
            setData({
                name: employee.name,
                role: employee.role ?? '',
                phone: employee.phone ?? '',
                notes: employee.notes ?? '',
                is_active: employee.is_active,
            });
        } else {
            reset();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, employee]);

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onOpenChange(false);
            },
        };
        if (isEdit && employee) {
            put(`/employees/${employee.id}`, options);
        } else {
            post('/employees', options);
        }
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {isEdit ? 'تعديل الموظف' : 'إضافة موظف جديد'}
                    </DialogTitle>
                    <DialogDescription>
                        {isEdit ? 'حدّث بيانات الموظف' : 'أدخل بيانات الموظف'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="space-y-5">
                    <div className="grid gap-2">
                        <Label htmlFor="name">الاسم</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={(e) => setData('name', e.target.value)}
                            required
                        />
                        <InputError message={errors.name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="role">المسمى الوظيفي</Label>
                        <Input
                            id="role"
                            value={data.role}
                            placeholder="فني، مساعد، سكرتير..."
                            onChange={(e) => setData('role', e.target.value)}
                        />
                        <InputError message={errors.role} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="phone">الهاتف</Label>
                        <Input
                            id="phone"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                        />
                        <InputError message={errors.phone} />
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

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="is_active"
                            checked={data.is_active}
                            onCheckedChange={(checked) =>
                                setData('is_active', checked === true)
                            }
                        />
                        <Label htmlFor="is_active">موظف نشط</Label>
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
