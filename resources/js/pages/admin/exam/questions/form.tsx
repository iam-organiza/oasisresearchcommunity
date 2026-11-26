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

type AreaOption = { value: string; label: string; advanced: boolean };

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
}: {
    question: Question | null;
    areas: AreaOption[];
    defaultArea: string;
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
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title={question ? 'Edit a question' : 'Add a question'}
                    description="One question with two to six options and one right answer. Changes apply to papers set from now on."
                />

                <form
                    onSubmit={save(false)}
                    noValidate
                    className="@container grid max-w-3xl gap-6 rounded-card border border-border bg-background p-6 md:p-8"
                >
                    <Field
                        id="area"
                        label="Research area"
                        hint={
                            area?.advanced
                                ? 'An advanced area: never asked of Student Members.'
                                : undefined
                        }
                        error={errors.area}
                    >
                        <select
                            id="area"
                            value={data.area}
                            aria-invalid={errors.area ? true : undefined}
                            onChange={(e) => setData('area', e.target.value)}
                            className={selectClass}
                        >
                            <option value="">Choose an area</option>
                            {areas.map((option) => (
                                <option key={option.value} value={option.value}>
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
                            aria-invalid={errors.question ? true : undefined}
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

                    <div className="flex flex-wrap gap-3">
                        <Button
                            type="submit"
                            size="lg"
                            disabled={form.processing}
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
                            >
                                Add and write another
                            </Button>
                        )}
                        <Button asChild size="lg" variant="ghost">
                            <Link href={index()}>
                                Back to the question bank
                            </Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

QuestionForm.layout = {
    breadcrumbs: [{ title: 'Question bank', href: index() }],
};
