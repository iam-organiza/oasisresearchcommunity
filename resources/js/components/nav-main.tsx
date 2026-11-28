import { Link } from '@inertiajs/react';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuBadge,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import type { NavItem } from '@/types';

/** The current link gets a crimson bar on its left edge, like a ribbon marking a page. */
const currentMark = [
    'relative h-9 gap-3 rounded-control px-3',
    'before:absolute before:inset-y-2 before:left-0 before:w-1 before:rounded-full',
    'before:bg-sidebar-primary before:opacity-0 before:transition-opacity',
    'data-[active=true]:font-semibold data-[active=true]:before:opacity-100',
    'group-data-[collapsible=icon]:before:inset-y-1.5',
].join(' ');

export function NavMain({
    items,
    label,
}: {
    items: NavItem[];
    label?: string;
}) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <SidebarGroup className="px-3 py-0">
            {label && <SidebarGroupLabel>{label}</SidebarGroupLabel>}
            <SidebarMenu className="gap-1">
                {items.map((item) => (
                    <SidebarMenuItem key={item.title}>
                        <SidebarMenuButton
                            asChild
                            isActive={isCurrentOrParentUrl(item.href)}
                            tooltip={{ children: item.title }}
                            className={currentMark}
                        >
                            <Link href={item.href} prefetch>
                                {item.icon && <item.icon />}
                                <span>{item.title}</span>
                            </Link>
                        </SidebarMenuButton>
                        {item.badge ? (
                            <SidebarMenuBadge className="top-2 rounded-full bg-brand-crimson text-white">
                                {item.badge}
                                <span className="sr-only"> waiting</span>
                            </SidebarMenuBadge>
                        ) : null}
                    </SidebarMenuItem>
                ))}
            </SidebarMenu>
        </SidebarGroup>
    );
}
