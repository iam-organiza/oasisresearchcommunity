import { Head, Link } from '@inertiajs/react';
import { ArrowRight, Inbox } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { registrar } from '@/routes/admin';
import { index as applications } from '@/routes/admin/applications';
import { index as members } from '@/routes/admin/members';

type Props = {
    waiting: number;
    statuses: { value: string; label: string; total: number }[];
    grades: { label: string; total: number; active: number }[];
    regions: {
        name: string;
        applicants: number;
        members: number;
        states: { name: string; applicants: number; members: number }[];
    }[];
    members: number;
};

export default function RegistrarOverview({
    waiting,
    statuses,
    grades,
    regions,
    members: memberCount,
}: Props) {
    const busiest = Math.max(
        1,
        ...regions.map((r) => r.applicants + r.members),
    );

    return (
        <>
            <Head title="Membership" />
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="Membership"
                    description="Applications flagged for a person, appeals, the register by grade, and where applicants and members are."
                />

                <div className="@container grid gap-6">
                    {/* What needs the Registrar now. */}
                    <section className="flex flex-wrap items-center justify-between gap-6 rounded-card bg-brand-navy p-6 text-white md:p-8 dark:bg-brand-night-raised">
                        <div className="flex items-center gap-5">
                            <span className="flex size-14 items-center justify-center rounded-full bg-white/10">
                                <Inbox aria-hidden className="size-7" />
                            </span>
                            <div>
                                <p className="text-heading01 leading-none font-black tabular-nums">
                                    {waiting}
                                </p>
                                <p className="mt-1 text-brand-on-dark">
                                    {waiting === 1
                                        ? 'application to check'
                                        : 'applications to check'}
                                </p>
                            </div>
                        </div>
                        <Button
                            asChild
                            size="lg"
                            className="bg-white text-brand-navy hover:bg-white/90"
                        >
                            <Link href={applications()}>
                                Check applications
                                <ArrowRight />
                            </Link>
                        </Button>
                    </section>

                    <div className="grid gap-6 @4xl:grid-cols-2">
                        <section className="rounded-card border border-border bg-background p-6">
                            <h2 className="text-heading03 font-semibold">
                                Applications
                            </h2>
                            <dl className="mt-4 divide-y divide-border">
                                {statuses.map((status) => (
                                    <div
                                        key={status.value}
                                        className="flex items-baseline justify-between gap-4 py-2.5"
                                    >
                                        <dt>{status.label}</dt>
                                        <dd className="font-semibold tabular-nums">
                                            {status.total}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </section>

                        <section className="rounded-card border border-border bg-background p-6">
                            <div className="flex items-baseline justify-between gap-4">
                                <h2 className="text-heading03 font-semibold">
                                    The register
                                </h2>
                                <Link
                                    href={members()}
                                    className="text-sm font-semibold underline decoration-border underline-offset-4 hover:decoration-current"
                                >
                                    {memberCount}{' '}
                                    {memberCount === 1 ? 'member' : 'members'}
                                </Link>
                            </div>
                            <dl className="mt-4 divide-y divide-border">
                                {grades.map((grade) => (
                                    <div
                                        key={grade.label}
                                        className="flex items-baseline justify-between gap-4 py-2.5"
                                    >
                                        <dt>{grade.label}</dt>
                                        <dd className="text-right tabular-nums">
                                            <span className="font-semibold">
                                                {grade.total}
                                            </span>
                                            {grade.total > grade.active && (
                                                <span className="ml-2 text-sm text-muted-foreground">
                                                    {grade.total - grade.active}{' '}
                                                    awaiting fee
                                                </span>
                                            )}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </section>
                    </div>

                    {/* Where people are, to help choose where to hold inductions. */}
                    <section className="rounded-card border border-border bg-background p-6">
                        <h2 className="text-heading03 font-semibold">
                            By region
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Applicants (submitted or further) and members, by
                            the state they live in.
                        </p>
                        {regions.every(
                            (r) => r.applicants + r.members === 0,
                        ) ? (
                            <p className="mt-6 text-muted-foreground">
                                Nobody has submitted an application yet.
                            </p>
                        ) : (
                            <ul className="mt-6 grid gap-5">
                                {regions.map((region) => (
                                    <li key={region.name}>
                                        <div className="flex items-baseline justify-between gap-4">
                                            <span className="font-semibold">
                                                {region.name}
                                            </span>
                                            <span className="text-sm text-muted-foreground tabular-nums">
                                                {region.applicants} applicants ·{' '}
                                                {region.members} members
                                            </span>
                                        </div>
                                        <div
                                            aria-hidden
                                            className="mt-2 flex h-2 overflow-hidden rounded-full bg-muted"
                                        >
                                            <div
                                                className="bg-brand-navy dark:bg-brand-on-dark"
                                                style={{
                                                    width: `${(region.members / busiest) * 100}%`,
                                                }}
                                            />
                                            <div
                                                className="bg-brand-crimson-strong dark:bg-brand-crimson"
                                                style={{
                                                    width: `${(region.applicants / busiest) * 100}%`,
                                                }}
                                            />
                                        </div>
                                        {region.states.length > 0 && (
                                            <p className="mt-2 text-sm text-muted-foreground">
                                                {region.states
                                                    .map(
                                                        (state) =>
                                                            `${state.name} ${state.applicants + state.members}`,
                                                    )
                                                    .join(', ')}
                                            </p>
                                        )}
                                    </li>
                                ))}
                            </ul>
                        )}
                        <p className="mt-6 flex flex-wrap gap-4 text-sm text-muted-foreground">
                            <span className="flex items-center gap-2">
                                <span
                                    aria-hidden
                                    className="size-3 rounded-full bg-brand-navy dark:bg-brand-on-dark"
                                />
                                Members
                            </span>
                            <span className="flex items-center gap-2">
                                <span
                                    aria-hidden
                                    className="size-3 rounded-full bg-brand-crimson-strong dark:bg-brand-crimson"
                                />
                                Applicants
                            </span>
                        </p>
                    </section>
                </div>
            </div>
        </>
    );
}

RegistrarOverview.layout = {
    breadcrumbs: [{ title: 'Membership', href: registrar() }],
};
