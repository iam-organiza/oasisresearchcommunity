import { Head } from '@inertiajs/react';
import { MapPin } from 'lucide-react';
import Heading from '@/components/heading';
import { index } from '@/routes/admin/chapters';

type Region = {
    id: number;
    name: string;
    states: { id: number; name: string }[];
};

export default function ChaptersIndex({ regions }: { regions: Region[] }) {
    const stateCount = regions.reduce(
        (total, region) => total + region.states.length,
        0,
    );

    return (
        <>
            <Head title="Chapters" />
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="Chapters"
                    description={`${regions.length} regional chapters, one for each geopolitical zone, holding the ${stateCount} state chapters. Applicants and members will be grouped by these.`}
                />

                {/* One card per region; the states inside are its state chapters. */}
                <ul className="grid grid-cols-[repeat(auto-fit,minmax(17rem,1fr))] gap-5">
                    {regions.map((region) => (
                        <li
                            key={region.id}
                            className="flex flex-col rounded-card border border-border bg-background"
                        >
                            <div className="flex items-start justify-between gap-3 border-b border-border px-5 py-4">
                                <h2 className="text-lg leading-snug font-semibold">
                                    {region.name}
                                </h2>
                                <span className="shrink-0 rounded-full bg-muted px-2.5 py-0.5 text-sm font-semibold text-muted-foreground tabular-nums">
                                    {region.states.length} states
                                </span>
                            </div>

                            <ul className="flex flex-wrap gap-2 p-5">
                                {region.states.map((state) => (
                                    <li
                                        key={state.id}
                                        className="flex items-center gap-1.5 rounded-control border border-border px-2.5 py-1 text-sm"
                                    >
                                        <MapPin
                                            aria-hidden
                                            className="size-3.5 text-brand-crimson-strong dark:text-brand-crimson"
                                        />
                                        {state.name}
                                    </li>
                                ))}
                            </ul>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

ChaptersIndex.layout = {
    breadcrumbs: [{ title: 'Chapters', href: index() }],
};
