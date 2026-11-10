import { router } from '@inertiajs/react';
import { ClipboardList, Search, User } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { formatDate } from '@/components/order-display';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { Spinner } from '@/components/ui/spinner';
import { formatMoney } from '@/lib/money';
import { cn } from '@/lib/utils';

type DentistHit = { id: number; name: string; phone: string | null };

type OrderHit = {
    id: number;
    dentist: string | null;
    due_date: string | null;
    items: string[];
    status: string;
    amount: number;
    currency: 'SYP' | 'USD';
};

type Results = { dentists: DentistHit[]; orders: OrderHit[] };

const EMPTY: Results = { dentists: [], orders: [] };

/** Same rule as SearchController: a bare number is a full order reference. */
const isSearchable = (term: string) => term.length >= 2 || /^#?\d+$/.test(term);

/**
 * The sidebar's search box: type a dentist's name or phone, an order number,
 * or a word from an order, and jump straight there.
 *
 * A dentist opens his statement (there is no dentist page of its own); an
 * order opens its edit form.
 */
export function GlobalSearch() {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<Results>(EMPTY);
    // The term the current `results` answer. Until it catches up with what
    // is typed, the list is stale and "no results" must not be claimed.
    const [answered, setAnswered] = useState('');
    const [failed, setFailed] = useState(false);
    const [active, setActive] = useState(0);
    const listRef = useRef<HTMLDivElement>(null);

    const term = query.trim();
    const searchable = isSearchable(term);
    const loading = searchable && answered !== term && !failed;

    // Ctrl/⌘+K from anywhere.
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setOpen((value) => !value);
            }
        };

        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    }, []);

    useEffect(() => {
        if (!open || !searchable) {
            return;
        }

        const controller = new AbortController();
        const timer = setTimeout(async () => {
            try {
                const response = await fetch(
                    `/search?q=${encodeURIComponent(term)}`,
                    {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                setResults((await response.json()) as Results);
                setAnswered(term);
                setFailed(false);
                setActive(0);
            } catch {
                if (!controller.signal.aborted) {
                    setFailed(true);
                }
            }
        }, 200);

        return () => {
            clearTimeout(timer);
            controller.abort();
        };
    }, [open, term, searchable]);

    const shown = searchable ? results : EMPTY;

    // One flat list for the keyboard, in the order the rows are drawn.
    const targets = useMemo(
        () => [
            ...shown.dentists.map(
                (dentist) => `/ledger/statement?dentist_id=${dentist.id}`,
            ),
            ...shown.orders.map((order) => `/orders/${order.id}/edit`),
        ],
        [shown],
    );

    const onOpenChange = (next: boolean) => {
        setOpen(next);

        if (!next) {
            setQuery('');
            setResults(EMPTY);
            setAnswered('');
            setFailed(false);
            setActive(0);
        }
    };

    const go = (href: string) => {
        onOpenChange(false);
        router.visit(href);
    };

    const move = (step: number) => {
        if (targets.length === 0) {
            return;
        }

        const next = (active + step + targets.length) % targets.length;
        setActive(next);
        listRef.current
            ?.querySelector(`[data-index="${next}"]`)
            ?.scrollIntoView({ block: 'nearest' });
    };

    const onKeyDown = (e: React.KeyboardEvent) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            move(1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            move(-1);
        } else if (e.key === 'Enter' && targets[active] && !loading) {
            e.preventDefault();
            go(targets[active]);
        }
    };

    const rowClass = (index: number) =>
        cn(
            'flex w-full items-center gap-3 rounded-[10px] px-3 py-2 text-right text-sm outline-hidden',
            index === active && 'bg-accent text-accent-foreground',
        );

    const empty = searchable && !loading && !failed && targets.length === 0;

    return (
        <>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton
                        onClick={() => setOpen(true)}
                        tooltip={{ children: 'بحث' }}
                        data-test="global-search-button"
                        className="h-8 gap-3 rounded-[10px] border border-sidebar-border px-3 text-[14.5px] text-muted-foreground group-data-[collapsible=icon]:border-transparent group-data-[collapsible=icon]:p-1.5! [&>svg]:size-5"
                    >
                        <Search />
                        <span>بحث عن طبيب أو طلب…</span>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>

            <Dialog open={open} onOpenChange={onOpenChange}>
                {/* The dialog's own close button would sit on top of the
                    input; Esc and the backdrop close it instead. */}
                <DialogContent
                    className="top-[12%] translate-y-0 gap-0 overflow-hidden p-0 sm:max-w-xl [&>button:last-child]:hidden"
                    onKeyDown={onKeyDown}
                >
                    <DialogTitle className="sr-only">بحث</DialogTitle>
                    <DialogDescription className="sr-only">
                        ابحث عن طبيب بالاسم أو الهاتف، أو عن طلب برقمه أو
                        محتواه.
                    </DialogDescription>

                    <div className="flex items-center gap-3 border-b px-4">
                        <Search className="size-[18px] shrink-0 text-muted-foreground" />
                        <input
                            autoFocus
                            value={query}
                            onChange={(e) => {
                                setQuery(e.target.value);
                                setFailed(false);
                            }}
                            placeholder="اسم طبيب، رقم هاتف، رقم طلب…"
                            className="h-13 w-full bg-transparent text-[15px] outline-hidden placeholder:text-muted-foreground"
                            data-test="global-search-input"
                        />
                        {loading && <Spinner className="shrink-0" />}
                    </div>

                    <div
                        ref={listRef}
                        className="max-h-[60vh] overflow-y-auto p-2"
                    >
                        {!searchable && (
                            <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                                اكتب حرفين على الأقل، أو رقم الطلب.
                            </p>
                        )}

                        {failed && (
                            <p className="px-3 py-6 text-center text-sm text-destructive">
                                تعذّر البحث. حاول مرة أخرى.
                            </p>
                        )}

                        {empty && (
                            <p className="px-3 py-6 text-center text-sm text-muted-foreground">
                                لا توجد نتائج لـ «{term}».
                            </p>
                        )}

                        {shown.dentists.length > 0 && (
                            <>
                                <p className="px-3 pt-1 pb-1.5 text-xs font-semibold text-muted-foreground">
                                    الأطباء
                                </p>
                                {shown.dentists.map((dentist, i) => (
                                    <button
                                        key={dentist.id}
                                        type="button"
                                        data-index={i}
                                        className={rowClass(i)}
                                        onMouseMove={() => setActive(i)}
                                        onClick={() => go(targets[i])}
                                    >
                                        <User className="size-[18px] shrink-0 text-muted-foreground" />
                                        <span className="flex-1 truncate font-medium">
                                            {dentist.name}
                                        </span>
                                        {dentist.phone && (
                                            <span
                                                dir="ltr"
                                                className="text-xs text-muted-foreground tabular-nums"
                                            >
                                                {dentist.phone}
                                            </span>
                                        )}
                                        <span className="text-xs text-muted-foreground">
                                            كشف حساب
                                        </span>
                                    </button>
                                ))}
                            </>
                        )}

                        {shown.orders.length > 0 && (
                            <>
                                <p className="px-3 pt-3 pb-1.5 text-xs font-semibold text-muted-foreground">
                                    الطلبات
                                </p>
                                {shown.orders.map((order, n) => {
                                    const i = shown.dentists.length + n;

                                    return (
                                        <button
                                            key={order.id}
                                            type="button"
                                            data-index={i}
                                            className={rowClass(i)}
                                            onMouseMove={() => setActive(i)}
                                            onClick={() => go(targets[i])}
                                        >
                                            <ClipboardList className="size-[18px] shrink-0 text-muted-foreground" />
                                            <span className="grid flex-1 gap-0.5">
                                                <span className="truncate font-medium">
                                                    <span
                                                        dir="ltr"
                                                        className="text-muted-foreground tabular-nums"
                                                    >
                                                        #{order.id}
                                                    </span>{' '}
                                                    {order.dentist ?? '—'}
                                                </span>
                                                <span className="truncate text-xs text-muted-foreground">
                                                    {[
                                                        formatDate(
                                                            order.due_date,
                                                        ),
                                                        order.items.join('، '),
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </span>
                                            </span>
                                            {order.status === 'cancelled' ? (
                                                <span className="text-xs text-destructive">
                                                    ملغى
                                                </span>
                                            ) : (
                                                <span
                                                    dir="ltr"
                                                    className="text-xs tabular-nums"
                                                >
                                                    {formatMoney(
                                                        order.amount,
                                                        order.currency,
                                                    )}
                                                </span>
                                            )}
                                        </button>
                                    );
                                })}
                            </>
                        )}
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
