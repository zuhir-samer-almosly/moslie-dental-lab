import { Head, useForm } from '@inertiajs/react';
import { Merge } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Combobox } from '@/components/ui/combobox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'أنواع العمل',
        href: '/work-types',
    },
];

type WorkType = {
    name: string;
    /** Order lines carrying this name. */
    lines: number;
    /** Dentists it was ordered for. */
    dentists: number;
    /** Price lists that price it. */
    price_lists: number;
};

type Props = {
    types: WorkType[];
    /** Groups of names that look like one product spelled several ways. */
    suggestions: WorkType[][];
};

export default function WorkTypesIndex({ types, suggestions }: Props) {
    // `null` = dialog closed. `from` is fixed by the row that opened it.
    const [merging, setMerging] = useState<WorkType | null>(null);
    const form = useForm({ from: '', to: '' });

    const openMerge = (type: WorkType, into = '') => {
        form.clearErrors();
        form.setData({ from: type.name, to: into });
        setMerging(type);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/work-types/merge', {
            preserveScroll: true,
            onSuccess: () => setMerging(null),
        });
    };

    const target = form.data.to.trim();
    const targetExists = types.some((type) => type.name === target);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="أنواع العمل" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4 md:p-6">
                <div className="space-y-1">
                    <h1 className="text-2xl font-bold tracking-tight">
                        أنواع العمل
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        كل الأسماء المكتوبة في حقل «النوع» على الطلبات وقوائم
                        الأسعار. ادمج الاسم المكتوب بطريقتين في اسم واحد — الدمج
                        يغيّر الاسم فقط ولا يغيّر أي مبلغ.
                    </p>
                </div>

                {suggestions.length > 0 && (
                    <Card>
                        <CardContent className="space-y-4 p-5">
                            <div className="space-y-1">
                                <h2 className="text-base font-bold">
                                    أسماء متشابهة ({suggestions.length})
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    تبدو هذه الأسماء نوعاً واحداً كُتب بأكثر من
                                    طريقة. راجعها وادمج ما هو مكرر فعلاً.
                                </p>
                            </div>
                            <div className="space-y-2">
                                {suggestions.map((group) => {
                                    const [keep, ...others] = group;

                                    return (
                                        <div
                                            key={keep.name}
                                            className="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-[10px] border px-3.5 py-2.5"
                                        >
                                            <span className="font-semibold">
                                                {keep.name}{' '}
                                                <span className="text-xs font-normal text-muted-foreground tabular-nums">
                                                    ({keep.lines})
                                                </span>
                                            </span>
                                            {others.map((other) => (
                                                <span
                                                    key={other.name}
                                                    className="flex items-center gap-2"
                                                >
                                                    <span>
                                                        {other.name}{' '}
                                                        <span className="text-xs text-muted-foreground tabular-nums">
                                                            ({other.lines})
                                                        </span>
                                                    </span>
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-7 gap-1.5 rounded-lg px-2.5 text-xs"
                                                        onClick={() =>
                                                            openMerge(
                                                                other,
                                                                keep.name,
                                                            )
                                                        }
                                                    >
                                                        <Merge className="size-3.5" />
                                                        دمج في «{keep.name}»
                                                    </Button>
                                                </span>
                                            ))}
                                        </div>
                                    );
                                })}
                            </div>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="text-right">
                                        النوع
                                    </TableHead>
                                    <TableHead className="text-right">
                                        عدد السطور
                                    </TableHead>
                                    <TableHead className="text-right">
                                        عدد الأطباء
                                    </TableHead>
                                    <TableHead className="text-right">
                                        في قوائم الأسعار
                                    </TableHead>
                                    <TableHead />
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {types.length === 0 && (
                                    <TableRow>
                                        <TableCell
                                            colSpan={5}
                                            className="py-8 text-center text-muted-foreground"
                                        >
                                            لا توجد أنواع بعد.
                                        </TableCell>
                                    </TableRow>
                                )}
                                {types.map((type) => (
                                    <TableRow key={type.name}>
                                        <TableCell className="font-medium">
                                            {type.name}
                                        </TableCell>
                                        <TableCell className="tabular-nums">
                                            {type.lines}
                                        </TableCell>
                                        <TableCell className="tabular-nums">
                                            {type.dentists}
                                        </TableCell>
                                        <TableCell className="tabular-nums">
                                            {type.price_lists}
                                        </TableCell>
                                        <TableCell className="text-left">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                className="h-8 gap-1.5 text-primary hover:text-primary"
                                                onClick={() => openMerge(type)}
                                            >
                                                <Merge className="size-4" />
                                                دمج / إعادة تسمية
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>

            <Dialog
                open={merging !== null}
                onOpenChange={(open) => !open && setMerging(null)}
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>دمج «{merging?.name}»</DialogTitle>
                        <DialogDescription>
                            اختر الاسم الصحيح، أو اكتب اسماً جديداً لإعادة
                            التسمية.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="merge-to">يصبح</Label>
                            <Combobox
                                id="merge-to"
                                value={form.data.to}
                                onChange={(value) =>
                                    form.setData('to', value ?? '')
                                }
                                options={types
                                    .filter(
                                        (type) => type.name !== merging?.name,
                                    )
                                    .map((type) => ({
                                        value: type.name,
                                        label: type.name,
                                        hint: String(type.lines),
                                    }))}
                                placeholder="اختر أو اكتب الاسم الصحيح..."
                                allowCustom
                                customHint={(query) =>
                                    `إعادة تسمية إلى «${query}»`
                                }
                            />
                            <InputError
                                message={form.errors.to || form.errors.from}
                            />
                        </div>

                        {merging &&
                            target !== '' &&
                            target !== merging.name && (
                                <p className="rounded-[10px] bg-secondary px-3.5 py-2.5 text-[13px] text-primary">
                                    {merging.lines} سطر و{merging.price_lists}{' '}
                                    قائمة أسعار ستتحول من «{merging.name}» إلى «
                                    {target}»
                                    {targetExists
                                        ? '، ولن يبقى الاسم القديم.'
                                        : ' (اسم جديد).'}{' '}
                                    لا يمكن التراجع عن الدمج تلقائياً.
                                </p>
                            )}

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setMerging(null)}
                            >
                                إلغاء
                            </Button>
                            <Button
                                type="submit"
                                disabled={
                                    form.processing ||
                                    target === '' ||
                                    target === merging?.name
                                }
                            >
                                دمج
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
