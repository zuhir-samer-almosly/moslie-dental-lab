import { Link } from '@inertiajs/react';
import { ClipboardList, CreditCard, FileText, LayoutGrid, Users } from 'lucide-react';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';
import AppLogo from './app-logo';
import { dashboard } from '@/routes';
import LanguageToggle from './language-toggle';
import { useTranslation } from '@/lib/translations';

export function AppSidebar() {
    const { t } = useTranslation();

    const mainNavItems: NavItem[] = [
        {
            title: t('nav.dashboard'),
            href: dashboard(),
            icon: LayoutGrid,
        },
        {
            title: t('nav.dentists'),
            href: '/dentists',
            icon: Users,
        },
        {
            title: t('nav.orders'),
            href: '/orders',
            icon: ClipboardList,
        },
        {
            title: t('nav.payments'),
            href: '/payments',
            icon: CreditCard,
        },
        {
            title: t('nav.invoices'),
            href: '/invoices',
            icon: FileText,
        },
    ];

    return (
        <Sidebar side="right" collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <div className="flex items-center justify-between px-2 pb-2">
                    <LanguageToggle />
                </div>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
