import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import RichTextEditor from '@/components/rich-text-editor';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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
    content_type: string;
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

const fieldClass =
    'w-full rounded-md border border-input bg-background px-3 py-2 text-sm';

function Field({
    id,
    label,
    hint,
    error,
    children,
}: {
    id: string;
    label: string;
    hint?: string;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && <p className="text-sm text-muted-foreground">{hint}</p>}
            <InputError message={error} />
        </div>
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
        <label htmlFor={id} className="flex min-h-9 items-center gap-3 text-sm">
            <input
                id={id}
                type="checkbox"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
                className="size-4 accent-[var(--primary)]"
            />
            {label}
        </label>
    );
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
        content_type: post?.content_type ?? 'text',
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
    });
    const [newCategory, setNewCategory] = useState('');
    const { data, setData, errors } = form;

    const submit = (event: FormEvent) => {
        event.preventDefault();

        if (post) {
            // Browsers send files only with POST, so the update is posted
            // and marked as a PUT for the server.
            form.transform((values) => ({ ...values, _method: 'put' }));
            form.post(update.url(post.id), { forceFormData: true });

            return;
        }

        form.post(store.url(), { forceFormData: true });
    };

    const addCategory = () => {
        if (newCategory.trim() === '') {
            return;
        }

        router.post(
            storeCategory.url(),
            { name: newCategory },
            {
                preserveState: true,
                preserveScroll: true,
                only: ['categories'],
                onSuccess: () => setNewCategory(''),
            },
        );
    };

    return (
        <>
            <Head title={post ? 'Edit post' : 'Write a post'} />
            <form
                onSubmit={submit}
                className="flex flex-col gap-8 p-4 md:p-6"
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
                    <div className="flex gap-2">
                        {post && (
                            <Button asChild variant="outline">
                                <a
                                    href={`/knowledge-base/${post.slug}`}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    View post
                                </a>
                            </Button>
                        )}
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? 'Saving' : 'Save post'}
                        </Button>
                    </div>
                </div>

                <div className="grid gap-8 lg:grid-cols-[minmax(0,1fr)_320px]">
                    <div className="grid content-start gap-6">
                        <Field id="title" label="Title" error={errors.title}>
                            <Input
                                id="title"
                                value={data.title}
                                onChange={(event) =>
                                    setData('title', event.target.value)
                                }
                                required
                            />
                        </Field>

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
                                className={fieldClass}
                            />
                        </Field>

                        <div className="grid gap-2">
                            <Label htmlFor="content">Article</Label>
                            <RichTextEditor
                                id="content"
                                value={data.content}
                                onChange={(html) => setData('content', html)}
                                uploadUrl={uploadImage.url()}
                            />
                            <InputError message={errors.content} />
                        </div>

                        <fieldset className="grid gap-5 rounded-xl border border-border p-5">
                            <legend className="px-2 font-medium">
                                Search and sharing
                            </legend>
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
                                id="meta_description"
                                label="Search description"
                                error={errors.meta_description}
                            >
                                <textarea
                                    id="meta_description"
                                    rows={2}
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
                                id="og_title"
                                label="Title when shared on social media"
                                error={errors.og_title}
                            >
                                <Input
                                    id="og_title"
                                    value={data.og_title}
                                    onChange={(event) =>
                                        setData('og_title', event.target.value)
                                    }
                                />
                            </Field>
                            <Field
                                id="og_description"
                                label="Description when shared on social media"
                                error={errors.og_description}
                            >
                                <textarea
                                    id="og_description"
                                    rows={2}
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
                        </fieldset>
                    </div>

                    <div className="grid content-start gap-6">
                        <Field id="status" label="Status" error={errors.status}>
                            <select
                                id="status"
                                value={data.status}
                                onChange={(event) =>
                                    setData('status', event.target.value)
                                }
                                className={fieldClass}
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
                                    : 'Left empty, a published post is dated now.'
                            }
                            error={errors.published_at}
                        >
                            <Input
                                id="published_at"
                                type="datetime-local"
                                value={data.published_at}
                                onChange={(event) =>
                                    setData('published_at', event.target.value)
                                }
                            />
                        </Field>

                        <Field
                            id="visibility"
                            label="Who can read it"
                            error={errors.visibility}
                        >
                            <select
                                id="visibility"
                                value={data.visibility}
                                onChange={(event) =>
                                    setData('visibility', event.target.value)
                                }
                                className={fieldClass}
                            >
                                <option value="public">Everyone</option>
                                <option value="private">Staff only</option>
                            </select>
                        </Field>

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
                                className={`${fieldClass} capitalize`}
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
                                    setData('author_name', event.target.value)
                                }
                                required
                            />
                        </Field>

                        <Field
                            id="featured_image_file"
                            label="Main image"
                            hint="JPG, PNG, WebP or GIF, up to 5 MB."
                            error={errors.featured_image_file}
                        >
                            {post?.featured_image_url && (
                                <img
                                    src={post.featured_image_url}
                                    alt="Current main image"
                                    className="aspect-video w-full rounded-lg object-cover"
                                />
                            )}
                            <Input
                                id="featured_image_file"
                                type="file"
                                accept="image/png,image/jpeg,image/webp,image/gif"
                                onChange={(event) =>
                                    setData(
                                        'featured_image_file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </Field>

                        <Field
                            id="content_type"
                            label="Kind of post"
                            error={errors.content_type}
                        >
                            <select
                                id="content_type"
                                value={data.content_type}
                                onChange={(event) =>
                                    setData('content_type', event.target.value)
                                }
                                className={fieldClass}
                            >
                                <option value="text">Article</option>
                                <option value="video">Video</option>
                            </select>
                        </Field>

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

                        <fieldset className="grid gap-1 rounded-xl border border-border p-4">
                            <legend className="px-2 font-medium">
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
                    </div>
                </div>

                <div className="flex gap-2">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Saving' : 'Save post'}
                    </Button>
                    <Button asChild variant="outline">
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
