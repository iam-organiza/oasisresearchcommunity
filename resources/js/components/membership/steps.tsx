import type { FormDataType } from '@inertiajs/core';
import { Link, router, useForm } from '@inertiajs/react';
import {
    Download,
    FileText,
    Plus,
    Trash2,
    TriangleAlert,
    Upload,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    destroy as destroyDocument,
    store as storeDocument,
} from '@/routes/membership/documents';
import { edit, submit as submitRoute, update } from '@/routes/membership/apply';
import {
    Checkbox,
    ChoiceTiles,
    Field,
    fieldClass,
    naira,
    selectClass,
} from './form-bits';
import type {
    ApplicationData,
    DocumentItem,
    Options,
    Publication,
    StepItem,
    StepKey,
} from './types';

type StepProps = {
    application: ApplicationData;
    options: Options;
    previous: StepKey | null;
};

/** The shared footer: back, save for later, and save and continue. */
function StepFooter({
    previous,
    processing,
    onSave,
    continueLabel = 'Save and continue',
}: {
    previous: StepKey | null;
    processing: boolean;
    onSave?: () => void;
    continueLabel?: string;
}) {
    return (
        <div className="mt-10 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-6">
            {previous ? (
                <Button asChild variant="ghost">
                    <Link href={edit(previous)}>Back</Link>
                </Button>
            ) : (
                <span />
            )}
            <div className="flex flex-wrap gap-3">
                {onSave && (
                    <Button
                        type="button"
                        variant="outline"
                        size="lg"
                        disabled={processing}
                        onClick={onSave}
                    >
                        Save for later
                    </Button>
                )}
                <Button type="submit" size="lg" disabled={processing}>
                    {continueLabel}
                </Button>
            </div>
        </div>
    );
}

/** useForm wired to the step's save route; `stay` keeps the person on the step. */
function useStepForm<T extends FormDataType<T>>(step: StepKey, initial: T) {
    const form = useForm<T>(initial);
    const save = (stay: boolean) => {
        form.transform((data) => ({ ...data, stay }));
        form.put(update.url(step), { preserveScroll: true });
    };

    return {
        form,
        onSubmit: (event: FormEvent) => {
            event.preventDefault();
            save(false);
        },
        saveForLater: () => save(true),
    };
}

export function AboutStep({ application, options, previous }: StepProps) {
    const { form, onSubmit, saveForLater } = useStepForm('about', {
        title: application.title,
        phone: application.phone,
        state_id: application.state_id,
        city: application.city,
    });
    const { data, setData, errors } = form;

    return (
        <form onSubmit={onSubmit} noValidate>
            <div className="grid gap-6">
                <div className="grid gap-6 @xl:grid-cols-[8rem_minmax(0,1fr)]">
                    <Field
                        id="title"
                        label="Title"
                        hint="Dr., Prof., Mr., Mrs."
                        error={errors.title}
                        optional
                    >
                        <Input
                            id="title"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                        />
                    </Field>
                    <Field
                        id="phone"
                        label="Phone number"
                        hint="The secretariat may call to check your details."
                        error={errors.phone}
                    >
                        <Input
                            id="phone"
                            type="tel"
                            autoComplete="tel"
                            value={data.phone}
                            aria-invalid={errors.phone ? true : undefined}
                            onChange={(e) => setData('phone', e.target.value)}
                        />
                    </Field>
                </div>
                <div className="grid gap-6 @xl:grid-cols-2">
                    <Field
                        id="state_id"
                        label="State you live in"
                        hint="This sets your state and regional chapter."
                        error={errors.state_id}
                    >
                        <select
                            id="state_id"
                            value={data.state_id}
                            aria-invalid={errors.state_id ? true : undefined}
                            onChange={(e) =>
                                setData('state_id', e.target.value)
                            }
                            className={selectClass}
                        >
                            <option value="">Choose a state</option>
                            {options.regions.map((region) => (
                                <optgroup key={region.name} label={region.name}>
                                    {region.states.map((state) => (
                                        <option
                                            key={state.value}
                                            value={state.value}
                                        >
                                            {state.label}
                                        </option>
                                    ))}
                                </optgroup>
                            ))}
                        </select>
                    </Field>
                    <Field id="city" label="City or town" error={errors.city}>
                        <Input
                            id="city"
                            value={data.city}
                            aria-invalid={errors.city ? true : undefined}
                            onChange={(e) => setData('city', e.target.value)}
                        />
                    </Field>
                </div>
            </div>
            <StepFooter
                previous={previous}
                processing={form.processing}
                onSave={saveForLater}
            />
        </form>
    );
}

export function GradeStep({ application, options, previous }: StepProps) {
    const { form, onSubmit, saveForLater } = useStepForm('grade', {
        grade: application.grade,
        fellow_basis: application.fellow_basis,
        grade_evidence: application.grade_evidence,
    });
    const { data, setData, errors } = form;

    return (
        <form onSubmit={onSubmit} noValidate>
            <div className="grid gap-8">
                <ChoiceTiles
                    name="grade"
                    legend="The grade you are applying for"
                    options={options.grades}
                    value={data.grade}
                    onChange={(value) => setData('grade', value)}
                    error={errors.grade}
                    describe={(option) => {
                        const grade = options.grades.find(
                            (item) => item.value === option.value,
                        );

                        return grade ? (
                            <>
                                <span className="block font-semibold text-foreground">
                                    {naira(grade.induction_fee)} induction, then{' '}
                                    {naira(grade.annual_dues)} a year
                                </span>
                                <span className="mt-2 block space-y-1">
                                    {grade.criteria.map((line) => (
                                        <span key={line} className="block">
                                            {line}
                                        </span>
                                    ))}
                                </span>
                            </>
                        ) : null;
                    }}
                />
                <p className="text-sm text-muted-foreground">
                    The Registrar may award a different grade if your evidence
                    fits another one better, and will tell you why.
                </p>

                {data.grade === 'fellow' && (
                    <div className="grid gap-6 rounded-control bg-muted/60 p-5">
                        <ChoiceTiles
                            name="fellow_basis"
                            legend="Your application to Fellow rests on"
                            options={options.fellowBases}
                            value={data.fellow_basis}
                            onChange={(value) => setData('fellow_basis', value)}
                            error={errors.fellow_basis}
                        />
                        <Field
                            id="grade_evidence"
                            label="The evidence"
                            hint="For example: your degree and where it is from, your current rank, or the office you hold or held. You upload documents in a later step."
                            error={errors.grade_evidence}
                        >
                            <textarea
                                id="grade_evidence"
                                rows={4}
                                value={data.grade_evidence}
                                aria-invalid={
                                    errors.grade_evidence ? true : undefined
                                }
                                onChange={(e) =>
                                    setData('grade_evidence', e.target.value)
                                }
                                className={fieldClass}
                            />
                        </Field>
                    </div>
                )}
            </div>
            <StepFooter
                previous={previous}
                processing={form.processing}
                onSave={saveForLater}
            />
        </form>
    );
}

const emptyPublication: Publication = {
    title: '',
    venue: '',
    year: '',
    link: '',
};

export function ExperienceStep({ application, options, previous }: StepProps) {
    const { form, onSubmit, saveForLater } = useStepForm('experience', {
        highest_qualification: application.highest_qualification,
        current_role: application.current_role,
        institution: application.institution,
        department: application.department,
        years_experience: application.years_experience,
        publications: application.publications,
        competencies: application.competencies,
    });
    const { data, setData, errors } = form;
    const errorFor = (key: string) =>
        (errors as Record<string, string | undefined>)[key];

    const setPublication = (
        index: number,
        key: keyof Publication,
        value: string,
    ) =>
        setData(
            'publications',
            data.publications.map((item, i) =>
                i === index ? { ...item, [key]: value } : item,
            ),
        );

    return (
        <form onSubmit={onSubmit} noValidate>
            <div className="grid gap-6">
                <div className="grid gap-6 @xl:grid-cols-2">
                    <Field
                        id="highest_qualification"
                        label="Highest qualification"
                        error={errors.highest_qualification}
                    >
                        <select
                            id="highest_qualification"
                            value={data.highest_qualification}
                            aria-invalid={
                                errors.highest_qualification ? true : undefined
                            }
                            onChange={(e) =>
                                setData('highest_qualification', e.target.value)
                            }
                            className={selectClass}
                        >
                            <option value="">Choose one</option>
                            {options.qualifications.map((item) => (
                                <option key={item.value} value={item.value}>
                                    {item.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        id="years_experience"
                        label="Years of research experience"
                        error={errors.years_experience}
                    >
                        <Input
                            id="years_experience"
                            type="number"
                            inputMode="numeric"
                            min={0}
                            max={70}
                            value={data.years_experience}
                            aria-invalid={
                                errors.years_experience ? true : undefined
                            }
                            onChange={(e) =>
                                setData('years_experience', e.target.value)
                            }
                        />
                    </Field>
                </div>
                <Field
                    id="current_role"
                    label="Current role and rank"
                    hint="For example: Lecturer I, Research Officer, Chief Executive, Final-year student."
                    error={errors.current_role}
                >
                    <Input
                        id="current_role"
                        value={data.current_role}
                        aria-invalid={errors.current_role ? true : undefined}
                        onChange={(e) =>
                            setData('current_role', e.target.value)
                        }
                    />
                </Field>
                <div className="grid gap-6 @xl:grid-cols-2">
                    <Field
                        id="institution"
                        label="Institution or organisation"
                        error={errors.institution}
                    >
                        <Input
                            id="institution"
                            value={data.institution}
                            aria-invalid={errors.institution ? true : undefined}
                            onChange={(e) =>
                                setData('institution', e.target.value)
                            }
                        />
                    </Field>
                    <Field
                        id="department"
                        label="Department or unit"
                        error={errors.department}
                        optional
                    >
                        <Input
                            id="department"
                            value={data.department}
                            onChange={(e) =>
                                setData('department', e.target.value)
                            }
                        />
                    </Field>
                </div>

                {/* Publications: one row each, added and removed freely. */}
                <fieldset className="grid gap-4 border-t border-border pt-6">
                    <legend className="font-semibold">Publications</legend>
                    <p className="-mt-2 text-sm text-muted-foreground">
                        Journal articles, books, ebooks and book chapters. Some
                        grades need a number of publications; list them all.
                    </p>
                    {data.publications.map((publication, index) => (
                        <div
                            key={index}
                            className="grid gap-4 rounded-control border border-border p-4 @2xl:grid-cols-[minmax(0,2fr)_minmax(0,1.4fr)_6rem]"
                        >
                            <Field
                                id={`pub-title-${index}`}
                                label="Title"
                                error={errorFor(`publications.${index}.title`)}
                            >
                                <Input
                                    id={`pub-title-${index}`}
                                    value={publication.title}
                                    onChange={(e) =>
                                        setPublication(
                                            index,
                                            'title',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                id={`pub-venue-${index}`}
                                label="Journal, publisher or book"
                                error={errorFor(`publications.${index}.venue`)}
                            >
                                <Input
                                    id={`pub-venue-${index}`}
                                    value={publication.venue}
                                    onChange={(e) =>
                                        setPublication(
                                            index,
                                            'venue',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <Field
                                id={`pub-year-${index}`}
                                label="Year"
                                error={errorFor(`publications.${index}.year`)}
                            >
                                <Input
                                    id={`pub-year-${index}`}
                                    inputMode="numeric"
                                    value={publication.year}
                                    onChange={(e) =>
                                        setPublication(
                                            index,
                                            'year',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                            <div className="flex items-end gap-3 @2xl:col-span-3">
                                <div className="min-w-0 flex-1">
                                    <Field
                                        id={`pub-link-${index}`}
                                        label="Link or DOI address"
                                        error={errorFor(
                                            `publications.${index}.link`,
                                        )}
                                        optional
                                    >
                                        <Input
                                            id={`pub-link-${index}`}
                                            type="url"
                                            placeholder="https://"
                                            value={publication.link}
                                            onChange={(e) =>
                                                setPublication(
                                                    index,
                                                    'link',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </Field>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                    onClick={() =>
                                        setData(
                                            'publications',
                                            data.publications.filter(
                                                (_, i) => i !== index,
                                            ),
                                        )
                                    }
                                >
                                    <Trash2 />
                                    Remove
                                </Button>
                            </div>
                        </div>
                    ))}
                    {data.publications.length < options.maxPublications && (
                        <div>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() =>
                                    setData('publications', [
                                        ...data.publications,
                                        { ...emptyPublication },
                                    ])
                                }
                            >
                                <Plus />
                                Add a publication
                            </Button>
                        </div>
                    )}
                </fieldset>

                {/* Optional self-rating on the five competencies. */}
                <fieldset className="grid gap-4 border-t border-border pt-6">
                    <legend className="font-semibold">
                        Your research competencies
                        <span className="ml-1.5 font-normal text-muted-foreground">
                            (optional)
                        </span>
                    </legend>
                    <p className="-mt-2 text-sm text-muted-foreground">
                        Rate yourself from 1 (beginner) to 5 (expert). This
                        helps OASIS suggest trainings; it does not affect your
                        grade.
                    </p>
                    <div className="grid gap-3">
                        {options.competencies.map((competency) => (
                            <div
                                key={competency.value}
                                className="flex flex-col gap-2 @xl:flex-row @xl:items-center @xl:justify-between"
                            >
                                <span
                                    id={`comp-${competency.value}`}
                                    className="text-sm"
                                >
                                    {competency.label}
                                </span>
                                <div
                                    role="radiogroup"
                                    aria-labelledby={`comp-${competency.value}`}
                                    className="flex gap-1.5"
                                >
                                    {[1, 2, 3, 4, 5].map((rating) => {
                                        const chosen =
                                            data.competencies[
                                                competency.value
                                            ] === rating;

                                        return (
                                            <label
                                                key={rating}
                                                className={[
                                                    'flex size-10 cursor-pointer items-center justify-center rounded-control border text-sm font-semibold tabular-nums transition-colors',
                                                    'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                                                    chosen
                                                        ? 'border-primary bg-primary text-primary-foreground'
                                                        : 'border-border hover:border-foreground/40',
                                                ].join(' ')}
                                            >
                                                <input
                                                    type="radio"
                                                    name={`comp-${competency.value}`}
                                                    value={rating}
                                                    checked={chosen}
                                                    onChange={() =>
                                                        setData(
                                                            'competencies',
                                                            {
                                                                ...data.competencies,
                                                                [competency.value]:
                                                                    rating,
                                                            },
                                                        )
                                                    }
                                                    className="sr-only"
                                                />
                                                {rating}
                                            </label>
                                        );
                                    })}
                                </div>
                            </div>
                        ))}
                    </div>
                </fieldset>
            </div>
            <StepFooter
                previous={previous}
                processing={form.processing}
                onSave={saveForLater}
            />
        </form>
    );
}

export function RouteStep({ application, options, previous }: StepProps) {
    const { form, onSubmit, saveForLater } = useStepForm('route', {
        route: application.route,
        exam_status: application.exam_status,
        exam_passed_on: application.exam_passed_on,
        induction_event: application.induction_event,
        induction_status: application.induction_status,
        induction_attended_on: application.induction_attended_on,
    });
    const { data, setData, errors } = form;

    return (
        <form onSubmit={onSubmit} noValidate>
            <div className="grid gap-8">
                <ChoiceTiles
                    name="route"
                    legend="How you qualify"
                    options={options.routes}
                    value={data.route}
                    onChange={(value) => setData('route', value)}
                    error={errors.route}
                />
                {data.route === 'exam' && (
                    <div className="grid gap-6 rounded-control bg-muted/60 p-5">
                        <ChoiceTiles
                            name="exam_status"
                            legend="The membership exam"
                            options={options.examStatuses}
                            value={data.exam_status}
                            onChange={(value) => setData('exam_status', value)}
                            error={errors.exam_status}
                        />
                        {data.exam_status === 'passed' && (
                            <div className="max-w-xs">
                                <Field
                                    id="exam_passed_on"
                                    label="Date you passed"
                                    error={errors.exam_passed_on}
                                >
                                    <Input
                                        id="exam_passed_on"
                                        type="date"
                                        value={data.exam_passed_on}
                                        onChange={(e) =>
                                            setData(
                                                'exam_passed_on',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        )}
                    </div>
                )}

                <div className="grid gap-6 border-t border-border pt-6">
                    <p className="text-sm text-muted-foreground">
                        Every new member attends one induction event.
                    </p>
                    <ChoiceTiles
                        name="induction_event"
                        legend="Your induction event"
                        options={options.inductionEvents}
                        value={data.induction_event}
                        onChange={(value) => setData('induction_event', value)}
                        error={errors.induction_event}
                    />
                    <ChoiceTiles
                        name="induction_status"
                        legend="Have you attended it?"
                        options={options.inductionStatuses}
                        value={data.induction_status}
                        onChange={(value) => setData('induction_status', value)}
                        error={errors.induction_status}
                        columns="two"
                    />
                    {data.induction_status === 'attended' && (
                        <div className="max-w-xs">
                            <Field
                                id="induction_attended_on"
                                label="Date you attended"
                                error={errors.induction_attended_on}
                            >
                                <Input
                                    id="induction_attended_on"
                                    type="date"
                                    value={data.induction_attended_on}
                                    onChange={(e) =>
                                        setData(
                                            'induction_attended_on',
                                            e.target.value,
                                        )
                                    }
                                />
                            </Field>
                        </div>
                    )}
                </div>
            </div>
            <StepFooter
                previous={previous}
                processing={form.processing}
                onSave={saveForLater}
            />
        </form>
    );
}

const fileSize = (bytes: number) =>
    bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;

export function DocumentsStep({
    options,
    previous,
    documents,
}: StepProps & { documents: DocumentItem[] }) {
    const form = useForm<{ kind: string; file: File | null }>({
        kind: documents.some((document) => document.kind === 'cv')
            ? 'certificate'
            : 'cv',
        file: null,
    });
    const [inputKey, setInputKey] = useState(0);
    const hasCv = documents.some((document) => document.kind === 'cv');

    const upload = (event: FormEvent) => {
        event.preventDefault();
        form.post(storeDocument.url(), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                // Once the CV is in, the next file is most likely a certificate.
                form.setData({ kind: 'certificate', file: null });
                setInputKey((key) => key + 1);
            },
        });
    };

    const remove = (document: DocumentItem) => {
        if (window.confirm(`Remove ${document.name}?`)) {
            router.delete(destroyDocument.url(document.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <div>
            <p className="text-muted-foreground">
                Upload your CV (required) and any certificates or other evidence
                for your grade. PDF, JPG or PNG, up to {options.maxDocumentMb}{' '}
                MB each, {options.maxDocuments} files at most. Only you and the
                Registrar can open them.
            </p>

            {documents.length > 0 && (
                <ul className="mt-6 divide-y divide-border rounded-control border border-border">
                    {documents.map((document) => (
                        <li
                            key={document.id}
                            className="flex flex-wrap items-center gap-3 p-3"
                        >
                            <FileText
                                aria-hidden
                                className="size-5 shrink-0 text-muted-foreground"
                            />
                            <div className="min-w-0 flex-1">
                                <p className="truncate font-semibold">
                                    {document.name}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {document.kind_label} ·{' '}
                                    {fileSize(document.size)}
                                </p>
                            </div>
                            <Button asChild size="sm" variant="ghost">
                                <a href={document.url}>
                                    <Download />
                                    Download
                                </a>
                            </Button>
                            <Button
                                size="sm"
                                variant="ghost"
                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                onClick={() => remove(document)}
                            >
                                <Trash2 />
                                Remove
                            </Button>
                        </li>
                    ))}
                </ul>
            )}

            {documents.length < options.maxDocuments && (
                <form
                    onSubmit={upload}
                    className="mt-6 grid gap-4 rounded-control border border-dashed border-border p-5 @2xl:grid-cols-[12rem_minmax(0,1fr)_auto] @2xl:items-end"
                    noValidate
                >
                    <Field
                        id="kind"
                        label="What it is"
                        error={form.errors.kind}
                    >
                        <select
                            id="kind"
                            value={form.data.kind}
                            onChange={(e) =>
                                form.setData('kind', e.target.value)
                            }
                            className={selectClass}
                        >
                            {options.documentKinds.map((kind) => (
                                <option key={kind.value} value={kind.value}>
                                    {kind.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field id="file" label="File" error={form.errors.file}>
                        <Input
                            key={inputKey}
                            id="file"
                            type="file"
                            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                            aria-invalid={form.errors.file ? true : undefined}
                            onChange={(e) =>
                                form.setData(
                                    'file',
                                    e.target.files?.[0] ?? null,
                                )
                            }
                        />
                    </Field>
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={form.processing || !form.data.file}
                    >
                        <Upload />
                        {form.processing ? 'Uploading' : 'Upload'}
                    </Button>
                </form>
            )}

            {!hasCv && (
                <p className="mt-4 flex items-center gap-2 text-sm font-semibold text-destructive">
                    <TriangleAlert aria-hidden className="size-4" />
                    Upload your CV to finish this step.
                </p>
            )}

            <div className="mt-10 flex flex-wrap items-center justify-between gap-3 border-t border-border pt-6">
                {previous ? (
                    <Button asChild variant="ghost">
                        <Link href={edit(previous)}>Back</Link>
                    </Button>
                ) : (
                    <span />
                )}
                <Button asChild size="lg">
                    <Link href={edit('referee')}>Continue</Link>
                </Button>
            </div>
        </div>
    );
}

export function RefereeStep({ application, previous }: StepProps) {
    const { form, onSubmit, saveForLater } = useStepForm('referee', {
        referee_name: application.referee_name,
        referee_email: application.referee_email,
        referee_relationship: application.referee_relationship,
        referee_membership_number: application.referee_membership_number,
    });
    const { data, setData, errors } = form;

    return (
        <form onSubmit={onSubmit} noValidate>
            <p className="text-muted-foreground">
                A referee is optional. If you name one, we will ask them by
                email to confirm they can vouch for you. They do not need to be
                an OASIS member.
            </p>
            <div className="mt-6 grid gap-6 @xl:grid-cols-2">
                <Field
                    id="referee_name"
                    label="Their name"
                    error={errors.referee_name}
                    optional
                >
                    <Input
                        id="referee_name"
                        value={data.referee_name}
                        onChange={(e) =>
                            setData('referee_name', e.target.value)
                        }
                    />
                </Field>
                <Field
                    id="referee_email"
                    label="Their email address"
                    error={errors.referee_email}
                    optional
                >
                    <Input
                        id="referee_email"
                        type="email"
                        value={data.referee_email}
                        aria-invalid={errors.referee_email ? true : undefined}
                        onChange={(e) =>
                            setData('referee_email', e.target.value)
                        }
                    />
                </Field>
                <Field
                    id="referee_relationship"
                    label="How they know you"
                    hint="For example: my head of department, my PhD supervisor."
                    error={errors.referee_relationship}
                    optional
                >
                    <Input
                        id="referee_relationship"
                        value={data.referee_relationship}
                        aria-invalid={
                            errors.referee_relationship ? true : undefined
                        }
                        onChange={(e) =>
                            setData('referee_relationship', e.target.value)
                        }
                    />
                </Field>
                <Field
                    id="referee_membership_number"
                    label="Their OASIS membership number"
                    hint="Only if they are a member."
                    error={errors.referee_membership_number}
                    optional
                >
                    <Input
                        id="referee_membership_number"
                        value={data.referee_membership_number}
                        onChange={(e) =>
                            setData('referee_membership_number', e.target.value)
                        }
                    />
                </Field>
            </div>
            <StepFooter
                previous={previous}
                processing={form.processing}
                onSave={saveForLater}
            />
        </form>
    );
}

/** One section of the review: a heading with a Change link, then the answers. */
function ReviewSection({
    step,
    title,
    complete,
    children,
}: {
    step: StepKey;
    title: string;
    complete: boolean;
    children: ReactNode;
}) {
    return (
        <section className="border-t border-border pt-5">
            <div className="flex items-baseline justify-between gap-3">
                <h3 className="font-semibold">{title}</h3>
                <Link
                    href={edit(step)}
                    className="text-sm font-semibold underline decoration-border underline-offset-4 hover:decoration-current"
                >
                    {complete ? 'Change' : 'Finish this step'}
                </Link>
            </div>
            <dl className="mt-3 grid gap-x-6 gap-y-2 text-sm @xl:grid-cols-[12rem_minmax(0,1fr)]">
                {children}
            </dl>
        </section>
    );
}

function Row({ label, value }: { label: string; value: ReactNode }) {
    return (
        <>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="break-words">{value || '—'}</dd>
        </>
    );
}

export function ReviewStep({
    application,
    options,
    previous,
    steps,
    documents,
}: StepProps & { steps: StepItem[]; documents: DocumentItem[] }) {
    const form = useForm({
        directory_consent: application.directory_consent,
        keep_documents_two_years: application.keep_documents_two_years,
        declaration: false,
    });
    const complete = (key: StepKey) =>
        steps.find((step) => step.key === key)?.complete ?? false;
    const missing = steps.filter(
        (step) => step.key !== 'review' && !step.complete,
    );
    const labelOf = (list: { value: string; label: string }[], value: string) =>
        list.find((item) => item.value === value)?.label ?? '';

    const onSubmit = (event: FormEvent) => {
        event.preventDefault();
        form.post(submitRoute.url(), { preserveScroll: true });
    };

    return (
        <form onSubmit={onSubmit} noValidate>
            {missing.length > 0 && (
                <p
                    role="status"
                    className="mb-6 rounded-control border border-destructive/30 bg-destructive/5 p-4 text-sm"
                >
                    <span className="font-semibold text-destructive">
                        Finish these steps before you submit:
                    </span>{' '}
                    {missing.map((step) => step.label).join(', ')}.
                </p>
            )}

            <div className="grid gap-6">
                <ReviewSection
                    step="about"
                    title="About you"
                    complete={complete('about')}
                >
                    <Row label="Title" value={application.title} />
                    <Row label="Phone" value={application.phone} />
                    <Row
                        label="Lives in"
                        value={[application.city, application.state_label]
                            .filter(Boolean)
                            .join(', ')}
                    />
                </ReviewSection>
                <ReviewSection
                    step="grade"
                    title="Grade"
                    complete={complete('grade')}
                >
                    <Row
                        label="Applying for"
                        value={labelOf(options.grades, application.grade)}
                    />
                    {application.grade === 'fellow' && (
                        <>
                            <Row
                                label="Rests on"
                                value={labelOf(
                                    options.fellowBases,
                                    application.fellow_basis,
                                )}
                            />
                            <Row
                                label="Evidence"
                                value={application.grade_evidence}
                            />
                        </>
                    )}
                </ReviewSection>
                <ReviewSection
                    step="experience"
                    title="Experience"
                    complete={complete('experience')}
                >
                    <Row
                        label="Qualification"
                        value={labelOf(
                            options.qualifications,
                            application.highest_qualification,
                        )}
                    />
                    <Row
                        label="Role and rank"
                        value={application.current_role}
                    />
                    <Row
                        label="Institution"
                        value={[application.institution, application.department]
                            .filter(Boolean)
                            .join(', ')}
                    />
                    <Row
                        label="Research experience"
                        value={
                            application.years_experience &&
                            `${application.years_experience} years`
                        }
                    />
                    <Row
                        label="Publications"
                        value={`${application.publications.length} listed`}
                    />
                </ReviewSection>
                <ReviewSection
                    step="route"
                    title="Route"
                    complete={complete('route')}
                >
                    <Row
                        label="Qualifying by"
                        value={labelOf(options.routes, application.route)}
                    />
                    {application.route === 'exam' && (
                        <Row
                            label="Exam"
                            value={`${labelOf(options.examStatuses, application.exam_status)}${application.exam_passed_on ? ` (${application.exam_passed_on})` : ''}`}
                        />
                    )}
                    <Row
                        label="Induction"
                        value={`${labelOf(options.inductionEvents, application.induction_event)}${application.induction_status ? `: ${labelOf(options.inductionStatuses, application.induction_status).toLowerCase()}` : ''}`}
                    />
                </ReviewSection>
                <ReviewSection
                    step="documents"
                    title="Documents"
                    complete={complete('documents')}
                >
                    <Row
                        label="Uploaded"
                        value={documents
                            .map(
                                (document) =>
                                    `${document.kind_label}: ${document.name}`,
                            )
                            .join('; ')}
                    />
                </ReviewSection>
                <ReviewSection
                    step="referee"
                    title="Referee"
                    complete={complete('referee')}
                >
                    <Row
                        label="Referee"
                        value={
                            application.referee_name
                                ? `${application.referee_name} (${application.referee_relationship}), ${application.referee_email}`
                                : 'None named'
                        }
                    />
                </ReviewSection>

                <div className="grid gap-3 border-t border-border pt-6">
                    <Checkbox
                        id="directory_consent"
                        checked={form.data.directory_consent}
                        onChange={(checked) =>
                            form.setData('directory_consent', checked)
                        }
                    >
                        List me in the OASIS member directory once I am a member
                        (name, grade and chapter only). Optional.
                    </Checkbox>
                    <Checkbox
                        id="keep_documents_two_years"
                        checked={form.data.keep_documents_two_years}
                        onChange={(checked) =>
                            form.setData('keep_documents_two_years', checked)
                        }
                    >
                        If my application is declined, keep my documents for up
                        to 2 years so I do not have to upload them again if I
                        reapply. Otherwise they are deleted 6 months after the
                        decision. Optional.
                    </Checkbox>
                    <Checkbox
                        id="declaration"
                        checked={form.data.declaration}
                        onChange={(checked) =>
                            form.setData('declaration', checked)
                        }
                        error={form.errors.declaration}
                    >
                        <strong>
                            I confirm that my answers and documents are true and
                            my own
                        </strong>
                        , and I will uphold OASIS's core values: excellence,
                        ethics and integrity.
                    </Checkbox>
                </div>
            </div>

            <StepFooter
                previous={previous}
                processing={form.processing}
                continueLabel="Submit application"
            />
        </form>
    );
}
