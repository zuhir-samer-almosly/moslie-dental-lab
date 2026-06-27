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
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'الموظفون',
        href: '/employees',
    },
    {
        title: 'إضافة موظف',
        href: '/employees/create',
    },
];

export default function EmployeesCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        role: '',
        phone: '',
        notes: '',
        is_active: true,
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/employees');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="إضافة موظف" />

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
                            إضافة موظف جديد
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            أدخل بيانات الموظف
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
                                حفظ
                            </Button>
                        </CardContent>
                    </Card>
                </form>
            </div>
        </AppLayout>
    );
}
