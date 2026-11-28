import { Head, Link, useForm } from '@inertiajs/react';
import { ExternalLink, Plus, X } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { cn } from '@/lib/utils';
import { history, update } from '@/routes/admin/website';

type Item = { title: string; text: string };
type Values = Record<string, string | string[] | Item[]>;
type SectionData = { key: string; label: string; hint: string; values: Values };
type Errors = Record<string, string | undefined>;

/** Where each section is on the public page, for "See it on the site". */
const ANCHORS: Record<string, string> = {
    'home.hero': '',
    'home.services': '#what-we-do',
    'home.benefits': '#membership',
    'home.categories': '#membership',
    'home.join': '#about-us',
    'home.about': '#about-us',
};

const fieldClass =
    'w-full rounded-control border border-input bg-background px-3 py-2 text-base shadow-none outline-none md:text-sm focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/20 aria-invalid:border-destructive';

/** A text box with a count against its limit, so nothing is cut off on the page. */
function TextBox({
    id,
    label,
    value,
    max,
    rows = 3,
    error,
    hint,
    onChange,
}: {
    id: string;
    label: string;
    value: string;
    max: number;
    rows?: number;
    error?: string;
    hint?: string;
    onChange: (value: string) => void;
}) {
    const over = value.length > max;

    return (
        <div className="grid content-start gap-2">
            <div className="flex items-baseline justify-between gap-4">
                <Label htmlFor={id} className="font-semibold">
                    {label}
                </Label>
                <span
                    className={cn(
                        'text-xs tabular-nums',
                        over
                            ? 'font-semibold text-destructive'
                            : 'text-muted-foreground',
                    )}
                >
                    {value.length} / {max}
                </span>
            </div>
            {rows === 1 ? (
                <Input
                    id={id}
                    value={value}
                    aria-invalid={error || over ? true : undefined}
                    onChange={(event) => onChange(event.target.value)}
                />
            ) : (
                <textarea
                    id={id}
                    rows={rows}
                    value={value}
                    aria-invalid={error || over ? true : undefined}
                    onChange={(event) => onChange(event.target.value)}
                    className={fieldClass}
                />
            )}
            {hint && <p className="text-sm text-muted-foreground">{hint}</p>}
            <InputError
                className="font-semibold text-destructive"
                message={error}
            />
        </div>
    );
}

/** Title-and-text items: the four services (fixed) or the benefits (one to five). */
function ItemList({
    name,
    items,
    errors,
    fixed,
    min = 1,
    max,
    titleMax,
    textMax,
    noun,
    onChange,
}: {
    name: string;
    items: Item[];
    errors: Errors;
    fixed?: boolean;
    min?: number;
    max: number;
    titleMax: number;
    textMax: number;
    noun: string;
    onChange: (items: Item[]) => void;
}) {
    const set = (index: number, field: keyof Item, value: string) =>
        onChange(
            items.map((item, i) =>
                i === index ? { ...item, [field]: value } : item,
            ),
        );

    return (
        <div className="grid gap-4">
            <ol className="grid gap-4 @3xl:grid-cols-2">
                {items.map((item, i) => (
                    <li
                        key={i}
                        className="grid gap-4 rounded-control border border-border p-4"
                    >
                        <div className="flex items-center justify-between gap-2">
                            <p className="text-sm font-semibold text-muted-foreground">
                                {noun} {i + 1}
                            </p>
                            {!fixed && items.length > min && (
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    onClick={() =>
                                        onChange(
                                            items.filter((_, j) => j !== i),
                                        )
                                    }
                                >
                                    <X />
                                    Remove
                                </Button>
                            )}
                        </div>
                        <TextBox
                            id={`${name}-${i}-title`}
                            label="Name"
                            rows={1}
                            value={item.title}
                            max={titleMax}
                            error={errors[`${name}.${i}.title`]}
                            onChange={(value) => set(i, 'title', value)}
                        />
                        <TextBox
                            id={`${name}-${i}-text`}
                            label="Text"
                            value={item.text}
                            max={textMax}
                            error={errors[`${name}.${i}.text`]}
                            onChange={(value) => set(i, 'text', value)}
                        />
                    </li>
                ))}
            </ol>
            {!fixed && items.length < max && (
                <Button
                    type="button"
                    variant="outline"
                    className="justify-self-start"
                    onClick={() =>
                        onChange([...items, { title: '', text: '' }])
                    }
                >
                    <Plus />
                    Add a {noun.toLowerCase()}
                </Button>
            )}
            <InputError
                className="font-semibold text-destructive"
                message={errors[name]}
            />
        </div>
    );
}

/**
 * One section of the home page in its own card and form: saving it puts
 * just this section live and adds it to the change history.
 */
function SectionForm({
    section,
    onDirtyChange,
}: {
    section: SectionData;
    onDirtyChange: (key: string, dirty: boolean) => void;
}) {
    const form = useForm<Values>(section.values);
    const { data, setData, processing } = form;
    const errors = form.errors as Errors;
    // Only the plain-text fields go through here; lists have their own editors.
    const text = (field: string): string => {
        const value = data[field];

        return typeof value === 'string' ? value : '';
    };
    const anchor = ANCHORS[section.key] ?? '';

    useEffect(() => {
        onDirtyChange(section.key, form.isDirty);
    }, [form.isDirty, section.key, onDirtyChange]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update.url(section.key), {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
        });
    };

    // Built only for this card's section: the others' fields are not in its data.
    const fieldsFor = (key: string): ReactNode => {
        switch (key) {
            case 'home.hero':
                return (
                    <TextBox
                        id="hero-intro"
                        label="Opening paragraph"
                        value={text('intro')}
                        max={400}
                        rows={4}
                        error={errors.intro}
                        onChange={(value) => setData('intro', value)}
                    />
                );
            case 'home.services':
                return (
                    <>
                        <TextBox
                            id="services-intro"
                            label="Introduction"
                            value={text('intro')}
                            max={400}
                            error={errors.intro}
                            onChange={(value) => setData('intro', value)}
                        />
                        <ItemList
                            name="services"
                            noun="Service"
                            fixed
                            max={4}
                            titleMax={24}
                            textMax={200}
                            items={data.services as Item[]}
                            errors={errors}
                            onChange={(items) => setData('services', items)}
                        />
                    </>
                );
            case 'home.benefits':
                return (
                    <>
                        <TextBox
                            id="benefits-intro"
                            label="Introduction"
                            value={text('intro')}
                            max={400}
                            rows={4}
                            error={errors.intro}
                            onChange={(value) => setData('intro', value)}
                        />
                        <ItemList
                            name="benefits"
                            noun="Benefit"
                            max={5}
                            titleMax={24}
                            textMax={200}
                            items={data.benefits as Item[]}
                            errors={errors}
                            onChange={(items) => setData('benefits', items)}
                        />
                    </>
                );
            case 'home.categories':
                return (
                    <>
                        <TextBox
                            id="categories-intro"
                            label="Introduction"
                            value={text('intro')}
                            max={500}
                            rows={4}
                            error={errors.intro}
                            onChange={(value) => setData('intro', value)}
                        />
                        <TextBox
                            id="categories-institutional"
                            label="Institutional Members line"
                            value={text('institutional')}
                            max={200}
                            rows={2}
                            error={errors.institutional}
                            onChange={(value) =>
                                setData('institutional', value)
                            }
                        />
                    </>
                );
            case 'home.join':
                return (
                    <TextBox
                        id="join-headline"
                        label="Headline"
                        value={text('headline')}
                        max={100}
                        rows={2}
                        error={errors.headline}
                        onChange={(value) => setData('headline', value)}
                    />
                );
            case 'home.about':
                return (
                    <>
                        <TextBox
                            id="about-intro"
                            label="Introduction"
                            value={text('intro')}
                            max={800}
                            rows={6}
                            error={errors.intro}
                            onChange={(value) => setData('intro', value)}
                        />
                        <fieldset className="grid gap-2">
                            <legend className="mb-2 text-sm font-semibold">
                                Core values
                            </legend>
                            <div className="flex flex-wrap gap-2">
                                {(data.values as string[]).map((value, i) => (
                                    <div
                                        key={i}
                                        className="flex items-center gap-1"
                                    >
                                        <Input
                                            aria-label={`Core value ${i + 1}`}
                                            value={value}
                                            className="w-40"
                                            onChange={(event) =>
                                                setData(
                                                    'values',
                                                    (
                                                        data.values as string[]
                                                    ).map((v, j) =>
                                                        j === i
                                                            ? event.target.value
                                                            : v,
                                                    ),
                                                )
                                            }
                                        />
                                        {(data.values as string[]).length >
                                            1 && (
                                            <Button
                                                type="button"
                                                size="icon"
                                                variant="ghost"
                                                aria-label={`Remove ${value || `core value ${i + 1}`}`}
                                                onClick={() =>
                                                    setData(
                                                        'values',
                                                        (
                                                            data.values as string[]
                                                        ).filter(
                                                            (_, j) => j !== i,
                                                        ),
                                                    )
                                                }
                                            >
                                                <X />
                                            </Button>
                                        )}
                                    </div>
                                ))}
                                {(data.values as string[]).length < 4 && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            setData('values', [
                                                ...(data.values as string[]),
                                                '',
                                            ])
                                        }
                                    >
                                        <Plus />
                                        Add a value
                                    </Button>
                                )}
                            </div>
                            <p className="text-sm text-muted-foreground">
                                One word each, up to four. The page adds the
                                full stop.
                            </p>
                            <InputError
                                className="font-semibold text-destructive"
                                message={
                                    errors.values ??
                                    Object.entries(errors).find(([k]) =>
                                        k.startsWith('values.'),
                                    )?.[1]
                                }
                            />
                        </fieldset>
                        <div className="grid gap-6 @3xl:grid-cols-2">
                            <TextBox
                                id="about-vision"
                                label="Vision"
                                value={text('vision')}
                                max={200}
                                error={errors.vision}
                                onChange={(value) => setData('vision', value)}
                            />
                            <TextBox
                                id="about-mission"
                                label="Mission"
                                value={text('mission')}
                                max={300}
                                error={errors.mission}
                                onChange={(value) => setData('mission', value)}
                            />
                        </div>
                        <TextBox
                            id="about-recognition"
                            label="Registration line"
                            value={text('recognition')}
                            max={400}
                            rows={3}
                            error={errors.recognition}
                            onChange={(value) => setData('recognition', value)}
                        />
                    </>
                );
            default:
                return null;
        }
    };

    return (
        <form
            id={section.key.replace('.', '-')}
            onSubmit={submit}
            noValidate
            className="@container scroll-mt-6 rounded-card border border-border bg-background p-6 md:p-8"
        >
            <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-2">
                <div>
                    <h2 className="text-heading03 font-semibold">
                        {section.label}
                    </h2>
                    <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                        {section.hint}
                    </p>
                </div>
                <a
                    href={`/${anchor}`}
                    target="_blank"
                    rel="noreferrer"
                    className="inline-flex items-center gap-1.5 text-sm font-semibold underline decoration-border underline-offset-4 hover:decoration-current"
                >
                    See it on the site
                    <ExternalLink className="size-3.5" />
                </a>
            </div>

            <div className="mt-6 grid gap-6">{fieldsFor(section.key)}</div>

            <div className="mt-6 flex flex-wrap items-center gap-3 border-t border-border pt-5">
                <Button type="submit" disabled={processing || !form.isDirty}>
                    {processing ? 'Saving' : `Save ${section.label}`}
                </Button>
                {form.isDirty && !processing && (
                    <p className="text-sm text-muted-foreground">
                        Not saved yet
                    </p>
                )}
            </div>
        </form>
    );
}

/**
 * The home page's words, section by section. Editors and admins both use
 * it; each section saves on its own and goes live at once.
 */
export default function HomePageContent({
    sections,
}: {
    sections: SectionData[];
}) {
    const [dirty, setDirty] = useState<Record<string, boolean>>({});
    const onDirtyChange = useCallback(
        (key: string, isDirty: boolean) =>
            setDirty((current) =>
                current[key] === isDirty
                    ? current
                    : { ...current, [key]: isDirty },
            ),
        [],
    );
    const leaveDialog = useUnsavedChanges(
        Object.values(dirty).some(Boolean),
        'the home page',
    );

    return (
        <>
            <Head title="Home page" />
            {leaveDialog}
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="Home page"
                    description="The words on the home page, section by section. Saving a section puts it on the site straight away."
                />

                <div className="flex flex-col gap-8 xl:flex-row xl:items-start">
                    {/* The sections in page order; on wide screens this list stays in view. */}
                    <nav
                        aria-label="Home page sections"
                        className="rounded-card border border-border bg-background p-4 xl:sticky xl:top-6 xl:w-64 xl:shrink-0"
                    >
                        <ol className="grid gap-1 text-sm">
                            {sections.map((section) => (
                                <li key={section.key}>
                                    <a
                                        href={`#${section.key.replace('.', '-')}`}
                                        className="flex min-h-9 items-center justify-between gap-2 rounded-control px-2 hover:bg-accent"
                                    >
                                        {section.label}
                                        {dirty[section.key] && (
                                            <span
                                                className="size-2 rounded-full bg-brand-crimson"
                                                title="Not saved yet"
                                            />
                                        )}
                                    </a>
                                </li>
                            ))}
                        </ol>
                        <p className="mt-3 border-t border-border px-2 pt-3 text-sm text-muted-foreground">
                            Featured members, the tagline and contact details
                            have their own pages. Every save is in the{' '}
                            <Link
                                href={history()}
                                className="font-semibold text-foreground underline underline-offset-4"
                            >
                                change history
                            </Link>
                            .
                        </p>
                    </nav>

                    <div className="grid min-w-0 flex-1 gap-6">
                        {sections.map((section) => (
                            <SectionForm
                                key={section.key}
                                section={section}
                                onDirtyChange={onDirtyChange}
                            />
                        ))}
                    </div>
                </div>
            </div>
        </>
    );
}

HomePageContent.layout = {
    breadcrumbs: [{ title: 'Home page', href: '/admin/website/home' }],
};
