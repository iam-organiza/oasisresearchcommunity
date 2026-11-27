import { Head, Link, router } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { naira, selectClass } from '@/components/membership/form-bits';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { show as showAccount } from '@/routes/admin/accounts';
import { show as showApplication } from '@/routes/admin/applications';
import { create, index } from '@/routes/admin/members';

type Row = {
    id: number;
    number: string | null;
    name: string;
    email: string;
    grade: string;
    chapter: string | null;
    joined_on: string;
    active: boolean;
    status: string;
    due_now: number;
    next_due: { amount: number; on: string } | null;
    user_id: number;
    application_id: number | null;
};

type Option = { value: string; label: string };

type Filters = { grade: string; region: string; fees: string; search: string };

const CHIPS = [
    { value: '', key: 'all', label: 'All' },
    { value: 'active', key: 'active', label: 'Active' },
    { value: 'awaiting', key: 'awaiting', label: 'Awaiting fee' },
    { value: 'lapsed', key: 'lapsed', label: 'Lapsed' },
] as const;

function StatusPill({ member }: { member: Row }) {
    return (
        <span
            className={cn(
                'inline-block rounded-full px-2.5 py-0.5 text-xs font-semibold whitespace-nowrap',
                member.active
                    ? 'bg-brand-navy text-white dark:bg-brand-on-dark dark:text-brand-navy'
                    : member.status === 'Lapsed'
                      ? 'text-brand-crimson-strong ring-1 ring-brand-crimson-strong ring-inset dark:text-brand-crimson dark:ring-brand-crimson'
                      : 'ring-1 ring-border ring-inset',
            )}
        >
            {/* "Awaiting induction fee" is shortened to match the chip. */}
            {member.status === 'Awaiting induction fee'
                ? 'Awaiting fee'
                : member.status}
        </span>
    );
}

/** What is due now, and the next subscription when one is coming. */
function Due({ member }: { member: Row }) {
    return (
        <span className="block">
            <span
                className={cn(
                    'block',
                    member.due_now > 0
                        ? 'font-semibold'
                        : 'text-muted-foreground',
                )}
            >
                {member.due_now > 0 ? naira(member.due_now) : 'Nothing'}
            </span>
            {member.next_due && (
                <span className="block text-xs text-muted-foreground">
                    Next {naira(member.next_due.amount)} on {member.next_due.on}
                </span>
            )}
        </span>
    );
}

function Links({ member }: { member: Row }) {
    return (
        <span className="flex flex-wrap gap-x-4 gap-y-1">
            <Link
                href={showAccount(member.user_id)}
                className="font-semibold underline decoration-border underline-offset-4 hover:decoration-current"
            >
                Fees
                <span className="sr-only"> and payments for {member.name}</span>
            </Link>
            {member.application_id && (
                <Link
                    href={showApplication(member.application_id)}
                    className="font-semibold underline decoration-border underline-offset-4 hover:decoration-current"
                >
                    Application
                    <span className="sr-only"> of {member.name}</span>
                </Link>
            )}
        </span>
    );
}

export default function MembersIndex({
    members,
    counts,
    filters,
    grades,
    regions,
}: {
    members: {
        data: Row[];
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
    };
    counts: Record<'all' | 'active' | 'awaiting' | 'lapsed', number>;
    filters: Filters;
    grades: Option[];
    regions: Option[];
}) {
    const [search, setSearch] = useState(filters.search);
    const url = (changes: Partial<Filters>) =>
        index.url({ query: { ...filters, ...changes } });
    const visit = (changes: Partial<Filters>) =>
        router.get(
            url(changes),
            {},
            { preserveScroll: true, preserveState: true },
        );
    const onSearch = (event: FormEvent) => {
        event.preventDefault();
        visit({ search });
    };
    const filtered =
        filters.search || filters.grade || filters.region || filters.fees;

    return (
        <>
            <Head title="Member register" />
            <div className="@container w-full px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <div className="mb-8 flex flex-col gap-4 @3xl:flex-row @3xl:items-start @3xl:justify-between">
                    <div className="max-w-prose [&>header]:mb-0">
                        <Heading
                            title="Member register"
                            description="Everyone admitted. A member is active once their induction fee is paid or waived, and lapses 30 days after a yearly subscription goes unpaid."
                        />
                    </div>
                    <Button asChild size="lg" className="shrink-0">
                        <Link href={create()}>
                            <Plus />
                            Record an existing member
                        </Link>
                    </Button>
                </div>

                <div className="rounded-card border border-border bg-background">
                    {/* Status chips with counts, then search and the other filters. */}
                    <div className="grid gap-3 border-b border-border p-4 md:px-6">
                        <nav
                            aria-label="Status"
                            className="flex flex-wrap gap-2"
                        >
                            {CHIPS.map((chip) => {
                                const current = filters.fees === chip.value;

                                return (
                                    <Link
                                        key={chip.key}
                                        href={url({ fees: chip.value })}
                                        preserveScroll
                                        aria-current={
                                            current ? 'page' : undefined
                                        }
                                        className={cn(
                                            'flex h-9 items-center gap-2 rounded-full pr-1.5 pl-4 text-sm font-semibold transition-colors',
                                            'focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                                            current
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground ring-1 ring-border ring-inset hover:bg-accent hover:text-foreground',
                                        )}
                                    >
                                        {chip.label}
                                        <span
                                            className={cn(
                                                'flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-xs',
                                                current
                                                    ? 'bg-primary-foreground/15'
                                                    : 'bg-muted',
                                            )}
                                        >
                                            {counts[chip.key]}
                                        </span>
                                    </Link>
                                );
                            })}
                        </nav>
                        <div className="grid gap-2 @3xl:grid-cols-[minmax(0,1fr)_minmax(0,28rem)]">
                            <form onSubmit={onSearch} className="flex gap-2">
                                <div className="relative flex-1">
                                    <Search
                                        aria-hidden
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <label htmlFor="search" className="sr-only">
                                        Search by name, email or number
                                    </label>
                                    <Input
                                        id="search"
                                        type="search"
                                        placeholder="Search by name, email or number"
                                        value={search}
                                        onChange={(e) =>
                                            setSearch(e.target.value)
                                        }
                                        className="pl-9"
                                    />
                                </div>
                                <Button type="submit" variant="outline">
                                    Search
                                </Button>
                            </form>
                            <div className="grid grid-cols-2 gap-2">
                                {[
                                    {
                                        id: 'grade' as const,
                                        label: 'Grade',
                                        all: 'All grades',
                                        options: grades,
                                    },
                                    {
                                        id: 'region' as const,
                                        label: 'Region',
                                        all: 'All regions',
                                        options: regions,
                                    },
                                ].map((filter) => (
                                    <div key={filter.id} className="min-w-0">
                                        <label
                                            htmlFor={`${filter.id}-filter`}
                                            className="sr-only"
                                        >
                                            {filter.label}
                                        </label>
                                        <select
                                            id={`${filter.id}-filter`}
                                            value={filters[filter.id]}
                                            onChange={(e) =>
                                                visit({
                                                    [filter.id]: e.target.value,
                                                })
                                            }
                                            className={selectClass}
                                        >
                                            <option value="">
                                                {filter.all}
                                            </option>
                                            {filter.options.map((option) => (
                                                <option
                                                    key={option.value}
                                                    value={option.value}
                                                >
                                                    {option.label}
                                                </option>
                                            ))}
                                        </select>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {members.data.length === 0 ? (
                        <p className="p-10 text-center text-muted-foreground">
                            {filtered
                                ? 'No members match these filters.'
                                : 'Nobody is on the register yet. Approved applicants appear here, or record someone admitted before the website.'}
                        </p>
                    ) : (
                        <>
                            {/* Wide screens: the register as a table. */}
                            <div className="hidden overflow-x-auto @5xl:block">
                                <table className="w-full text-sm">
                                    <thead>
                                        <tr className="border-b border-border text-left text-muted-foreground">
                                            <th className="px-6 py-3 font-medium">
                                                Number
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Member
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Grade
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Chapter
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Status
                                            </th>
                                            <th className="px-3 py-3 font-medium">
                                                Due now
                                            </th>
                                            <th className="px-6 py-3 font-medium">
                                                <span className="sr-only">
                                                    Links
                                                </span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {members.data.map((member) => (
                                            <tr
                                                key={member.id}
                                                className="align-top"
                                            >
                                                <td className="py-3.5 pr-3 pl-6 font-semibold whitespace-nowrap">
                                                    {member.number ?? (
                                                        <span className="font-normal tracking-normal text-muted-foreground">
                                                            When active
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="min-w-48 px-3 py-3.5">
                                                    <span className="block font-semibold">
                                                        {member.name}
                                                    </span>
                                                    <span className="block [overflow-wrap:anywhere] text-muted-foreground">
                                                        {member.email}
                                                    </span>
                                                    <span className="block text-xs text-muted-foreground">
                                                        Since {member.joined_on}
                                                    </span>
                                                </td>
                                                <td className="px-3 py-3.5 whitespace-nowrap">
                                                    {member.grade}
                                                </td>
                                                <td className="min-w-36 px-3 py-3.5 text-muted-foreground">
                                                    {member.chapter ?? '—'}
                                                </td>
                                                <td className="px-3 py-3.5">
                                                    <StatusPill
                                                        member={member}
                                                    />
                                                </td>
                                                <td className="min-w-32 px-3 py-3.5">
                                                    <Due member={member} />
                                                </td>
                                                <td className="px-6 py-3.5">
                                                    <Links member={member} />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {/* Narrow screens: one card per member. */}
                            <ul className="divide-y divide-border @5xl:hidden">
                                {members.data.map((member) => (
                                    <li
                                        key={member.id}
                                        className="grid gap-2 p-4 text-sm md:px-6"
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div className="min-w-0">
                                                <p className="text-base font-semibold">
                                                    {member.name}
                                                </p>
                                                <p className="break-all text-muted-foreground">
                                                    {member.email}
                                                </p>
                                            </div>
                                            <StatusPill member={member} />
                                        </div>
                                        <p>
                                            <span className="font-semibold tracking-wide">
                                                {member.number ??
                                                    'Number when active'}
                                            </span>
                                            <span className="text-muted-foreground">
                                                , {member.grade}
                                                {member.chapter &&
                                                    `, ${member.chapter}`}
                                                , since {member.joined_on}
                                            </span>
                                        </p>
                                        <div className="flex items-end justify-between gap-3">
                                            <div>
                                                <span className="text-muted-foreground">
                                                    Due now
                                                </span>
                                                <Due member={member} />
                                            </div>
                                            <Links member={member} />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </>
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

MembersIndex.layout = {
    breadcrumbs: [{ title: 'Member register', href: index() }],
};
