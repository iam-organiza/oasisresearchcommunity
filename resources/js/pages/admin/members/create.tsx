import { Head, Link, useForm } from '@inertiajs/react';
import { CircleAlert, CircleCheck, LoaderCircle } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import Heading from '@/components/heading';
import {
    ChoiceTiles,
    Field,
    naira,
    selectClass,
} from '@/components/membership/form-bits';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { create, index, lookup, store } from '@/routes/admin/members';

type Option = { value: string; label: string };
type GradeOption = Option & { induction_fee: number };

type Lookup = {
    account: {
        name: string;
        on_register: boolean;
        number: string | null;
    } | null;
    next_number: string | null;
};

const prettyDate = (iso: string) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString('en-GB', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

/** A titled part of the form. */
function Part({
    title,
    hint,
    children,
}: {
    title: string;
    hint?: string;
    children: ReactNode;
}) {
    return (
        <section className="grid gap-5 border-t border-border pt-6 first:border-t-0 first:pt-0">
            <div>
                <h2 className="text-heading03 font-semibold">{title}</h2>
                {hint && (
                    <p className="mt-0.5 text-sm text-muted-foreground">
                        {hint}
                    </p>
                )}
            </div>
            {children}
        </section>
    );
}

export default function RecordMember({
    grades,
    regions,
}: {
    grades: GradeOption[];
    regions: { name: string; states: Option[] }[];
}) {
    const form = useForm({
        email: '',
        grade: '',
        state_id: '',
        number: '',
        joined_on: '',
        induction: '',
        induction_amount: '',
    });
    const { data, setData, errors } = form;
    const [checkedEmail, setCheckedEmail] = useState('');
    const [account, setAccount] = useState<Lookup['account'] | undefined>();
    const [checking, setChecking] = useState(false);
    const [nextNumber, setNextNumber] = useState<string | null>(null);

    const grade = grades.find((item) => item.value === data.grade);
    const year = data.joined_on
        ? Number(data.joined_on.slice(0, 4))
        : new Date().getFullYear();
    const stateName = regions
        .flatMap((region) => region.states)
        .find((state) => state.value === data.state_id)?.label;

    /** Asks the register who the email belongs to. */
    const checkEmail = async () => {
        const email = data.email.trim();
        if (email === '' || email === checkedEmail) {
            return;
        }
        setChecking(true);
        try {
            const response = await fetch(lookup.url({ query: { email } }), {
                headers: { Accept: 'application/json' },
            });
            const result = (await response.json()) as Lookup;
            setAccount(result.account);
            setCheckedEmail(email);
        } catch {
            setAccount(undefined);
        } finally {
            setChecking(false);
        }
    };

    // The number the register would give, for the grade and the year they joined.
    useEffect(() => {
        if (!data.grade) {
            setNextNumber(null);

            return;
        }
        const controller = new AbortController();
        fetch(lookup.url({ query: { grade: data.grade, year } }), {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => response.json() as Promise<Lookup>)
            .then((result) => setNextNumber(result.next_number))
            .catch(() => undefined);

        return () => controller.abort();
    }, [data.grade, year]);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(store.url());
    };

    const emailChecked =
        account !== undefined && checkedEmail === data.email.trim();
    const amount = Number(data.induction_amount || 0);
    const outcome = {
        paid: 'Active straight away. The fee is recorded as paid before the website, with no receipt emailed.',
        owed: `${amount > 0 ? naira(amount) : 'The induction fee'} goes on their account. They become active once it is paid.`,
        waived: 'Active straight away, with the fee waived.',
    }[data.induction as 'paid' | 'owed' | 'waived'];
    // Members recorded here get their number straight away, whatever the fee.
    const number = data.number || nextNumber;

    return (
        <>
            <Head title="Record an existing member" />
            <div className="@container w-full px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="Record an existing member"
                    description="For people admitted before the website. They need an account first; no application is needed."
                />

                <form
                    onSubmit={submit}
                    noValidate
                    className="grid items-start gap-6 *:min-w-0 @5xl:grid-cols-[minmax(0,1fr)_22rem]"
                >
                    <div className="grid gap-8 rounded-card border border-border bg-background p-6 md:p-8">
                        <Part
                            title="Who"
                            hint="The email address they signed up with."
                        >
                            <Field
                                id="email"
                                label="Email address of their account"
                                error={errors.email}
                            >
                                <Input
                                    id="email"
                                    type="email"
                                    autoComplete="off"
                                    value={data.email}
                                    aria-invalid={
                                        errors.email ? true : undefined
                                    }
                                    aria-describedby="email-check"
                                    onChange={(e) =>
                                        setData('email', e.target.value)
                                    }
                                    onBlur={checkEmail}
                                />
                            </Field>
                            <p
                                id="email-check"
                                aria-live="polite"
                                className="-mt-3 min-h-5 text-sm"
                            >
                                {checking ? (
                                    <span className="flex items-center gap-2 text-muted-foreground">
                                        <LoaderCircle
                                            aria-hidden
                                            className="size-4 animate-spin"
                                        />
                                        Looking for their account
                                    </span>
                                ) : emailChecked && account ? (
                                    account.on_register ? (
                                        <span className="flex items-center gap-2 font-semibold text-destructive">
                                            <CircleAlert
                                                aria-hidden
                                                className="size-4"
                                            />
                                            {account.name} is already on the
                                            register
                                            {account.number &&
                                                ` as ${account.number}`}
                                            .
                                        </span>
                                    ) : (
                                        <span className="flex items-center gap-2 font-semibold">
                                            <CircleCheck
                                                aria-hidden
                                                className="size-4 text-brand-navy dark:text-brand-on-dark"
                                            />
                                            {account.name}'s account
                                        </span>
                                    )
                                ) : emailChecked ? (
                                    <span className="flex items-center gap-2 font-semibold text-destructive">
                                        <CircleAlert
                                            aria-hidden
                                            className="size-4"
                                        />
                                        No account uses this email. Ask them to
                                        create one first.
                                    </span>
                                ) : null}
                            </p>
                        </Part>

                        <Part title="Membership">
                            <div className="grid gap-6 @xl:grid-cols-2">
                                <Field
                                    id="grade"
                                    label="Grade"
                                    error={errors.grade}
                                >
                                    <select
                                        id="grade"
                                        value={data.grade}
                                        aria-invalid={
                                            errors.grade ? true : undefined
                                        }
                                        onChange={(e) => {
                                            const fee = grades.find(
                                                (item) =>
                                                    item.value ===
                                                    e.target.value,
                                            )?.induction_fee;
                                            form.setData({
                                                ...data,
                                                grade: e.target.value,
                                                induction_amount: fee
                                                    ? String(fee)
                                                    : '',
                                            });
                                        }}
                                        className={selectClass}
                                    >
                                        <option value="">Choose a grade</option>
                                        {grades.map((item) => (
                                            <option
                                                key={item.value}
                                                value={item.value}
                                            >
                                                {item.label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    id="state_id"
                                    label="State"
                                    error={errors.state_id}
                                    optional
                                >
                                    <select
                                        id="state_id"
                                        value={data.state_id}
                                        onChange={(e) =>
                                            setData('state_id', e.target.value)
                                        }
                                        className={selectClass}
                                    >
                                        <option value="">Not known</option>
                                        {regions.map((region) => (
                                            <optgroup
                                                key={region.name}
                                                label={region.name}
                                            >
                                                {region.states.map((state) => (
                                                    <option
                                                        key={state.value}
                                                        value={state.value}
                                                    >
                                                        {state.label}
                                                    </option>
                                                ))}
                                            </optgroup>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    id="joined_on"
                                    label="Member since"
                                    error={errors.joined_on}
                                >
                                    <Input
                                        id="joined_on"
                                        type="date"
                                        value={data.joined_on}
                                        aria-invalid={
                                            errors.joined_on ? true : undefined
                                        }
                                        onChange={(e) =>
                                            setData('joined_on', e.target.value)
                                        }
                                    />
                                </Field>
                                <Field
                                    id="number"
                                    label="Membership number"
                                    hint={
                                        nextNumber
                                            ? `Leave empty to give them ${nextNumber}.`
                                            : 'Leave empty to give them the next number.'
                                    }
                                    error={errors.number}
                                    optional
                                >
                                    <Input
                                        id="number"
                                        value={data.number}
                                        placeholder={nextNumber ?? undefined}
                                        aria-invalid={
                                            errors.number ? true : undefined
                                        }
                                        onChange={(e) =>
                                            setData(
                                                'number',
                                                e.target.value.toUpperCase(),
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </Part>

                        <Part title="Induction fee">
                            <ChoiceTiles
                                name="induction"
                                legend="How it stands"
                                options={[
                                    {
                                        value: 'paid',
                                        label: 'Paid before the website',
                                    },
                                    { value: 'owed', label: 'Still owed' },
                                    { value: 'waived', label: 'Waived' },
                                ]}
                                value={data.induction}
                                onChange={(value) =>
                                    setData('induction', value)
                                }
                                error={errors.induction}
                                describe={(option) =>
                                    ({
                                        paid: 'Recorded as received, without emailing a receipt.',
                                        owed: 'Added to their account to pay.',
                                        waived: 'For example a scholarship. They are active straight away.',
                                    })[option.value]
                                }
                            />
                            <div className="max-w-xs">
                                <Field
                                    id="induction_amount"
                                    label="Induction fee, in naira"
                                    hint={
                                        grade
                                            ? `Today's fee for ${grade.label} is ${naira(grade.induction_fee)}. Change it if they joined at an older fee.`
                                            : 'Choose a grade first.'
                                    }
                                    error={errors.induction_amount}
                                >
                                    <Input
                                        id="induction_amount"
                                        inputMode="numeric"
                                        value={data.induction_amount}
                                        aria-invalid={
                                            errors.induction_amount
                                                ? true
                                                : undefined
                                        }
                                        onChange={(e) =>
                                            setData(
                                                'induction_amount',
                                                e.target.value.replace(
                                                    /\D/g,
                                                    '',
                                                ),
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </Part>
                    </div>

                    {/* What will go on the register, as it is filled in. */}
                    <aside className="grid gap-4 rounded-card bg-brand-navy p-6 text-white @5xl:sticky @5xl:top-6 dark:bg-brand-night-raised">
                        <h2 className="text-sm font-semibold text-brand-on-dark">
                            What will be recorded
                        </h2>
                        <div>
                            <p
                                className={cn(
                                    'text-heading03 font-bold',
                                    !(emailChecked && account) &&
                                        'text-brand-on-dark',
                                )}
                            >
                                {emailChecked && account
                                    ? account.name
                                    : 'Their name, once the email matches an account'}
                            </p>
                            <p className="mt-1 font-semibold tracking-wide">
                                {number ?? (
                                    <span className="font-normal tracking-normal text-brand-on-dark">
                                        Number once a grade is chosen
                                    </span>
                                )}
                            </p>
                        </div>
                        <dl className="grid gap-2 border-t border-white/15 pt-4 text-sm">
                            {[
                                ['Grade', grade?.label],
                                [
                                    'Member since',
                                    data.joined_on
                                        ? prettyDate(data.joined_on)
                                        : undefined,
                                ],
                                ['Chapter', stateName ?? 'Not known'],
                                [
                                    'Induction fee',
                                    amount > 0 ? naira(amount) : undefined,
                                ],
                            ].map(([label, value]) => (
                                <div
                                    key={label}
                                    className="flex justify-between gap-4"
                                >
                                    <dt className="text-brand-on-dark">
                                        {label}
                                    </dt>
                                    <dd className="text-right font-semibold">
                                        {value ?? '—'}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                        <p className="rounded-control bg-white/10 p-3 text-sm">
                            {outcome ??
                                'Choose how their induction fee stands to see what happens.'}
                        </p>
                        <div className="grid gap-2">
                            <Button
                                type="submit"
                                size="lg"
                                disabled={form.processing}
                                className="bg-white text-brand-navy hover:bg-white/90"
                            >
                                Add to the register
                            </Button>
                            <Button
                                asChild
                                size="lg"
                                variant="outline"
                                className="border-white/40 bg-transparent text-white hover:bg-white/10 hover:text-white"
                            >
                                <Link href={index()}>Back to the register</Link>
                            </Button>
                        </div>
                    </aside>
                </form>
            </div>
        </>
    );
}

RecordMember.layout = {
    breadcrumbs: [
        { title: 'Member register', href: index() },
        { title: 'Record an existing member', href: create() },
    ],
};
