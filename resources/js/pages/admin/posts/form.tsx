import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import { ChevronDown, ImageIcon } from 'lucide-react';
import type { FormEvent, KeyboardEvent, ReactNode } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import RichTextEditor from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { showToast } from '@/components/ui/sonner';
import { useUnsavedChanges } from '@/hooks/use-unsaved-changes';
import { store as storeCategory } from '@/routes/admin/categories';
import { index, store, update } from '@/routes/admin/posts';
import { image as uploadImage } from '@/routes/admin/uploads';

type PostData = {
    id: number;
    title: string;
    slug: string;
    excerpt: string | null;
    content: string;
    author_name: string;
    post_category_id: number | null;
    reading_time: number;
    allow_comments: boolean;
    is_featured: boolean;
    is_editors_choice: boolean;
    is_popular: boolean;
    meta_title: string | null;
    meta_description: string | null;
    canonical_url: string | null;
    og_title: string | null;
    og_description: string | null;
    status: string;
    visibility: string;
    published_at: string | null;
    tags: string;
    featured_image_url: string | null;
    is_live: boolean;
};

type Props = {
    post: PostData | null;
    defaultAuthor: string;
    categories: { id: number; name: string }[];
};

const fieldClass = [
    'w-full rounded-control border border-input bg-background px-3 py-2 text-base shadow-none outline-none md:text-sm',
    'transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/20',
].join(' ');

const selectClass = `${fieldClass} h-10 py-0`;

// The search and sharing fields fold away; any error in them opens the group.
const SHARING_FIELDS = [
    'slug',
    'meta_title',
    'meta_description',
    'og_title',
    'og_description',
    'canonical_url',
] as const;

function Field({
    id,
    label,
    hint,
    error,
    labelHidden = false,
    children,
}: {
    id: string;
    label: string;
    hint?: string;
    error?: string;
    labelHidden?: boolean;
    children: ReactNode;
}) {
    return (
        <div className="grid content-start gap-2">
            <Label
                htmlFor={id}
                className={labelHidden ? 'sr-only' : 'font-semibold'}
            >
                {label}
            </Label>
            {children}
            {hint && <p className="text-sm text-muted-foreground">{hint}</p>}
            <InputError
                className="font-semibold text-destructive"
                message={error}
            />
        </div>
    );
}

function RailCard({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="rounded-card border border-border bg-background p-5">
            <h2 className="mb-4 font-semibold">{title}</h2>
            <div className="grid gap-5">{children}</div>
        </section>
    );
}

function Check({
    id,
    label,
    checked,
    onChange,
}: {
    id: string;
    label: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
}) {
    return (
        <label
            htmlFor={id}
            className="-mx-2 flex min-h-10 cursor-pointer items-center gap-3 rounded-control px-2 text-sm hover:bg-accent"
        >
            <input
                id={id}
                type="checkbox"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
                className="size-4 accent-primary"
            />
            {label}
        </label>
    );
}

function previewUrlFor(file: File | null): string | null {
    return file ? URL.createObjectURL(file) : null;
}

export default function PostForm({ post, defaultAuthor, categories }: Props) {
    const form = useForm({
        title: post?.title ?? '',
        slug: post?.slug ?? '',
        excerpt: post?.excerpt ?? '',
        content: post?.content ?? '',
        author_name: post?.author_name ?? defaultAuthor,
        post_category_id: post?.post_category_id ?? ('' as number | ''),
        tags: post?.tags ?? '',
        status: post?.status ?? 'draft',
        visibility: post?.visibility ?? 'public',
        published_at: post?.published_at ?? '',
        reading_time: post?.reading_time ?? 0,
        allow_comments: post?.allow_comments ?? true,
        is_featured: post?.is_featured ?? false,
        is_editors_choice: post?.is_editors_choice ?? false,
        is_popular: post?.is_popular ?? false,
        meta_title: post?.meta_title ?? '',
        meta_description: post?.meta_description ?? '',
        canonical_url: post?.canonical_url ?? '',
        og_title: post?.og_title ?? '',
        og_description: post?.og_description ?? '',
        featured_image_file: null as File | null,
        remove_featured_image: false,
    });
    const [newCategory, setNewCategory] = useState('');
    const [categoryError, setCategoryError] = useState('');
    const leaveDialog = useUnsavedChanges(form.isDirty);

    // The chosen main image is shown before saving.
    const chosenImageUrl = useMemo(
        () => previewUrlFor(form.data.featured_image_file),
        [form.data.featured_image_file],
    );
    useEffect(
        () => () => {
            if (chosenImageUrl) {
                URL.revokeObjectURL(chosenImageUrl);
            }
        },
        [chosenImageUrl],
    );
    const shownImageUrl =
        chosenImageUrl ??
        (form.data.remove_featured_image ? null : post?.featured_image_url);
    const { data, setData, errors } = form;
    const sharingHasError = SHARING_FIELDS.some((field) => errors[field]);
    const [sharingOpen, setSharingOpen] = useState(false);

    useEffect(() => {
        if (sharingHasError) {
            setSharingOpen(true);
        }
    }, [sharingHasError]);

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (post) {
            // Browsers send files only with POST, so the update is posted
            // and marked as a PUT for the server.
            form.transform((values) => ({ ...values, _method: 'put' }));
            form.post(update.url(post.id), {
                forceFormData: true,
                onSuccess: () => form.setDefaults(),
            });

            return;
        }

        form.post(store.url(), {
            forceFormData: true,
            onSuccess: () => form.setDefaults(),
        });
    };

    const addCategory = () => {
        setCategoryError('');

        if (newCategory.trim() === '') {
            setCategoryError('Type a name for the new category.');

            return;
        }

        router.post(
            storeCategory.url(),
            { name: newCategory },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['categories'],
                onSuccess: (page) => {
                    const id = (page.flash as { newCategoryId?: number })
                        .newCategoryId;

                    if (id) {
                        setData('post_category_id', id);
                    }

                    showToast(
                        'success',
                        `“${newCategory.trim()}” added and chosen.`,
                    );
                    setNewCategory('');
                },
                onError: (problems) =>
                    setCategoryError(
                        problems.name ?? 'The category could not be added.',
                    ),
            },
        );
    };

    // The button names what saving does with the post as it is set.
    const saveLabel =
        data.visibility === 'private' && data.status === 'published'
            ? 'Save for editors'
            : ({
                  draft: 'Save draft',
                  published: post?.is_live ? 'Update post' : 'Publish now',
                  scheduled: 'Schedule',
                  archived: 'Archive',
              }[data.status] ?? 'Save post');

    // Enter in a one-line box would save (and maybe publish) the whole post.
    const holdEnter = (event: KeyboardEvent<HTMLFormElement>) => {
        const target = event.target as HTMLElement;

        // Pop-ups (the link box) are outside the form on the page, and keep their own Enter.
        if (
            event.key === 'Enter' &&
            target.tagName === 'INPUT' &&
            event.currentTarget.contains(target)
        ) {
            event.preventDefault();
        }
    };

    const saveButton = (className?: string) => (
        <Button
            type="submit"
            size="lg"
            disabled={form.processing}
            className={className}
        >
            {form.processing ? 'Saving' : saveLabel}
        </Button>
    );

    return (
        <>
            <Head title={post ? 'Edit post' : 'Write a post'} />
            {leaveDialog}
            <form
                onSubmit={submit}
                onKeyDown={holdEnter}
                className="px-4 py-8 md:px-8 md:py-10 xl:px-12"
                noValidate
            >
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title={post ? 'Edit post' : 'Write a post'}
                        description={
                            post
                                ? post.is_live
                                    ? 'This post is on the site. Saving updates it straight away.'
                                    : 'Readers cannot see this post yet.'
                                : 'Save it as a draft and publish when it is ready.'
                        }
                    />
                    {post && (
                        <Button asChild variant="outline" className="mb-8">
                            <a
                                href={`/knowledge-base/${post.slug}`}
                                target="_blank"
                                rel="noreferrer"
                            >
                                View post
                            </a>
                        </Button>
                    )}
                </div>

                {/* The writing sheet on the left; on wide screens the settings rail stays in view beside it. */}
                <div className="flex flex-col gap-6 xl:flex-row xl:items-start">
                    <section className="@container min-w-0 flex-1 rounded-card border border-border bg-background p-5 md:p-8">
                        <div className="grid gap-8">
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="title"
                                    className="font-semibold text-muted-foreground"
                                >
                                    Title
                                </Label>
                                <textarea
                                    id="title"
                                    rows={1}
                                    value={data.title}
                                    onChange={(event) =>
                                        setData(
                                            'title',
                                            event.target.value.replace(
                                                /\n/g,
                                                ' ',
                                            ),
                                        )
                                    }
                                    onKeyDown={(event) => {
                                        if (event.key === 'Enter') {
                                            event.preventDefault();
                                        }
                                    }}
                                    required
                                    className="field-sizing-content w-full resize-none border-b-2 border-border bg-transparent pb-2 text-heading02 leading-tight font-bold outline-none focus-visible:border-ring"
                                />
                                <InputError
                                    className="font-semibold text-destructive"
                                    message={errors.title}
                                />
                            </div>

                            <Field
                                id="excerpt"
                                label="Summary"
                                hint="One or two sentences shown on listing pages. Left empty, the opening of the article is used."
                                error={errors.excerpt}
                            >
                                <textarea
                                    id="excerpt"
                                    rows={3}
                                    value={data.excerpt}
                                    onChange={(event) =>
                                        setData('excerpt', event.target.value)
                                    }
                                    className={`${fieldClass} text-base! md:text-lg!`}
                                />
                            </Field>

                            <div className="grid gap-2">
                                <Label
                                    htmlFor="content"
                                    className="font-semibold"
                                >
                                    Article
                                </Label>
                                <RichTextEditor
                                    id="content"
                                    value={data.content}
                                    onChange={(html) =>
                                        setData('content', html)
                                    }
                                    uploadUrl={uploadImage.url()}
                                />
                                <InputError
                                    className="font-semibold text-destructive"
                                    message={errors.content}
                                />
                            </div>
                        </div>

                        <details
                            open={sharingOpen}
                            onToggle={(event) =>
                                setSharingOpen(event.currentTarget.open)
                            }
                            className="group mt-8 border-t border-border pt-6"
                        >
                            <summary className="-mx-2 flex cursor-pointer list-none items-center justify-between gap-4 rounded-control px-2 py-2 hover:bg-accent focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none [&::-webkit-details-marker]:hidden">
                                <span className="text-heading03 font-semibold">
                                    Search and sharing
                                </span>
                                <ChevronDown
                                    aria-hidden
                                    className="size-5 text-muted-foreground transition-transform group-open:rotate-180"
                                />
                            </summary>

                            <div className="mt-6 grid gap-6 @2xl:grid-cols-2">
                                <Field
                                    id="slug"
                                    label="Address"
                                    hint="The last part of the link. Left empty, it is made from the title. Changing it breaks links people already shared."
                                    error={errors.slug}
                                >
                                    <Input
                                        id="slug"
                                        value={data.slug}
                                        onChange={(event) =>
                                            setData('slug', event.target.value)
                                        }
                                    />
                                </Field>
                                <Field
                                    id="canonical_url"
                                    label="Original address"
                                    hint="Only if this article was first published somewhere else."
                                    error={errors.canonical_url}
                                >
                                    <Input
                                        id="canonical_url"
                                        type="url"
                                        value={data.canonical_url}
                                        onChange={(event) =>
                                            setData(
                                                'canonical_url',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    id="meta_title"
                                    label="Search title"
                                    error={errors.meta_title}
                                >
                                    <Input
                                        id="meta_title"
                                        value={data.meta_title}
                                        onChange={(event) =>
                                            setData(
                                                'meta_title',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    id="og_title"
                                    label="Title when shared on social media"
                                    error={errors.og_title}
                                >
                                    <Input
                                        id="og_title"
                                        value={data.og_title}
                                        onChange={(event) =>
                                            setData(
                                                'og_title',
                                                event.target.value,
                                            )
                                        }
                                    />
                                </Field>
                                <Field
                                    id="meta_description"
                                    label="Search description"
                                    error={errors.meta_description}
                                >
                                    <textarea
                                        id="meta_description"
                                        rows={3}
                                        value={data.meta_description}
                                        onChange={(event) =>
                                            setData(
                                                'meta_description',
                                                event.target.value,
                                            )
                                        }
                                        className={fieldClass}
                                    />
                                </Field>
                                <Field
                                    id="og_description"
                                    label="Description when shared on social media"
                                    error={errors.og_description}
                                >
                                    <textarea
                                        id="og_description"
                                        rows={3}
                                        value={data.og_description}
                                        onChange={(event) =>
                                            setData(
                                                'og_description',
                                                event.target.value,
                                            )
                                        }
                                        className={fieldClass}
                                    />
                                </Field>
                            </div>
                        </details>
                    </section>

                    <aside className="grid content-start gap-6 lg:grid-cols-2 xl:sticky xl:top-6 xl:max-h-[calc(100svh-3rem)] xl:w-96 xl:shrink-0 xl:grid-cols-1 xl:overflow-y-auto xl:overscroll-contain">
                        <RailCard title="Publishing">
                            <Field
                                id="status"
                                label="Status"
                                error={errors.status}
                            >
                                <select
                                    id="status"
                                    value={data.status}
                                    onChange={(event) =>
                                        setData('status', event.target.value)
                                    }
                                    className={selectClass}
                                >
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                    <option value="scheduled">Scheduled</option>
                                    <option value="archived">Archived</option>
                                </select>
                            </Field>

                            <Field
                                id="published_at"
                                label="Publish date"
                                hint={
                                    data.status === 'scheduled'
                                        ? 'The post appears on the site at this time.'
                                        : data.status === 'published'
                                          ? 'Left empty, it is dated now. For a later date, choose Scheduled.'
                                          : 'When it goes out. Left empty, it is dated when published.'
                                }
                                error={errors.published_at}
                            >
                                <Input
                                    id="published_at"
                                    type="datetime-local"
                                    value={data.published_at}
                                    onChange={(event) =>
                                        setData(
                                            'published_at',
                                            event.target.value,
                                        )
                                    }
                                />
                            </Field>

                            <Field
                                id="visibility"
                                label="Who can read it"
                                hint={
                                    data.visibility === 'private'
                                        ? 'Readers never see it. Editors and admins can open it to check it.'
                                        : undefined
                                }
                                error={errors.visibility}
                            >
                                <select
                                    id="visibility"
                                    value={data.visibility}
                                    onChange={(event) =>
                                        setData(
                                            'visibility',
                                            event.target.value,
                                        )
                                    }
                                    className={selectClass}
                                >
                                    <option value="public">Everyone</option>
                                    <option value="private">
                                        Editors and admins only
                                    </option>
                                </select>
                            </Field>

                            {saveButton('w-full')}
                        </RailCard>

                        <RailCard title="Main image">
                            <Field
                                id="featured_image_file"
                                label="Main image"
                                hint="JPG, PNG, WebP or GIF, up to 5 MB."
                                error={errors.featured_image_file}
                                labelHidden
                            >
                                {shownImageUrl ? (
                                    <img
                                        src={shownImageUrl}
                                        alt={
                                            chosenImageUrl
                                                ? 'Chosen main image, not saved yet'
                                                : 'Current main image'
                                        }
                                        className="aspect-video w-full rounded-control object-cover ring-1 ring-border"
                                    />
                                ) : (
                                    <div
                                        aria-hidden
                                        className="flex aspect-video w-full items-center justify-center rounded-control bg-muted text-muted-foreground"
                                    >
                                        <ImageIcon className="size-8" />
                                    </div>
                                )}
                                <Input
                                    id="featured_image_file"
                                    type="file"
                                    accept="image/png,image/jpeg,image/webp,image/gif"
                                    onChange={(event) => {
                                        setData(
                                            'featured_image_file',
                                            event.target.files?.[0] ?? null,
                                        );
                                        setData('remove_featured_image', false);
                                    }}
                                    className="file:mr-3 file:font-semibold file:text-foreground"
                                />
                                {(chosenImageUrl ||
                                    (post?.featured_image_url &&
                                        !data.remove_featured_image)) && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        className="justify-self-start"
                                        onClick={() => {
                                            const input =
                                                document.getElementById(
                                                    'featured_image_file',
                                                ) as HTMLInputElement | null;

                                            if (input) {
                                                input.value = '';
                                            }

                                            setData(
                                                'featured_image_file',
                                                null,
                                            );
                                            setData(
                                                'remove_featured_image',
                                                Boolean(
                                                    post?.featured_image_url,
                                                ) && !chosenImageUrl,
                                            );
                                        }}
                                    >
                                        {chosenImageUrl
                                            ? 'Clear chosen image'
                                            : 'Remove image'}
                                    </Button>
                                )}
                                {data.remove_featured_image && (
                                    <p className="text-sm text-muted-foreground">
                                        The image is removed when you save.
                                    </p>
                                )}
                            </Field>
                        </RailCard>

                        <RailCard title="Details">
                            <Field
                                id="post_category_id"
                                label="Category"
                                error={errors.post_category_id}
                            >
                                <select
                                    id="post_category_id"
                                    value={data.post_category_id}
                                    onChange={(event) =>
                                        setData(
                                            'post_category_id',
                                            event.target.value === ''
                                                ? ''
                                                : Number(event.target.value),
                                        )
                                    }
                                    className={`${selectClass} capitalize`}
                                >
                                    <option value="">Choose a category</option>
                                    {categories.map((category) => (
                                        <option
                                            key={category.id}
                                            value={category.id}
                                        >
                                            {category.name}
                                        </option>
                                    ))}
                                </select>
                                <div className="flex gap-2">
                                    <Input
                                        aria-label="New category name"
                                        placeholder="New category"
                                        value={newCategory}
                                        onChange={(event) =>
                                            setNewCategory(event.target.value)
                                        }
                                        onKeyDown={(event) => {
                                            if (event.key === 'Enter') {
                                                event.preventDefault();
                                                addCategory();
                                            }
                                        }}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={addCategory}
                                    >
                                        Add
                                    </Button>
                                </div>
                                <InputError
                                    className="font-semibold text-destructive"
                                    message={categoryError}
                                />
                            </Field>

                            <Field
                                id="tags"
                                label="Tags"
                                hint="Separate tags with commas."
                                error={errors.tags}
                            >
                                <Input
                                    id="tags"
                                    value={data.tags}
                                    onChange={(event) =>
                                        setData('tags', event.target.value)
                                    }
                                />
                            </Field>

                            <Field
                                id="author_name"
                                label="Author shown on the post"
                                error={errors.author_name}
                            >
                                <Input
                                    id="author_name"
                                    value={data.author_name}
                                    onChange={(event) =>
                                        setData(
                                            'author_name',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                            </Field>

                            <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-1">
                                <Field
                                    id="reading_time"
                                    label="Reading time in minutes"
                                    hint="Left at 0, it is worked out from the length."
                                    error={errors.reading_time}
                                >
                                    <Input
                                        id="reading_time"
                                        type="number"
                                        min={0}
                                        value={data.reading_time}
                                        onChange={(event) =>
                                            setData(
                                                'reading_time',
                                                Number(event.target.value),
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </RailCard>

                        <RailCard title="Where it shows">
                            <fieldset className="grid">
                                <legend className="sr-only">
                                    Where it shows
                                </legend>
                                <Check
                                    id="is_featured"
                                    label="Featured Posts"
                                    checked={data.is_featured}
                                    onChange={(value) =>
                                        setData('is_featured', value)
                                    }
                                />
                                <Check
                                    id="is_editors_choice"
                                    label="Editor’s Choice"
                                    checked={data.is_editors_choice}
                                    onChange={(value) =>
                                        setData('is_editors_choice', value)
                                    }
                                />
                                <Check
                                    id="is_popular"
                                    label="Popular Posts"
                                    checked={data.is_popular}
                                    onChange={(value) =>
                                        setData('is_popular', value)
                                    }
                                />
                                <Check
                                    id="allow_comments"
                                    label="Readers can comment"
                                    checked={data.allow_comments}
                                    onChange={(value) =>
                                        setData('allow_comments', value)
                                    }
                                />
                            </fieldset>
                        </RailCard>
                    </aside>
                </div>

                <div className="mt-8 flex flex-wrap gap-2">
                    {saveButton()}
                    <Button asChild variant="outline" size="lg">
                        <Link href={index()}>Back to posts</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

PostForm.layout = {
    breadcrumbs: [{ title: 'Posts', href: index() }],
};
