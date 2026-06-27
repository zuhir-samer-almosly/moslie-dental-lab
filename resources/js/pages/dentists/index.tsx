import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2, Users } from 'lucide-react';
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
import type { BreadcrumbItem, Dentist } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'أطباء الأسنان',
        href: '/dentists',
    },
];

export default function DentistsIndex({ dentists }: { dentists: Dentist[] }) {
    const handleDelete = (id: number) => {
        if (confirm('هل أنت متأكد من حذف هذا الطبيب؟')) {
            router.delete(`/dentists/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="أطباء الأسنان" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                {/* Header */}
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div className="space-y-1">
                        <h1 className="text-2xl font-bold tracking-tight">
                            أطباء الأسنان
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {dentists.length} طبيب مسجّل في المختبر
                        </p>
                    </div>
                    <Button asChild size="lg" className="gap-2 sm:w-auto">
                        <Link href="/dentists/create">
                            <Plus className="size-4" />
                            إضافة طبيب
                        </Link>
                    </Button>
                </div>

                {/* Table */}
                <Card className="gap-0 overflow-hidden py-0">
                    <div className="flex items-center justify-between border-b p-5">
                        <div className="space-y-0.5">
                            <h2 className="font-semibold">قائمة الأطباء</h2>
                            <p className="text-xs text-muted-foreground">
                                جميع أطباء الأسنان المسجلين
                            </p>
                        </div>
                    </div>
                    <CardContent className="p-0">
                        {dentists.length === 0 ? (
                            <EmptyState icon={Users} text="لا يوجد أطباء بعد" />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>الاسم</TableHead>
                                        <TableHead>الهاتف</TableHead>
                                        <TableHead>العنوان</TableHead>
                                        <TableHead className="text-end">
                                            الإجراءات
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {dentists.map((dentist) => (
                                        <TableRow key={dentist.id}>
                                            <TableCell className="font-medium">
                                                {dentist.name}
                                            </TableCell>
                                            <TableCell>
                                                {dentist.phone || '-'}
                                            </TableCell>
                                            <TableCell>
                                                {dentist.address || '-'}
                                            </TableCell>
                                            <TableCell className="text-end">
                                                <div className="flex justify-end gap-2">
                                                    <Button
                                                        asChild
                                                        variant="outline"
                                                        size="sm"
                                                    >
                                                        <Link
                                                            href={`/dentists/${dentist.id}/edit`}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Link>
                                                    </Button>
                                                    <Button
                                                        variant="destructive"
                                                        size="sm"
                                                        onClick={() =>
                                                            handleDelete(
                                                                dentist.id,
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
    icon: typeof Users;
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
