import { Head, Link, router } from '@inertiajs/react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { destroy, index, update } from '@/routes/admin/comments';

type CommentRow = {
    id: number;
    author_name: string;
    author_email: string;
    content: string;
    status: string;
    is_reply: boolean;
    date: string | null;
    post_title: string;
    post_slug: string;
};

type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

const TABS = [
    { value: 'pending', label: 'Waiting' },
    { value: 'approved', label: 'Approved' },
    { value: 'rejected', label: 'Rejected' },
];

const EMPTY: Record<string, string> = {
    pending: 'No comments are waiting for a decision.',
    approved: 'No comments have been approved yet.',
    rejected: 'No comments have been rejected.',
};

export default function CommentsIndex({
    comments,
    status,
    counts,
}: {
    comments: Paginated<CommentRow>;
    status: string;
    counts: Record<string, number>;
}) {
    const setStatus = (comment: CommentRow, next: string) => {
        router.patch(
            update.url(comment.id),
            { status: next },
            { preserveScroll: true },
        );
    };

    const remove = (comment: CommentRow) => {
        if (
            window.confirm(
                `Delete the comment by ${comment.author_name}? Replies to it are deleted too.`,
            )
        ) {
            router.delete(destroy.url(comment.id), { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Comments" />
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <Heading
                    title="Comments"
                    description="Readers’ comments appear on a post only after they are approved."
                />

                <nav
                    aria-label="Comment status"
                    className="flex flex-wrap gap-2"
                >
                    {TABS.map((tab) => (
                        <Button
                            key={tab.value}
                            asChild
                            variant={
                                tab.value === status ? 'default' : 'outline'
                            }
                        >
                            <Link
                                href={index.url({
                                    query: { status: tab.value },
                                })}
                                aria-current={
                                    tab.value === status ? 'page' : undefined
                                }
                            >
                                {tab.label} ({counts[tab.value] ?? 0})
                            </Link>
                        </Button>
                    ))}
                </nav>

                {comments.data.length === 0 ? (
                    <p className="rounded-xl border border-border p-8 text-center text-muted-foreground">
                        {EMPTY[status]}
                    </p>
                ) : (
                    <ul className="grid gap-4">
                        {comments.data.map((comment) => (
                            <li
                                key={comment.id}
                                className="rounded-xl border border-border p-5"
                            >
                                <div className="flex flex-wrap items-baseline justify-between gap-2">
                                    <p className="font-medium">
                                        {comment.author_name}{' '}
                                        <span className="font-normal text-muted-foreground">
                                            {comment.author_email}
                                        </span>
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {comment.date}
                                    </p>
                                </div>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {comment.is_reply ? 'Reply on ' : 'On '}
                                    <a
                                        href={`/knowledge-base/${comment.post_slug}`}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="underline"
                                    >
                                        {comment.post_title}
                                    </a>
                                </p>
                                <p className="mt-3 whitespace-pre-line">
                                    {comment.content}
                                </p>
                                <div className="mt-4 flex flex-wrap gap-2">
                                    {comment.status !== 'approved' && (
                                        <Button
                                            size="sm"
                                            onClick={() =>
                                                setStatus(comment, 'approved')
                                            }
                                        >
                                            Approve
                                        </Button>
                                    )}
                                    {comment.status !== 'rejected' && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                setStatus(comment, 'rejected')
                                            }
                                        >
                                            Reject
                                        </Button>
                                    )}
                                    <Button
                                        size="sm"
                                        variant="destructive"
                                        onClick={() => remove(comment)}
                                    >
                                        Delete
                                    </Button>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <div className="flex gap-2">
                    {comments.prev_page_url && (
                        <Button asChild variant="outline" size="sm">
                            <Link href={comments.prev_page_url}>Previous</Link>
                        </Button>
                    )}
                    {comments.next_page_url && (
                        <Button asChild variant="outline" size="sm">
                            <Link href={comments.next_page_url}>Next</Link>
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}

CommentsIndex.layout = {
    breadcrumbs: [{ title: 'Comments', href: index() }],
};
