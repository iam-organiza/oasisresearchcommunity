import { Head, Link, router } from '@inertiajs/react';
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
            <div className="flex flex-col gap-6 p-4 md:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <Heading
                        title="Featured members"
                        description="The people shown under Featured Members on the landing page, in the order they were added."
                    />
                    <Button asChild>
                        <Link href={create()}>Add a member</Link>
                    </Button>
                </div>

                {members.length === 0 ? (
                    <p className="rounded-xl border border-border p-8 text-center text-muted-foreground">
                        Nobody is featured yet. Add the first member to show the
                        section on the landing page.
                    </p>
                ) : (
                    <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        {members.map((member) => (
                            <li
                                key={member.id}
                                className="flex gap-4 rounded-xl border border-border p-4"
                            >
                                {member.avatar_url ? (
                                    <img
                                        src={member.avatar_url}
                                        alt=""
                                        className="size-20 shrink-0 rounded-lg object-cover"
                                    />
                                ) : (
                                    <div className="size-20 shrink-0 rounded-lg bg-muted" />
                                )}
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium">
                                        {member.full_name}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {member.position}
                                    </p>
                                    <p className="mt-1 line-clamp-2 text-sm whitespace-pre-line text-muted-foreground">
                                        {member.job_description}
                                    </p>
                                    <div className="mt-3 flex gap-2">
                                        <Button
                                            asChild
                                            size="sm"
                                            variant="outline"
                                        >
                                            <Link href={edit(member.id)}>
                                                Edit
                                            </Link>
                                        </Button>
                                        <Button
                                            size="sm"
                                            variant="destructive"
                                            onClick={() => remove(member)}
                                        >
                                            Remove
                                        </Button>
                                    </div>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

FeaturedMembersIndex.layout = {
    breadcrumbs: [{ title: 'Featured members', href: index() }],
};
