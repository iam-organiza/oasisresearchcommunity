import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, UserMinus } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import { index, store, update } from '@/routes/admin/staff';

type Role = { value: string; label: string; description: string };

type StaffRow = {
    id: number;
    name: string;
    email: string;
    role: string;
    is_you: boolean;
};

const selectClass = [
    'h-10 rounded-control border border-input bg-background px-3 text-base shadow-none outline-none md:text-sm',
    'transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/20',
    'disabled:cursor-not-allowed disabled:opacity-60',
].join(' ');

const initials = (name: string): string =>
    name
        .split(/\s+/)
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0]?.toUpperCase())
        .join('');

export default function StaffIndex({
    staff,
    roles,
}: {
    staff: StaffRow[];
    roles: Role[];
}) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const form = useForm({ email: '', role: roles[1]?.value ?? '' });
    const labelFor = (value: string) =>
        roles.find((role) => role.value === value)?.label ?? value;

    const give = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset('email'),
        });
    };

    const changeRole = (person: StaffRow, role: string) => {
        if (
            window.confirm(
                `Make ${person.name} ${role === 'admin' ? 'an' : 'a'} ${labelFor(role).toLowerCase()}?`,
            )
        ) {
            router.patch(
                update.url(person.id),
                { role },
                { preserveScroll: true },
            );
        }
    };

    const remove = (person: StaffRow) => {
        if (
            window.confirm(
                `Take ${person.name} off the staff? Their account stays; they become a member again.`,
            )
        ) {
            router.patch(
                update.url(person.id),
                { role: 'member' },
                { preserveScroll: true },
            );
        }
    };

    return (
        <>
            <Head title="Staff" />
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="Staff"
                    description="Who can open the staff area, and which part of it. Roles are given to people who already have an account."
                />

                <div className="grid gap-8">
                    {/* Give a role: the email of an existing account and one of the three roles. */}
                    <form
                        onSubmit={give}
                        className="@container rounded-card border border-border bg-background p-6 md:p-8"
                        noValidate
                    >
                        <h2 className="mb-6 text-heading03 font-semibold">
                            Give someone a staff role
                        </h2>

                        <div className="grid gap-6">
                            <div className="grid max-w-xl gap-2">
                                <Label
                                    htmlFor="email"
                                    className="font-semibold"
                                >
                                    Email address of their account
                                </Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={form.data.email}
                                    aria-invalid={
                                        form.errors.email ? true : undefined
                                    }
                                    onChange={(event) =>
                                        form.setData(
                                            'email',
                                            event.target.value,
                                        )
                                    }
                                />
                                <InputError
                                    className="font-semibold text-destructive"
                                    message={form.errors.email}
                                />
                            </div>

                            <fieldset className="grid gap-2">
                                <legend className="mb-2 text-sm font-semibold">
                                    Role
                                </legend>
                                <div
                                    role="radiogroup"
                                    className="grid gap-3 @2xl:grid-cols-3"
                                >
                                    {roles.map((role) => {
                                        const chosen =
                                            form.data.role === role.value;

                                        return (
                                            <label
                                                key={role.value}
                                                className={cn(
                                                    'relative flex cursor-pointer flex-col gap-1 rounded-control border p-4 transition-colors',
                                                    'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                                                    chosen
                                                        ? 'border-brand-crimson-strong ring-1 ring-brand-crimson-strong dark:border-brand-crimson dark:ring-brand-crimson'
                                                        : 'border-border hover:border-foreground/40',
                                                )}
                                            >
                                                <input
                                                    type="radio"
                                                    name="role"
                                                    value={role.value}
                                                    checked={chosen}
                                                    onChange={() =>
                                                        form.setData(
                                                            'role',
                                                            role.value,
                                                        )
                                                    }
                                                    className="sr-only"
                                                />
                                                <span className="flex items-center justify-between gap-2 font-semibold">
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
                                <InputError
                                    className="font-semibold text-destructive"
                                    message={form.errors.role}
                                />
                            </fieldset>

                            <div>
                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={form.processing}
                                >
                                    Give role
                                </Button>
                            </div>
                        </div>
                    </form>

                    {/* The staff list: each person's role can be changed, or taken away. */}
                    <section className="@container rounded-card border border-border bg-background">
                        <div className="flex items-baseline justify-between gap-3 border-b border-border px-4 py-4 md:px-6">
                            <h2 className="text-heading03 font-semibold">
                                Current staff
                            </h2>
                            <span className="text-sm text-muted-foreground tabular-nums">
                                {staff.length}{' '}
                                {staff.length === 1 ? 'person' : 'people'}
                            </span>
                        </div>

                        {errors.role && !form.errors.role && (
                            <p
                                role="alert"
                                className="border-b border-border bg-destructive/5 px-4 py-3 text-sm font-semibold text-destructive md:px-6"
                            >
                                {errors.role}
                            </p>
                        )}

                        <ul className="divide-y divide-border">
                            {staff.map((person) => (
                                <li
                                    key={person.id}
                                    className="flex gap-4 p-4 md:px-6 md:py-5"
                                >
                                    <span
                                        aria-hidden
                                        className="flex size-10 shrink-0 items-center justify-center rounded-full bg-brand-navy text-sm font-semibold text-white dark:bg-brand-night-raised"
                                    >
                                        {initials(person.name)}
                                    </span>

                                    <div className="flex min-w-0 flex-1 flex-col gap-3 @2xl:flex-row @2xl:items-center @2xl:gap-6">
                                        <div className="min-w-0 flex-1">
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

                                        <div className="flex flex-wrap items-center gap-2">
                                            <label
                                                htmlFor={`role-${person.id}`}
                                                className="sr-only"
                                            >
                                                Role for {person.name}
                                            </label>
                                            <select
                                                id={`role-${person.id}`}
                                                value={person.role}
                                                disabled={person.is_you}
                                                title={
                                                    person.is_you
                                                        ? 'Ask another admin to change your role.'
                                                        : undefined
                                                }
                                                onChange={(event) =>
                                                    changeRole(
                                                        person,
                                                        event.target.value,
                                                    )
                                                }
                                                className={selectClass}
                                            >
                                                {roles.map((role) => (
                                                    <option
                                                        key={role.value}
                                                        value={role.value}
                                                    >
                                                        {role.label}
                                                    </option>
                                                ))}
                                            </select>
                                            {!person.is_you && (
                                                <Button
                                                    size="sm"
                                                    variant="ghost"
                                                    className="text-destructive hover:bg-destructive/10 hover:text-destructive"
                                                    onClick={() =>
                                                        remove(person)
                                                    }
                                                >
                                                    <UserMinus />
                                                    Remove
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            </div>
        </>
    );
}

StaffIndex.layout = {
    breadcrumbs: [{ title: 'Staff', href: index() }],
};
