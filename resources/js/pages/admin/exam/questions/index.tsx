import { Head, Link, router, useForm } from '@inertiajs/react';
import { Check, Download, Plus, Search, Upload } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
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

/** Each area's count against the target: the bank's health at a glance. */
function AreaLedger({
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

    return (
        <section
            aria-labelledby="areas-heading"
            className="@container rounded-card border border-border bg-background p-4 md:p-6"
        >
            <div className="flex flex-wrap items-baseline justify-between gap-x-6 gap-y-1">
                <h2 id="areas-heading" className="font-semibold">
                    Questions by area
                </h2>
                <p className="text-sm text-muted-foreground">
                    {total} in use · {ready} of {areas.length} areas have{' '}
                    {target} or more
                </p>
            </div>
            <ul className="mt-4 grid grid-cols-1 gap-2 @md:grid-cols-2 @3xl:grid-cols-3 @4xl:grid-cols-4 @[90rem]:grid-cols-7">
                {areas.map((area) => {
                    const share = Math.min(1, area.count / target);
                    const chosen = current === area.value;

                    return (
                        <li key={area.value}>
                            <button
                                type="button"
                                aria-pressed={chosen}
                                onClick={() => onPick(chosen ? '' : area.value)}
                                className={cn(
                                    'flex h-full w-full flex-col gap-2 rounded-control border p-3 text-left transition-colors',
                                    'focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                    chosen
                                        ? 'border-brand-crimson-strong ring-1 ring-brand-crimson-strong dark:border-brand-crimson dark:ring-brand-crimson'
                                        : 'border-border hover:border-foreground/40',
                                )}
                            >
                                <span className="text-sm leading-snug font-semibold">
                                    {area.label}
                                </span>
                                <span className="mt-auto flex items-baseline justify-between gap-2 text-sm">
                                    <span
                                        className={cn(
                                            'font-semibold',
                                            area.count < target &&
                                                'text-muted-foreground',
                                        )}
                                    >
                                        {area.count}
                                        <span className="font-normal text-muted-foreground">
                                            {' '}
                                            / {target}
                                        </span>
                                    </span>
                                    {area.advanced && (
                                        <span className="text-xs text-muted-foreground">
                                            Advanced
                                        </span>
                                    )}
                                </span>
                                <span
                                    aria-hidden
                                    className="h-1.5 overflow-hidden rounded-full bg-muted"
                                >
                                    <span
                                        className={cn(
                                            'block h-full rounded-full',
                                            share >= 1
                                                ? 'bg-primary'
                                                : 'bg-brand-crimson-strong dark:bg-brand-crimson',
                                        )}
                                        style={{ width: `${share * 100}%` }}
                                    />
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ul>
        </section>
    );
}

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

function QuestionCard({ question }: { question: Question }) {
    return (
        <li
            className={cn(
                'grid gap-3 p-4 md:px-6 md:py-5',
                question.retired && 'bg-muted/40',
            )}
        >
            <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                <span className="font-semibold text-foreground">
                    {question.area_label}
                </span>
                <span>{question.advanced ? 'Advanced' : 'Basic'}</span>
                {question.retired && (
                    <span className="rounded-full bg-muted px-2 py-0.5 font-semibold">
                        Retired
                    </span>
                )}
            </p>
            {question.scenario && (
                <p className="max-w-prose border-l-2 border-border pl-3 text-sm whitespace-pre-line text-muted-foreground">
                    {question.scenario}
                </p>
            )}
            <p className="max-w-prose font-semibold whitespace-pre-line">
                {question.question}
            </p>
            <ol className="grid max-w-prose gap-1.5">
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
                                    {right ? ', the right answer' : ''}:{' '}
                                </span>
                                {option}
                            </span>
                        </li>
                    );
                })}
            </ol>
            {question.explanation && (
                <p className="max-w-prose text-sm text-muted-foreground">
                    <span className="font-semibold">Why:</span>{' '}
                    {question.explanation}
                </p>
            )}
            <div className="flex flex-wrap items-center justify-between gap-3 text-sm">
                <span className="text-muted-foreground">
                    Edited {question.updated}
                    {question.updated_by && ` by ${question.updated_by}`}
                </span>
                <span className="flex gap-2">
                    <Button asChild size="sm" variant="outline">
                        <Link href={edit(question.id)}>
                            Edit
                            <span className="sr-only"> this question</span>
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
        </li>
    );
}

export default function QuestionsIndex({
    areas,
    target,
    questions,
    filters,
    importResult,
}: {
    areas: Area[];
    target: number;
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
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Question bank"
                        description="The membership exam's questions. Each paper draws 50 from the areas an applicant claims; advanced areas are never asked of Student Members."
                    />
                    <div className="mb-8 flex flex-wrap gap-2">
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

                <div className="grid gap-6">
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

                    <AreaLedger
                        areas={areas}
                        target={target}
                        current={filters.area}
                        onPick={(area) => visit({ area })}
                    />

                    <section
                        aria-label="Questions"
                        className="@container rounded-card border border-border bg-background"
                    >
                        <div className="flex flex-col gap-3 border-b border-border p-4 md:px-6 @3xl:flex-row @3xl:items-center">
                            <form
                                onSubmit={(event) => {
                                    event.preventDefault();
                                    visit({ search });
                                }}
                                className="flex flex-1 gap-2"
                            >
                                <div className="relative flex-1">
                                    <Search
                                        aria-hidden
                                        className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                    />
                                    <label htmlFor="search" className="sr-only">
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
                            <div className="flex flex-wrap gap-2">
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
                                    className={cn(selectClass, 'w-auto')}
                                >
                                    <option value="">All areas</option>
                                    {areas.map((area) => (
                                        <option
                                            key={area.value}
                                            value={area.value}
                                        >
                                            {area.label}
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
                                    <QuestionCard
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
                                        <Link href={questions.prev_page_url}>
                                            Previous
                                        </Link>
                                    </Button>
                                ) : (
                                    <span />
                                )}
                                {questions.next_page_url && (
                                    <Button asChild variant="outline">
                                        <Link href={questions.next_page_url}>
                                            Next
                                        </Link>
                                    </Button>
                                )}
                            </div>
                        )}
                    </section>
                </div>
            </div>

            {importing && <ImportDialog onClose={() => setImporting(false)} />}
        </>
    );
}

QuestionsIndex.layout = {
    breadcrumbs: [{ title: 'Question bank', href: index() }],
};
