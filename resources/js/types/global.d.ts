import type { Auth } from '@/types/auth';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            flash: {
                success?: string | null;
                error?: string | null;
            };
            sidebarOpen: boolean;
            /**
             * Today's lira-per-dollar rate for the sidebar control. `rate` is
             * the rate in effect (which may be carried over from an earlier
             * day); `recorded_today` is false when it was.
             */
            dailyRate: {
                rate: string | null;
                recorded_today: boolean;
            };
            /** is_active-filtered: what expense forms OFFER. */
            expenseCategories: Record<string, string>;
            /** Unfiltered: what already-recorded expenses are LABELED with. */
            expenseCategoryLabels: Record<string, string>;
            [key: string]: unknown;
        };
    }
}
