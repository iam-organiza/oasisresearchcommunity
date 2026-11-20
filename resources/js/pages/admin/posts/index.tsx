import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
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

    const remove = (post: PostRow) => {
        if (
            window.confirm(
                `Delete “${post.title}” and its comments? This cannot be undone.`,
            )
        ) {
            router.delete(destroy.url(post.id), { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Posts" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Posts"
                        description="Knowledge Base articles, newest first."
                    />
                    <Button asChild>
                        <Link href={create()}>Write a post</Link>
                    </Button>
                </div>

                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(event) => {
                        event.preventDefault();
                        applyFilters({ search, status });
                    }}
                >
                    <div className="grid gap-2">
                        <Label htmlFor="search">Search titles</Label>
                        <Input
                            id="search"
                            type="search"
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            className="w-64 max-w-full"
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="status">Status</Label>
                        <select
                            id="status"
                            value={status}
                            onChange={(event) => {
                                setStatus(event.target.value);
                                applyFilters({
                                    search,
                                    status: event.target.value,
                                });
                            }}
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm capitalize"
                        >
                            <option value="">All</option>
                            {STATUSES.map((value) => (
                                <option key={value} value={value}>
                                    {value}
                                </option>
                            ))}
                        </select>
                    </div>
                    <Button type="submit" variant="outline">
                        Search
                    </Button>
                </form>

                {posts.data.length === 0 ? (
                    <p className="rounded-xl border border-border p-8 text-center text-muted-foreground">
                        No posts match. Clear the filters, or write the first
                        one.
                    </p>
                ) : (
                    <div className="overflow-x-auto rounded-xl border border-border">
                        <table className="w-full min-w-[720px] text-left text-sm">
                            <thead className="bg-muted text-muted-foreground">
                                <tr>
                                    <th className="px-4 py-3 font-medium">
                                        Title
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Category
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Date
                                    </th>
                                    <th className="px-4 py-3 font-medium">
                                        Comments
                                    </th>
                                    <th className="px-4 py-3">
                                        <span className="sr-only">Actions</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {posts.data.map((post) => (
                                    <tr key={post.id}>
                                        <td className="px-4 py-3">
                                            <Link
                                                href={edit(post.id)}
                                                className="font-medium hover:underline"
                                            >
                                                {post.title}
                                            </Link>
                                            <div className="text-muted-foreground">
                                                By {post.author_name}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <Badge
                                                variant={
                                                    post.is_live
                                                        ? 'default'
                                                        : 'secondary'
                                                }
                                                className="capitalize"
                                            >
                                                {post.status}
                                            </Badge>
                                            {post.visibility === 'private' && (
                                                <div className="mt-1 text-muted-foreground">
                                                    Private
                                                </div>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 capitalize">
                                            {post.category ?? 'None'}
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            {post.date}
                                        </td>
                                        <td className="px-4 py-3">
                                            {post.comments_count}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex justify-end gap-2">
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <a
                                                        href={`/knowledge-base/${post.slug}`}
                                                        target="_blank"
                                                        rel="noreferrer"
                                                    >
                                                        View
                                                    </a>
                                                </Button>
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    variant="outline"
                                                >
                                                    <Link href={edit(post.id)}>
                                                        Edit
                                                    </Link>
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="destructive"
                                                    onClick={() => remove(post)}
                                                >
                                                    Delete
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <div className="flex flex-wrap items-center justify-between gap-3 text-sm text-muted-foreground">
                    <span>
                        {posts.total === 0
                            ? 'No posts'
                            : `Showing ${posts.from} to ${posts.to} of ${posts.total}`}
                    </span>
                    <div className="flex gap-2">
                        {posts.prev_page_url && (
                            <Button asChild variant="outline" size="sm">
                                <Link href={posts.prev_page_url}>Previous</Link>
                            </Button>
                        )}
                        {posts.next_page_url && (
                            <Button asChild variant="outline" size="sm">
                                <Link href={posts.next_page_url}>Next</Link>
                            </Button>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

PostsIndex.layout = {
    breadcrumbs: [{ title: 'Posts', href: index() }],
};
