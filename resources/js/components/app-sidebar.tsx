import { Link, usePage } from '@inertiajs/react';
import {
    Banknote,
    BookOpen,
    CalendarRange,
    ClipboardList,
    CreditCard,
    FileText,
    LayoutGrid,
    NotebookPen,
    NotebookTabs,
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

/** The daily sheet and the dentists' notebooks — all an `entry` login sees. */
const daybookGroup: NavGroup = {
    label: 'اليومية',
    items: [
        { title: 'اليومية', href: '/daybook', icon: NotebookPen },
        { title: 'دفاتر الأطباء', href: '/notebook', icon: NotebookTabs },
    ],
};

const navGroups: NavGroup[] = [
    {
        items: [{ title: 'لوحة التحكم', href: dashboard(), icon: LayoutGrid }],
    },
    daybookGroup,
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
    // The tablet's `entry` login gets its two pages and nothing that leads
    // to a 403: no search, no exchange-rate control, no other sections.
    const isAdmin = usePage().props.auth.user.role === 'admin';
    const home = isAdmin ? dashboard() : '/daybook';

    return (
        <Sidebar side="right" collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={home} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                {isAdmin && <GlobalSearch />}
            </SidebarHeader>

            <SidebarContent className="[scrollbar-width:thin]">
                <NavMain groups={isAdmin ? navGroups : [daybookGroup]} />
            </SidebarContent>

            <SidebarFooter>
                {isAdmin && <NavRate />}
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
