import { Head, Link, router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { selectClass } from '@/components/membership/form-bits';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { index, show } from '@/routes/admin/applications';

type Row = {
    id: number;
    name: string;
    email: string;
    grade: string | null;
    offered: string | null;
    flags: string[];
    chapter: string | null;
    route: string | null;
    exemption_status: string | null;
    appealed: boolean;
    reasons: string[];
    lower: boolean;
    waiting_days: number | null;
    date: string | null;
};

/** Seven days in front of the Registrar is when a wait starts to show. */
const LONG_WAIT = 7;

const waiting = (days: number) =>
    days === 0 ? 'Today' : days === 1 ? '1 day' : `${days} days`;

function GradeCell({ row }: { row: Row }) {
    if (row.offered && row.grade && row.offered !== row.grade) {
        return (
            <span>
                <span className="text-muted-foreground">{row.grade}</span>
                <span aria-hidden className="mx-1.5 text-muted-foreground">
                    →
                </span>
                <span className="sr-only"> applied, offered </span>
                <span
                    className={cn(
                        'font-semibold',
                        row.lower &&
                            'text-brand-crimson-strong dark:text-brand-crimson',
                    )}
                >
                    {row.offered}
                </span>
            </span>
        );
    }

    return (
        <span className="font-semibold">{row.offered ?? row.grade ?? '—'}</span>
    );
}

function Reasons({ reasons }: { reasons: string[] }) {
    if (reasons.length === 0) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <span className="flex flex-wrap gap-1.5">
            {reasons.map((reason) => (
                <span
                    key={reason}
                    className={cn(
                        'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        reason === 'Appeal' || reason === 'Exemption requested'
                            ? 'text-brand-crimson-strong ring-1 ring-brand-crimson-strong ring-inset dark:text-brand-crimson dark:ring-brand-crimson'
                            : 'bg-muted',
                    )}
                >
                    {reason}
                </span>
            ))}
        </span>
    );
}

function When({ row }: { row: Row }) {
    if (row.waiting_days !== null) {
        return (
            <span
                className={cn(
                    row.waiting_days > LONG_WAIT &&
                        'font-semibold text-brand-crimson-strong dark:text-brand-crimson',
                )}
            >
                {waiting(row.waiting_days)}
            </span>
        );
    }

    return <span className="text-muted-foreground">{row.date ?? '—'}</span>;
}

type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type Option = { value: string; label: string };

const TABS = [
    { value: 'check', label: 'To check' },
    { value: 'submitted', label: 'Appeals' },
    { value: 'offered', label: 'Offered' },
    { value: 'accepted', label: 'Accepted' },
    { value: 'more_information', label: 'More information' },
    { value: 'approved', label: 'Approved' },
    { value: 'declined', label: 'Declined' },
];

const EMPTY: Record<string, string> = {
    check: 'Nothing to check. The site has offered every applicant a grade, and none needs a person to look.',
    submitted: 'No appeals are waiting.',
    offered: 'No offers are waiting for an answer.',
    accepted: 'Nobody has an accepted offer in progress.',
    more_information: 'Nobody has been asked for more information.',
    approved: 'No approved applications yet.',
    declined: 'No declined applications.',
};

export default function ApplicationsIndex({
    applications,
    counts,
    filters,
    grades,
    regions,
}: {
    applications: Paginated<Row>;
    counts: Record<string, number>;
    filters: { status: string; grade: string; region: string; search: string };
    grades: Option[];
    regions: Option[];
}) {
    const [search, setSearch] = useState(filters.search);
    const visit = (changes: Partial<typeof filters>) =>
        router.get(
            index.url({ query: { ...filters, ...changes } }),
            {},
            { preserveScroll: true, preserveState: true },
        );

    const waitingTab = ['check', 'submitted'].includes(filters.status);
    const onSearch = (event: FormEvent) => {
        event.preventDefault();
        visit({ search });
    };

    return (
        <>
            <Head title="Applications" />
            <div className="w-full px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="Applications"
                    description="The site offers every applicant a grade when they submit. Check the ones flagged for a person, answer appeals, and correct an offer when needed."
                />

                <div className="@container rounded-card border border-border bg-background">
                    {/* Status tabs with counts; the waiting count turns crimson when there is work. */}
                    <nav
                        aria-label="Application status"
                        className="flex flex-wrap gap-2 border-b border-border p-4 md:px-6"
                    >
                        {TABS.map((tab) => {
                            const current = filters.status === tab.value;
                            const count = counts[tab.value] ?? 0;

                            return (
                                <Link
                                    key={tab.value}
                                    href={index.url({
                                        query: {
                                            ...filters,
                                            status: tab.value,
                                        },
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
                                    {tab.label}
                                    <span
                                        className={cn(
                                            'flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-xs tabular-nums',
                                            ['check', 'submitted'].includes(
                                                tab.value,
                                            ) && count > 0
                                                ? 'bg-brand-crimson text-white'
                                                : current
                                                  ? 'bg-primary-foreground/15'
                                                  : 'bg-muted',
                                        )}
                                    >
                                        {count}
                                    </span>
                                </Link>
                            );
                        })}
                    </nav>

                    <div className="flex flex-col gap-3 border-b border-border p-4 md:px-6 @3xl:flex-row @3xl:items-end">
                        <form onSubmit={onSearch} className="flex flex-1 gap-2">
                            <div className="relative flex-1">
                                <Search
                                    aria-hidden
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <label htmlFor="search" className="sr-only">
                                    Search by name or email
                                </label>
                                <Input
                                    id="search"
                                    type="search"
                                    placeholder="Search by name or email"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-9"
                                />
                            </div>
                            <Button type="submit" variant="outline">
                                Search
                            </Button>
                        </form>
                        <div className="grid grid-cols-2 gap-2 @3xl:flex">
                            <label htmlFor="grade-filter" className="sr-only">
                                Grade
                            </label>
                            <select
                                id="grade-filter"
                                value={filters.grade}
                                onChange={(e) =>
                                    visit({ grade: e.target.value })
                                }
                                className={cn(
                                    selectClass,
                                    'min-w-0 @3xl:w-auto',
                                )}
                            >
                                <option value="">All grades</option>
                                {grades.map((grade) => (
                                    <option
                                        key={grade.value}
                                        value={grade.value}
                                    >
                                        {grade.label}
                                    </option>
                                ))}
                            </select>
                            <label htmlFor="region-filter" className="sr-only">
                                Region
                            </label>
                            <select
                                id="region-filter"
                                value={filters.region}
                                onChange={(e) =>
                                    visit({ region: e.target.value })
                                }
                                className={cn(
                                    selectClass,
                                    'min-w-0 @3xl:w-auto',
                                )}
                            >
                                <option value="">All regions</option>
                                {regions.map((region) => (
                                    <option
                                        key={region.value}
                                        value={region.value}
                                    >
                                        {region.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {applications.data.length === 0 ? (
                        <p className="p-10 text-center text-muted-foreground">
                            {filters.search || filters.grade || filters.region
                                ? 'No applications match these filters.'
                                : EMPTY[filters.status]}
                        </p>
                    ) : (
                        <>
                            {/* Wide screens: a table, oldest wait first on the working tabs. */}
                            <table className="hidden w-full text-sm @4xl:table">
                                <thead>
                                    <tr className="border-b border-border text-left text-muted-foreground">
                                        <th className="px-6 py-3 font-medium">
                                            Applicant
                                        </th>
                                        <th className="px-3 py-3 font-medium">
                                            Grade
                                        </th>
                                        <th className="px-3 py-3 font-medium">
                                            Why it's here
                                        </th>
                                        <th className="px-3 py-3 font-medium">
                                            Chapter
                                        </th>
                                        <th className="px-6 py-3 text-right font-medium">
                                            {waitingTab ? 'Waiting' : 'Updated'}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-border">
                                    {applications.data.map((row) => (
                                        <tr
                                            key={row.id}
                                            onClick={() =>
                                                router.visit(show.url(row.id))
                                            }
                                            className="cursor-pointer transition-colors hover:bg-accent/50 has-focus-visible:bg-accent/50"
                                        >
                                            <td className="px-6 py-3.5">
                                                {/* The row opens the application on click; the name is the link for the keyboard. */}
                                                <Link
                                                    href={show(row.id)}
                                                    onClick={(event) =>
                                                        event.stopPropagation()
                                                    }
                                                    className="rounded-sm font-semibold focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                                >
                                                    {row.name}
                                                </Link>
                                                <p className="break-all text-muted-foreground">
                                                    {row.email}
                                                </p>
                                            </td>
                                            <td className="px-3 py-3.5">
                                                <GradeCell row={row} />
                                            </td>
                                            <td className="max-w-80 px-3 py-3.5">
                                                <Reasons
                                                    reasons={row.reasons}
                                                />
                                            </td>
                                            <td className="px-3 py-3.5 text-muted-foreground">
                                                {row.chapter ?? '—'}
                                            </td>
                                            <td className="px-6 py-3.5 text-right whitespace-nowrap">
                                                <When row={row} />
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>

                            {/* Narrow screens: one card per application. */}
                            <ul className="divide-y divide-border @4xl:hidden">
                                {applications.data.map((row) => (
                                    <li key={row.id}>
                                        <Link
                                            href={show(row.id)}
                                            className="grid gap-2 p-4 text-sm transition-colors hover:bg-accent/50 focus-visible:bg-accent/50 focus-visible:outline-none md:px-6"
                                        >
                                            <span className="flex items-baseline justify-between gap-3">
                                                <span className="min-w-0">
                                                    <span className="block text-base font-semibold">
                                                        {row.name}
                                                    </span>
                                                    <span className="block break-all text-muted-foreground">
                                                        {row.email}
                                                    </span>
                                                </span>
                                                <span className="shrink-0">
                                                    <When row={row} />
                                                </span>
                                            </span>
                                            <GradeCell row={row} />
                                            {row.reasons.length > 0 && (
                                                <Reasons
                                                    reasons={row.reasons}
                                                />
                                            )}
                                            {row.chapter && (
                                                <span className="text-muted-foreground">
                                                    {row.chapter}
                                                </span>
                                            )}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        </>
                    )}

                    {(applications.prev_page_url ||
                        applications.next_page_url) && (
                        <div className="flex justify-between gap-3 border-t border-border p-4 md:px-6">
                            {applications.prev_page_url ? (
                                <Button asChild variant="outline">
                                    <Link href={applications.prev_page_url}>
                                        Previous
                                    </Link>
                                </Button>
                            ) : (
                                <span />
                            )}
                            {applications.next_page_url && (
                                <Button asChild variant="outline">
                                    <Link href={applications.next_page_url}>
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

ApplicationsIndex.layout = {
    breadcrumbs: [{ title: 'Applications', href: index() }],
};
