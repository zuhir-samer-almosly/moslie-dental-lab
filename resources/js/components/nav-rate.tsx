import { useForm, usePage } from '@inertiajs/react';
import { CircleDollarSign } from 'lucide-react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
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
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { formatRate, rateValue } from '@/lib/money';

/**
 * Today's lira-per-dollar rate, set by hand.
 *
 * Before this, the day's rate was whatever number was last typed into a dollar
 * order — so a week without a dollar entry left every invoice's "≈ $" figure
 * priced at a stale rate. The owner sets it here each morning instead, and a
 * rate set here is not overwritten by a later entry.
 *
 * Setting a rate never moves money already booked: each entry keeps the rate
 * it was converted at. What moves is what new forms offer and what lira totals
 * are read back through.
 */
export function NavRate() {
    const { dailyRate } = usePage().props;
    const [open, setOpen] = useState(false);

    const { data, setData, post, processing, errors, clearErrors } = useForm({
        rate: rateValue(dailyRate.rate),
    });

    const stale = !dailyRate.recorded_today;

    const openDialog = () => {
        clearErrors();
        setData('rate', rateValue(dailyRate.rate));
        setOpen(true);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post('/exchange-rate', {
            preserveScroll: true,
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        size="lg"
                        onClick={openDialog}
                        tooltip={{ children: 'سعر الدولار' }}
                        className="text-sidebar-accent-foreground"
                        data-test="sidebar-rate-button"
                    >
                        <div className="relative flex size-9 shrink-0 items-center justify-center rounded-full bg-secondary">
                            <CircleDollarSign className="size-5 text-secondary-foreground" />
                            {stale && (
                                <span
                                    className="absolute -top-0.5 -left-0.5 size-2.5 rounded-full bg-amber-500 ring-2 ring-sidebar"
                                    aria-hidden
                                />
                            )}
                        </div>
                        {/* pb/-mb as in UserInfo: Arabic descenders fall
                            outside the tight line box and `truncate` clips them. */}
                        <div className="grid flex-1 text-right text-sm leading-tight">
                            <span className="-mb-1 truncate pb-1 font-semibold">
                                {dailyRate.rate === null ? (
                                    'حدّد سعر الدولار'
                                ) : (
                                    <span dir="ltr" className="tabular-nums">
                                        1$ = {formatRate(dailyRate.rate)}
                                    </span>
                                )}
                            </span>
                            <span
                                className={`-mb-1 truncate pb-1 text-xs ${
                                    stale
                                        ? 'text-amber-600 dark:text-amber-500'
                                        : 'text-muted-foreground'
                                }`}
                            >
                                {stale ? 'لم يُحدَّث اليوم' : 'سعر اليوم'}
                            </span>
                        </div>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>سعر الدولار اليوم</DialogTitle>
                        <DialogDescription>
                            {dailyRate.rate === null
                                ? 'لم يُسجَّل أي سعر بعد.'
                                : stale
                                  ? `السعر المعروض (${formatRate(dailyRate.rate)}) منقول من يوم سابق.`
                                  : `سعر اليوم المسجَّل: ${formatRate(dailyRate.rate)} ليرة للدولار.`}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="daily-rate">
                                كم ليرة للدولار الواحد؟
                            </Label>
                            <Input
                                id="daily-rate"
                                type="number"
                                step="0.000001"
                                min="0.000001"
                                dir="ltr"
                                autoFocus
                                value={data.rate}
                                onChange={(e) =>
                                    setData('rate', e.target.value)
                                }
                                required
                            />
                            <InputError message={errors.rate} />
                            <p className="text-xs text-muted-foreground">
                                يُطبَّق على ما يُسجَّل بعد الآن وعلى قراءة
                                المبالغ بالدولار. المبالغ المسجَّلة سابقًا تبقى
                                على سعرها.
                            </p>
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
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
        </>
    );
}
