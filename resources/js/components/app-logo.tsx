import { usePage } from '@inertiajs/react';
import OasisMark from '@/components/oasis-mark';

/**
 * The mark and the community's name in the current text colour (navy on the white rail,
 * white in dark appearance). The two upper pages of the mark stay crimson.
 */
export default function AppLogo() {
    const { name } = usePage().props;
    // "OASIS Research Community" is too long for one line in the rail, so the first word leads.
    const [lead, ...rest] = name.split(' ');

    return (
        <>
            {/* Wrapped so the sidebar's own icon sizing (direct svg children) leaves the mark alone. */}
            <span className="flex size-9 shrink-0 items-center justify-center group-data-[collapsible=icon]:size-8">
                <OasisMark ink="currentColor" className="size-full" />
            </span>
            <span className="ml-1 grid flex-1 text-left leading-tight">
                <span className="truncate text-base font-bold tracking-tight">
                    {lead}
                </span>
                {rest.length > 0 && (
                    <span className="truncate text-xs opacity-75">
                        {rest.join(' ')}
                    </span>
                )}
            </span>
        </>
    );
}
