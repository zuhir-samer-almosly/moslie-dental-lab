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
            /** is_active-filtered: what expense forms OFFER. */
            expenseCategories: Record<string, string>;
            /** Unfiltered: what already-recorded expenses are LABELED with. */
            expenseCategoryLabels: Record<string, string>;
            [key: string]: unknown;
        };
    }
}
