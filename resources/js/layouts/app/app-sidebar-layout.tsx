import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import FlashMessages from '@/components/flash-messages';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            {/* overflow-x-clip, not -hidden: `hidden` forces overflow-y to
                `auto`, which turns this element into the scroll container that
                `position: sticky` binds to — breaking sticky headers on every
                page. `clip` clips the same way without that side effect. */}
            <AppContent variant="sidebar" className="overflow-x-clip">
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                {children}
                <FlashMessages />
            </AppContent>
        </AppShell>
    );
}
