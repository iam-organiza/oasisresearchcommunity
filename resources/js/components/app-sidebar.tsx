import { Link, usePage } from '@inertiajs/react';
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
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={home()} prefetch>
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
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
