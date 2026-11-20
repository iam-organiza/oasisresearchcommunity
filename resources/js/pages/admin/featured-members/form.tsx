import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
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
};

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
    });
    const { data, setData, errors } = form;

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

    const text = (
        id:
            | 'title'
            | 'first_name'
            | 'middle_name'
            | 'last_name'
            | 'position'
            | 'speciality',
        label: string,
        hint?: string,
    ) => (
        <div className="grid gap-2">
            <Label htmlFor={id}>{label}</Label>
            <Input
                id={id}
                value={data[id]}
                onChange={(event) => setData(id, event.target.value)}
            />
            {hint && <p className="text-sm text-muted-foreground">{hint}</p>}
            <InputError message={errors[id]} />
        </div>
    );

    return (
        <>
            <Head title={member ? 'Edit featured member' : 'Add a member'} />
            <form
                onSubmit={submit}
                className="flex max-w-2xl flex-col gap-6 p-4 md:p-6"
                noValidate
            >
                <Heading
                    title={member ? 'Edit featured member' : 'Add a member'}
                    description="Shown on the landing page as soon as it is saved."
                />

                <div className="grid gap-5 sm:grid-cols-[120px_minmax(0,1fr)]">
                    {text('title', 'Title', 'Dr., Prof., Mr.')}
                    {text('first_name', 'First name')}
                </div>
                <div className="grid gap-5 sm:grid-cols-2">
                    {text('middle_name', 'Middle name')}
                    {text('last_name', 'Last name')}
                </div>
                {text('position', 'Membership grade', 'For example, Fellow.')}
                {text('speciality', 'Speciality')}

                <div className="grid gap-2">
                    <Label htmlFor="job_description">
                        Roles and affiliations
                    </Label>
                    <textarea
                        id="job_description"
                        rows={4}
                        value={data.job_description}
                        onChange={(event) =>
                            setData('job_description', event.target.value)
                        }
                        className="w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    />
                    <p className="text-sm text-muted-foreground">
                        Put each role on its own line.
                    </p>
                    <InputError message={errors.job_description} />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="avatar_file">Photo</Label>
                    {member?.avatar_url && (
                        <img
                            src={member.avatar_url}
                            alt="Current photo"
                            className="size-32 rounded-lg object-cover"
                        />
                    )}
                    <Input
                        id="avatar_file"
                        type="file"
                        accept="image/png,image/jpeg,image/webp"
                        onChange={(event) =>
                            setData(
                                'avatar_file',
                                event.target.files?.[0] ?? null,
                            )
                        }
                    />
                    <p className="text-sm text-muted-foreground">
                        A square JPG, PNG or WebP, up to 5 MB.
                        {member && ' Leave empty to keep the current photo.'}
                    </p>
                    <InputError message={errors.avatar_file} />
                </div>

                <div className="flex gap-2">
                    <Button type="submit" disabled={form.processing}>
                        {form.processing ? 'Saving' : 'Save member'}
                    </Button>
                    <Button asChild variant="outline">
                        <Link href={index()}>Back to featured members</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

FeaturedMemberForm.layout = {
    breadcrumbs: [{ title: 'Featured members', href: index() }],
};
