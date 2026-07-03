import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

type LedgerTab = 'materials' | 'expenses';

const TABS: { key: LedgerTab; label: string; href: string }[] = [
    { key: 'materials', label: 'المواد', href: '/material-purchases' },
    { key: 'expenses', label: 'المصاريف', href: '/expenses' },
];

/**
 * Tab bar shared by the Materials and Expenses ledgers. Both are month-filtered
 * logs on the expense side, so they share one header and the selected month is
 * carried across tabs via the `month` query param.
 */
export function LedgerTabs({
    active,
    month,
}: {
    active: LedgerTab;
    month?: string;
}) {
    return (
        <div className="inline-flex items-center gap-1 rounded-lg bg-muted p-1">
            {TABS.map((tab) => (
                <Link
                    key={tab.key}
                    href={month ? `${tab.href}?month=${month}` : tab.href}
                    className={cn(
                        'rounded-md px-4 py-1.5 text-sm font-medium transition-colors',
                        tab.key === active
                            ? 'bg-background text-foreground shadow-sm'
                            : 'text-muted-foreground hover:text-foreground',
                    )}
                >
                    {tab.label}
                </Link>
            ))}
        </div>
    );
}
