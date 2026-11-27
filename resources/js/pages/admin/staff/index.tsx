import { Head, router, useForm, usePage } from '@inertiajs/react';
import {
    Check,
    CircleAlert,
    CircleCheck,
    LoaderCircle,
    UserMinus,
} from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { index, lookup, store, update } from '@/routes/admin/staff';

type Role = { value: string; label: string; description: string };

type StaffRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    is_you: boolean;
};

type Lookup = {
    account: { name: string; role: string; role_label: string } | null;
};

/** Plural headings for the groups. */
const GROUP: Record<string, string> = {
    admin: 'Admins',
    registrar: 'Registrars',
    editor: 'Editors',
};

const initials = (name: string): string =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

/** Role choices as tiles, used to give a role and to change one. */
function RoleTiles({
    roles,
    value,
    onChange,
    name,
}: {
    roles: Role[];
    value: string;
    onChange: (value: string) => void;
    name: string;
}) {
    return (
        <div role="radiogroup" className="grid gap-2">
            {roles.map((role) => {
                const chosen = value === role.value;

                return (
                    <label
                        key={role.value}
                        className={cn(
                            'relative flex cursor-pointer flex-col gap-0.5 rounded-control border p-3 transition-colors',
                            'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                            chosen
                                ? 'border-brand-crimson-strong ring-1 ring-brand-crimson-strong dark:border-brand-crimson dark:ring-brand-crimson'
                                : 'border-border hover:border-foreground/40',
                        )}
                    >
                        <input
                            type="radio"
                            name={name}
                            value={role.value}
                            checked={chosen}
                            onChange={() => onChange(role.value)}
                            className="sr-only"
                        />
                        <span className="flex items-center justify-between gap-2 text-sm font-semibold">
                            {role.label}
                            {chosen && (
                                <Check
                                    aria-hidden
                                    className="size-4 text-brand-crimson-strong dark:text-brand-crimson"
                                />
                            )}
                        </span>
                        <span className="text-sm text-muted-foreground">
                            {role.description}
                        </span>
                    </label>
                );
            })}
        </div>
    );
}

/** Give a role: the email of an existing account, checked as you leave it, and a role. */
function GiveRole({ roles }: { roles: Role[] }) {
    const form = useForm({ email: '', role: roles[1]?.value ?? '' });
    const [checked, setChecked] = useState('');
    const [account, setAccount] = useState<Lookup['account'] | undefined>();
    const [checking, setChecking] = useState(false);
    const emailChecked =
        account !== undefined && checked === form.data.email.trim();

    const check = async () => {
        const email = form.data.email.trim();
        if (email === '' || email === checked) {
            return;
        }
        setChecking(true);
        try {
            const response = await fetch(lookup.url({ query: { email } }), {
                headers: { Accept: 'application/json' },
            });
            setAccount(((await response.json()) as Lookup).account);
            setChecked(email);
        } catch {
            setAccount(undefined);
        } finally {
            setChecking(false);
        }
    };

    const give = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.reset('email');
                setChecked('');
                setAccount(undefined);
            },
        });
    };

    return (
        <form
            onSubmit={give}
            noValidate
            className="grid gap-5 rounded-card border border-border bg-background p-6"
        >
            <h2 className="text-heading03 font-semibold">
                Give someone a staff role
            </h2>
            <div className="grid gap-2">
                <Label htmlFor="email" className="font-semibold">
                    Email address of their account
                </Label>
                <Input
                    id="email"
                    type="email"
                    autoComplete="off"
                    value={form.data.email}
                    aria-invalid={form.errors.email ? true : undefined}
                    aria-describedby="email-check"
                    onChange={(event) =>
                        form.setData('email', event.target.value)
                    }
                    onBlur={check}
                />
                <p id="email-check" aria-live="polite" className="text-sm">
                    {checking ? (
                        <span className="flex items-center gap-2 text-muted-foreground">
                            <LoaderCircle
                                aria-hidden
                                className="size-4 animate-spin"
                            />
                            Looking for their account
                        </span>
                    ) : emailChecked && account ? (
                        account.role === 'member' ? (
                            <span className="flex items-center gap-2 font-semibold">
                                <CircleCheck
                                    aria-hidden
                                    className="size-4 text-brand-navy dark:text-brand-on-dark"
                                />
                                {account.name}'s account
                            </span>
                        ) : (
                            <span className="flex items-center gap-2 font-semibold text-destructive">
                                <CircleAlert aria-hidden className="size-4" />
                                {account.name} is already{' '}
                                {account.role === 'admin' ? 'an' : 'a'}{' '}
                                {account.role_label.toLowerCase()}. Change it in
                                the list.
                            </span>
                        )
                    ) : emailChecked ? (
                        <span className="flex items-center gap-2 font-semibold text-destructive">
                            <CircleAlert aria-hidden className="size-4" />
                            No account uses this email. Ask them to create one
                            first.
                        </span>
                    ) : null}
                </p>
                <InputError
                    className="font-semibold text-destructive"
                    message={form.errors.email}
                />
            </div>
            <fieldset className="grid gap-2">
                <legend className="mb-2 text-sm font-semibold">Role</legend>
                <RoleTiles
                    roles={roles}
                    value={form.data.role}
                    onChange={(value) => form.setData('role', value)}
                    name="role"
                />
                <InputError
                    className="font-semibold text-destructive"
                    message={form.errors.role}
                />
            </fieldset>
            <div>
                <Button type="submit" size="lg" disabled={form.processing}>
                    Give role
                </Button>
            </div>
        </form>
    );
}

function ChangeDialog({
    person,
    roles,
    onClose,
}: {
    person: StaffRow;
    roles: Role[];
    onClose: () => void;
}) {
    const [role, setRole] = useState(person.role);
    const [processing, setProcessing] = useState(false);

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Change {person.name}'s role</DialogTitle>
                    <DialogDescription>
                        It applies from their next page. Their account and
                        membership are not affected.
                    </DialogDescription>
                </DialogHeader>
                <RoleTiles
                    roles={roles}
                    value={role}
                    onChange={setRole}
                    name="change-role"
                />
                <DialogFooter className="gap-2">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Keep as it is
                    </Button>
                    <Button
                        type="button"
                        disabled={processing || role === person.role}
                        onClick={() => {
                            setProcessing(true);
                            router.patch(
                                update.url(person.id),
                                { role },
                                { preserveScroll: true, onFinish: onClose },
                            );
                        }}
                    >
                        Change role
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function RemoveDialog({
    person,
    onClose,
}: {
    person: StaffRow;
    onClose: () => void;
}) {
    const [processing, setProcessing] = useState(false);

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Take {person.name} off the staff?</DialogTitle>
                    <DialogDescription>
                        They lose access to the staff area from their next page.
                        Their account stays, and they become a member again. You
                        can give them a role later.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter className="gap-2">
                    <Button type="button" variant="outline" onClick={onClose}>
                        Keep them
                    </Button>
                    <Button
                        type="button"
                        variant="destructive"
                        disabled={processing}
                        onClick={() => {
                            setProcessing(true);
                            router.patch(
                                update.url(person.id),
                                { role: 'member' },
                                { preserveScroll: true, onFinish: onClose },
                            );
                        }}
                    >
                        Take off the staff
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

export default function StaffIndex({
    staff,
    roles,
}: {
    staff: StaffRow[];
    roles: Role[];
}) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [changing, setChanging] = useState<StaffRow | null>(null);
    const [removing, setRemoving] = useState<StaffRow | null>(null);
    // Admins first, then the membership side, then the Knowledge Base.
    const order = ['admin', 'registrar', 'editor'];
    const groups = order
        .map((value) => ({
            role: roles.find((role) => role.value === value),
            people: staff.filter((person) => person.role === value),
        }))
        .filter((group) => group.role);

    return (
        <>
            <Head title="Staff" />
            <div className="@container w-full px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="Staff"
                    description="Who can open the staff area, and which part of it. Roles are given to people who already have an account."
                />

                <div className="grid items-start gap-6 *:min-w-0 @5xl:grid-cols-[minmax(0,1fr)_24rem]">
                    <section
                        aria-labelledby="team-heading"
                        className="rounded-card border border-border bg-background"
                    >
                        <div className="flex items-baseline justify-between gap-3 border-b border-border px-4 py-4 md:px-6">
                            <h2
                                id="team-heading"
                                className="text-heading03 font-semibold"
                            >
                                Current staff
                            </h2>
                            <span className="text-sm text-muted-foreground">
                                {staff.length}{' '}
                                {staff.length === 1 ? 'person' : 'people'}
                            </span>
                        </div>

                        {errors.role && (
                            <p
                                role="alert"
                                className="border-b border-border bg-destructive/5 px-4 py-3 text-sm font-semibold text-destructive md:px-6"
                            >
                                {errors.role}
                            </p>
                        )}

                        {groups.map(({ role, people }) => (
                            <div
                                key={role!.value}
                                className="border-b border-border last:border-b-0"
                            >
                                <div className="bg-muted/50 px-4 py-2.5 md:px-6">
                                    <h3 className="text-sm font-semibold">
                                        {GROUP[role!.value] ?? role!.label}
                                        <span className="ml-2 font-normal text-muted-foreground">
                                            {people.length}
                                        </span>
                                    </h3>
                                    <p className="text-xs text-muted-foreground">
                                        {role!.description}
                                    </p>
                                </div>
                                {people.length === 0 ? (
                                    <p className="px-4 py-3 text-sm text-muted-foreground md:px-6">
                                        No one has this role.
                                    </p>
                                ) : (
                                    <ul className="divide-y divide-border">
                                        {people.map((person) => (
                                            <li
                                                key={person.id}
                                                className="grid grid-cols-[auto_minmax(0,1fr)] items-center gap-x-4 gap-y-3 px-4 py-3.5 md:px-6 @2xl:grid-cols-[auto_minmax(0,1fr)_auto]"
                                            >
                                                <span
                                                    aria-hidden
                                                    className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-navy text-sm font-semibold text-white dark:bg-brand-night-raised"
                                                >
                                                    {initials(person.name)}
                                                </span>
                                                <div className="min-w-0">
                                                    <p className="flex flex-wrap items-center gap-2 font-semibold">
                                                        {person.name}
                                                        {person.is_you && (
                                                            <span className="rounded-full bg-muted px-2 py-0.5 text-xs font-semibold text-muted-foreground">
                                                                You
                                                            </span>
                                                        )}
                                                    </p>
                                                    <p className="text-sm break-all text-muted-foreground">
                                                        {person.email}
                                                    </p>
                                                </div>
                                                {person.is_you ? (
                                                    <p className="col-start-2 text-sm text-muted-foreground @2xl:col-start-auto">
                                                        Another admin can change
                                                        your role.
                                                    </p>
                                                ) : (
                                                    <div className="col-start-2 flex gap-2 @2xl:col-start-auto">
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setChanging(
                                                                    person,
                                                                )
                                                            }
                                                        >
                                                            Change role
                                                            <span className="sr-only">
                                                                {' '}
                                                                for{' '}
                                                                {person.name}
                                                            </span>
                                                        </Button>
                                                        <Button
                                                            size="sm"
                                                            variant="ghost"
                                                            className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                            onClick={() =>
                                                                setRemoving(
                                                                    person,
                                                                )
                                                            }
                                                        >
                                                            <UserMinus />
                                                            Remove
                                                            <span className="sr-only">
                                                                {' '}
                                                                {person.name}
                                                            </span>
                                                        </Button>
                                                    </div>
                                                )}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        ))}
                    </section>

                    <aside className="@5xl:sticky @5xl:top-6">
                        <GiveRole roles={roles} />
                    </aside>
                </div>
            </div>

            {changing && (
                <ChangeDialog
                    person={changing}
                    roles={roles}
                    onClose={() => setChanging(null)}
                />
            )}
            {removing && (
                <RemoveDialog
                    person={removing}
                    onClose={() => setRemoving(null)}
                />
            )}
        </>
    );
}

StaffIndex.layout = {
    breadcrumbs: [{ title: 'Staff', href: index() }],
};
