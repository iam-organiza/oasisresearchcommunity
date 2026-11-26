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

/** How a stored figure reads in the history: naira, a percentage or a rate. */
function figure(key: string, amount: number | null) {
    if (amount === null) {
        return key === 'usd_rate_override' ? 'daily rate' : '—';
    }
    if (key === 'upgrade_percent') {
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
}) {
    const usd = value === '' ? null : toDollars(Number(value));

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
                    className="pl-7"
                />
            </div>
            <p id={`${id}-usd`} className="text-sm text-muted-foreground">
                {usd === null ? 'No dollar rate yet' : `About ${dollars(usd)}`}
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
    upgradePercent,
    rate,
    history,
}: {
    grades: GradeFees[];
    exam: number;
    upgradePercent: number;
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
        upgrade_percent: String(upgradePercent),
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

    const setGradeFee = (
        kind: 'induction' | 'subscription',
        grade: string,
        value: string,
    ) => setData(kind, { ...data[kind], [grade]: value });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.put(update.url(), {
            preserveScroll: true,
            onSuccess: () => form.setData('reason', ''),
        });
    };

    return (
        <>
            <Head title="Fees" />
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="Fees"
                    description="What each fee is from now on. Charges already on someone's account keep the amount they were added at."
                />

                <div className="grid max-w-6xl gap-6">
                    <form
                        onSubmit={submit}
                        noValidate
                        className="@container grid gap-6"
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
                                Exam and upgrades
                            </h2>
                            <div className="grid content-start gap-1">
                                <MoneyInput
                                    id="exam"
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
                            <Field
                                id="upgrade_percent"
                                label="Upgrade charge"
                                hint="Added to the induction-fee difference when a member moves up a grade within a year of joining it."
                                error={errors.upgrade_percent}
                            >
                                <div className="relative max-w-40">
                                    <Input
                                        id="upgrade_percent"
                                        inputMode="numeric"
                                        value={data.upgrade_percent}
                                        aria-invalid={
                                            errors.upgrade_percent
                                                ? true
                                                : undefined
                                        }
                                        onChange={(e) =>
                                            setData(
                                                'upgrade_percent',
                                                digits(e.target.value).slice(
                                                    0,
                                                    3,
                                                ),
                                            )
                                        }
                                        className="pr-8"
                                    />
                                    <span
                                        aria-hidden
                                        className="pointer-events-none absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground"
                                    >
                                        %
                                    </span>
                                </div>
                            </Field>
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
                                    className="max-w-40"
                                />
                            </Field>
                        </section>

                        <section className="grid gap-4 rounded-card border border-border bg-background p-4 md:p-6">
                            <Field
                                id="reason"
                                label="Reason for the change"
                                hint="Kept in the history with your name, for example the Council decision it follows."
                                error={errors.reason}
                            >
                                <textarea
                                    id="reason"
                                    rows={2}
                                    value={data.reason}
                                    aria-invalid={
                                        errors.reason ? true : undefined
                                    }
                                    onChange={(e) =>
                                        setData('reason', e.target.value)
                                    }
                                    className={fieldClass}
                                />
                            </Field>
                            <div className="flex flex-wrap items-center gap-3">
                                <Button
                                    type="submit"
                                    size="lg"
                                    disabled={form.processing || !form.isDirty}
                                >
                                    Save changes
                                </Button>
                                <Button
                                    type="button"
                                    size="lg"
                                    variant="outline"
                                    disabled={!form.isDirty}
                                    onClick={() => form.reset()}
                                >
                                    Undo my edits
                                </Button>
                            </div>
                        </section>
                    </form>

                    <section className="rounded-card border border-border bg-background p-4 md:p-6">
                        <h2 className="font-semibold">History</h2>
                        {history.length === 0 ? (
                            <p className="mt-2 text-sm text-muted-foreground">
                                No changes yet. These are the figures OASIS set
                                in November 2026.
                            </p>
                        ) : (
                            <ol className="mt-3 grid gap-x-8 gap-y-4 md:grid-cols-2 xl:grid-cols-3">
                                {history.map((change) => (
                                    <li key={change.id} className="text-sm">
                                        <p className="font-semibold">
                                            {change.label}
                                        </p>
                                        <p>
                                            {figure(change.key, change.old)} to{' '}
                                            {figure(change.key, change.new)}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {change.reason}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {change.who ?? 'Someone removed'},{' '}
                                            {change.when}
                                        </p>
                                    </li>
                                ))}
                            </ol>
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
