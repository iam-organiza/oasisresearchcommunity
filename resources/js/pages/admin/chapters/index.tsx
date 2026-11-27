import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import Heading from '@/components/heading';
import { cn } from '@/lib/utils';
import { index } from '@/routes/admin/chapters';
import { index as register } from '@/routes/admin/members';

type State = { id: number; name: string; members: number; applicants: number };

type Region = {
    id: number;
    name: string;
    members: number;
    applicants: number;
    states: State[];
};

const count = (value: number) => (value > 0 ? value : '—');

const plural = (value: number, one: string, many: string) =>
    `${value} ${value === 1 ? one : many}`;

/** One regional chapter: its totals, its state chapters busiest first, and its register. */
function RegionCard({ region }: { region: Region }) {
    const busy = region.states.filter(
        (state) => state.members + state.applicants > 0,
    );
    const quiet = region.states.filter(
        (state) => state.members + state.applicants === 0,
    );

    return (
        <li
            className="flex flex-col rounded-card border border-border bg-background"
            aria-labelledby={`region-${region.id}`}
        >
            <div className="border-b border-border px-5 py-4">
                <h2
                    id={`region-${region.id}`}
                    className="text-lg leading-snug font-semibold"
                >
                    {region.name}
                </h2>
                <p className="mt-0.5 text-sm text-muted-foreground">
                    {plural(region.states.length, 'state', 'states')}
                    {', '}
                    <span
                        className={cn(
                            region.members > 0 &&
                                'font-semibold text-foreground',
                        )}
                    >
                        {plural(region.members, 'member', 'members')}
                    </span>
                    {', '}
                    {plural(region.applicants, 'applicant', 'applicants')}
                </p>
            </div>

            <table className="w-full text-sm">
                {busy.length > 0 && (
                    <thead>
                        <tr className="text-xs text-muted-foreground">
                            <th className="px-5 pt-3 pb-1 text-left font-medium">
                                State
                            </th>
                            <th className="w-20 px-2 pt-3 pb-1 text-right font-medium">
                                Members
                            </th>
                            <th className="w-24 pt-3 pr-5 pb-1 pl-2 text-right font-medium">
                                Applicants
                            </th>
                        </tr>
                    </thead>
                )}
                <tbody className="divide-y divide-border">
                    {busy.map((state) => (
                        <tr key={state.id}>
                            <th
                                scope="row"
                                className="px-5 py-2 text-left font-medium"
                            >
                                {state.name}
                            </th>
                            <td className="w-20 px-2 py-2 text-right">
                                <span className="font-semibold">
                                    {count(state.members)}
                                </span>
                            </td>
                            <td className="w-24 py-2 pr-5 pl-2 text-right text-muted-foreground">
                                {count(state.applicants)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
            {quiet.length > 0 && (
                <p className="px-5 py-3 text-sm text-muted-foreground">
                    {busy.length > 0
                        ? 'No one yet in '
                        : 'No one yet in any state: '}
                    {quiet.map((state) => state.name).join(', ')}.
                </p>
            )}

            <div className="mt-auto border-t border-border px-5 py-3">
                <Link
                    href={register.url({ query: { region: region.id } })}
                    className="group inline-flex items-center gap-1 text-sm font-semibold underline decoration-border underline-offset-4 hover:decoration-current"
                >
                    Open the register for {region.name}
                    <ArrowRight
                        aria-hidden
                        className="size-4 transition-transform group-hover:translate-x-0.5"
                    />
                </Link>
            </div>
        </li>
    );
}

export default function ChaptersIndex({ regions }: { regions: Region[] }) {
    const states = regions.reduce(
        (total, region) => total + region.states.length,
        0,
    );
    const members = regions.reduce(
        (total, region) => total + region.members,
        0,
    );
    const applicants = regions.reduce(
        (total, region) => total + region.applicants,
        0,
    );

    return (
        <>
            <Head title="Chapters" />
            <div className="w-full px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="Chapters"
                    description={`${regions.length} regional chapters, one for each geopolitical zone, holding the ${states} state chapters. Members and applicants belong to the chapter of the state they live in.`}
                />

                <p className="mb-6 flex flex-wrap items-baseline gap-x-6 gap-y-1 rounded-card bg-brand-navy px-5 py-4 text-white md:px-6 dark:bg-brand-night-raised">
                    <span>
                        <span className="text-heading03 font-bold">
                            {members}
                        </span>{' '}
                        <span className="text-brand-on-dark">
                            {members === 1 ? 'member' : 'members'}
                        </span>
                    </span>
                    <span>
                        <span className="text-heading03 font-bold">
                            {applicants}
                        </span>{' '}
                        <span className="text-brand-on-dark">
                            {applicants === 1
                                ? 'applicant in progress'
                                : 'applicants in progress'}
                        </span>
                    </span>
                    <span className="text-sm text-brand-on-dark">
                        Across {regions.length} regional and {states} state
                        chapters
                    </span>
                </p>

                <ul className="grid grid-cols-[repeat(auto-fill,minmax(min(100%,20rem),1fr))] gap-5">
                    {regions.map((region) => (
                        <RegionCard key={region.id} region={region} />
                    ))}
                </ul>
            </div>
        </>
    );
}

ChaptersIndex.layout = {
    breadcrumbs: [{ title: 'Chapters', href: index() }],
};
