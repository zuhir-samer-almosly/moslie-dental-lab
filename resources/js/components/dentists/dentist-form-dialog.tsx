import DentistForm from '@/components/dentists/dentist-form';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Dentist } from '@/types';

/**
 * Add/edit a dentist in a dialog. The form is remounted per dentist via `key`,
 * so switching rows always shows that row's data with no reset effect.
 */
export default function DentistFormDialog({
    open,
    onOpenChange,
    dentist = null,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    dentist?: Dentist | null;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            {/* Price lists get long — let the dialog scroll instead of the page. */}
            <DialogContent className="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>
                        {dentist ? 'تعديل طبيب' : 'إضافة طبيب جديد'}
                    </DialogTitle>
                    <DialogDescription>
                        {dentist
                            ? 'تحديث بيانات الطبيب وقائمة أسعاره'
                            : 'أدخل بيانات الطبيب وقائمة أسعاره'}
                    </DialogDescription>
                </DialogHeader>

                {open && (
                    <DentistForm
                        key={dentist?.id ?? 'new'}
                        dentist={dentist}
                        onSaved={() => onOpenChange(false)}
                        onCancel={() => onOpenChange(false)}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}
