import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDown,
    ArrowUp,
    Eye,
    EyeOff,
    Pencil,
    Plus,
    Trash2,
} from 'lucide-react';
import { useConfirm } from '@/components/confirm-dialog';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    create,
    destroy,
    edit,
    index,
    move,
    visibility,
} from '@/routes/admin/featured-members';

type MemberRow = {
    id: number;
    full_name: string;
    position: string | null;
    job_description: string | null;
    avatar_url: string | null;
    is_visible: boolean;
};

const initials = (name: string): string =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

export default function FeaturedMembersIndex({
    members,
}: {
    members: MemberRow[];
}) {
    const { confirm, dialog } = useConfirm();
    const remove = async (member: MemberRow) => {
        const yes = await confirm({
            title: `Remove ${member.full_name} from the landing page?`,
            description:
                'Their card, photo and description are deleted. This cannot be undone.',
            confirmLabel: 'Remove',
            cancelLabel: 'Keep them',
            tone: 'danger',
        });
        if (yes) {
            router.delete(destroy.url(member.id), { preserveScroll: true });
        }
    };

    const reorder = (member: MemberRow, direction: 'up' | 'down') =>
        router.patch(
            move.url(member.id),
            { direction },
            { preserveScroll: true },
        );

    const setVisible = (member: MemberRow, isVisible: boolean) =>
        router.patch(
            visibility.url(member.id),
            { is_visible: isVisible },
            { preserveScroll: true },
        );

    // Numbered as the home page shows them; hidden members are skipped.
    let shown = 0;

    return (
        <>
            <Head title="Featured members" />
            {dialog}
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Featured members"
                        description="The people shown under Featured Members on the home page, in this order. Hide someone to take them off the page without deleting them."
                    />
                    <Button asChild size="lg" className="mb-8">
                        <Link href={create()}>Add a member</Link>
                    </Button>
                </div>

                {members.length === 0 && (
                    <p className="mb-6 text-muted-foreground">
                        Nobody is featured yet. Add the first member to show the
                        section on the landing page.
                    </p>
                )}

                {/* Portrait cards, like the spotlight on the landing page; as many columns as fit. */}
                <ul className="grid grid-cols-[repeat(auto-fill,minmax(12rem,1fr))] gap-5">
                    <li className="min-h-48">
                        <Link
                            href={create()}
                            className="flex size-full flex-col items-center justify-center gap-3 rounded-card border-2 border-dashed border-border p-6 text-muted-foreground transition-colors hover:border-foreground/40 hover:bg-background hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                        >
                            <span className="flex size-12 items-center justify-center rounded-full bg-muted">
                                <Plus className="size-6" />
                            </span>
                            <span className="font-semibold">Add a member</span>
                        </Link>
                    </li>

                    {members.map((member, i) => (
                        <li
                            key={member.id}
                            className={cn(
                                'relative flex flex-col overflow-clip rounded-card border bg-background',
                                member.is_visible
                                    ? 'border-border'
                                    : 'border-dashed border-foreground/30',
                            )}
                        >
                            {/* Its place on the home page, or that it is hidden. */}
                            <span
                                className={cn(
                                    'absolute top-3 left-3 z-10 flex h-7 min-w-7 items-center justify-center rounded-full px-2 text-xs font-bold shadow-sm',
                                    member.is_visible
                                        ? 'bg-brand-navy text-white dark:bg-brand-night-raised'
                                        : 'bg-background text-foreground ring-1 ring-border',
                                )}
                            >
                                {member.is_visible ? (
                                    <>
                                        <span className="sr-only">Shown</span>
                                        {(shown += 1)}
                                        <span className="sr-only">
                                            {' '}
                                            on the home page
                                        </span>
                                    </>
                                ) : (
                                    'Hidden'
                                )}
                            </span>
                            {member.avatar_url ? (
                                <img
                                    src={member.avatar_url}
                                    alt=""
                                    className={cn(
                                        'aspect-square w-full object-cover object-top',
                                        !member.is_visible &&
                                            'opacity-40 grayscale',
                                    )}
                                />
                            ) : (
                                <div
                                    aria-hidden
                                    className="flex aspect-square w-full items-center justify-center bg-brand-navy text-heading02 font-bold text-white dark:bg-brand-night-raised"
                                >
                                    {initials(member.full_name)}
                                </div>
                            )}

                            <div className="flex-1 p-5">
                                <p className="text-lg leading-snug font-semibold">
                                    {member.full_name}
                                </p>
                                {member.position && (
                                    <p className="mt-0.5 text-sm font-semibold text-brand-crimson-strong dark:text-brand-crimson">
                                        {member.position}
                                    </p>
                                )}
                                {member.job_description && (
                                    <p className="mt-2 line-clamp-2 text-sm whitespace-pre-line text-muted-foreground">
                                        {member.job_description}
                                    </p>
                                )}
                            </div>

                            {/* Order and visibility, then edit and remove; the two groups wrap onto two lines on narrow cards. */}
                            <div className="flex flex-wrap items-center justify-between gap-x-2 gap-y-1 border-t border-border px-3 py-2">
                                <div className="flex items-center">
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        disabled={i === 0}
                                        onClick={() => reorder(member, 'up')}
                                        aria-label={`Move ${member.full_name} earlier`}
                                        title="Move earlier"
                                    >
                                        <ArrowUp />
                                    </Button>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        disabled={i === members.length - 1}
                                        onClick={() => reorder(member, 'down')}
                                        aria-label={`Move ${member.full_name} later`}
                                        title="Move later"
                                    >
                                        <ArrowDown />
                                    </Button>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        onClick={() =>
                                            setVisible(
                                                member,
                                                !member.is_visible,
                                            )
                                        }
                                        aria-label={
                                            member.is_visible
                                                ? `Hide ${member.full_name} from the home page`
                                                : `Show ${member.full_name} on the home page`
                                        }
                                        title={
                                            member.is_visible
                                                ? 'Hide from the home page'
                                                : 'Show on the home page'
                                        }
                                    >
                                        {member.is_visible ? (
                                            <EyeOff />
                                        ) : (
                                            <Eye />
                                        )}
                                    </Button>
                                </div>
                                <div className="flex items-center">
                                    <Button asChild size="sm" variant="ghost">
                                        <Link href={edit(member.id)}>
                                            <Pencil />
                                            Edit
                                        </Link>
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                        onClick={() => remove(member)}
                                    >
                                        <Trash2 />
                                        Remove
                                    </Button>
                                </div>
                            </div>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

FeaturedMembersIndex.layout = {
    breadcrumbs: [{ title: 'Featured members', href: index() }],
};
