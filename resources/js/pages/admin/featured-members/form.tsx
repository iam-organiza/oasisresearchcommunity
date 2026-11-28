import { Head, Link, useForm } from '@inertiajs/react';
import { ImageUp } from 'lucide-react';
import { useEffect, useMemo } from 'react';
import type { FormEvent, ReactNode } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { index, store, update } from '@/routes/admin/featured-members';

type MemberData = {
    id: number;
    title: string | null;
    first_name: string;
    middle_name: string | null;
    last_name: string;
    position: string | null;
    speciality: string | null;
    job_description: string | null;
    avatar_url: string | null;
    is_visible: boolean;
};

type TextField =
    | 'title'
    | 'first_name'
    | 'middle_name'
    | 'last_name'
    | 'position'
    | 'speciality';

const textareaClass = [
    'w-full rounded-control border border-input bg-background px-3 py-2 text-base shadow-none outline-none md:text-sm',
    'transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/20',
    'aria-invalid:border-destructive aria-invalid:ring-destructive/20',
].join(' ');

const initials = (first: string, last: string): string =>
    [first, last].map((part) => part.trim()[0]?.toUpperCase() ?? '').join('');

function FormCard({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="@container rounded-card border border-border bg-background p-6 md:p-8">
            <h2 className="mb-6 text-heading03 font-semibold">{title}</h2>
            <div className="grid gap-6">{children}</div>
        </section>
    );
}

export default function FeaturedMemberForm({
    member,
}: {
    member: MemberData | null;
}) {
    const form = useForm({
        title: member?.title ?? '',
        first_name: member?.first_name ?? '',
        middle_name: member?.middle_name ?? '',
        last_name: member?.last_name ?? '',
        position: member?.position ?? '',
        speciality: member?.speciality ?? '',
        job_description: member?.job_description ?? '',
        avatar_file: null as File | null,
        is_visible: member?.is_visible ?? true,
    });
    const { data, setData, errors } = form;
    const pageTitle = member ? 'Edit featured member' : 'Add a member';

    // A chosen photo is shown straight away; its temporary address is
    // released when it is replaced or the page closes.
    const chosenPhoto = useMemo(
        () => (data.avatar_file ? URL.createObjectURL(data.avatar_file) : null),
        [data.avatar_file],
    );

    useEffect(
        () => () => {
            if (chosenPhoto) {
                URL.revokeObjectURL(chosenPhoto);
            }
        },
        [chosenPhoto],
    );

    const photo = chosenPhoto ?? member?.avatar_url ?? null;

    const previewName = useMemo(
        () =>
            [data.title, data.first_name, data.middle_name, data.last_name]
                .map((part) => part.trim())
                .filter(Boolean)
                .join(' '),
        [data.title, data.first_name, data.middle_name, data.last_name],
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (member) {
            // Browsers send files only with POST, so the update is posted
            // and marked as a PUT for the server.
            form.transform((values) => ({ ...values, _method: 'put' }));
            form.post(update.url(member.id), { forceFormData: true });

            return;
        }

        form.post(store.url(), { forceFormData: true });
    };

    const text = (id: TextField, label: string, hint?: string) => (
        <div className="grid content-start gap-2">
            <Label htmlFor={id} className="font-semibold">
                {label}
            </Label>
            <Input
                id={id}
                value={data[id]}
                aria-invalid={errors[id] ? true : undefined}
                onChange={(event) => setData(id, event.target.value)}
            />
            {hint && <p className="text-sm text-muted-foreground">{hint}</p>}
            <InputError
                className="font-semibold text-destructive"
                message={errors[id]}
            />
        </div>
    );

    return (
        <>
            <Head title={pageTitle} />
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title={pageTitle}
                    description="Shown on the landing page as soon as it is saved."
                />

                <div className="mt-8 flex flex-col gap-8 lg:flex-row lg:items-start">
                    <form
                        onSubmit={submit}
                        className="@container/form min-w-0 flex-1"
                        noValidate
                    >
                        {/* On very wide screens the three cards sit side by side. */}
                        <div className="grid items-start gap-6 @7xl/form:grid-cols-3">
                            <FormCard title="Name">
                                <div className="grid gap-6 @xl:grid-cols-[8rem_minmax(0,1fr)]">
                                    {text('title', 'Title', 'Dr., Prof., Mr.')}
                                    {text('first_name', 'First name')}
                                </div>
                                <div className="grid gap-6 @xl:grid-cols-2">
                                    {text('middle_name', 'Middle name')}
                                    {text('last_name', 'Last name')}
                                </div>
                            </FormCard>

                            <FormCard title="Role">
                                <div className="grid gap-6 @xl:grid-cols-2">
                                    {text(
                                        'position',
                                        'Membership grade',
                                        'For example, Fellow.',
                                    )}
                                    {text('speciality', 'Speciality')}
                                </div>

                                <div className="grid gap-2">
                                    <Label
                                        htmlFor="job_description"
                                        className="font-semibold"
                                    >
                                        Roles and affiliations
                                    </Label>
                                    <textarea
                                        id="job_description"
                                        rows={5}
                                        value={data.job_description}
                                        aria-invalid={
                                            errors.job_description
                                                ? true
                                                : undefined
                                        }
                                        onChange={(event) =>
                                            setData(
                                                'job_description',
                                                event.target.value,
                                            )
                                        }
                                        className={textareaClass}
                                    />
                                    <p className="text-sm text-muted-foreground">
                                        Put each role on its own line.
                                    </p>
                                    <InputError
                                        className="font-semibold text-destructive"
                                        message={errors.job_description}
                                    />
                                </div>
                            </FormCard>

                            <FormCard title="Photo">
                                <div className="grid gap-2">
                                    <Label
                                        htmlFor="avatar_file"
                                        className="sr-only"
                                    >
                                        Photo
                                    </Label>
                                    {/* The real file input covers the whole zone, so clicking
                                    or dropping a file onto it both work. */}
                                    <div
                                        className={[
                                            'relative flex items-center gap-5 rounded-card border-2 border-dashed p-4',
                                            'transition-colors hover:border-foreground/40 hover:bg-muted/50',
                                            'has-focus-visible:border-ring has-focus-visible:ring-3 has-focus-visible:ring-ring/20',
                                            errors.avatar_file
                                                ? 'border-destructive'
                                                : 'border-border',
                                        ].join(' ')}
                                    >
                                        {photo ? (
                                            <img
                                                src={photo}
                                                alt=""
                                                className="size-20 shrink-0 rounded-control object-cover object-top"
                                            />
                                        ) : (
                                            <span className="flex size-20 shrink-0 items-center justify-center rounded-control bg-muted text-muted-foreground">
                                                <ImageUp className="size-7" />
                                            </span>
                                        )}
                                        <div className="min-w-0">
                                            <p className="font-semibold">
                                                {data.avatar_file
                                                    ? 'Choose a different photo'
                                                    : 'Choose or drop a photo'}
                                            </p>
                                            <p className="truncate text-sm text-muted-foreground">
                                                {data.avatar_file?.name ??
                                                    (member?.avatar_url
                                                        ? 'Current photo'
                                                        : 'No photo yet')}
                                            </p>
                                        </div>
                                        <input
                                            id="avatar_file"
                                            type="file"
                                            accept="image/png,image/jpeg,image/webp"
                                            aria-invalid={
                                                errors.avatar_file
                                                    ? true
                                                    : undefined
                                            }
                                            className="absolute inset-0 size-full cursor-pointer opacity-0"
                                            onChange={(event) =>
                                                setData(
                                                    'avatar_file',
                                                    event.target.files?.[0] ??
                                                        null,
                                                )
                                            }
                                        />
                                    </div>
                                    <p className="text-sm text-muted-foreground">
                                        A square JPG, PNG or WebP, up to 5 MB.
                                        {member &&
                                            ' Leave empty to keep the current photo.'}
                                    </p>
                                    <InputError
                                        className="font-semibold text-destructive"
                                        message={errors.avatar_file}
                                    />
                                </div>
                            </FormCard>

                            <FormCard title="On the home page">
                                <label
                                    htmlFor="is_visible"
                                    className="-mx-2 flex min-h-10 cursor-pointer items-start gap-3 rounded-control px-2 py-2 hover:bg-accent"
                                >
                                    <input
                                        id="is_visible"
                                        type="checkbox"
                                        checked={data.is_visible}
                                        onChange={(event) =>
                                            setData(
                                                'is_visible',
                                                event.target.checked,
                                            )
                                        }
                                        className="mt-0.5 size-4 accent-primary"
                                    />
                                    <span>
                                        <span className="font-semibold">
                                            Show under Featured Members
                                        </span>
                                        <span className="block text-sm text-muted-foreground">
                                            Untick to take them off the home
                                            page and keep everything here.
                                        </span>
                                    </span>
                                </label>
                            </FormCard>

                            <div className="flex flex-wrap gap-3 @7xl/form:col-span-3">
                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={form.processing}
                                >
                                    {form.processing ? 'Saving' : 'Save member'}
                                </Button>
                                <Button asChild size="lg" variant="outline">
                                    <Link href={index()}>
                                        Back to featured members
                                    </Link>
                                </Button>
                            </div>
                        </div>
                    </form>

                    {/* The same portrait card as the featured members list, updated as you type.
                        On narrow screens it sits above the form as a short strip. */}
                    <aside
                        aria-label="Preview"
                        className="order-first lg:sticky lg:top-6 lg:order-none lg:w-64 lg:shrink-0 xl:w-80"
                    >
                        <p className="mb-3 text-sm font-semibold text-muted-foreground">
                            Preview
                        </p>
                        <div className="grid grid-cols-[6rem_minmax(0,1fr)] overflow-clip rounded-card border border-border bg-background lg:grid-cols-1">
                            {photo ? (
                                <img
                                    src={photo}
                                    alt=""
                                    className="aspect-square size-full object-cover object-top"
                                />
                            ) : (
                                <div
                                    aria-hidden
                                    className="flex aspect-square w-full items-center justify-center bg-brand-navy text-heading03 font-bold text-white lg:text-heading02 dark:bg-brand-night-raised"
                                >
                                    {initials(data.first_name, data.last_name)}
                                </div>
                            )}

                            <div className="min-w-0 p-4 lg:p-5">
                                <p
                                    className={[
                                        'text-lg leading-snug font-semibold',
                                        previewName
                                            ? ''
                                            : 'text-muted-foreground',
                                    ].join(' ')}
                                >
                                    {previewName || pageTitle}
                                </p>
                                {data.position.trim() && (
                                    <p className="mt-0.5 text-sm font-semibold text-brand-crimson-strong dark:text-brand-crimson">
                                        {data.position}
                                    </p>
                                )}
                                {data.job_description.trim() && (
                                    <p className="mt-2 line-clamp-3 text-sm whitespace-pre-line text-muted-foreground lg:line-clamp-none">
                                        {data.job_description}
                                    </p>
                                )}
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </>
    );
}

FeaturedMemberForm.layout = {
    breadcrumbs: [{ title: 'Featured members', href: index() }],
};
