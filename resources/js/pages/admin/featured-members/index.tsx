import { Head, Link, router } from '@inertiajs/react';
import { Pencil, Plus, Trash2 } from 'lucide-react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { create, destroy, edit, index } from '@/routes/admin/featured-members';

type MemberRow = {
    id: number;
    full_name: string;
    position: string | null;
    job_description: string | null;
    avatar_url: string | null;
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
    const remove = (member: MemberRow) => {
        if (
            window.confirm(
                `Remove ${member.full_name} from the landing page? This cannot be undone.`,
            )
        ) {
            router.delete(destroy.url(member.id), { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Featured members" />
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Featured members"
                        description="The people shown under Featured Members on the landing page, in the order they were added."
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

                    {members.map((member) => (
                        <li
                            key={member.id}
                            className="flex flex-col overflow-clip rounded-card border border-border bg-background"
                        >
                            {member.avatar_url ? (
                                <img
                                    src={member.avatar_url}
                                    alt=""
                                    className="aspect-square w-full object-cover object-top"
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

                            <div className="flex gap-1 border-t border-border px-3 py-2">
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
