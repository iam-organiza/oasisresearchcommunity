import { usePage } from '@inertiajs/react';
import {
    BadgeCheck,
    BookOpen,
    Banknote,
    BookUser,
    Inbox,
    Library,
    ListChecks,
    LayoutDashboard,
    FileText,
    Globe,
    Map,
    MessageSquare,
    Package,
    ReceiptText,
    ShieldCheck,
    Users,
    Contact,
    History,
    House,
} from 'lucide-react';
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
import { registrar } from '@/routes/admin';
import { index as applications } from '@/routes/admin/applications';
import { index as chapters } from '@/routes/admin/chapters';
import { index as prepMaterials } from '@/routes/admin/exam/materials';
import { index as questionBank } from '@/routes/admin/exam/questions';
import { index as fees } from '@/routes/admin/fees';
import { index as inductionPacks } from '@/routes/admin/packs';
import { index as comments } from '@/routes/admin/comments';
import { index as featuredMembers } from '@/routes/admin/featured-members';
import { index as memberRegister } from '@/routes/admin/members';
import { index as posts } from '@/routes/admin/posts';
import { account, show as membership } from '@/routes/membership';
import { index as staff } from '@/routes/admin/staff';
import {
    edit as websiteSection,
    history as websiteHistory,
} from '@/routes/admin/website';
import type { Auth, NavItem } from '@/types';

const contentNavItems: NavItem[] = [
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
];

// The public site's own content. The legal pages join here in the last phase.
const websiteNavItems: NavItem[] = [
    {
        title: 'Home page',
        href: websiteSection('home'),
        icon: House,
    },
    {
        title: 'Knowledge Base page',
        href: websiteSection('knowledge-base'),
        icon: BookOpen,
    },
    {
        title: 'Featured members',
        href: featuredMembers(),
        icon: Users,
    },
];

// Admins only: the organisation's details.
const websiteAdminNavItems: NavItem[] = [
    {
        title: 'Contact & social',
        href: websiteSection('contact'),
        icon: Contact,
    },
];

// Last in the group, for everyone who edits the site.
const websiteHistoryNavItem: NavItem = {
    title: 'Change history',
    href: websiteHistory(),
    icon: History,
};

const accountNavItems: NavItem[] = [
    {
        title: 'My membership',
        href: membership(),
        icon: BadgeCheck,
    },
    {
        title: 'Fees and payments',
        href: account(),
        icon: ReceiptText,
    },
];

const membershipNavItems: NavItem[] = [
    {
        title: 'Overview',
        href: registrar(),
        icon: LayoutDashboard,
    },
    {
        title: 'Applications',
        href: applications(),
        icon: Inbox,
    },
    {
        title: 'Member register',
        href: memberRegister(),
        icon: BookUser,
    },
    {
        title: 'Induction packs',
        href: inductionPacks(),
        icon: Package,
    },
    {
        title: 'Chapters',
        href: chapters(),
        icon: Map,
    },
];

const examNavItems: NavItem[] = [
    {
        title: 'Question bank',
        href: questionBank(),
        icon: ListChecks,
    },
    {
        title: 'Prep materials',
        href: prepMaterials(),
        icon: Library,
    },
];

const moneyNavItems: NavItem[] = [
    {
        title: 'Fees',
        href: fees(),
        icon: Banknote,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Staff',
        href: staff(),
        icon: ShieldCheck,
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
    const { auth, pendingComments } = usePage<{
        auth: Auth;
        pendingComments: number | null;
    }>().props;
    // Each role sees only its own areas, staff work first and their own account last;
    // labels appear once there is more than one group.
    const membersOnly = (items: NavItem[]) =>
        auth.can.manageMembers ? items : [];
    const groups = [
        { label: 'Membership', items: membersOnly(membershipNavItems) },
        { label: 'Exam', items: membersOnly(examNavItems) },
        { label: 'Money', items: membersOnly(moneyNavItems) },
        {
            label: 'Knowledge Base',
            items: auth.can.manageContent
                ? contentNavItems.map((item) =>
                      item.title === 'Comments'
                          ? { ...item, badge: pendingComments ?? 0 }
                          : item,
                  )
                : [],
        },
        {
            label: 'Website',
            items: [
                ...(auth.can.manageContent ? websiteNavItems : []),
                ...(auth.can.manageStaff ? websiteAdminNavItems : []),
                ...(auth.can.manageContent ? [websiteHistoryNavItem] : []),
            ],
        },
        { label: 'Admin', items: auth.can.manageStaff ? adminNavItems : [] },
        { label: 'My account', items: accountNavItems },
    ].filter((group) => group.items.length > 0);

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
                {groups.map((group) => (
                    <NavMain
                        key={group.label}
                        items={group.items}
                        label={groups.length > 1 ? group.label : undefined}
                    />
                ))}
            </SidebarContent>

            <SidebarFooter className="gap-1 border-t border-sidebar-border px-3 pt-3 pb-4">
                <NavFooter items={footerNavItems} className="p-0" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
