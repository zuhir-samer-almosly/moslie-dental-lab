import { Head, Link, router, usePage } from '@inertiajs/react';
import {
    Banknote,
    ChevronLeft,
    ChevronRight,
    FileText,
    Pencil,
    Trash2,
    Wrench,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import PaymentLineForm from '@/components/daybook/payment-line-form';
import type { DaybookLine } from '@/components/daybook/types';
import WorkLineForm from '@/components/daybook/work-line-form';
import type { WorkTypesProp } from '@/components/order-form';
import { Button } from '@/components/ui/button';
import { DateInput } from '@/components/ui/date-input';
import AppLayout from '@/layouts/app-layout';
import { addDays, formatLongDate } from '@/lib/dates';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, Dentist } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'اليومية', href: '/daybook' }];

type Mode = 'work' | 'payment';

/** Sum of `value` per currency, for the lines of one kind. */
const totals = (lines: DaybookLine[], kind: DaybookLine['kind']) => {
    const sum = { SYP: 0, USD: 0 };
    for (const line of lines) {
        if (line.kind === kind) {
            sum[line.currency] += line.value;
        }
    }

    return sum;
};

function Total({
    label,
    sum,
}: {
    label: string;
    sum: { SYP: number; USD: number };
}) {
    return (
        <div className="flex flex-1 flex-col gap-1 rounded-[10px] bg-muted px-4 py-3">
            <span className="text-sm text-muted-foreground">{label}</span>
            <span className="text-lg font-bold tabular-nums">
                {formatMoney(sum.SYP)}{' '}
                <span className="text-sm font-medium">ل.س</span>
            </span>
            {sum.USD !== 0 && (
                <span className="font-bold tabular-nums" dir="ltr">
                    {formatMoney(sum.USD, 'USD')}
                </span>
            )}
        </div>
    );
}

/**
 * اليومية — the lab's daily sheet, built for a tablet or phone at the bench:
 * big targets, one day at a time, a form that keeps what was typed when the
 * connection drops.
 */
export default function Daybook({
    date,
    today,
    lines,
    dentists,
    workTypes,
    todayRate,
}: {
    date: string;
    today: string;
    lines: DaybookLine[];
    dentists: Dentist[];
    workTypes: WorkTypesProp;
    todayRate: string | null;
}) {
    const isAdmin = usePage().props.auth.user.role === 'admin';

    const [mode, setMode] = useState<Mode>('work');
    const [dentistId, setDentistId] = useState('');
    const [editing, setEditing] = useState<DaybookLine | null>(null);
    // Bumped to remount the form with a fresh draft (start/cancel an edit).
    const [formKey, setFormKey] = useState(0);
    const [notice, setNotice] = useState('');
    const formRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        // A tablet left open past the session lifetime answers the next save
        // with 419 (stale CSRF token). Instead of Inertia's error overlay,
        // quietly reload the page data — which renews the token and keeps the
        // form as typed — and ask for the save to be sent again.
        const offInvalid = router.on('invalid', (event) => {
            if (event.detail.response.status === 419) {
                event.preventDefault();
                router.reload();
                setNotice('انتهت الجلسة وتم تجديدها — اضغط «حفظ» مرة أخرى.');
            }
        });
        // A request that never got an answer is reported by the form itself
        // (useDaybookSave); this only stops it surfacing as an uncaught error.
        const offException = router.on('exception', (event) => {
            event.preventDefault();
        });

        return () => {
            offInvalid();
            offException();
        };
    }, []);

    const goTo = (next: string) => router.get('/daybook', { date: next });

    const resetForm = () => {
        setEditing(null);
        setFormKey((key) => key + 1);
    };

    const startEdit = (line: DaybookLine) => {
        setEditing(line);
        setMode(line.kind);
        setDentistId(line.dentist_id.toString());
        setFormKey((key) => key + 1);
        formRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const remove = (line: DaybookLine) => {
        if (!confirm('حذف هذا السطر؟')) {
            return;
        }

        router.delete(
            line.kind === 'work'
                ? `/daybook/orders/${line.order_id}`
                : `/daybook/payments/${line.payment_id}`,
            { preserveScroll: true, preserveState: true },
        );

        if (editing?.key === line.key) {
            resetForm();
        }
    };

    const switchMode = (next: Mode) => {
        if (next !== mode) {
            setMode(next);
            resetForm();
        }
    };

    const onSaved = () => {
        setNotice('');
        if (editing) {
            resetForm();
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="اليومية" />

            <div className="mx-auto flex w-full max-w-[1200px] flex-1 flex-col gap-5 p-4 md:p-6">
                {/* The day */}
                <div className="flex flex-wrap items-center gap-3">
                    <Button
                        variant="outline"
                        onClick={() => goTo(addDays(date, -1))}
                        aria-label="اليوم السابق"
                        className="size-12 rounded-[10px]"
                    >
                        <ChevronRight className="size-6" />
                    </Button>
                    <div className="min-w-0 flex-1">
                        <h1 className="text-xl font-bold md:text-2xl">
                            {formatLongDate(date)}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {date === today ? 'يومية اليوم' : 'يومية يوم سابق'}
                        </p>
                    </div>
                    <Button
                        variant="outline"
                        onClick={() => goTo(addDays(date, 1))}
                        aria-label="اليوم التالي"
                        className="size-12 rounded-[10px]"
                    >
                        <ChevronLeft className="size-6" />
                    </Button>
                    <div className="flex w-full items-center gap-3 xl:w-auto">
                        <DateInput
                            value={date}
                            onChange={(value) => value && goTo(value)}
                            aria-label="انتقل إلى تاريخ"
                            className="h-12 flex-1 rounded-[10px] text-base xl:w-40"
                        />
                        {date !== today && (
                            <Button
                                variant="outline"
                                onClick={() => goTo(today)}
                                className="h-12 rounded-[10px] px-5 text-base"
                            >
                                اليوم
                            </Button>
                        )}
                        <Button
                            asChild
                            variant="outline"
                            className="h-12 rounded-[10px] px-4 text-base"
                        >
                            <a
                                href={`/daybook/pdf?date=${date}`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                <FileText className="size-5" />
                                PDF
                            </a>
                        </Button>
                    </div>
                </div>

                <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,440px)_minmax(0,1fr)]">
                    {/* The line being written */}
                    <section
                        ref={formRef}
                        className="flex scroll-mt-4 flex-col gap-4 rounded-2xl border bg-card p-4 md:p-5"
                    >
                        <div className="grid grid-cols-2 gap-2">
                            {(
                                [
                                    ['work', 'عمل', Wrench],
                                    ['payment', 'دفعة', Banknote],
                                ] as const
                            ).map(([value, label, Icon]) => (
                                <button
                                    key={value}
                                    type="button"
                                    onClick={() => switchMode(value)}
                                    aria-pressed={mode === value}
                                    className={cn(
                                        'flex h-14 items-center justify-center gap-2 rounded-[10px] border text-lg font-bold transition-colors',
                                        mode === value
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'bg-background hover:border-primary',
                                    )}
                                >
                                    <Icon className="size-5" />
                                    {label}
                                </button>
                            ))}
                        </div>

                        {editing && (
                            <p className="rounded-[10px] bg-amber-100 px-4 py-2 text-[15px] font-medium text-amber-900 dark:bg-amber-950 dark:text-amber-200">
                                تعديل سطر محفوظ
                            </p>
                        )}

                        {notice && (
                            <p
                                role="status"
                                className="rounded-[10px] bg-amber-100 px-4 py-3 text-[15px] font-medium text-amber-900 dark:bg-amber-950 dark:text-amber-200"
                            >
                                {notice}
                            </p>
                        )}

                        {mode === 'work' ? (
                            <WorkLineForm
                                key={`work-${formKey}`}
                                dentists={dentists}
                                workTypes={workTypes}
                                todayRate={todayRate}
                                date={date}
                                editing={
                                    editing?.kind === 'work' ? editing : null
                                }
                                dentistId={dentistId}
                                onDentistChange={setDentistId}
                                onSaved={onSaved}
                                onCancel={resetForm}
                            />
                        ) : (
                            <PaymentLineForm
                                key={`payment-${formKey}`}
                                dentists={dentists}
                                todayRate={todayRate}
                                date={date}
                                editing={
                                    editing?.kind === 'payment' ? editing : null
                                }
                                dentistId={dentistId}
                                onDentistChange={setDentistId}
                                onSaved={onSaved}
                                onCancel={resetForm}
                            />
                        )}
                    </section>

                    {/* The day's sheet */}
                    <section className="flex flex-col gap-3">
                        <h2 className="text-base font-bold">
                            سطور اليوم{' '}
                            <span className="font-normal text-muted-foreground tabular-nums">
                                ({lines.length})
                            </span>
                        </h2>

                        {lines.length === 0 ? (
                            <p className="rounded-2xl border border-dashed p-8 text-center text-muted-foreground">
                                لا يوجد شيء مسجّل في هذا اليوم بعد.
                            </p>
                        ) : (
                            <ul className="flex flex-col gap-2">
                                {lines.map((line) => (
                                    <li
                                        key={line.key}
                                        className={cn(
                                            'flex items-center gap-3 rounded-2xl border bg-card p-3 md:p-4',
                                            editing?.key === line.key &&
                                                'border-primary ring-2 ring-primary/30',
                                        )}
                                    >
                                        <div
                                            className={cn(
                                                'flex size-10 shrink-0 items-center justify-center rounded-full',
                                                line.kind === 'work'
                                                    ? 'bg-primary/10 text-primary'
                                                    : 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
                                            )}
                                        >
                                            {line.kind === 'work' ? (
                                                <Wrench className="size-5" />
                                            ) : (
                                                <Banknote className="size-5" />
                                            )}
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-base font-bold">
                                                {line.dentist_name}
                                            </p>
                                            {line.kind === 'work' ? (
                                                <p className="text-[15px] text-muted-foreground">
                                                    {line.type}
                                                    {line.quantity > 1 &&
                                                        ` × ${line.quantity}`}
                                                    {line.patient_name &&
                                                        ` — ${line.patient_name}`}
                                                    {line.selected_teeth
                                                        .length > 0 && (
                                                        <>
                                                            {' — '}
                                                            <span
                                                                dir="ltr"
                                                                className="tabular-nums"
                                                            >
                                                                {line.selected_teeth.join(
                                                                    ' ',
                                                                )}
                                                            </span>
                                                        </>
                                                    )}
                                                </p>
                                            ) : (
                                                <p className="text-[15px] text-muted-foreground">
                                                    دفعة
                                                    {line.notes &&
                                                        ` — ${line.notes}`}
                                                </p>
                                            )}
                                        </div>

                                        <span
                                            dir={
                                                line.currency === 'USD'
                                                    ? 'ltr'
                                                    : undefined
                                            }
                                            className={cn(
                                                'shrink-0 text-base font-bold tabular-nums',
                                                line.kind === 'payment' &&
                                                    'text-emerald-700 dark:text-emerald-400',
                                            )}
                                        >
                                            {formatMoney(
                                                line.value,
                                                line.currency,
                                            )}
                                        </span>

                                        {line.editable ? (
                                            <div className="flex shrink-0 gap-1">
                                                <Button
                                                    variant="ghost"
                                                    onClick={() =>
                                                        startEdit(line)
                                                    }
                                                    aria-label="تعديل"
                                                    className="size-12"
                                                >
                                                    <Pencil className="size-5" />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    onClick={() => remove(line)}
                                                    aria-label="حذف"
                                                    className="size-12 text-destructive"
                                                >
                                                    <Trash2 className="size-5" />
                                                </Button>
                                            </div>
                                        ) : (
                                            // Part of a larger order: changing
                                            // it here would replace the whole
                                            // order, so it is edited there.
                                            isAdmin &&
                                            line.kind === 'work' && (
                                                <Link
                                                    href={`/orders/${line.order_id}/edit`}
                                                    className="shrink-0 px-2 text-sm font-medium text-primary"
                                                >
                                                    فتح الطلب
                                                </Link>
                                            )
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}

                        {lines.length > 0 && (
                            <div className="flex flex-wrap gap-3">
                                <Total
                                    label="مجموع الأعمال"
                                    sum={totals(lines, 'work')}
                                />
                                <Total
                                    label="مجموع الدفعات"
                                    sum={totals(lines, 'payment')}
                                />
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </AppLayout>
    );
}
