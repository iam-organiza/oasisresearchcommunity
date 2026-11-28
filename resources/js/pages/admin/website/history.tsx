import { Head, Link, router } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { useConfirm } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { edit, history, restoreOriginal } from '@/routes/admin/website';
import { restore } from '@/routes/admin/website/history';

type Entry = {
    id: number;
    section: string;
    section_key: string;
    page: string;
    action: 'saved' | 'restored';
    by: string;
    when: string | null;
    changes: { field: string; before: string; after: string }[];
    is_current: boolean;
};

type SectionSummary = { key: string; label: string; is_original: boolean };

type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

/**
 * Every change to the public site's sections, newest first: who made it,
 * when, what it changed, and a way to put that version back.
 */
export default function WebsiteHistory({
    entries,
    sections,
}: {
    entries: Paginated<Entry>;
    sections: SectionSummary[];
}) {
    const { confirm, dialog } = useConfirm();

    const putBack = async (entry: Entry) => {
        const yes = await confirm({
            title: `Put back ${entry.section} from ${entry.when}?`,
            description:
                'The site shows that version straight away. What is there now stays in this history, so this can be undone the same way.',
            confirmLabel: 'Put this version back',
        });

        if (yes) {
            router.post(restore.url(entry.id), {}, { preserveScroll: true });
        }
    };

    const putBackOriginal = async (section: SectionSummary) => {
        const yes = await confirm({
            title: `Put back the original ${section.label}?`,
            description:
                'The site goes back to the text it had before anyone edited it here. What is there now stays in this history.',
            confirmLabel: 'Put the original back',
        });

        if (yes) {
            router.post(
                restoreOriginal.url(section.key),
                {},
                { preserveScroll: true },
            );
        }
    };

    return (
        <>
            <Head title="Change history" />
            {dialog}
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="Change history"
                    description="Every change made to the public site here, newest first. Any version can be put back."
                />

                <section className="rounded-card border border-border bg-background">
                    {entries.data.length === 0 ? (
                        <p className="p-10 text-center text-muted-foreground">
                            Nothing has been changed yet. Saves made on the
                            Website pages appear here.
                        </p>
                    ) : (
                        <ol className="divide-y divide-border">
                            {entries.data.map((entry) => (
                                <li
                                    key={entry.id}
                                    className="flex flex-col gap-4 p-4 md:flex-row md:gap-8 md:px-6 md:py-5"
                                >
                                    <div className="min-w-0 flex-1">
                                        <p className="flex flex-wrap items-baseline gap-x-2">
                                            <Link
                                                href={edit(entry.page)}
                                                className="font-semibold underline decoration-border underline-offset-4 hover:decoration-current"
                                            >
                                                {entry.section}
                                            </Link>
                                            <span className="text-sm text-muted-foreground">
                                                {entry.action === 'restored'
                                                    ? 'put back'
                                                    : 'saved'}{' '}
                                                by {entry.by}, {entry.when}
                                            </span>
                                            {entry.is_current && (
                                                <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold">
                                                    On the site now
                                                </span>
                                            )}
                                        </p>
                                        {entry.changes.length === 0 ? (
                                            <p className="mt-2 text-sm text-muted-foreground">
                                                Saved with no changes.
                                            </p>
                                        ) : (
                                            <dl className="mt-3 grid gap-2 text-sm">
                                                {entry.changes.map((change) => (
                                                    <div
                                                        key={change.field}
                                                        className="grid gap-x-4 sm:grid-cols-[10rem_minmax(0,1fr)]"
                                                    >
                                                        <dt className="font-semibold">
                                                            {change.field}
                                                        </dt>
                                                        <dd className="min-w-0 break-words">
                                                            <del className="text-muted-foreground decoration-destructive/60">
                                                                {change.before}
                                                            </del>
                                                            <span
                                                                aria-hidden
                                                                className="mx-2 text-muted-foreground"
                                                            >
                                                                to
                                                            </span>
                                                            <ins className="font-medium no-underline">
                                                                {change.after}
                                                            </ins>
                                                        </dd>
                                                    </div>
                                                ))}
                                            </dl>
                                        )}
                                    </div>
                                    {!entry.is_current && (
                                        <div className="shrink-0">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => putBack(entry)}
                                            >
                                                <RotateCcw />
                                                Put this version back
                                            </Button>
                                        </div>
                                    )}
                                </li>
                            ))}
                        </ol>
                    )}

                    {(entries.prev_page_url || entries.next_page_url) && (
                        <div className="flex gap-2 border-t border-border px-4 py-3 md:px-6">
                            {entries.prev_page_url && (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={entries.prev_page_url}>
                                        Newer
                                    </Link>
                                </Button>
                            )}
                            {entries.next_page_url && (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={entries.next_page_url}>
                                        Older
                                    </Link>
                                </Button>
                            )}
                        </div>
                    )}
                </section>

                {/* The text each section had before anyone edited it here. */}
                {sections.some((section) => !section.is_original) && (
                    <section className="mt-8 rounded-card border border-border bg-background p-4 md:p-6">
                        <h2 className="font-semibold">Original text</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Each section's text from before it was first edited
                            here.
                        </p>
                        <ul className="mt-4 flex flex-wrap gap-2">
                            {sections
                                .filter((section) => !section.is_original)
                                .map((section) => (
                                    <li key={section.key}>
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                putBackOriginal(section)
                                            }
                                        >
                                            <RotateCcw />
                                            Put back the original{' '}
                                            {section.label}
                                        </Button>
                                    </li>
                                ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

WebsiteHistory.layout = {
    breadcrumbs: [{ title: 'Change history', href: history() }],
};
