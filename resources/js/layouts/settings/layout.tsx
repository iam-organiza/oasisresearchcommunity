import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import OasisMark from '@/components/oasis-mark';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { useInitials } from '@/hooks/use-initials';
import { toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: edit(),
        icon: null,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
];

/** Same marking as the main sidebar: a pale tint and a crimson bar on the current link. */
const linkLook = [
    'relative flex h-11 items-center rounded-control px-4 font-semibold transition-colors outline-none',
    'before:absolute before:inset-y-2.5 before:left-0 before:w-1 before:rounded-full before:bg-brand-crimson',
    'focus-visible:ring-[3px] focus-visible:ring-ring/20',
].join(' ');

/** The signed-in member, shown like a membership card: initials, name and email on navy. */
function MemberCard() {
    const { auth } = usePage().props;
    const getInitials = useInitials();

    return (
        <div className="relative overflow-clip rounded-card bg-brand-navy p-6 text-white shadow-page ring-1 ring-white/10 ring-inset">
            <OasisMark
                ink="#fff"
                className="pointer-events-none absolute -right-10 -bottom-12 w-44 opacity-10"
            />
            <span className="flex size-14 items-center justify-center rounded-full bg-brand-crimson-strong text-heading03 font-semibold">
                {getInitials(auth.user.name)}
            </span>
            <p className="mt-5 truncate text-heading03 font-semibold">
                {auth.user.name}
            </p>
            <p className="mt-1 break-all text-brand-on-dark">
                {auth.user.email}
            </p>
        </div>
    );
}

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { isCurrentOrParentUrl } = useCurrentUrl();

    return (
        <div className="max-w-6xl px-4 py-8 md:px-8 md:py-10 xl:px-12">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
            />

            <div className="grid gap-8 lg:grid-cols-12 lg:gap-10">
                <aside className="space-y-4 lg:col-span-4">
                    <MemberCard />

                    <nav
                        aria-label="Settings"
                        className="flex gap-1 overflow-x-auto lg:flex-col"
                    >
                        {sidebarNavItems.map((item, index) => {
                            const current = isCurrentOrParentUrl(item.href);

                            return (
                                <Link
                                    key={`${toUrl(item.href)}-${index}`}
                                    href={item.href}
                                    aria-current={current ? 'page' : undefined}
                                    className={`${linkLook} ${
                                        current
                                            ? 'bg-accent text-foreground before:opacity-100'
                                            : 'text-muted-foreground before:opacity-0 hover:bg-accent hover:text-foreground'
                                    }`}
                                >
                                    {item.title}
                                </Link>
                            );
                        })}
                    </nav>
                </aside>

                <div className="min-w-0 space-y-12 lg:col-span-8">
                    {children}
                </div>
            </div>
        </div>
    );
}
