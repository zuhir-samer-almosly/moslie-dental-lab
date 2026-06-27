import { Head, useForm } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Employee } from '@/types';

export default function EmployeesEdit({ employee }: { employee: Employee }) {
    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'الموظفون',
            href: '/employees',
        },
        {
            title: employee.name,
            href: `/employees/${employee.id}/edit`,
        },
    ];

    const { data, setData, put, processing, errors } = useForm({
        name: employee.name,
        role: employee.role ?? '',
        phone: employee.phone ?? '',
        notes: employee.notes ?? '',
        is_active: employee.is_active,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        put(`/employees/${employee.id}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="تعديل موظف" />

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
                            تعديل بيانات الموظف
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {employee.name}
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="max-w-2xl">
                    <Card className="gap-0 py-0">
                        <CardContent className="space-y-6 p-5 md:p-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">الاسم</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
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
                                    onChange={(e) =>
                                        setData('role', e.target.value)
                                    }
                                />
                                <InputError message={errors.role} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="phone">الهاتف</Label>
                                <Input
                                    id="phone"
                                    value={data.phone}
                                    onChange={(e) =>
                                        setData('phone', e.target.value)
                                    }
                                />
                                <InputError message={errors.phone} />
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

                            <Button type="submit" disabled={processing}>
                                حفظ التعديلات
                            </Button>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
