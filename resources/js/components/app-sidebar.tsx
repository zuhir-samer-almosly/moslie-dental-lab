import { Link } from '@inertiajs/react';
import {
    Banknote,
    BookOpen,
    CalendarRange,
    ClipboardList,
    CreditCard,
    FileText,
    LayoutGrid,
    Package,
    PiggyBank,
    Receipt,
    Scale,
    Tags,
    UserCog,
    Users,
    Wallet,
} from 'lucide-react';
import { GlobalSearch } from '@/components/global-search';
import { NavMain } from '@/components/nav-main';
import type { NavGroup } from '@/components/nav-main';
import { NavRate } from '@/components/nav-rate';
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
import AppLogo from './app-logo';

const navGroups: NavGroup[] = [
    {
        items: [{ title: 'لوحة التحكم', href: dashboard(), icon: LayoutGrid }],
    },
    {
        label: 'الأطباء والطلبات',
        items: [
            { title: 'أطباء الأسنان', href: '/dentists', icon: Users },
            { title: 'الطلبات', href: '/orders', icon: ClipboardList },
            { title: 'أنواع العمل', href: '/work-types', icon: Tags },
            { title: 'المدفوعات', href: '/payments', icon: CreditCard },
            { title: 'الفواتير', href: '/invoices', icon: FileText },
            { title: 'الأرصدة المستحقة', href: '/outstanding', icon: Wallet },
        ],
    },
    {
        label: 'المصاريف',
        items: [
            { title: 'الموظفون', href: '/employees', icon: UserCog },
            { title: 'النفقات', href: '/material-purchases', icon: Package },
        ],
    },
    {
        label: 'التقارير',
        items: [
            { title: 'المالية', href: '/finance', icon: PiggyBank },
            { title: 'التقرير', href: '/report', icon: CalendarRange },
        ],
    },
    {
        label: 'الدفاتر المحاسبية',
        items: [
            { title: 'الصندوق', href: '/ledger/cash', icon: Banknote },
            {
                title: 'ميزان المراجعة',
                href: '/ledger/trial-balance',
                icon: Scale,
            },
            { title: 'قيود اليومية', href: '/ledger/journal', icon: BookOpen },
            { title: 'كشف حساب', href: '/ledger/statement', icon: Receipt },
        ],
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
                <GlobalSearch />
            </SidebarHeader>

            <SidebarContent className="[scrollbar-width:thin]">
                <NavMain groups={navGroups} />
            </SidebarContent>

            <SidebarFooter>
                <NavRate />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
