import { Link, usePage } from '@inertiajs/react';
import OasisMark from '@/components/oasis-mark';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

/**
 * Account pages frame: a navy panel with the OASIS mark and tagline on wide screens,
 * and the page's title, description and form on a pale panel beside it.
 */
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    const { tagline } = usePage().props;

    return (
        <div className="grid min-h-svh bg-brand-paper font-sans text-brand-navy lg:grid-cols-12">
            <aside className="relative hidden flex-col justify-between overflow-clip bg-brand-navy p-14 text-white lg:col-span-5 lg:flex">
                <Link href={home()} className="w-fit">
                    <img
                        src="/images/oasis-logo-white.png"
                        alt="OASIS Research Community"
                        width={900}
                        height={336}
                        className="h-12 w-auto"
                    />
                </Link>
                <OasisMark ink="#fff" className="w-full max-w-md self-center" />
                <p className="font-display text-heading01 font-black">
                    {tagline}
                </p>
            </aside>

            <main className="flex flex-col px-6 py-10 sm:px-12 lg:col-span-7 lg:justify-center lg:px-20 lg:py-16">
                <Link href={home()} className="mb-12 w-fit lg:hidden">
                    <img
                        src="/images/oasis-logo.png"
                        alt="OASIS Research Community"
                        width={900}
                        height={336}
                        className="h-11 w-auto"
                    />
                </Link>
                <div className="w-full max-w-md">
                    <h1 className="font-display text-heading01 font-black">
                        {title}
                    </h1>
                    {description && (
                        <p className="mt-3 text-body01 text-brand-navy/70">
                            {description}
                        </p>
                    )}
                    <div className="mt-10">{children}</div>
                </div>
            </main>
        </div>
    );
}
