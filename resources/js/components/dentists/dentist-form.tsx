import { useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PriceListEditor, {
    DEFAULT_WORK_TYPES,
    findDuplicateNames,
    type PriceRow,
} from '@/components/price-list-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { Dentist } from '@/types';

/** A new dentist starts from the default work types; an existing one from theirs. */
const toRows = (dentist?: Dentist | null): PriceRow[] =>
    dentist
        ? Object.entries(dentist.price_list ?? {}).map(([name, price]) => ({
            name,
            price,
        }))
        : DEFAULT_WORK_TYPES.map((name) => ({ name, price: 0 }));

/**
 * The one dentist form in the app. Rendered three ways: the standalone
 * create/edit pages, the dialog on the dentists list, and the manager dialog
 * that floats over the order form. It owns no chrome — no Dialog, no
 * AppLayout — so each caller supplies its own.
 *
 * It has no reset-on-prop-change effect on purpose: callers remount it with a
 * `key` when they switch which dentist is being edited, which is simpler and
 * cannot go stale.
 */
export default function DentistForm({
    dentist = null,
    redirectToIndex = false,
    onSaved,
    onCancel,
}: {
    dentist?: Dentist | null;
    redirectToIndex?: boolean;
    onSaved?: () => void;
    onCancel?: () => void;
}) {
    const isEdit = Boolean(dentist);

    const {
        data,
        setData,
        transform,
        post,
        put,
        processing,
        errors,
        setError,
        clearErrors,
    } = useForm({
        name: dentist?.name ?? '',
        gender: dentist?.gender ?? 'male',
        phone: dentist?.phone ?? '',
        address: dentist?.address ?? '',
        price_list: toRows(dentist),
    });

    transform((payload) => ({
        name: payload.name,
        gender: payload.gender,
        phone: payload.phone,
        address: payload.address,
        price_list: Object.fromEntries(
            payload.price_list
                .filter((row) => row.name.trim() !== '')
                .map((row) => [row.name.trim(), row.price]),
        ),
        // Only the standalone pages want to land on the list afterwards; the
        // dialogs must stay on whatever page they opened over.
        ...(redirectToIndex ? { to_index: true } : {}),
    }));

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        // This form is rendered inside dialogs that may float over another
        // form. Radix portals the DOM out to <body>, but React events still
        // bubble through the React tree, so without this an outer <form>
        // would submit too. The callers keep the dialog out of their form as
        // well; this is the belt to that pair of braces.
        e.stopPropagation();
        clearErrors();

        const duplicates = findDuplicateNames(data.price_list);
        if (duplicates.length > 0) {
            setError(
                'price_list',
                `أنواع عمل مكررة: ${duplicates.join('، ')}. لكل نوع سطر واحد فقط.`,
            );
            return;
        }

        // preserveState is false by default for post/put. Without it Inertia
        // remounts the page underneath — which, when this form is in a dialog
        // over a half-typed order, throws the whole draft away.
        const options = {
            preserveState: true,
            preserveScroll: true,
            onSuccess: () => onSaved?.(),
        };

        if (dentist) {
            put(`/dentists/${dentist.id}`, options);
        } else {
            post('/dentists', options);
        }
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="name">الاسم *</Label>
                <Input
                    id="name"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    required
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label>الجنس *</Label>
                <div className="flex gap-4">
                    <label className="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            name="gender"
                            value="male"
                            checked={data.gender === 'male'}
                            onChange={() => setData('gender', 'male')}
                            className="accent-primary"
                        />
                        <span>ذكر</span>
                    </label>
                    <label className="flex items-center gap-2 cursor-pointer">
                        <input
                            type="radio"
                            name="gender"
                            value="female"
                            checked={data.gender === 'female'}
                            onChange={() => setData('gender', 'female')}
                            className="accent-primary"
                        />
                        <span>أنثى</span>
                    </label>
                </div>
                <InputError message={errors.gender} />
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
                <Label htmlFor="address">العنوان</Label>
                <Textarea
                    id="address"
                    value={data.address}
                    onChange={(e) => setData('address', e.target.value)}
                    rows={3}
                />
                <InputError message={errors.address} />
            </div>

            <div className="space-y-3">
                <Label>قائمة الأسعار</Label>
                <p className="text-sm text-muted-foreground">
                    حدد أنواع العمل وأسعارها لهذا الطبيب. ستظهر هذه الأنواع عند
                    إضافة عناصر الطلب ويُملأ سعرها تلقائياً.
                </p>
                <PriceListEditor
                    value={data.price_list}
                    onChange={(rows) => setData('price_list', rows)}
                />
                <InputError message={errors.price_list} />
            </div>

            <div className="flex justify-end gap-2">
                {onCancel && (
                    <Button type="button" variant="outline" onClick={onCancel}>
                        إلغاء
                    </Button>
                )}
                <Button type="submit" disabled={processing}>
                    {isEdit ? 'تحديث' : 'حفظ'}
                </Button>
            </div>
        </form>
    );
}
