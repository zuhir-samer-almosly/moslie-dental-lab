import { router } from '@inertiajs/react';
import { ArrowRight, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import DentistForm from '@/components/dentists/dentist-form';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import type { Dentist } from '@/types';

/**
 * Full dentist management from inside the order form: add, edit (including the
 * price list) and delete without leaving a half-typed order.
 *
 * One Dialog with two views rather than a dialog opening another dialog —
 * nested Radix dialogs bring focus-trap and portal problems for no benefit.
 */
export default function DentistsManagerDialog({
    open,
    onOpenChange,
    dentists,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    dentists: Dentist[];
}) {
    const [view, setView] = useState<'list' | 'form'>('list');
    const [editing, setEditing] = useState<Dentist | null>(null);

    const showList = () => {
        setView('list');
        setEditing(null);
    };

    // Reset on close rather than on open: it is equivalent (the dialog can
    // only be reopened after a close, and it starts on the list) and it keeps
    // the reset in an event handler instead of an effect.
    const handleOpenChange = (next: boolean) => {
        if (!next) {
            showList();
        }
        onOpenChange(next);
    };

    const handleDelete = (dentist: Dentist) => {
        if (!confirm(`هل أنت متأكد من حذف ${dentist.name}؟`)) {
            return;
        }
        // preserveState keeps the order draft underneath this dialog alive.
        // The server refuses the delete if the dentist has orders or payments
        // and flashes an error instead — the refreshed list below is the
        // source of truth either way.
        router.delete(`/dentists/${dentist.id}`, {
            preserveState: true,
            preserveScroll: true,
        });
    };

    return (
        <Dialog open={open} onOpenChange={handleOpenChange}>
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>
                        {view === 'list'
                            ? 'إدارة الأطباء'
                            : editing
                              ? 'تعديل طبيب'
                              : 'إضافة طبيب جديد'}
                    </DialogTitle>
                    <DialogDescription>
                        {view === 'list'
                            ? 'أضف طبيباً أو عدّل بياناته وأسعاره دون مغادرة الطلب'
                            : 'تنطبق الأسعار الجديدة على عناصر الطلب الحالي فوراً'}
                    </DialogDescription>
                </DialogHeader>

                {view === 'list' ? (
                    <div className="space-y-4">
                        <Button
                            type="button"
                            variant="outline"
                            className="gap-2"
                            onClick={() => {
                                setEditing(null);
                                setView('form');
                            }}
                        >
                            <Plus className="size-4" />
                            إضافة طبيب
                        </Button>

                        {dentists.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                لا يوجد أطباء بعد
                            </p>
                        ) : (
                            <div className="overflow-hidden rounded-lg border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>الاسم</TableHead>
                                            <TableHead>الهاتف</TableHead>
                                            <TableHead>أنواع العمل</TableHead>
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
                                                <TableCell className="tabular-nums">
                                                    {
                                                        Object.keys(
                                                            dentist.price_list ??
                                                                {},
                                                        ).length
                                                    }
                                                </TableCell>
                                                <TableCell className="text-end">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            type="button"
                                                            variant="outline"
                                                            size="sm"
                                                            title="تعديل الطبيب وأسعاره"
                                                            onClick={() => {
                                                                setEditing(
                                                                    dentist,
                                                                );
                                                                setView('form');
                                                            }}
                                                        >
                                                            <Pencil className="h-4 w-4" />
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            variant="destructive"
                                                            size="sm"
                                                            title="حذف الطبيب"
                                                            onClick={() =>
                                                                handleDelete(
                                                                    dentist,
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
                            </div>
                        )}
                    </div>
                ) : (
                    <div className="space-y-4">
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="w-fit gap-2 text-muted-foreground"
                            onClick={showList}
                        >
                            <ArrowRight className="size-4" />
                            رجوع إلى القائمة
                        </Button>

                        <DentistForm
                            key={editing?.id ?? 'new'}
                            dentist={editing}
                            onSaved={showList}
                            onCancel={showList}
                        />
                    </div>
                )}
            </DialogContent>
        </Dialog>
    );
}
