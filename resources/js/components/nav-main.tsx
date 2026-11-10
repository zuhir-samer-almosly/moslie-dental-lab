import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarSeparator,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

export type NavGroup = {
    /** Omitted for the lone top entry (the dashboard), which needs no heading. */
    label?: string;
    items: NavItem[];
};

export function NavMain({ groups = [] }: { groups: NavGroup[] }) {
    const { currentUrl } = useCurrentUrl();

    // A section stays lit on its sub-pages (/orders/create, /dentists/5/edit),
    // not only on its list page.
    const isActive = (href: NavItem['href']) => {
        const url = toUrl(href);

        return currentUrl === url || currentUrl.startsWith(`${url}/`);
    };

    return (
        <>
            {groups.map((group, index) => (
                <SidebarGroup
                    key={group.label ?? index}
                    className="px-2 py-0 not-first:mt-1"
                >
                    {/* Collapsed to icons, the headings hide; a hairline
                        keeps the sections apart instead. */}
                    {index > 0 && (
                        <SidebarSeparator className="mx-1 mb-2 hidden group-data-[collapsible=icon]:block" />
                    )}
                    {group.label && (
                        <SidebarGroupLabel className="h-5 px-3 text-[12.5px] font-bold text-muted-foreground">
                            {group.label}
                        </SidebarGroupLabel>
                    )}
                    <SidebarMenu className="gap-0.5">
                        {group.items.map((item) => (
                            <SidebarMenuItem key={item.title}>
                                <SidebarMenuButton
                                    asChild
                                    isActive={isActive(item.href)}
                                    tooltip={{ children: item.title }}
                                    className="h-[34px] gap-3 rounded-[10px] px-3 text-base font-semibold group-data-[collapsible=icon]:p-1.5! data-[active=true]:bg-sidebar-primary data-[active=true]:font-bold data-[active=true]:text-sidebar-primary-foreground data-[active=true]:hover:bg-sidebar-primary data-[active=true]:hover:text-sidebar-primary-foreground [&>svg]:size-5 [&>svg]:stroke-[2.25]"
                                >
                                    <Link href={item.href} prefetch>
                                        {item.icon && <item.icon />}
                                        <span>{item.title}</span>
                                    </Link>
                                </SidebarMenuButton>
                            </SidebarMenuItem>
                        ))}
                    </SidebarMenu>
                </SidebarGroup>
            ))}
        </>
    );
}
