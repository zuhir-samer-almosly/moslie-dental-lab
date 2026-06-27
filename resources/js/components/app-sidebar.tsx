import { Link } from '@inertiajs/react';
import {
    ClipboardList,
    CreditCard,
    FileText,
    HandCoins,
    LayoutGrid,
    PiggyBank,
    UserCog,
    Users,
} from 'lucide-react';
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
import { dashboard } from '@/routes';
import type { NavItem } from '@/types';
import AppLogo from './app-logo';

const mainNavItems: NavItem[] = [
    {
        title: 'لوحة التحكم',
        href: dashboard(),
        icon: LayoutGrid,
    },
    {
        title: 'أطباء الأسنان',
        href: '/dentists',
        icon: Users,
    },
    {
        title: 'الطلبات',
        href: '/orders',
        icon: ClipboardList,
    },
    {
        title: 'المدفوعات',
        href: '/payments',
        icon: CreditCard,
    },
    {
        title: 'الفواتير',
        href: '/invoices',
        icon: FileText,
    },
    {
        title: 'الموظفون',
        href: '/employees',
        icon: UserCog,
    },
    {
        title: 'الرواتب',
        href: '/employee-payments',
        icon: HandCoins,
    },
    {
        title: 'المالية',
        href: '/finance',
        icon: PiggyBank,
    },
];

export function AppSidebar() {
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
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
