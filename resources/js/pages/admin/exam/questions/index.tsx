import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Check,
    ChevronRight,
    Download,
    Plus,
    Search,
    Upload,
    X,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { selectClass } from '@/components/membership/form-bits';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import {
    create,
    edit,
    importMethod,
    index,
    retire,
    template,
} from '@/routes/admin/exam/questions';

type Area = { value: string; label: string; advanced: boolean; count: number };

type Question = {
    id: number;
    area: string;
    area_label: string;
    advanced: boolean;
    scenario: string | null;
    question: string;
    options: string[];
    correct_option: number;
    explanation: string | null;
    retired: boolean;
    updated: string;
    updated_by: string | null;
};

type Filters = { area: string; status: string; search: string };

type ImportResult = { imported: number; skipped: number; errors: string[] };

const letter = (index: number) => String.fromCharCode(65 + index);

function ImportDialog({ onClose }: { onClose: () => void }) {
    const form = useForm<{ file: File | null }>({ file: null });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(importMethod.url(), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: onClose,
        });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-lg">
                <form onSubmit={submit} noValidate className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>Import from a spreadsheet</DialogTitle>
                        <DialogDescription>
                            For the committee's questions in bulk. Every row is
                            checked first: if any has a problem, nothing is
                            imported and the problems are listed by row.
                        </DialogDescription>
                    </DialogHeader>
                    <ol className="grid list-decimal gap-2 pl-5 text-sm">
                        <li>
                            <a
                                href={template.url()}
                                className="inline-flex items-center gap-1 font-semibold underline decoration-border underline-offset-4 hover:decoration-current"
                            >
                                <Download aria-hidden className="size-4" />
                                Download the template
                            </a>{' '}
                            and open it in Excel or Google Sheets.
                        </li>
                        <li>
                            One question per row. Area is one of the 14 area
                            names; Correct is the letter of the right option.
                            Delete the two example rows, or leave them: rows
                            starting "EXAMPLE:" are never imported.
                        </li>
                        <li>
                            Save or download it as CSV, then choose it here.
                        </li>
                    </ol>
                    <div className="grid gap-2">
                        <label htmlFor="file" className="text-sm font-semibold">
                            CSV file
                        </label>
                        <input
                            id="file"
                            type="file"
                            accept=".csv,text/csv"
                            onChange={(e) =>
                                form.setData(
                                    'file',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                            className="text-sm file:mr-3 file:rounded-control file:border file:border-border file:bg-background file:px-3 file:py-1.5 file:font-semibold"
                        />
                        <InputError
                            className="font-semibold text-destructive"
                            message={form.errors.file}
                        />
                    </div>
                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Close
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing || !form.data.file}
                        >
                            <Upload />
                            Check and import
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

type Readiness = {
    grade: string;
    label: string;
    ready: boolean;
    basic: { have: number; need: number };
    advanced: { have: number; need: number };
}[];

/** Whether the exam can set a paper for each grade from the questions in use. */
function ReadinessLine({ readiness }: { readiness: Readiness }) {
    return (
        <section
            aria-label="Papers the bank can set"
            className="flex flex-col gap-3 rounded-card bg-brand-navy p-5 text-white md:px-6 @4xl:flex-row @4xl:items-center @4xl:gap-6 dark:bg-brand-night-raised"
        >
            <p className="shrink-0 font-semibold">Papers can be set for</p>
            <ul className="flex flex-wrap gap-2">
                {readiness.map((row) => {
                    const short = [
                        row.basic.have < row.basic.need &&
                            `${row.basic.need - row.basic.have} more basic`,
                        row.advanced.have < row.advanced.need &&
                            `${row.advanced.need - row.advanced.have} more advanced`,
                    ].filter(Boolean);

                    return (
                        <li
                            key={row.grade}
                            className={cn(
                                'flex items-center gap-2 rounded-full px-3 py-1 text-sm font-semibold',
                                row.ready
                                    ? 'bg-white text-brand-navy'
                                    : 'bg-white/10 text-brand-on-dark ring-1 ring-white/25 ring-inset',
                            )}
                        >
                            {row.ready ? (
                                <Check aria-hidden className="size-4" />
                            ) : (
                                <X aria-hidden className="size-4" />
                            )}
                            {row.label}
                            <span className="sr-only">
                                {row.ready ? ': ready' : ': not yet'}
                            </span>
                            {!row.ready && (
                                <span className="font-normal">
                                    , needs {short.join(' and ')}
                                </span>
                            )}
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

/** The 14 areas, Basic then Advanced, each with its count; picking one filters the list. */
function AreaRail({
    areas,
    target,
    current,
    onPick,
}: {
    areas: Area[];
    target: number;
    current: string;
    onPick: (area: string) => void;
}) {
    const total = areas.reduce((sum, area) => sum + area.count, 0);
    const ready = areas.filter((area) => area.count >= target).length;
    const item = (
        key: string,
        label: ReactNode,
        count: number,
        bar: boolean,
    ) => {
        const chosen = current === key;
        const share = Math.min(1, count / target);

        return (
            <li key={key || 'all'}>
                <button
                    type="button"
                    aria-pressed={chosen}
                    onClick={() => onPick(key)}
                    className={cn(
                        'grid w-full gap-1.5 rounded-control px-3 py-2 text-left text-sm transition-colors',
                        'focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                        chosen
                            ? 'bg-accent font-semibold'
                            : 'hover:bg-accent/60',
                    )}
                >
                    <span className="flex items-baseline justify-between gap-3">
                        <span className="leading-snug">{label}</span>
                        <span
                            className={cn(
                                'shrink-0 font-semibold',
                                bar &&
                                    count < target &&
                                    'text-muted-foreground',
                            )}
                        >
                            {count}
                            {bar && (
                                <span className="font-normal text-muted-foreground">
                                    /{target}
                                </span>
                            )}
                        </span>
                    </span>
                    {bar && (
                        <span
                            aria-hidden
                            className="h-1 overflow-hidden rounded-full bg-muted"
                        >
                            <span
                                className={cn(
                                    'block h-full rounded-full',
                                    share >= 1
                                        ? 'bg-brand-navy dark:bg-brand-on-dark'
                                        : 'bg-brand-crimson-strong dark:bg-brand-crimson',
                                )}
                                style={{ width: `${share * 100}%` }}
                            />
                        </span>
                    )}
                </button>
            </li>
        );
    };

    return (
        <nav
            aria-label="Areas"
            className="hidden rounded-card border border-border bg-background p-3 @5xl:sticky @5xl:top-6 @5xl:block"
        >
            <p className="px-3 pt-2 pb-3 text-sm text-muted-foreground">
                {ready} of {areas.length} areas have {target} or more, the
                number that keeps retakes from repeating.
            </p>
            <ul className="grid gap-0.5">
                {item('', 'All areas', total, false)}
            </ul>
            {[false, true].map((advanced) => (
                <div key={String(advanced)} className="mt-3">
                    <p className="px-3 pb-1 text-xs font-semibold text-muted-foreground">
                        {advanced
                            ? 'Advanced: Fellows and Members only'
                            : 'Basic: every grade'}
                    </p>
                    <ul className="grid gap-0.5">
                        {areas
                            .filter((area) => area.advanced === advanced)
                            .map((area) =>
                                item(area.value, area.label, area.count, true),
                            )}
                    </ul>
                </div>
            ))}
        </nav>
    );
}

/** One question as a single line that opens in place. */
function QuestionRow({ question }: { question: Question }) {
    return (
        <li className={cn(question.retired && 'bg-muted/40')}>
            <details className="group">
                <summary
                    className={cn(
                        'grid cursor-pointer list-none grid-cols-[auto_minmax(0,1fr)_auto] items-start gap-3 px-4 py-3 md:px-6',
                        'hover:bg-accent/40 focus-visible:bg-accent/50 focus-visible:outline-none [&::-webkit-details-marker]:hidden',
                    )}
                >
                    <ChevronRight
                        aria-hidden
                        className="mt-0.5 size-4 text-muted-foreground transition-transform group-open:rotate-90"
                    />
                    <span className="min-w-0">
                        <span className="block truncate font-semibold group-open:whitespace-normal">
                            {question.question}
                        </span>
                        <span className="mt-0.5 flex flex-wrap gap-x-3 text-xs text-muted-foreground">
                            <span>{question.area_label}</span>
                            {question.scenario && <span>Has a scenario</span>}
                            {!question.explanation && (
                                <span className="text-brand-crimson-strong dark:text-brand-crimson">
                                    No explanation
                                </span>
                            )}
                            {question.retired && (
                                <span className="font-semibold">Retired</span>
                            )}
                        </span>
                    </span>
                    <span
                        className="flex size-7 items-center justify-center rounded-full bg-brand-navy text-xs font-semibold text-white dark:bg-brand-on-dark dark:text-brand-navy"
                        title="The right answer"
                    >
                        <span className="sr-only">Right answer </span>
                        {letter(question.correct_option)}
                    </span>
                </summary>

                <div className="grid gap-3 px-4 pb-5 pl-11 md:px-6 md:pl-13">
                    {question.scenario && (
                        <p className="max-w-prose rounded-control bg-muted/60 p-3 text-sm whitespace-pre-line">
                            {question.scenario}
                        </p>
                    )}
                    <ol className="grid max-w-3xl gap-1.5 @3xl:grid-cols-2">
                        {question.options.map((option, i) => {
                            const right = i === question.correct_option;

                            return (
                                <li
                                    key={i}
                                    className={cn(
                                        'flex items-start gap-3 rounded-control px-2 py-1 text-sm',
                                        right && 'bg-primary/5 font-semibold',
                                    )}
                                >
                                    <span
                                        aria-hidden
                                        className={cn(
                                            'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                            right
                                                ? 'bg-primary text-primary-foreground'
                                                : 'ring-1 ring-border ring-inset',
                                        )}
                                    >
                                        {right ? (
                                            <Check className="size-3.5" />
                                        ) : (
                                            letter(i)
                                        )}
                                    </span>
                                    <span className="pt-0.5">
                                        <span className="sr-only">
                                            {letter(i)}
                                            {right ? ', the right answer' : ''}
                                            :{' '}
                                        </span>
                                        {option}
                                    </span>
                                </li>
                            );
                        })}
                    </ol>
                    {question.explanation && (
                        <p className="max-w-prose text-sm text-muted-foreground">
                            <span className="font-semibold text-foreground">
                                Why:
                            </span>{' '}
                            {question.explanation}
                        </p>
                    )}
                    <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                        <span className="text-muted-foreground">
                            {question.advanced ? 'Advanced' : 'Basic'}, edited{' '}
                            {question.updated}
                            {question.updated_by &&
                                ` by ${question.updated_by}`}
                        </span>
                        <span className="flex gap-2">
                            <Button asChild size="sm" variant="outline">
                                <Link href={edit(question.id)}>
                                    Edit
                                    <span className="sr-only">
                                        {' '}
                                        this question
                                    </span>
                                </Link>
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() =>
                                    router.patch(
                                        retire.url(question.id),
                                        { retired: !question.retired },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {question.retired ? 'Bring back' : 'Retire'}
                            </Button>
                        </span>
                    </div>
                </div>
            </details>
        </li>
    );
}

export default function QuestionsIndex({
    areas,
    target,
    readiness,
    questions,
    filters,
    importResult,
}: {
    areas: Area[];
    target: number;
    readiness: Readiness;
    questions: {
        data: Question[];
        prev_page_url: string | null;
        next_page_url: string | null;
        total: number;
    };
    filters: Filters;
    importResult: ImportResult | null;
}) {
    const [importing, setImporting] = useState(false);
    const [search, setSearch] = useState(filters.search);
    const visit = (changes: Partial<Filters>) =>
        router.get(
            index.url({ query: { ...filters, ...changes } }),
            {},
            { preserveScroll: true, preserveState: true },
        );

    return (
        <>
            <Head title="Question bank" />
            <div className="@container w-full px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <div className="mb-8 flex flex-col gap-4 @3xl:flex-row @3xl:items-start @3xl:justify-between">
                    <div className="max-w-prose [&>header]:mb-0">
                        <Heading
                            title="Question bank"
                            description="The membership exam's questions. Each paper draws 50 from the areas an applicant claims; advanced areas are never asked of Graduate or Student Members."
                        />
                    </div>
                    <div className="flex shrink-0 flex-wrap gap-2">
                        <Button
                            size="lg"
                            variant="outline"
                            onClick={() => setImporting(true)}
                        >
                            <Upload />
                            Import from a spreadsheet
                        </Button>
                        <Button asChild size="lg">
                            <Link
                                href={create({
                                    query: filters.area
                                        ? { area: filters.area }
                                        : {},
                                })}
                            >
                                <Plus />
                                Add a question
                            </Link>
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 *:min-w-0">
                    {importResult && importResult.errors.length > 0 && (
                        <section
                            role="alert"
                            className="rounded-card border border-destructive/40 bg-destructive/5 p-4 md:p-6"
                        >
                            <h2 className="font-semibold text-destructive">
                                Nothing was imported. Fix these rows and upload
                                the file again:
                            </h2>
                            <ul className="mt-3 grid list-disc gap-1 pl-5 text-sm">
                                {importResult.errors.map((error) => (
                                    <li key={error}>{error}</li>
                                ))}
                            </ul>
                        </section>
                    )}

                    <ReadinessLine readiness={readiness} />

                    <div className="grid items-start gap-6 *:min-w-0 @5xl:grid-cols-[20rem_minmax(0,1fr)]">
                        <AreaRail
                            areas={areas}
                            target={target}
                            current={filters.area}
                            onPick={(area) => visit({ area })}
                        />

                        <section
                            aria-label="Questions"
                            className="@container rounded-card border border-border bg-background"
                        >
                            <div className="grid grid-cols-[minmax(0,1fr)] gap-3 border-b border-border p-4 md:px-6 @2xl:grid-cols-[minmax(0,1fr)_auto] @2xl:items-center">
                                <form
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        visit({ search });
                                    }}
                                    className="flex gap-2"
                                >
                                    <div className="relative flex-1">
                                        <Search
                                            aria-hidden
                                            className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                        />
                                        <label
                                            htmlFor="search"
                                            className="sr-only"
                                        >
                                            Search the questions
                                        </label>
                                        <Input
                                            id="search"
                                            type="search"
                                            placeholder="Search the questions"
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
                                <div className="flex flex-wrap items-center gap-3">
                                    {/* Narrower screens: the areas as a dropdown instead of the rail. */}
                                    <label
                                        htmlFor="area-filter"
                                        className="sr-only"
                                    >
                                        Area
                                    </label>
                                    <select
                                        id="area-filter"
                                        value={filters.area}
                                        onChange={(e) =>
                                            visit({ area: e.target.value })
                                        }
                                        className={cn(
                                            selectClass,
                                            'w-full min-w-0 @2xl:w-auto @2xl:max-w-64 @5xl:hidden',
                                        )}
                                    >
                                        <option value="">All areas</option>
                                        {areas.map((area) => (
                                            <option
                                                key={area.value}
                                                value={area.value}
                                            >
                                                {area.label} ({area.count}/
                                                {target})
                                            </option>
                                        ))}
                                    </select>
                                    <label
                                        htmlFor="status-filter"
                                        className="sr-only"
                                    >
                                        Status
                                    </label>
                                    <select
                                        id="status-filter"
                                        value={filters.status}
                                        onChange={(e) =>
                                            visit({ status: e.target.value })
                                        }
                                        className={cn(selectClass, 'w-auto')}
                                    >
                                        <option value="active">In use</option>
                                        <option value="retired">Retired</option>
                                        <option value="all">All</option>
                                    </select>
                                    <span className="text-sm whitespace-nowrap text-muted-foreground">
                                        {questions.total}{' '}
                                        {questions.total === 1
                                            ? 'question'
                                            : 'questions'}
                                    </span>
                                </div>
                            </div>

                            {questions.data.length === 0 ? (
                                <p className="p-10 text-center text-muted-foreground">
                                    {filters.search || filters.area
                                        ? 'No questions match these filters.'
                                        : 'No questions yet. Add them one by one, or import the committee’s spreadsheet.'}
                                </p>
                            ) : (
                                <ul className="divide-y divide-border">
                                    {questions.data.map((question) => (
                                        <QuestionRow
                                            key={question.id}
                                            question={question}
                                        />
                                    ))}
                                </ul>
                            )}

                            {(questions.prev_page_url ||
                                questions.next_page_url) && (
                                <div className="flex justify-between gap-3 border-t border-border p-4 md:px-6">
                                    {questions.prev_page_url ? (
                                        <Button asChild variant="outline">
                                            <Link
                                                href={questions.prev_page_url}
                                            >
                                                Previous
                                            </Link>
                                        </Button>
                                    ) : (
                                        <span />
                                    )}
                                    {questions.next_page_url && (
                                        <Button asChild variant="outline">
                                            <Link
                                                href={questions.next_page_url}
                                            >
                                                Next
                                            </Link>
                                        </Button>
                                    )}
                                </div>
                            )}
                        </section>
                    </div>
                </div>
            </div>

            {importing && <ImportDialog onClose={() => setImporting(false)} />}
        </>
    );
}

QuestionsIndex.layout = {
    breadcrumbs: [{ title: 'Question bank', href: index() }],
};
