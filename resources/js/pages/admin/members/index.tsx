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
    owed: number;
    user_id: number;
    application_id: number | null;
};

type Option = { value: string; label: string };

export default function MembersIndex({
    members,
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
    filters: { grade: string; region: string; fees: string; search: string };
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
            <Head title="Member register" />
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Member register"
                        description="Everyone with a membership number. A member is active once their induction fee is paid or waived, and lapses 30 days after a yearly subscription goes unpaid."
                    />
                    <Button asChild size="lg" className="mb-8">
                        <Link href={create()}>
                            <Plus />
                            Record an existing member
                        </Link>
                    </Button>
                </div>

                <div className="@container rounded-card border border-border bg-background">
                    <div className="flex flex-col gap-3 border-b border-border p-4 md:px-6 @4xl:flex-row @4xl:items-end">
                        <form onSubmit={onSearch} className="flex flex-1 gap-2">
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
                                    onChange={(e) => setSearch(e.target.value)}
                                    className="pl-9"
                                />
                            </div>
                            <Button type="submit" variant="outline">
                                Search
                            </Button>
                        </form>
                        <div className="flex flex-wrap gap-2">
                            {[
                                {
                                    id: 'grade',
                                    label: 'Grade',
                                    all: 'All grades',
                                    options: grades,
                                },
                                {
                                    id: 'region',
                                    label: 'Region',
                                    all: 'All regions',
                                    options: regions,
                                },
                                {
                                    id: 'fees',
                                    label: 'Status',
                                    all: 'Any status',
                                    options: [
                                        { value: 'active', label: 'Active' },
                                        {
                                            value: 'awaiting',
                                            label: 'Awaiting induction fee',
                                        },
                                        { value: 'lapsed', label: 'Lapsed' },
                                    ],
                                },
                            ].map((filter) => (
                                <div key={filter.id}>
                                    <label
                                        htmlFor={`${filter.id}-filter`}
                                        className="sr-only"
                                    >
                                        {filter.label}
                                    </label>
                                    <select
                                        id={`${filter.id}-filter`}
                                        value={
                                            filters[
                                                filter.id as keyof typeof filters
                                            ]
                                        }
                                        onChange={(e) =>
                                            visit({
                                                [filter.id]: e.target.value,
                                            })
                                        }
                                        className={cn(selectClass, 'w-auto')}
                                    >
                                        <option value="">{filter.all}</option>
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

                    {members.data.length === 0 ? (
                        <p className="p-10 text-center text-muted-foreground">
                            {filters.search ||
                            filters.grade ||
                            filters.region ||
                            filters.fees
                                ? 'No members match these filters.'
                                : 'Nobody is on the register yet. Approved applicants appear here, or record someone admitted before the website.'}
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {members.data.map((member) => (
                                <li
                                    key={member.id}
                                    className="flex flex-col gap-3 p-4 md:px-6 @3xl:flex-row @3xl:items-center @3xl:gap-6"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="flex flex-wrap items-baseline gap-x-3 font-semibold">
                                            {member.name}
                                            <span className="text-sm font-semibold tracking-wide text-muted-foreground tabular-nums">
                                                {member.number ??
                                                    'Number when active'}
                                            </span>
                                        </p>
                                        <p className="text-sm break-all text-muted-foreground">
                                            {member.email}
                                        </p>
                                        <p className="mt-1 text-sm">
                                            {member.grade}
                                            {member.chapter &&
                                                ` · ${member.chapter}`}{' '}
                                            · since {member.joined_on}
                                            {member.application_id && (
                                                <>
                                                    {' · '}
                                                    <Link
                                                        href={showApplication(
                                                            member.application_id,
                                                        )}
                                                        className="underline decoration-border underline-offset-4 hover:decoration-current"
                                                    >
                                                        Application
                                                    </Link>
                                                </>
                                            )}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap items-center gap-3 text-sm">
                                        <span
                                            className={cn(
                                                'rounded-full px-2.5 py-0.5 font-semibold',
                                                member.active
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'ring-1 ring-border ring-inset',
                                            )}
                                        >
                                            {member.status}
                                        </span>
                                        <span className="text-muted-foreground">
                                            {member.owed > 0
                                                ? `Owes ${naira(member.owed)}`
                                                : 'Nothing owed'}
                                        </span>
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link
                                                href={showAccount(
                                                    member.user_id,
                                                )}
                                            >
                                                Fees and payments
                                                <span className="sr-only">
                                                    {' '}
                                                    for {member.name}
                                                </span>
                                            </Link>
                                        </Button>
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

MembersIndex.layout = {
    breadcrumbs: [{ title: 'Member register', href: index() }],
};
