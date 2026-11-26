import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/admin/packs';

type Row = {
    id: number;
    name: string;
    email: string;
    number: string | null;
    grade: string;
    active: boolean;
    address: string;
    phone: string | null;
    posted: string | null;
    delivered: string | null;
};

const TABS = [
    { value: 'to_post', label: 'To post' },
    { value: 'posted', label: 'On the way' },
    { value: 'delivered', label: 'Delivered' },
];

const EMPTY: Record<string, string> = {
    to_post:
        'No packs to post. Members who choose to have their pack posted appear here.',
    posted: 'No packs on the way.',
    delivered: 'No packs delivered yet.',
};

export default function InductionPacks({
    tab,
    counts,
    members,
}: {
    tab: string;
    counts: Record<string, number>;
    members: {
        data: Row[];
        prev_page_url: string | null;
        next_page_url: string | null;
    };
}) {
    const mark = (row: Row, status: 'posted' | 'delivered') => {
        if (
            status === 'delivered' &&
            !window.confirm(
                `Mark ${row.name}'s pack as delivered? Their delivery address is deleted.`,
            )
        ) {
            return;
        }
        router.patch(update.url(row.id), { status }, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Induction packs" />
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="Induction packs"
                    description="New members who chose to have their certificate, award and gown posted instead of attending the ceremony. Post once their membership is active; addresses are deleted on delivery."
                />

                <div className="@container rounded-card border border-border bg-background">
                    <nav
                        aria-label="Pack status"
                        className="flex flex-wrap gap-2 border-b border-border p-4 md:px-6"
                    >
                        {TABS.map((item) => {
                            const current = tab === item.value;

                            return (
                                <Link
                                    key={item.value}
                                    href={index.url({
                                        query: { tab: item.value },
                                    })}
                                    aria-current={current ? 'page' : undefined}
                                    className={cn(
                                        'flex h-9 items-center gap-2 rounded-full pr-1.5 pl-4 text-sm font-semibold transition-colors',
                                        'focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                                        current
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground ring-1 ring-border ring-inset hover:bg-accent hover:text-foreground',
                                    )}
                                >
                                    {item.label}
                                    <span
                                        className={cn(
                                            'flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-xs tabular-nums',
                                            item.value === 'to_post' &&
                                                (counts[item.value] ?? 0) > 0
                                                ? 'bg-brand-crimson text-white'
                                                : current
                                                  ? 'bg-primary-foreground/15'
                                                  : 'bg-muted',
                                        )}
                                    >
                                        {counts[item.value] ?? 0}
                                    </span>
                                </Link>
                            );
                        })}
                    </nav>

                    {members.data.length === 0 ? (
                        <p className="p-10 text-center text-muted-foreground">
                            {EMPTY[tab]}
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {members.data.map((row) => (
                                <li
                                    key={row.id}
                                    className="flex flex-col gap-3 p-4 md:px-6 @3xl:flex-row @3xl:items-center @3xl:gap-6"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="flex flex-wrap items-baseline gap-x-3 font-semibold">
                                            {row.name}
                                            <span className="text-sm text-muted-foreground tabular-nums">
                                                {row.number ?? 'No number yet'}
                                            </span>
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {row.grade} · {row.email}
                                        </p>
                                        {row.address && (
                                            <p className="mt-1 text-sm">
                                                {row.address}
                                                {row.phone && ` · ${row.phone}`}
                                            </p>
                                        )}
                                        {(row.posted || row.delivered) && (
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {row.posted &&
                                                    `Posted ${row.posted}`}
                                                {row.delivered &&
                                                    ` · delivered ${row.delivered}`}
                                            </p>
                                        )}
                                    </div>
                                    <div className="flex flex-wrap items-center gap-2 text-sm">
                                        {!row.active && tab === 'to_post' && (
                                            <span className="rounded-full px-2.5 py-0.5 ring-1 ring-border ring-inset">
                                                Induction fee not settled
                                            </span>
                                        )}
                                        {tab === 'to_post' && (
                                            <Button
                                                size="sm"
                                                disabled={!row.active}
                                                onClick={() =>
                                                    mark(row, 'posted')
                                                }
                                            >
                                                Mark as posted
                                                <span className="sr-only">
                                                    {' '}
                                                    for {row.name}
                                                </span>
                                            </Button>
                                        )}
                                        {tab !== 'delivered' && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                disabled={
                                                    !row.active &&
                                                    tab === 'to_post'
                                                }
                                                onClick={() =>
                                                    mark(row, 'delivered')
                                                }
                                            >
                                                Mark as delivered
                                                <span className="sr-only">
                                                    {' '}
                                                    for {row.name}
                                                </span>
                                            </Button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}

                    {(members.prev_page_url || members.next_page_url) && (
                        <div className="flex justify-between gap-3 border-t border-border p-4 md:px-6">
                            {members.prev_page_url ? (
                                <Button asChild variant="outline">
                                    <Link href={members.prev_page_url}>
                                        Previous
                                    </Link>
                                </Button>
                            ) : (
                                <span />
                            )}
                            {members.next_page_url && (
                                <Button asChild variant="outline">
                                    <Link href={members.next_page_url}>
                                        Next
                                    </Link>
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}

InductionPacks.layout = {
    breadcrumbs: [{ title: 'Induction packs', href: index() }],
};
