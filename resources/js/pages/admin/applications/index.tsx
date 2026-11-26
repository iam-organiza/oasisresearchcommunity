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
    date: string | null;
};

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

    const onSearch = (event: FormEvent) => {
        event.preventDefault();
        visit({ search });
    };

    return (
        <>
            <Head title="Applications" />
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
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
                        <div className="flex gap-2">
                            <label htmlFor="grade-filter" className="sr-only">
                                Grade
                            </label>
                            <select
                                id="grade-filter"
                                value={filters.grade}
                                onChange={(e) =>
                                    visit({ grade: e.target.value })
                                }
                                className={cn(selectClass, 'w-auto')}
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
                                className={cn(selectClass, 'w-auto')}
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
                        <ul className="divide-y divide-border">
                            {applications.data.map((row) => (
                                <li key={row.id}>
                                    <Link
                                        href={show(row.id)}
                                        className="flex flex-col gap-2 p-4 transition-colors hover:bg-accent/50 focus-visible:bg-accent/50 focus-visible:outline-none md:px-6 @3xl:flex-row @3xl:items-center @3xl:gap-6"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="font-semibold">
                                                {row.name}
                                            </p>
                                            <p className="text-sm break-all text-muted-foreground">
                                                {row.email}
                                            </p>
                                        </div>
                                        <div className="flex flex-wrap items-center gap-2 text-sm @3xl:w-[28rem] @3xl:justify-end">
                                            {row.offered ? (
                                                <span className="rounded-full bg-muted px-2.5 py-0.5 font-semibold">
                                                    Offered {row.offered}
                                                    {row.grade !==
                                                        row.offered &&
                                                        ` (asked for ${row.grade})`}
                                                </span>
                                            ) : (
                                                row.grade && (
                                                    <span className="rounded-full bg-muted px-2.5 py-0.5 font-semibold">
                                                        {row.grade}
                                                    </span>
                                                )
                                            )}
                                            {row.flags
                                                .filter(
                                                    (flag) =>
                                                        !flag.startsWith(
                                                            'Asks',
                                                        ),
                                                )
                                                .map((flag) => (
                                                    <span
                                                        key={flag}
                                                        className="rounded-full px-2.5 py-0.5 ring-1 ring-border ring-inset"
                                                    >
                                                        {flag}
                                                    </span>
                                                ))}
                                            {row.route === 'exemption' && (
                                                <span className="rounded-full px-2.5 py-0.5 font-semibold text-brand-crimson-strong ring-1 ring-brand-crimson-strong ring-inset dark:text-brand-crimson dark:ring-brand-crimson">
                                                    Exemption{' '}
                                                    {row.exemption_status ??
                                                        'requested'}
                                                </span>
                                            )}
                                            {row.appealed && (
                                                <span className="rounded-full px-2.5 py-0.5 font-semibold ring-1 ring-border ring-inset">
                                                    Appeal
                                                </span>
                                            )}
                                            {row.chapter && (
                                                <span className="text-muted-foreground">
                                                    {row.chapter}
                                                </span>
                                            )}
                                            {row.date && (
                                                <span className="text-muted-foreground tabular-nums">
                                                    {row.date}
                                                </span>
                                            )}
                                        </div>
                                    </Link>
                                </li>
                            ))}
                        </ul>
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
