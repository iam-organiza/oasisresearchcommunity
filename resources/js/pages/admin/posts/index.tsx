import { Head, Link, router } from '@inertiajs/react';
import { ExternalLink, Lock, Pencil, Search, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { useConfirm } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { create, destroy, edit, index } from '@/routes/admin/posts';

type PostRow = {
    id: number;
    title: string;
    slug: string;
    status: string;
    visibility: string;
    category: string | null;
    author_name: string;
    comments_count: number;
    is_live: boolean;
    date: string | null;
};

type Paginated<T> = {
    data: T[];
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
    from: number | null;
    to: number | null;
};

const STATUSES = ['draft', 'published', 'scheduled', 'archived'];

// A live post is solid navy; the rest are quieter so live work stands out in the list.
const statusTone = (post: PostRow): string => {
    if (post.is_live) {
        return 'bg-primary text-primary-foreground';
    }

    if (post.status === 'scheduled') {
        return 'text-brand-crimson-strong ring-1 ring-brand-crimson-strong/40 ring-inset dark:text-brand-crimson';
    }

    if (post.status === 'archived') {
        return 'text-muted-foreground ring-1 ring-border ring-inset';
    }

    return 'bg-muted text-muted-foreground';
};

export default function PostsIndex({
    posts,
    filters,
}: {
    posts: Paginated<PostRow>;
    filters: { search: string; status: string };
}) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState(filters.status);

    const applyFilters = (next: { search: string; status: string }) => {
        router.get(
            index.url(),
            {
                search: next.search || undefined,
                status: next.status || undefined,
            },
            { preserveState: true, replace: true },
        );
    };

    const { confirm, dialog } = useConfirm();
    const remove = async (post: PostRow) => {
        const yes = await confirm({
            title: `Delete “${post.title}”?`,
            description:
                'The post and all its comments are deleted. This cannot be undone.',
            confirmLabel: 'Delete post',
            cancelLabel: 'Keep it',
            tone: 'danger',
        });
        if (yes) {
            router.delete(destroy.url(post.id), { preserveScroll: true });
        }
    };

    const chooseStatus = (value: string) => {
        setStatus(value);
        applyFilters({ search, status: value });
    };

    return (
        <>
            <Head title="Posts" />
            {dialog}
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Posts"
                        description="Knowledge Base articles, newest first."
                    />
                    <Button asChild size="lg" className="mb-8">
                        <Link href={create()}>Write a post</Link>
                    </Button>
                </div>

                <section className="@container rounded-card border border-border bg-background">
                    {/* Filters: status chips, then title search. Rows and filters go side by side only once the card itself is wide enough. */}
                    <div className="flex flex-col gap-4 border-b border-border p-4 md:p-6 @3xl:flex-row @3xl:items-center @3xl:justify-between">
                        <div
                            role="group"
                            aria-label="Status"
                            className="flex flex-wrap gap-2"
                        >
                            {['', ...STATUSES].map((value) => {
                                const chosen = status === value;

                                return (
                                    <button
                                        key={value || 'all'}
                                        type="button"
                                        aria-pressed={chosen}
                                        onClick={() => chooseStatus(value)}
                                        className={cn(
                                            'h-9 rounded-full px-4 text-sm font-semibold capitalize transition-colors',
                                            'focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                                            chosen
                                                ? 'bg-primary text-primary-foreground'
                                                : 'text-muted-foreground ring-1 ring-border ring-inset hover:bg-accent hover:text-foreground',
                                        )}
                                    >
                                        {value || 'All'}
                                    </button>
                                );
                            })}
                        </div>

                        <form
                            role="search"
                            className="flex gap-2"
                            onSubmit={(event) => {
                                event.preventDefault();
                                applyFilters({ search, status });
                            }}
                        >
                            <Label htmlFor="search" className="sr-only">
                                Search titles
                            </Label>
                            <div className="relative flex-1 @3xl:w-72 @3xl:flex-none">
                                <Search
                                    aria-hidden
                                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
                                />
                                <Input
                                    id="search"
                                    type="search"
                                    placeholder="Search titles"
                                    value={search}
                                    onChange={(event) =>
                                        setSearch(event.target.value)
                                    }
                                    className="pl-9"
                                />
                            </div>
                            <Button type="submit" variant="outline">
                                Search
                            </Button>
                        </form>
                    </div>

                    {posts.data.length === 0 ? (
                        <p className="p-10 text-center text-muted-foreground">
                            No posts match. Clear the filters, or write the
                            first one.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {posts.data.map((post) => (
                                <li
                                    key={post.id}
                                    className="flex flex-col gap-3 p-4 md:px-6 @2xl:flex-row @2xl:items-center @2xl:gap-6"
                                >
                                    <div className="min-w-0 flex-1">
                                        <div className="mb-1.5 flex flex-wrap items-center gap-2">
                                            <span
                                                className={cn(
                                                    'rounded-full px-2.5 py-0.5 text-xs font-semibold capitalize',
                                                    statusTone(post),
                                                )}
                                            >
                                                {post.status}
                                            </span>
                                            {post.visibility === 'private' && (
                                                <span className="flex items-center gap-1 text-xs font-semibold text-muted-foreground">
                                                    <Lock className="size-3" />
                                                    Editors and admins only
                                                </span>
                                            )}
                                        </div>

                                        <Link
                                            href={edit(post.id)}
                                            className="text-lg leading-snug font-semibold text-pretty underline-offset-4 hover:underline"
                                        >
                                            {post.title}
                                        </Link>

                                        <div className="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-muted-foreground">
                                            <span>By {post.author_name}</span>
                                            <span className="capitalize">
                                                {post.category ?? 'None'}
                                            </span>
                                            {post.date && (
                                                <span className="whitespace-nowrap">
                                                    {post.date}
                                                </span>
                                            )}
                                            <span className="tabular-nums">
                                                {post.comments_count}{' '}
                                                {post.comments_count === 1
                                                    ? 'comment'
                                                    : 'comments'}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="-ml-2 flex shrink-0 gap-1 @2xl:ml-0">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <a
                                                href={`/knowledge-base/${post.slug}`}
                                                target="_blank"
                                                rel="noreferrer"
                                            >
                                                <ExternalLink />
                                                View
                                            </a>
                                        </Button>
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="ghost"
                                        >
                                            <Link href={edit(post.id)}>
                                                <Pencil />
                                                Edit
                                            </Link>
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                            onClick={() => remove(post)}
                                        >
                                            <Trash2 />
                                            Delete
                                        </Button>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}

                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-3 text-sm text-muted-foreground md:px-6">
                        <span className="tabular-nums">
                            {posts.total === 0
                                ? 'No posts'
                                : `Showing ${posts.from} to ${posts.to} of ${posts.total}`}
                        </span>
                        <div className="flex gap-2">
                            {posts.prev_page_url && (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={posts.prev_page_url}>
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {posts.next_page_url && (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={posts.next_page_url}>Next</Link>
                                </Button>
                            )}
                        </div>
                    </div>
                </section>
            </div>
        </>
    );
}

PostsIndex.layout = {
    breadcrumbs: [{ title: 'Posts', href: index() }],
};
