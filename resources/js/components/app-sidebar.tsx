import { usePage } from '@inertiajs/react';
import { FileText, Globe, MessageSquare, Users } from 'lucide-react';
import AppLogo from '@/components/app-logo';
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
import { home } from '@/routes';
import { index as comments } from '@/routes/admin/comments';
import { index as featuredMembers } from '@/routes/admin/featured-members';
import { index as posts } from '@/routes/admin/posts';
import type { Auth, NavItem } from '@/types';

const staffNavItems: NavItem[] = [
    {
        title: 'Posts',
        href: posts(),
        icon: FileText,
    },
    {
        title: 'Comments',
        href: comments(),
        icon: MessageSquare,
    },
    {
        title: 'Featured members',
        href: featuredMembers(),
        icon: Users,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'View the site',
        href: home(),
        icon: Globe,
    },
];

export function AppSidebar() {
    const { auth } = usePage<{ auth: Auth }>().props;
    const mainNavItems = auth.user.role === 'member' ? [] : staffNavItems;

    return (
        <Sidebar
            collapsible="icon"
            variant="sidebar"
            className="border-r-sidebar-border"
        >
            <SidebarHeader className="px-3 pt-5 pb-4">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="text-sidebar-accent-foreground group-data-[collapsible=icon]:p-0! hover:bg-transparent hover:text-sidebar-accent-foreground"
                        >
                            {/* The home page is not a React page, so a plain link loads it in full. */}
                            <a href={home.url()}>
                                <AppLogo />
                            </a>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                {mainNavItems.length > 0 && <NavMain items={mainNavItems} />}
            </SidebarContent>

            <SidebarFooter className="gap-1 border-t border-sidebar-border px-3 pt-3 pb-4">
                <NavFooter items={footerNavItems} className="p-0" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
