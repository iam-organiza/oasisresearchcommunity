import { Head, Link, router } from '@inertiajs/react';
import { Check, Trash2, X } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
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

const initials = (name: string): string =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

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
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="Comments"
                    description="Readers’ comments appear on a post only after they are approved."
                />

                <section className="@container rounded-card border border-border bg-background">
                    <nav
                        aria-label="Comment status"
                        className="flex flex-wrap gap-2 border-b border-border p-4 md:p-6"
                    >
                        {TABS.map((tab) => {
                            const current = tab.value === status;
                            const count = counts[tab.value] ?? 0;
                            // A waiting queue with comments in it is the one thing that needs attention.
                            const needsAttention =
                                tab.value === 'pending' && count > 0;

                            return (
                                <Link
                                    key={tab.value}
                                    href={index.url({
                                        query: { status: tab.value },
                                    })}
                                    aria-current={current ? 'page' : undefined}
                                    className={cn(
                                        'flex h-9 items-center gap-2 rounded-full pr-1.5 pl-4 text-sm font-semibold transition-colors',
                                        'focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                                        current
                                            ? 'bg-primary text-primary-foreground'
                                            : 'text-muted-foreground ring-1 ring-border ring-inset hover:bg-accent hover:text-foreground',
                                    )}
                                >
                                    {tab.label}
                                    <span
                                        className={cn(
                                            'flex h-6 min-w-6 items-center justify-center rounded-full px-1.5 text-xs tabular-nums',
                                            needsAttention
                                                ? 'bg-brand-crimson text-white'
                                                : current
                                                  ? 'bg-primary-foreground/15'
                                                  : 'bg-muted',
                                        )}
                                    >
                                        {count}
                                    </span>
                                </Link>
                            );
                        })}
                    </nav>

                    {comments.data.length === 0 ? (
                        <p className="p-10 text-center text-muted-foreground">
                            {EMPTY[status]}
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {comments.data.map((comment) => (
                                <li
                                    key={comment.id}
                                    className="flex gap-4 p-4 md:px-6 md:py-5"
                                >
                                    <span
                                        aria-hidden
                                        className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-navy text-sm font-semibold text-white dark:bg-brand-night-raised"
                                    >
                                        {initials(comment.author_name)}
                                    </span>

                                    <div className="flex min-w-0 flex-1 flex-col gap-4 @3xl:flex-row @3xl:gap-8">
                                        <div className="min-w-0 flex-1">
                                            <div className="flex flex-wrap items-baseline gap-x-3 gap-y-0.5">
                                                <p className="font-semibold">
                                                    {comment.author_name}
                                                </p>
                                                <p className="text-sm break-all text-muted-foreground">
                                                    {comment.author_email}
                                                </p>
                                                {comment.date && (
                                                    <p className="text-sm whitespace-nowrap text-muted-foreground">
                                                        {comment.date}
                                                    </p>
                                                )}
                                            </div>
                                            <p className="mt-0.5 text-sm text-muted-foreground">
                                                {comment.is_reply
                                                    ? 'Reply on '
                                                    : 'On '}
                                                <a
                                                    href={`/knowledge-base/${comment.post_slug}`}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="font-semibold text-foreground underline decoration-border underline-offset-4 transition-colors hover:decoration-current"
                                                >
                                                    {comment.post_title}
                                                </a>
                                            </p>
                                            <p className="mt-3 max-w-prose text-base leading-relaxed text-pretty whitespace-pre-line md:text-lg">
                                                {comment.content}
                                            </p>
                                        </div>

                                        <div className="flex shrink-0 flex-wrap items-start gap-2">
                                            {comment.status !== 'approved' && (
                                                <Button
                                                    size="sm"
                                                    onClick={() =>
                                                        setStatus(
                                                            comment,
                                                            'approved',
                                                        )
                                                    }
                                                >
                                                    <Check />
                                                    Approve
                                                </Button>
                                            )}
                                            {comment.status !== 'rejected' && (
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    onClick={() =>
                                                        setStatus(
                                                            comment,
                                                            'rejected',
                                                        )
                                                    }
                                                >
                                                    <X />
                                                    Reject
                                                </Button>
                                            )}
                                            <Button
                                                size="sm"
                                                variant="ghost"
                                                className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                onClick={() => remove(comment)}
                                            >
                                                <Trash2 />
                                                Delete
                                            </Button>
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}

                    {(comments.prev_page_url || comments.next_page_url) && (
                        <div className="flex gap-2 border-t border-border px-4 py-3 md:px-6">
                            {comments.prev_page_url && (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={comments.prev_page_url}>
                                        Previous
                                    </Link>
                                </Button>
                            )}
                            {comments.next_page_url && (
                                <Button asChild variant="outline" size="sm">
                                    <Link href={comments.next_page_url}>
                                        Next
                                    </Link>
                                </Button>
                            )}
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

CommentsIndex.layout = {
    breadcrumbs: [{ title: 'Comments', href: index() }],
};
