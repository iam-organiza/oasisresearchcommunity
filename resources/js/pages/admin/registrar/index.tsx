import { Head, Link } from '@inertiajs/react';
import { ArrowRight, ChevronRight } from 'lucide-react';
import type { ReactNode } from 'react';
import Heading from '@/components/heading';
import { cn } from '@/lib/utils';
import { registrar } from '@/routes/admin';
import { index as applications } from '@/routes/admin/applications';
import { index as members } from '@/routes/admin/members';
import { index as packs } from '@/routes/admin/packs';

type Status = { value: string; label: string; total: number };

type Props = {
    waiting: number;
    statuses: Status[];
    grades: {
        label: string;
        total: number;
        active: number;
        awaiting: number;
        lapsed: number;
    }[];
    regions: {
        name: string;
        applicants: number;
        members: number;
        states: { name: string; applicants: number; members: number }[];
    }[];
    members: number;
    queues: {
        appeals: number;
        more_information: number;
        awaiting_fee: number;
        lapsed: number;
        packs_to_post: number;
    };
};

const plural = (count: number, one: string, many: string) =>
    count === 1 ? one : many;

/** One work queue: a count, what it is, and the list that holds it. */
function Queue({
    count,
    label,
    note,
    href,
}: {
    count: number;
    label: string;
    note: string;
    href: string;
}) {
    return (
        <Link
            href={href}
            className={cn(
                'group flex flex-col rounded-card border border-border bg-background p-4 transition-colors @2xl:p-5',
                'hover:border-foreground/40 focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
            )}
        >
            <span
                className={cn(
                    'text-heading02 leading-none font-bold',
                    count === 0 && 'text-muted-foreground',
                )}
            >
                {count}
            </span>
            <span className="mt-2 font-semibold">{label}</span>
            <span className="mt-0.5 text-sm text-muted-foreground">{note}</span>
            <span className="mt-auto flex items-center gap-1 pt-4 text-sm font-semibold">
                Open
                <ArrowRight
                    aria-hidden
                    className="size-4 transition-transform group-hover:translate-x-0.5"
                />
            </span>
        </Link>
    );
}

/** A stage in the pipeline; a link when the applications list has a tab for it. */
function Stage({
    status,
    href,
    tone = 'plain',
}: {
    status: Status | undefined;
    href?: string;
    tone?: 'plain' | 'done';
}) {
    if (!status) {
        return null;
    }
    const body: ReactNode = (
        <>
            <span className="text-heading03 font-bold">{status.total}</span>
            <span className="text-sm">{status.label}</span>
        </>
    );
    const className = cn(
        'flex min-w-0 flex-col gap-0.5 rounded-control px-4 py-3',
        tone === 'done'
            ? 'bg-brand-navy text-white dark:bg-brand-on-dark dark:text-brand-navy'
            : 'bg-muted',
    );

    return href ? (
        <Link
            href={href}
            className={cn(
                className,
                'transition-opacity hover:opacity-85 focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
            )}
        >
            {body}
        </Link>
    ) : (
        <div className={className}>{body}</div>
    );
}

export default function RegistrarOverview({
    waiting,
    statuses,
    grades,
    regions,
    members: memberCount,
    queues,
}: Props) {
    const status = (value: string) =>
        statuses.find((item) => item.value === value);
    const byStatus = (value: string) =>
        applications.url({ query: { status: value } });
    const biggestGrade = Math.max(1, ...grades.map((grade) => grade.total));
    const busiest = Math.max(
        1,
        ...regions.map((region) => region.applicants + region.members),
    );
    const anyoneInRegions = regions.some(
        (region) => region.applicants + region.members > 0,
    );

    return (
        <>
            <Head title="Membership" />
            <div className="@container w-full px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="Membership"
                    description="What needs you now, where applications stand, the register and where people are."
                />

                <div className="grid gap-10 *:min-w-0">
                    {/* What needs the Registrar now, each linking to its list. */}
                    <section aria-labelledby="queues-heading">
                        <h2 id="queues-heading" className="sr-only">
                            Work queues
                        </h2>
                        <div className="grid grid-cols-2 gap-3 @2xl:gap-4 @4xl:grid-cols-3 @5xl:grid-cols-[1.4fr_repeat(5,minmax(0,1fr))]">
                            <Link
                                href={byStatus('check')}
                                className={cn(
                                    'group col-span-2 flex flex-col rounded-card bg-brand-navy p-6 text-white @4xl:col-span-3 @5xl:col-span-1 dark:bg-brand-night-raised',
                                    'focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                                )}
                            >
                                <span className="font-display text-heading01 leading-none font-black">
                                    {waiting}
                                </span>
                                <span className="mt-2 font-semibold">
                                    {plural(
                                        waiting,
                                        'Application to check',
                                        'Applications to check',
                                    )}
                                </span>
                                <span className="mt-0.5 text-sm text-brand-on-dark">
                                    Flagged offers, appeals and exemption
                                    requests
                                </span>
                                <span className="mt-auto flex items-center gap-1 pt-5 font-semibold">
                                    Check applications
                                    <ArrowRight
                                        aria-hidden
                                        className="size-4 transition-transform group-hover:translate-x-0.5"
                                    />
                                </span>
                            </Link>
                            <Queue
                                count={queues.appeals}
                                label={plural(
                                    queues.appeals,
                                    'Appeal',
                                    'Appeals',
                                )}
                                note="With the Registrar"
                                href={byStatus('submitted')}
                            />
                            <Queue
                                count={queues.more_information}
                                label="Waiting on the applicant"
                                note="More information asked for"
                                href={byStatus('more_information')}
                            />
                            <Queue
                                count={queues.awaiting_fee}
                                label="Awaiting induction fee"
                                note="Admitted, no number yet"
                                href={members.url({
                                    query: { fees: 'awaiting' },
                                })}
                            />
                            <Queue
                                count={queues.lapsed}
                                label="Lapsed"
                                note="Subscription unpaid past grace"
                                href={members.url({
                                    query: { fees: 'lapsed' },
                                })}
                            />
                            <Queue
                                count={queues.packs_to_post}
                                label={plural(
                                    queues.packs_to_post,
                                    'Pack to post',
                                    'Packs to post',
                                )}
                                note="Induction by post"
                                href={packs.url({ query: { tab: 'to_post' } })}
                            />
                        </div>
                    </section>

                    <div className="grid items-start gap-6 *:min-w-0 @6xl:grid-cols-2">
                        {/* Where applications stand, in the order they move. */}
                        <section
                            aria-labelledby="pipeline-heading"
                            className="rounded-card border border-border bg-background p-6"
                        >
                            <h2
                                id="pipeline-heading"
                                className="text-heading03 font-semibold"
                            >
                                Applications
                            </h2>
                            <ol className="mt-5 grid gap-2 @xl:grid-cols-[repeat(4,minmax(0,1fr))]">
                                {[
                                    { value: 'draft' },
                                    { value: 'offered', href: true },
                                    { value: 'accepted', href: true },
                                    {
                                        value: 'approved',
                                        href: true,
                                        done: true,
                                    },
                                ].map((stage, i, all) => (
                                    <li
                                        key={stage.value}
                                        className="relative grid"
                                    >
                                        <Stage
                                            status={status(stage.value)}
                                            href={
                                                stage.href
                                                    ? byStatus(stage.value)
                                                    : undefined
                                            }
                                            tone={stage.done ? 'done' : 'plain'}
                                        />
                                        {i < all.length - 1 && (
                                            <ChevronRight
                                                aria-hidden
                                                className="absolute top-1/2 -right-2.5 z-10 hidden size-4 -translate-y-1/2 rounded-full bg-background text-muted-foreground @xl:block"
                                            />
                                        )}
                                    </li>
                                ))}
                            </ol>
                            <p className="mt-2 text-sm text-muted-foreground">
                                Accepted offers are in the exam or waiting for
                                the Registrar's check.
                            </p>
                            <ul className="mt-5 flex flex-wrap gap-x-6 gap-y-2 border-t border-border pt-4 text-sm">
                                {(
                                    [
                                        ['submitted', 'with the Registrar'],
                                        [
                                            'more_information',
                                            'waiting on the applicant',
                                        ],
                                        ['declined', 'declined'],
                                    ] as const
                                ).map(([value, words]) => {
                                    const side = status(value);

                                    return (
                                        side && (
                                            <li key={value}>
                                                <Link
                                                    href={byStatus(value)}
                                                    className="underline decoration-border underline-offset-4 hover:decoration-current"
                                                >
                                                    <span className="font-semibold">
                                                        {side.total}
                                                    </span>{' '}
                                                    {words}
                                                </Link>
                                            </li>
                                        )
                                    );
                                })}
                            </ul>
                        </section>

                        {/* The register by grade: active, awaiting fee, lapsed. */}
                        <section
                            aria-labelledby="register-heading"
                            className="rounded-card border border-border bg-background p-6"
                        >
                            <div className="flex items-baseline justify-between gap-4">
                                <h2
                                    id="register-heading"
                                    className="text-heading03 font-semibold"
                                >
                                    The register
                                </h2>
                                <Link
                                    href={members()}
                                    className="text-sm font-semibold underline decoration-border underline-offset-4 hover:decoration-current"
                                >
                                    {memberCount}{' '}
                                    {plural(memberCount, 'member', 'members')}
                                </Link>
                            </div>
                            <ul className="mt-5 grid gap-4">
                                {grades.map((grade) => (
                                    <li key={grade.label}>
                                        <div className="flex flex-wrap items-baseline justify-between gap-x-4 text-sm">
                                            <span className="font-semibold">
                                                {grade.label}
                                            </span>
                                            <span className="text-muted-foreground">
                                                <span className="font-semibold text-foreground">
                                                    {grade.total}
                                                </span>
                                                {grade.awaiting > 0 &&
                                                    `, ${grade.awaiting} awaiting fee`}
                                                {grade.lapsed > 0 &&
                                                    `, ${grade.lapsed} lapsed`}
                                            </span>
                                        </div>
                                        <div
                                            aria-hidden
                                            className="mt-1.5 flex h-2.5 overflow-hidden rounded-full bg-muted"
                                        >
                                            <span
                                                className="bg-brand-navy dark:bg-brand-on-dark"
                                                style={{
                                                    width: `${(grade.active / biggestGrade) * 100}%`,
                                                }}
                                            />
                                            <span
                                                className="bg-brand-navy/35 dark:bg-brand-on-dark/40"
                                                style={{
                                                    width: `${(grade.awaiting / biggestGrade) * 100}%`,
                                                }}
                                            />
                                            <span
                                                className="bg-brand-crimson-strong dark:bg-brand-crimson"
                                                style={{
                                                    width: `${(grade.lapsed / biggestGrade) * 100}%`,
                                                }}
                                            />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                            <ul className="mt-5 flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                                <li className="flex items-center gap-1.5">
                                    <span
                                        aria-hidden
                                        className="size-2.5 rounded-full bg-brand-navy dark:bg-brand-on-dark"
                                    />
                                    Active
                                </li>
                                <li className="flex items-center gap-1.5">
                                    <span
                                        aria-hidden
                                        className="size-2.5 rounded-full bg-brand-navy/35 dark:bg-brand-on-dark/40"
                                    />
                                    Awaiting fee
                                </li>
                                <li className="flex items-center gap-1.5">
                                    <span
                                        aria-hidden
                                        className="size-2.5 rounded-full bg-brand-crimson-strong dark:bg-brand-crimson"
                                    />
                                    Lapsed
                                </li>
                            </ul>
                        </section>
                    </div>

                    {/* Where people are, to help choose where to hold inductions. */}
                    <section
                        aria-labelledby="regions-heading"
                        className="rounded-card border border-border bg-background"
                    >
                        <div className="p-6 pb-4">
                            <h2
                                id="regions-heading"
                                className="text-heading03 font-semibold"
                            >
                                By region
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Applicants (submitted or further) and members,
                                by the state they live in. Useful for choosing
                                where to hold inductions.
                            </p>
                        </div>
                        {anyoneInRegions ? (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[36rem] text-sm">
                                    <thead>
                                        <tr className="border-y border-border text-left text-muted-foreground">
                                            <th className="px-6 py-2.5 font-medium">
                                                Region
                                            </th>
                                            <th className="px-3 py-2.5 text-right font-medium">
                                                Applicants
                                            </th>
                                            <th className="px-3 py-2.5 text-right font-medium">
                                                Members
                                            </th>
                                            <th className="w-1/3 px-3 py-2.5 font-medium">
                                                <span className="sr-only">
                                                    Share
                                                </span>
                                            </th>
                                            <th className="px-6 py-2.5 font-medium">
                                                States
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {regions.map((region) => (
                                            <tr key={region.name}>
                                                <th
                                                    scope="row"
                                                    className="px-6 py-3 text-left font-semibold"
                                                >
                                                    {region.name}
                                                </th>
                                                <td className="px-3 py-3 text-right">
                                                    {region.applicants}
                                                </td>
                                                <td className="px-3 py-3 text-right">
                                                    {region.members}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <div
                                                        aria-hidden
                                                        className="flex h-2 overflow-hidden rounded-full bg-muted"
                                                    >
                                                        <span
                                                            className="bg-brand-navy dark:bg-brand-on-dark"
                                                            style={{
                                                                width: `${(region.members / busiest) * 100}%`,
                                                            }}
                                                        />
                                                        <span
                                                            className="bg-brand-crimson-strong dark:bg-brand-crimson"
                                                            style={{
                                                                width: `${(region.applicants / busiest) * 100}%`,
                                                            }}
                                                        />
                                                    </div>
                                                </td>
                                                <td className="px-6 py-3 text-muted-foreground">
                                                    {region.states.length > 0
                                                        ? region.states
                                                              .map(
                                                                  (state) =>
                                                                      `${state.name} ${state.applicants + state.members}`,
                                                              )
                                                              .join(', ')
                                                        : '—'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        ) : (
                            <p className="px-6 pb-6 text-muted-foreground">
                                Nobody has submitted an application yet.
                            </p>
                        )}
                        {anyoneInRegions && (
                            <ul className="flex flex-wrap gap-x-4 gap-y-1 px-6 py-4 text-xs text-muted-foreground">
                                <li className="flex items-center gap-1.5">
                                    <span
                                        aria-hidden
                                        className="size-2.5 rounded-full bg-brand-navy dark:bg-brand-on-dark"
                                    />
                                    Members
                                </li>
                                <li className="flex items-center gap-1.5">
                                    <span
                                        aria-hidden
                                        className="size-2.5 rounded-full bg-brand-crimson-strong dark:bg-brand-crimson"
                                    />
                                    Applicants
                                </li>
                            </ul>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

RegistrarOverview.layout = {
    breadcrumbs: [{ title: 'Membership', href: registrar() }],
};
