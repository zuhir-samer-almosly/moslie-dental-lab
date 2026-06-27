import { Head, useForm } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'المواد',
        href: '/material-purchases',
    },
    {
        title: 'تسجيل مادة',
        href: '/material-purchases/create',
    },
];

export default function MaterialPurchasesCreate() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        supplier: '',
        quantity: '',
        amount: '',
        purchase_date: new Date().toISOString().split('T')[0],
        notes: '',
    });

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/material-purchases');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="تسجيل مادة" />

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
                            تسجيل مادة جديدة
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            سجّل عملية شراء مادة أو مستهلك
                        </p>
                    </div>
                </div>

                <form onSubmit={handleSubmit} className="max-w-2xl">
                    <Card className="gap-0 py-0">
                        <CardContent className="space-y-6 p-5 md:p-6">
                            <div className="grid gap-2">
                                <Label htmlFor="name">المادة</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    placeholder="خزف، جبس، زركون..."
                                    onChange={(e) =>
                                        setData('name', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="supplier">المورّد</Label>
                                <Input
                                    id="supplier"
                                    value={data.supplier}
                                    placeholder="من اشتريت منه"
                                    onChange={(e) =>
                                        setData('supplier', e.target.value)
                                    }
                                />
                                <InputError message={errors.supplier} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="quantity">الكمية</Label>
                                <Input
                                    id="quantity"
                                    value={data.quantity}
                                    placeholder="مثال: 5 كغ"
                                    onChange={(e) =>
                                        setData('quantity', e.target.value)
                                    }
                                />
                                <InputError message={errors.quantity} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="amount">السعر</Label>
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
                                <Label htmlFor="purchase_date">التاريخ</Label>
                                <Input
                                    id="purchase_date"
                                    type="date"
                                    value={data.purchase_date}
                                    onChange={(e) =>
                                        setData('purchase_date', e.target.value)
                                    }
                                    required
                                />
                                <InputError message={errors.purchase_date} />
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
