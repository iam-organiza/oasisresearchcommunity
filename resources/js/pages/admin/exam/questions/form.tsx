import { Head, Link, useForm } from '@inertiajs/react';
import { Check, Plus, X } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    Field,
    fieldClass,
    selectClass,
} from '@/components/membership/form-bits';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { index, store, update } from '@/routes/admin/exam/questions';

type AreaOption = {
    value: string;
    label: string;
    advanced: boolean;
    count: number;
};

type Question = {
    id: number;
    area: string;
    scenario: string | null;
    question: string;
    options: string[];
    correct_option: number;
    explanation: string | null;
};

const MAX_OPTIONS = 6;
const letter = (i: number) => String.fromCharCode(65 + i);

export default function QuestionForm({
    question,
    areas,
    defaultArea,
    target,
}: {
    question: Question | null;
    areas: AreaOption[];
    defaultArea: string;
    target: number;
}) {
    const form = useForm({
        area: question?.area ?? defaultArea,
        scenario: question?.scenario ?? '',
        question: question?.question ?? '',
        options: question?.options ?? ['', '', '', ''],
        correct_option: question?.correct_option ?? -1,
        explanation: question?.explanation ?? '',
        add_another: false,
    });
    const { data, setData, errors } = form;
    const error = (key: string) =>
        (errors as Record<string, string | undefined>)[key];
    const optionError = data.options
        .map((_, i) => error(`options.${i}`))
        .find(Boolean);

    const setOption = (i: number, value: string) =>
        setData(
            'options',
            data.options.map((option, j) => (j === i ? value : option)),
        );
    const removeOption = (i: number) => {
        form.setData({
            ...data,
            options: data.options.filter((_, j) => j !== i),
            correct_option:
                data.correct_option === i
                    ? -1
                    : data.correct_option > i
                      ? data.correct_option - 1
                      : data.correct_option,
        });
    };

    const save = (addAnother: boolean) => (event?: FormEvent) => {
        event?.preventDefault();
        form.transform((values) => ({ ...values, add_another: addAnother }));
        if (question) {
            form.put(update.url(question.id));
        } else {
            form.post(store.url(), {
                onSuccess: () => {
                    if (addAnother) {
                        form.reset();
                        form.setData('area', data.area);
                    }
                },
            });
        }
    };

    const area = areas.find((item) => item.value === data.area);

    return (
        <>
            <Head title={question ? 'Edit a question' : 'Add a question'} />
            <div className="@container w-full px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title={question ? 'Edit a question' : 'Add a question'}
                    description="One question with two to six options and one right answer. Changes apply to papers set from now on."
                />

                <form
                    onSubmit={save(false)}
                    noValidate
                    className="grid items-start gap-6 *:min-w-0 @5xl:grid-cols-[minmax(0,1fr)_minmax(0,26rem)] @7xl:grid-cols-[minmax(0,1fr)_minmax(0,32rem)]"
                >
                    <div className="@container grid gap-6 rounded-card border border-border bg-background p-6 md:p-8">
                        <Field
                            id="area"
                            label="Research area"
                            hint={
                                area?.advanced
                                    ? 'An advanced area: asked of Fellows and Members only.'
                                    : undefined
                            }
                            error={errors.area}
                        >
                            <select
                                id="area"
                                value={data.area}
                                aria-invalid={errors.area ? true : undefined}
                                onChange={(e) =>
                                    setData('area', e.target.value)
                                }
                                className={selectClass}
                            >
                                <option value="">Choose an area</option>
                                {areas.map((option) => (
                                    <option
                                        key={option.value}
                                        value={option.value}
                                    >
                                        {option.label}
                                        {option.advanced ? ' (advanced)' : ''}
                                    </option>
                                ))}
                            </select>
                        </Field>

                        <Field
                            id="scenario"
                            label="Scenario"
                            hint="A short situation the question is about, if it needs one."
                            error={errors.scenario}
                            optional
                        >
                            <textarea
                                id="scenario"
                                rows={3}
                                value={data.scenario}
                                onChange={(e) =>
                                    setData('scenario', e.target.value)
                                }
                                className={fieldClass}
                            />
                        </Field>

                        <Field
                            id="question"
                            label="Question"
                            error={errors.question}
                        >
                            <textarea
                                id="question"
                                rows={2}
                                value={data.question}
                                aria-invalid={
                                    errors.question ? true : undefined
                                }
                                onChange={(e) =>
                                    setData('question', e.target.value)
                                }
                                className={fieldClass}
                            />
                        </Field>

                        <fieldset className="grid gap-3">
                            <legend className="mb-1 font-semibold">
                                Options
                                <span className="ml-1.5 font-normal text-muted-foreground">
                                    (tick the right answer)
                                </span>
                            </legend>
                            {data.options.map((option, i) => {
                                const right = data.correct_option === i;

                                return (
                                    <div
                                        key={i}
                                        className="flex items-center gap-2"
                                    >
                                        <label
                                            className={cn(
                                                'flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-full text-sm font-semibold',
                                                'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                                                right
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'ring-1 ring-border ring-inset hover:ring-foreground/40',
                                            )}
                                        >
                                            <input
                                                type="radio"
                                                name="correct_option"
                                                checked={right}
                                                onChange={() =>
                                                    setData('correct_option', i)
                                                }
                                                className="sr-only"
                                            />
                                            <span className="sr-only">
                                                Option {letter(i)} is the right
                                                answer
                                            </span>
                                            <span aria-hidden>
                                                {right ? (
                                                    <Check className="size-4" />
                                                ) : (
                                                    letter(i)
                                                )}
                                            </span>
                                        </label>
                                        <label
                                            htmlFor={`option-${i}`}
                                            className="sr-only"
                                        >
                                            Option {letter(i)}
                                        </label>
                                        <input
                                            id={`option-${i}`}
                                            value={option}
                                            aria-invalid={
                                                error(`options.${i}`)
                                                    ? true
                                                    : undefined
                                            }
                                            onChange={(e) =>
                                                setOption(i, e.target.value)
                                            }
                                            className={fieldClass}
                                        />
                                        {data.options.length > 2 && (
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                onClick={() => removeOption(i)}
                                            >
                                                <X />
                                                <span className="sr-only">
                                                    Remove option {letter(i)}
                                                </span>
                                            </Button>
                                        )}
                                    </div>
                                );
                            })}
                            {data.options.length < MAX_OPTIONS && (
                                <div>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            setData('options', [
                                                ...data.options,
                                                '',
                                            ])
                                        }
                                    >
                                        <Plus />
                                        Add an option
                                    </Button>
                                </div>
                            )}
                            <InputError
                                className="font-semibold text-destructive"
                                message={
                                    optionError ??
                                    errors.options ??
                                    errors.correct_option
                                }
                            />
                        </fieldset>

                        <Field
                            id="explanation"
                            label="Why the answer is right"
                            hint="For the committee and the Registrar. Candidates do not see it."
                            error={errors.explanation}
                            optional
                        >
                            <textarea
                                id="explanation"
                                rows={2}
                                value={data.explanation}
                                onChange={(e) =>
                                    setData('explanation', e.target.value)
                                }
                                className={fieldClass}
                            />
                        </Field>
                    </div>

                    {/* What the candidate will see, and what only the committee sees. */}
                    <aside className="grid gap-4 @5xl:sticky @5xl:top-6">
                        <section
                            aria-labelledby="preview-heading"
                            className="rounded-card border border-border bg-brand-paper p-5 dark:bg-background"
                        >
                            <h2
                                id="preview-heading"
                                className="text-sm font-semibold text-muted-foreground"
                            >
                                How candidates see it
                            </h2>
                            <div className="mt-3 grid gap-4 rounded-card border border-border bg-background p-5">
                                {data.scenario.trim() && (
                                    <p className="rounded-control bg-muted/70 p-3 text-sm whitespace-pre-line">
                                        {data.scenario}
                                    </p>
                                )}
                                <p
                                    className={cn(
                                        'text-lg leading-snug font-semibold whitespace-pre-line',
                                        !data.question.trim() &&
                                            'text-muted-foreground',
                                    )}
                                >
                                    {data.question.trim() ||
                                        'The question appears here.'}
                                </p>
                                <ol className="grid gap-2">
                                    {data.options.map((option, i) => (
                                        <li
                                            key={i}
                                            className="flex items-start gap-3 rounded-control border border-border p-3 text-sm"
                                        >
                                            <span
                                                aria-hidden
                                                className="flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold ring-1 ring-border ring-inset"
                                            >
                                                {letter(i)}
                                            </span>
                                            <span
                                                className={cn(
                                                    'pt-1',
                                                    !option.trim() &&
                                                        'text-muted-foreground',
                                                )}
                                            >
                                                <span className="sr-only">
                                                    {letter(i)}.{' '}
                                                </span>
                                                {option.trim() ||
                                                    `Option ${letter(i)}`}
                                            </span>
                                        </li>
                                    ))}
                                </ol>
                            </div>
                            <p className="mt-3 text-xs text-muted-foreground">
                                The options are shuffled for each candidate.
                            </p>
                        </section>

                        <section
                            aria-label="For the committee"
                            className="grid gap-3 rounded-card bg-brand-navy p-5 text-sm text-white dark:bg-brand-night-raised"
                        >
                            <dl className="grid gap-2">
                                <div className="flex justify-between gap-4">
                                    <dt className="text-brand-on-dark">
                                        Right answer
                                    </dt>
                                    <dd className="font-semibold">
                                        {data.correct_option >= 0
                                            ? `${letter(data.correct_option)}${data.options[data.correct_option]?.trim() ? `: ${data.options[data.correct_option]}` : ''}`
                                            : 'Not ticked yet'}
                                    </dd>
                                </div>
                                {area && (
                                    <div className="flex justify-between gap-4">
                                        <dt className="text-brand-on-dark">
                                            {area.label}
                                        </dt>
                                        <dd className="shrink-0 font-semibold">
                                            {area.count} of {target} in use
                                        </dd>
                                    </div>
                                )}
                            </dl>
                            <div className="grid gap-2 border-t border-white/15 pt-4">
                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={form.processing}
                                    className="bg-white text-brand-navy hover:bg-white/90"
                                >
                                    {question
                                        ? 'Save the question'
                                        : 'Add the question'}
                                </Button>
                                {!question && (
                                    <Button
                                        type="button"
                                        size="lg"
                                        variant="outline"
                                        disabled={form.processing}
                                        onClick={() => save(true)()}
                                        className="border-white/40 bg-transparent text-white hover:bg-white/10 hover:text-white"
                                    >
                                        Add and write another
                                    </Button>
                                )}
                                <Link
                                    href={index()}
                                    className="mt-1 text-center font-semibold text-brand-on-dark underline decoration-white/30 underline-offset-4 hover:text-white"
                                >
                                    Back to the question bank
                                </Link>
                            </div>
                        </section>
                    </aside>
                </form>
            </div>
        </>
    );
}

QuestionForm.layout = {
    breadcrumbs: [{ title: 'Question bank', href: index() }],
};
