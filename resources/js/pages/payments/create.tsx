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
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem, Dentist } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'المدفوعات',
        href: '/payments',
    },
    {
        title: 'إضافة دفعة',
        href: '/payments/create',
    },
];

export default function PaymentsCreate({ dentists }: { dentists: Dentist[] }) {
    const { data, setData, post, processing, errors } = useForm({
        dentist_id: '',
        amount: '',
        payment_date: new Date().toISOString().split('T')[0],
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/payments');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="إضافة دفعة" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
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
                            إضافة دفعة جديدة
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            سجّل دفعة جديدة مستلمة من الطبيب
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="max-w-2xl">
                    <Card className="gap-0 py-0">
                        <CardContent className="space-y-6 p-5 md:p-6">
                            <div className="grid gap-2">
                                <Label htmlFor="dentist_id">الطبيب</Label>
                                <Select
                                    value={data.dentist_id}
                                    onValueChange={(value) =>
                                        setData('dentist_id', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="اختر الطبيب" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {dentists.map((dentist) => (
                                            <SelectItem
                                                key={dentist.id}
                                                value={dentist.id.toString()}
                                            >
                                                {dentist.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                <InputError message={errors.dentist_id} />
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
