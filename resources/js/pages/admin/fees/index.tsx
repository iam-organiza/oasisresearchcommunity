import { Head, useForm } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import {
    Field,
    dollars,
    fieldClass,
    naira,
} from '@/components/membership/form-bits';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import { index, update } from '@/routes/admin/fees';

type GradeFees = {
    value: string;
    label: string;
    induction: number;
    subscription: number;
};

type Rate = {
    current: number | null;
    override: number | null;
    fetched: { rate: number; on: string } | null;
    stale: boolean;
    margin: number;
    attribution: string;
};

type Change = {
    id: number;
    key: string;
    label: string;
    old: number | null;
    new: number | null;
    reason: string;
    who: string | null;
    when: string;
};

const digits = (value: string) => value.replace(/\D/g, '');

type PercentKey =
    | 'upgrade_percent'
    | 'upgrade_discount_1y'
    | 'upgrade_discount_2y'
    | 'promotion_discount';

const percentFields: { key: PercentKey; label: string }[] = [
    { key: 'upgrade_percent', label: 'Admin fee, first year' },
    { key: 'upgrade_discount_1y', label: 'Discount after one year' },
    { key: 'upgrade_discount_2y', label: 'Discount after two years' },
    { key: 'promotion_discount', label: 'Promotion discount' },
];

/** How a stored figure reads in the history: naira, a percentage or a rate. */
function figure(key: string, amount: number | null) {
    if (amount === null) {
        return key === 'usd_rate_override' ? 'daily rate' : '—';
    }
    if (key === 'upgrade_percent' || key.includes('discount')) {
        return `${amount}%`;
    }
    if (key === 'usd_rate_override') {
        return `${naira(amount)} to the dollar`;
    }

    return naira(amount);
}

/** A naira amount field with its dollar equivalent underneath. */
function MoneyInput({
    id,
    label,
    value,
    onChange,
    error,
    toDollars,
    labelAlwaysShown = false,
    hiddenPrefix,
    was,
}: {
    id: string;
    label: string;
    /** Read out before the label but not shown, e.g. the grade above it. */
    hiddenPrefix?: string;
    /** In the grade table the column header names the field on wide screens. */
    labelAlwaysShown?: boolean;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    toDollars: (naira: number) => number | null;
    /** The saved figure; a different value is marked as changed. */
    was: string;
}) {
    const usd = value === '' ? null : toDollars(Number(value));
    const changed = value !== was;

    return (
        <div className="grid content-start gap-1">
            <label
                htmlFor={id}
                className={cn(
                    'font-semibold',
                    labelAlwaysShown ? 'text-sm' : 'text-sm @2xl:sr-only',
                )}
            >
                {hiddenPrefix && (
                    <span className="sr-only">{hiddenPrefix} </span>
                )}
                {label}
            </label>
            <div className="relative">
                <span
                    aria-hidden
                    className="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground"
                >
                    ₦
                </span>
                <Input
                    id={id}
                    inputMode="numeric"
                    value={
                        value === ''
                            ? ''
                            : Number(value).toLocaleString('en-NG')
                    }
                    aria-invalid={error ? true : undefined}
                    aria-describedby={`${id}-usd`}
                    onChange={(e) => onChange(digits(e.target.value))}
                    className={cn(
                        'pl-7',
                        changed &&
                            'border-brand-crimson-strong ring-1 ring-brand-crimson-strong dark:border-brand-crimson dark:ring-brand-crimson',
                    )}
                />
            </div>
            <p id={`${id}-usd`} className="text-sm text-muted-foreground">
                {usd === null ? 'No dollar rate yet' : `About ${dollars(usd)}`}
                {changed && (
                    <span className="block font-semibold text-brand-crimson-strong dark:text-brand-crimson">
                        Was {was === '' ? 'empty' : naira(Number(was))}
                    </span>
                )}
            </p>
            <InputError
                className="font-semibold text-destructive"
                message={error}
            />
        </div>
    );
}

export default function FeesIndex({
    grades,
    exam,
    postage,
    percents,
    rate,
    history,
}: {
    grades: GradeFees[];
    exam: number;
    postage: number;
    percents: Record<PercentKey, number>;
    rate: Rate;
    history: Change[];
}) {
    const form = useForm({
        induction: Object.fromEntries(
            grades.map((grade) => [grade.value, String(grade.induction)]),
        ) as Record<string, string>,
        subscription: Object.fromEntries(
            grades.map((grade) => [grade.value, String(grade.subscription)]),
        ) as Record<string, string>,
        exam: String(exam),
        postage: String(postage),
        upgrade_percent: String(percents.upgrade_percent),
        upgrade_discount_1y: String(percents.upgrade_discount_1y),
        upgrade_discount_2y: String(percents.upgrade_discount_2y),
        promotion_discount: String(percents.promotion_discount),
        usd_rate_override: rate.override ? String(rate.override) : '',
        reason: '',
    });
    const { data, setData, errors } = form;
    const error = (key: string) =>
        (errors as Record<string, string | undefined>)[key];

    // The preview follows a rate typed in by hand before it is saved.
    const previewRate = Number(data.usd_rate_override) || rate.current;
    const toDollars = (amount: number) =>
        previewRate
            ? Math.ceil(
                  Math.round(
                      (amount / previewRate) * (1 + rate.margin / 100) * 100,
                  ) / 100,
              )
            : null;

    // What differs from the saved figures, for the review bar.
    const changes = [
        ...grades.flatMap((grade) => [
            {
                label: `${grade.label} induction fee`,
                from: grade.induction,
                to: data.induction[grade.value],
                money: true,
            },
            {
                label: `${grade.label} yearly subscription`,
                from: grade.subscription,
                to: data.subscription[grade.value],
                money: true,
            },
        ]),
        { label: 'Exam fee', from: exam, to: data.exam, money: true },
        {
            label: 'Induction pack postage',
            from: postage,
            to: data.postage,
            money: true,
        },
        ...percentFields.map((field) => ({
            label: field.label,
            from: percents[field.key],
            to: data[field.key],
            money: false,
        })),
    ].filter((change) => String(change.from) !== change.to);
    const rateChanged =
        (rate.override ? String(rate.override) : '') !== data.usd_rate_override;
    const changeCount = changes.length + (rateChanged ? 1 : 0);
    const shown = (value: string | number, money: boolean) =>
        value === '' ? 'empty' : money ? naira(Number(value)) : `${value}%`;

    // A worked upgrade, Member to Fellow, on the figures as typed (App\Account\Upgrades).
    const fellow = Number(data.induction.fellow || 0);
    const member = Number(data.induction.member || 0);
    const less = (amount: number, percent: string) =>
        Math.round((amount * (100 - Number(percent || 0))) / 100);
    const difference = Math.max(0, fellow - member);
    const example = [
        [
            'Within the first year',
            difference +
                Math.ceil(
                    (difference * Number(data.upgrade_percent || 0)) / 100,
                ),
        ],
        ['After one year', less(fellow, data.upgrade_discount_1y)],
        ['After two years', less(fellow, data.upgrade_discount_2y)],
        ['After three years', fellow],
        ['On promotion', less(fellow, data.promotion_discount)],
    ] as const;

    const setGradeFee = (
        kind: 'induction' | 'subscription',
        grade: string,
        value: string,
    ) => setData(kind, { ...data[kind], [grade]: value });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update.url(), {
            preserveScroll: true,
            onSuccess: () => {
                form.setData('reason', '');
                form.setDefaults();
            },
        });
    };

    return (
        <>
            <Head title="Fees" />
            <div className="w-full px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="Fees"
                    description="What each fee is from now on. Charges already on someone's account keep the amount they were added at."
                />

                <div className="grid gap-6 *:min-w-0">
                    <form
                        onSubmit={submit}
                        noValidate
                        className="@container grid gap-6 *:min-w-0"
                    >
                        <section
                            aria-labelledby="grade-fees"
                            className="rounded-card border border-border bg-background"
                        >
                            <h2
                                id="grade-fees"
                                className="border-b border-border p-4 font-semibold md:px-6"
                            >
                                Fees by grade
                            </h2>
                            <div
                                aria-hidden
                                className="hidden grid-cols-[minmax(10rem,1fr)_minmax(0,1fr)_minmax(0,1fr)] gap-6 border-b border-border px-6 py-2 text-sm font-semibold text-muted-foreground @2xl:grid"
                            >
                                <span>Grade</span>
                                <span>Induction fee (once)</span>
                                <span>Yearly subscription</span>
                            </div>
                            <ul className="divide-y divide-border">
                                {grades.map((grade) => (
                                    <li
                                        key={grade.value}
                                        className="grid gap-4 p-4 md:px-6 @2xl:grid-cols-[minmax(10rem,1fr)_minmax(0,1fr)_minmax(0,1fr)] @2xl:items-start @2xl:gap-6"
                                    >
                                        <p className="font-semibold @2xl:pt-2">
                                            {grade.label}
                                        </p>
                                        <div className="grid grid-cols-2 gap-4 @2xl:contents">
                                            <MoneyInput
                                                id={`induction-${grade.value}`}
                                                was={String(grade.induction)}
                                                label="Induction fee"
                                                hiddenPrefix={grade.label}
                                                value={
                                                    data.induction[
                                                        grade.value
                                                    ] ?? ''
                                                }
                                                onChange={(value) =>
                                                    setGradeFee(
                                                        'induction',
                                                        grade.value,
                                                        value,
                                                    )
                                                }
                                                error={error(
                                                    `induction.${grade.value}`,
                                                )}
                                                toDollars={toDollars}
                                            />
                                            <MoneyInput
                                                id={`subscription-${grade.value}`}
                                                was={String(grade.subscription)}
                                                label="Yearly subscription"
                                                hiddenPrefix={grade.label}
                                                value={
                                                    data.subscription[
                                                        grade.value
                                                    ] ?? ''
                                                }
                                                onChange={(value) =>
                                                    setGradeFee(
                                                        'subscription',
                                                        grade.value,
                                                        value,
                                                    )
                                                }
                                                error={error(
                                                    `subscription.${grade.value}`,
                                                )}
                                                toDollars={toDollars}
                                            />
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </section>

                        <section
                            aria-labelledby="other-fees"
                            className="grid gap-6 rounded-card border border-border bg-background p-4 md:p-6 @2xl:grid-cols-2"
                        >
                            <h2
                                id="other-fees"
                                className="font-semibold @2xl:col-span-2"
                            >
                                Exam, postage and upgrades
                            </h2>
                            <div className="grid content-start gap-1">
                                <MoneyInput
                                    id="exam"
                                    was={String(exam)}
                                    label="Exam fee"
                                    value={data.exam}
                                    onChange={(value) => setData('exam', value)}
                                    error={errors.exam}
                                    toDollars={toDollars}
                                    labelAlwaysShown
                                />
                                <p className="text-sm text-muted-foreground">
                                    The same for every grade, paid before the
                                    exam materials and the exam.
                                </p>
                            </div>
                            <div className="grid content-start gap-1">
                                <MoneyInput
                                    id="postage"
                                    was={String(postage)}
                                    label="Induction pack postage"
                                    value={data.postage}
                                    onChange={(value) =>
                                        setData('postage', value)
                                    }
                                    error={errors.postage}
                                    toDollars={toDollars}
                                    labelAlwaysShown
                                />
                                <p className="text-sm text-muted-foreground">
                                    Charged when a member chooses to have their
                                    pack posted. ₦0 means free.
                                </p>
                            </div>
                            <fieldset className="grid content-start gap-4 @2xl:col-span-2 @2xl:grid-cols-2 @5xl:grid-cols-4">
                                <legend className="mb-2 text-sm text-muted-foreground @2xl:col-span-2 @5xl:col-span-4">
                                    Upgrades: under a year on a grade, members
                                    pay the difference in induction fees plus
                                    the admin fee on it; later, the new grade's
                                    induction fee less a discount; after three
                                    years, the full fee.
                                </legend>
                                {percentFields.map((field) => (
                                    <Field
                                        key={field.key}
                                        id={field.key}
                                        label={field.label}
                                        error={error(field.key)}
                                    >
                                        <div className="relative max-w-40">
                                            <Input
                                                id={field.key}
                                                inputMode="numeric"
                                                value={data[field.key]}
                                                aria-invalid={
                                                    error(field.key)
                                                        ? true
                                                        : undefined
                                                }
                                                onChange={(e) =>
                                                    setData(
                                                        field.key,
                                                        digits(
                                                            e.target.value,
                                                        ).slice(0, 3),
                                                    )
                                                }
                                                className={cn(
                                                    'pr-8',
                                                    String(
                                                        percents[field.key],
                                                    ) !== data[field.key] &&
                                                        'border-brand-crimson-strong ring-1 ring-brand-crimson-strong dark:border-brand-crimson dark:ring-brand-crimson',
                                                )}
                                            />
                                            <span
                                                aria-hidden
                                                className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground"
                                            >
                                                %
                                            </span>
                                        </div>
                                    </Field>
                                ))}
                            </fieldset>
                            <div className="rounded-control bg-muted/60 p-4 @2xl:col-span-2">
                                <p className="text-sm font-semibold">
                                    For example, a Member moving up to Fellow
                                    pays
                                </p>
                                <dl className="mt-2 grid gap-x-6 gap-y-1 text-sm @xl:grid-cols-5">
                                    {example.map(([when, amount]) => (
                                        <div key={when}>
                                            <dt className="text-muted-foreground">
                                                {when}
                                            </dt>
                                            <dd className="font-semibold">
                                                {naira(amount)}
                                            </dd>
                                        </div>
                                    ))}
                                </dl>
                            </div>
                        </section>

                        <section
                            aria-labelledby="dollar-rate"
                            className="grid gap-4 rounded-card border border-border bg-background p-4 md:p-6"
                        >
                            <div>
                                <h2 id="dollar-rate" className="font-semibold">
                                    Dollar prices
                                </h2>
                                <p className="mt-1 max-w-prose text-sm text-muted-foreground">
                                    People abroad see each fee in dollars: the
                                    naira price at the day's rate from{' '}
                                    <a
                                        href={rate.attribution}
                                        target="_blank"
                                        rel="noopener"
                                        className="underline decoration-border underline-offset-4 hover:decoration-current"
                                    >
                                        ExchangeRate-API
                                    </a>
                                    , plus {rate.margin}%, rounded up to a whole
                                    dollar.
                                </p>
                            </div>
                            <p
                                className={cn(
                                    'flex items-start gap-2 rounded-control p-3 text-sm',
                                    rate.stale && !rate.override
                                        ? 'bg-destructive/10 font-semibold text-destructive'
                                        : 'bg-muted',
                                )}
                            >
                                {rate.stale && !rate.override && (
                                    <TriangleAlert
                                        aria-hidden
                                        className="mt-0.5 size-4 shrink-0"
                                    />
                                )}
                                {rate.override
                                    ? `Using ${naira(rate.override)} to the dollar, set by hand.${rate.fetched ? ` The daily rate is ${naira(Math.round(rate.fetched.rate))}.` : ''}`
                                    : rate.fetched
                                      ? `${naira(Math.round(rate.fetched.rate))} to the dollar, fetched ${rate.fetched.on}.${rate.stale ? ' This is over a week old: the daily fetch is failing. Set a rate by hand below until it recovers.' : ''}`
                                      : 'No rate fetched yet. The site fetches one every morning; until then no dollar prices are shown.'}
                            </p>
                            <Field
                                id="usd_rate_override"
                                label="Set a rate by hand"
                                hint="Only if the daily rate is failing or wrong. Naira per dollar, for example 1550. Leave empty to use the daily rate."
                                error={errors.usd_rate_override}
                                optional
                            >
                                <Input
                                    id="usd_rate_override"
                                    inputMode="numeric"
                                    value={data.usd_rate_override}
                                    aria-invalid={
                                        errors.usd_rate_override
                                            ? true
                                            : undefined
                                    }
                                    onChange={(e) =>
                                        setData(
                                            'usd_rate_override',
                                            digits(e.target.value).slice(0, 6),
                                        )
                                    }
                                    className={cn(
                                        'max-w-40',
                                        rateChanged &&
                                            'border-brand-crimson-strong ring-1 ring-brand-crimson-strong dark:border-brand-crimson dark:ring-brand-crimson',
                                    )}
                                />
                            </Field>
                        </section>

                        {/* Once anything changes: what changed, the reason, and save. */}
                        {changeCount > 0 && (
                            <section
                                aria-labelledby="review-heading"
                                className="sticky bottom-4 z-10 grid gap-4 rounded-card bg-brand-navy p-5 text-white shadow-lg md:p-6 @4xl:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)] dark:bg-brand-night-raised"
                            >
                                <div>
                                    <h2
                                        id="review-heading"
                                        className="font-semibold"
                                    >
                                        {changeCount === 1
                                            ? '1 change'
                                            : `${changeCount} changes`}
                                    </h2>
                                    <ul className="mt-2 grid gap-1 text-sm text-brand-on-dark">
                                        {changes.slice(0, 4).map((change) => (
                                            <li key={change.label}>
                                                {change.label}:{' '}
                                                {shown(
                                                    change.from,
                                                    change.money,
                                                )}{' '}
                                                <span aria-hidden>→</span>
                                                <span className="sr-only">
                                                    to
                                                </span>{' '}
                                                <span className="font-semibold text-white">
                                                    {shown(
                                                        change.to,
                                                        change.money,
                                                    )}
                                                </span>
                                            </li>
                                        ))}
                                        {rateChanged && (
                                            <li>
                                                Dollar rate:{' '}
                                                <span className="font-semibold text-white">
                                                    {data.usd_rate_override
                                                        ? `${naira(Number(data.usd_rate_override))} by hand`
                                                        : 'the daily rate'}
                                                </span>
                                            </li>
                                        )}
                                        {changes.length > 4 && (
                                            <li>
                                                and {changes.length - 4} more
                                            </li>
                                        )}
                                    </ul>
                                </div>
                                <div className="grid gap-3">
                                    <div className="grid gap-1">
                                        <label
                                            htmlFor="reason"
                                            className="text-sm font-semibold"
                                        >
                                            Reason for the change
                                        </label>
                                        <textarea
                                            id="reason"
                                            rows={2}
                                            value={data.reason}
                                            placeholder="For example the Council decision it follows"
                                            aria-invalid={
                                                errors.reason ? true : undefined
                                            }
                                            onChange={(e) =>
                                                setData(
                                                    'reason',
                                                    e.target.value,
                                                )
                                            }
                                            className={cn(
                                                fieldClass,
                                                'text-foreground',
                                            )}
                                        />
                                        <InputError
                                            className="font-semibold text-white"
                                            message={errors.reason}
                                        />
                                        <p className="text-xs text-brand-on-dark">
                                            Kept in the history with your name.
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            type="submit"
                                            disabled={form.processing}
                                            className="bg-white text-brand-navy hover:bg-white/90"
                                        >
                                            Save changes
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            onClick={() => form.reset()}
                                            className="border-white/40 bg-transparent text-white hover:bg-white/10 hover:text-white"
                                        >
                                            Undo my edits
                                        </Button>
                                    </div>
                                </div>
                            </section>
                        )}
                    </form>

                    <section
                        aria-labelledby="history-heading"
                        className="rounded-card border border-border bg-background"
                    >
                        <h2
                            id="history-heading"
                            className="border-b border-border p-4 font-semibold md:px-6"
                        >
                            History
                        </h2>
                        {history.length === 0 ? (
                            <p className="p-4 text-sm text-muted-foreground md:px-6">
                                No changes yet. These are the figures OASIS set
                                in November 2026.
                            </p>
                        ) : (
                            <div className="overflow-x-auto">
                                <table className="w-full min-w-[40rem] text-sm">
                                    <thead>
                                        <tr className="border-b border-border text-left text-muted-foreground">
                                            <th className="px-6 py-2.5 font-medium">
                                                When
                                            </th>
                                            <th className="px-3 py-2.5 font-medium">
                                                What
                                            </th>
                                            <th className="px-3 py-2.5 font-medium">
                                                Change
                                            </th>
                                            <th className="px-3 py-2.5 font-medium">
                                                Reason
                                            </th>
                                            <th className="px-6 py-2.5 font-medium">
                                                Who
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-border">
                                        {history.map((change) => (
                                            <tr
                                                key={change.id}
                                                className="align-top"
                                            >
                                                <td className="px-6 py-3 whitespace-nowrap text-muted-foreground">
                                                    {change.when}
                                                </td>
                                                <td className="px-3 py-3 font-semibold">
                                                    {change.label}
                                                </td>
                                                <td className="px-3 py-3 whitespace-nowrap">
                                                    {figure(
                                                        change.key,
                                                        change.old,
                                                    )}{' '}
                                                    <span aria-hidden>→</span>
                                                    <span className="sr-only">
                                                        to
                                                    </span>{' '}
                                                    <span className="font-semibold">
                                                        {figure(
                                                            change.key,
                                                            change.new,
                                                        )}
                                                    </span>
                                                </td>
                                                <td className="max-w-md px-3 py-3 text-muted-foreground">
                                                    {change.reason}
                                                </td>
                                                <td className="px-6 py-3 whitespace-nowrap text-muted-foreground">
                                                    {change.who ??
                                                        'A deleted account'}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

FeesIndex.layout = {
    breadcrumbs: [{ title: 'Fees', href: index() }],
};
