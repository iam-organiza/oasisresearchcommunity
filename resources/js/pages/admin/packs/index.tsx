import { Head, Link, router } from '@inertiajs/react';
import { Check, Copy } from 'lucide-react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { ConfirmDialog, useConfirm } from '@/components/confirm-dialog';
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
    lines: string[];
    phone: string | null;
    waiting: 'fee' | 'lapsed' | null;
    days_on_the_way: number | null;
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

const WAITING: Record<'fee' | 'lapsed', string> = {
    fee: 'Induction fee not paid yet',
    lapsed: 'Membership lapsed',
};

/** The address as a mailing label, with a button to copy it for the courier. */
function AddressLabel({ row }: { row: Row }) {
    const [copied, setCopied] = useState(false);
    const text = [row.name, ...row.lines, row.phone].filter(Boolean).join('\n');

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(text);
            setCopied(true);
            window.setTimeout(() => setCopied(false), 2000);
        } catch {
            setCopied(false);
        }
    };

    if (row.lines.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Address deleted on delivery.
            </p>
        );
    }

    return (
        <div className="flex flex-wrap items-start gap-3">
            <address className="min-w-56 rounded-control border border-dashed border-border bg-brand-paper px-4 py-3 text-sm leading-relaxed not-italic dark:bg-muted/40">
                <span className="block font-semibold">{row.name}</span>
                {row.lines.map((line) => (
                    <span key={line} className="block">
                        {line}
                    </span>
                ))}
                {row.phone && (
                    <span className="block text-muted-foreground">
                        {row.phone}
                    </span>
                )}
            </address>
            <Button
                type="button"
                size="sm"
                variant="ghost"
                onClick={copy}
                aria-live="polite"
            >
                {copied ? <Check /> : <Copy />}
                {copied ? 'Copied' : 'Copy address'}
                <span className="sr-only"> for {row.name}</span>
            </Button>
        </div>
    );
}

function PackRow({
    row,
    tab,
    onPost,
    onDeliver,
}: {
    row: Row;
    tab: string;
    onPost: () => void;
    onDeliver: () => void;
}) {
    return (
        <li
            className={cn(
                'grid gap-4 px-4 py-5 md:px-6 @4xl:grid-cols-[16rem_minmax(0,1fr)_auto] @4xl:items-start',
                row.waiting && tab === 'to_post' && 'bg-muted/40',
            )}
        >
            <div className="min-w-0">
                <p className="font-semibold">{row.name}</p>
                <p className="text-sm text-muted-foreground">
                    {row.grade}
                    {row.number ? `, ${row.number}` : ', no number yet'}
                </p>
                <p className="text-sm break-all text-muted-foreground">
                    {row.email}
                </p>
                {tab === 'to_post' && row.waiting && (
                    <p className="mt-2 inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold ring-1 ring-border ring-inset">
                        {WAITING[row.waiting]}
                    </p>
                )}
                {tab === 'posted' && row.posted && (
                    <p className="mt-2 text-sm">
                        Posted {row.posted}
                        {row.days_on_the_way !== null && (
                            <span
                                className={cn(
                                    'text-muted-foreground',
                                    row.days_on_the_way > 7 &&
                                        'font-semibold text-brand-crimson-strong dark:text-brand-crimson',
                                )}
                            >
                                ,{' '}
                                {row.days_on_the_way === 0
                                    ? 'today'
                                    : row.days_on_the_way === 1
                                      ? '1 day ago'
                                      : `${row.days_on_the_way} days ago`}
                            </span>
                        )}
                    </p>
                )}
                {tab === 'delivered' && (
                    <p className="mt-2 text-sm">
                        Posted {row.posted}, delivered {row.delivered}
                    </p>
                )}
            </div>
            <AddressLabel row={row} />
            <div className="flex flex-wrap gap-2 @4xl:justify-end">
                {tab === 'to_post' && !row.waiting && (
                    <Button size="sm" onClick={onPost}>
                        Mark as posted
                        <span className="sr-only"> for {row.name}</span>
                    </Button>
                )}
                {(tab === 'posted' || (tab === 'to_post' && !row.waiting)) && (
                    <Button
                        size="sm"
                        variant={tab === 'posted' ? 'default' : 'outline'}
                        onClick={onDeliver}
                    >
                        Mark as delivered
                        <span className="sr-only"> for {row.name}</span>
                    </Button>
                )}
            </div>
        </li>
    );
}

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
    const [delivering, setDelivering] = useState<Row | null>(null);
    const [processing, setProcessing] = useState(false);
    const { confirm, dialog } = useConfirm();
    const markPosted = async (row: Row) => {
        const yes = await confirm({
            title: `Mark ${row.name}'s pack as posted?`,
            description:
                'They can no longer change how they receive their induction. Their address stays until you mark it delivered.',
            confirmLabel: 'Mark as posted',
            cancelLabel: 'Not yet',
        });
        if (yes) {
            router.patch(
                update.url(row.id),
                { status: 'posted' },
                { preserveScroll: true },
            );
        }
    };
    const ready = members.data.filter((row) => !row.waiting);
    const waiting = members.data.filter((row) => row.waiting);
    const list = (rows: Row[]) => (
        <ul className="divide-y divide-border">
            {rows.map((row) => (
                <PackRow
                    key={row.id}
                    row={row}
                    tab={tab}
                    onPost={() => markPosted(row)}
                    onDeliver={() => setDelivering(row)}
                />
            ))}
        </ul>
    );

    return (
        <>
            <Head title="Induction packs" />
            {dialog}
            <div className="w-full px-4 py-8 md:px-8 md:py-10 xl:px-12">
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
                                            'flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-xs',
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
                    ) : tab === 'to_post' ? (
                        <>
                            <section aria-labelledby="ready-heading">
                                <h2
                                    id="ready-heading"
                                    className="border-b border-border px-4 py-3 font-semibold md:px-6"
                                >
                                    Ready to post
                                    <span className="ml-2 font-normal text-muted-foreground">
                                        {ready.length}
                                    </span>
                                </h2>
                                {ready.length > 0 ? (
                                    list(ready)
                                ) : (
                                    <p className="px-4 py-5 text-sm text-muted-foreground md:px-6">
                                        None ready yet. Packs go out once the
                                        member's induction fee is paid.
                                    </p>
                                )}
                            </section>
                            {waiting.length > 0 && (
                                <section
                                    aria-labelledby="waiting-heading"
                                    className="border-t border-border"
                                >
                                    <h2
                                        id="waiting-heading"
                                        className="border-b border-border px-4 py-3 font-semibold md:px-6"
                                    >
                                        Waiting
                                        <span className="ml-2 font-normal text-muted-foreground">
                                            {waiting.length}
                                        </span>
                                    </h2>
                                    {list(waiting)}
                                </section>
                            )}
                        </>
                    ) : (
                        list(members.data)
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

            {delivering && (
                <ConfirmDialog
                    open
                    title="Mark as delivered?"
                    description={`${delivering.name}'s pack. Their delivery address and phone number are deleted, as the privacy policy promises.`}
                    confirmLabel="Mark as delivered"
                    cancelLabel="Not yet"
                    processing={processing}
                    onCancel={() => setDelivering(null)}
                    onConfirm={() => {
                        setProcessing(true);
                        router.patch(
                            update.url(delivering.id),
                            { status: 'delivered' },
                            {
                                preserveScroll: true,
                                onFinish: () => {
                                    setProcessing(false);
                                    setDelivering(null);
                                },
                            },
                        );
                    }}
                />
            )}
        </>
    );
}

InductionPacks.layout = {
    breadcrumbs: [{ title: 'Induction packs', href: index() }],
};
