import { Head, Link, useForm } from '@inertiajs/react';
import { CircleAlert, CircleCheck, Clock } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import type { Statement, StatementCharge } from '@/components/account/types';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { dollars, Field, naira } from '@/components/membership/form-bits';
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
import { cn } from '@/lib/utils';
import { account, show } from '@/routes/membership';
import { start } from '@/routes/membership/payments';

type Online = {
    usd: boolean;
    fees: {
        ngn: {
            percent: number;
            flat: number;
            flat_waived_below: number;
            cap: number;
        };
        usd: { percent: number };
    };
    rate: number | null;
    margin: number;
    minimum_part: number;
};

type Notice = { tone: 'success' | 'error' | 'info'; message: string };

type Waiting = {
    reference: string;
    charge: string;
    total: string;
    started: string;
};

/**
 * What the member pays for an amount in naira: the fee, Paystack's charge
 * and the total, in naira or whole cents. The server works this out again
 * from the database before anything is charged (App\Account\Paystack).
 */
function quote(amount: number, currency: 'NGN' | 'USD', online: Online) {
    if (currency === 'USD') {
        if (!online.rate) {
            return null;
        }
        const usd = Math.ceil(
            Math.round(
                (amount / online.rate) * (1 + online.margin / 100) * 100,
            ) / 100,
        );
        const total = Math.ceil(
            (usd * 100) / (1 - online.fees.usd.percent / 100),
        );

        return { fee: usd * 100, charge: total - usd * 100, total };
    }

    const {
        percent,
        flat,
        flat_waived_below: waivedBelow,
        cap,
    } = online.fees.ngn;
    const minor = amount * 100;
    const rate = 1 - percent / 100;
    let total = Math.ceil((minor + flat * 100) / rate / 100) * 100;
    if (total < waivedBelow * 100) {
        total = Math.ceil(minor / rate / 100) * 100;
    }
    total = Math.min(total, minor + cap * 100);

    return { fee: minor, charge: total - minor, total };
}

const money = (minor: number, currency: 'NGN' | 'USD') =>
    currency === 'USD'
        ? `US$${(minor / 100).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`
        : naira(Math.round(minor / 100));

function PayDialog({
    charge,
    online,
    onClose,
}: {
    charge: StatementCharge;
    online: Online;
    onClose: () => void;
}) {
    const inParts = charge.kind === 'subscription';
    const form = useForm({
        amount: String(charge.balance),
        currency: 'NGN' as 'NGN' | 'USD',
    });
    const { data, setData, errors } = form;
    const amount = Number(data.amount || 0);
    const figures = amount > 0 ? quote(amount, data.currency, online) : null;

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(start.url(charge.id), { preserveScroll: true });
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90dvh] overflow-y-auto sm:max-w-md">
                <form onSubmit={submit} noValidate className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>Pay online</DialogTitle>
                        <DialogDescription>
                            {charge.label}. You pay on Paystack's secure page by
                            card, bank transfer or USSD, then come back here for
                            your receipt.
                        </DialogDescription>
                    </DialogHeader>

                    {inParts && (
                        <Field
                            id="amount"
                            label="How much to pay now, in naira"
                            hint={`A subscription can be paid in parts, at least ${naira(Math.min(online.minimum_part, charge.balance))} at a time. ${naira(charge.balance)} is left.`}
                            error={errors.amount}
                        >
                            <Input
                                id="amount"
                                inputMode="numeric"
                                value={
                                    data.amount === ''
                                        ? ''
                                        : Number(data.amount).toLocaleString(
                                              'en-NG',
                                          )
                                }
                                aria-invalid={errors.amount ? true : undefined}
                                onChange={(e) =>
                                    setData(
                                        'amount',
                                        e.target.value.replace(/\D/g, ''),
                                    )
                                }
                            />
                        </Field>
                    )}

                    {online.usd && (
                        <fieldset className="grid gap-2">
                            <legend className="mb-1 font-semibold">
                                Pay in
                            </legend>
                            <div className="grid grid-cols-2 gap-2">
                                {(['NGN', 'USD'] as const).map((currency) => (
                                    <label
                                        key={currency}
                                        className={cn(
                                            'flex cursor-pointer items-center justify-center rounded-control border p-2.5 font-semibold',
                                            'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                                            data.currency === currency
                                                ? 'border-brand-crimson-strong ring-1 ring-brand-crimson-strong dark:border-brand-crimson dark:ring-brand-crimson'
                                                : 'border-border hover:border-foreground/40',
                                        )}
                                    >
                                        <input
                                            type="radio"
                                            name="currency"
                                            value={currency}
                                            checked={data.currency === currency}
                                            onChange={() =>
                                                setData('currency', currency)
                                            }
                                            className="sr-only"
                                        />
                                        {currency === 'NGN'
                                            ? 'Naira'
                                            : 'US dollars'}
                                    </label>
                                ))}
                            </div>
                            <InputError
                                className="font-semibold text-destructive"
                                message={errors.currency}
                            />
                        </fieldset>
                    )}

                    {!inParts && (
                        <InputError
                            className="font-semibold text-destructive"
                            message={errors.amount}
                        />
                    )}

                    {figures && (
                        <div className="rounded-control border border-border">
                            <dl className="grid grid-cols-[1fr_auto] gap-x-6 gap-y-2 p-4 text-sm">
                                <dt className="text-muted-foreground">
                                    {inParts && amount < charge.balance
                                        ? 'Towards the subscription'
                                        : 'Fee'}
                                </dt>
                                <dd className="text-right">
                                    {money(figures.fee, data.currency)}
                                </dd>
                                <dt className="text-muted-foreground">
                                    Paystack's charge
                                </dt>
                                <dd className="text-right">
                                    {money(figures.charge, data.currency)}
                                </dd>
                            </dl>
                            <div className="flex items-baseline justify-between gap-4 rounded-b-control bg-brand-navy px-4 py-3 text-white dark:bg-brand-night-raised">
                                <span className="font-semibold text-brand-on-dark">
                                    You pay
                                </span>
                                <span className="text-heading03 font-bold">
                                    {money(figures.total, data.currency)}
                                </span>
                            </div>
                        </div>
                    )}
                    {data.currency === 'USD' && (
                        <p className="text-sm text-muted-foreground">
                            Dollars are the naira fee at today's rate plus{' '}
                            {online.margin}%, rounded up. The amount is fixed
                            when you continue.
                        </p>
                    )}

                    <DialogFooter className="gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={onClose}
                        >
                            Close
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing || !figures}
                        >
                            Continue to Paystack
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

/** One line in "Payments and receipts": a payment, a waiver or a cancellation. */
type HistoryRow = {
    key: string;
    date: string;
    what: string;
    how: string;
    receipt: { number: string; url: string | null } | null;
    amount: string | null;
    note: string | null;
    voided: boolean;
};

/** Every payment, waiver and cancellation across the charges, newest first. */
function historyRows(charges: StatementCharge[]): HistoryRow[] {
    const rows: (HistoryRow & { sort: string })[] = [];
    // ISO dates sort as text; the id keeps same-day lines in the order they happened.
    const key = (date: string | null, id: number) =>
        `${date ?? ''}#${String(id).padStart(10, '0')}`;

    for (const charge of charges) {
        for (const payment of charge.payments) {
            rows.push({
                key: `p${payment.id}`,
                date: payment.paid_on,
                what: charge.label,
                how: payment.method,
                receipt: {
                    number: payment.receipt_number,
                    url: payment.receipt_url,
                },
                amount: naira(payment.amount),
                note: payment.voided
                    ? `Voided ${payment.voided.on}: ${payment.voided.reason}`
                    : payment.reference
                      ? `Reference ${payment.reference}`
                      : null,
                voided: payment.voided !== null,
                sort: key(payment.paid_on_iso, payment.id),
            });
        }
        if (charge.waiver) {
            rows.push({
                key: `w${charge.id}`,
                date: charge.waiver.on ?? charge.added_on,
                what: charge.label,
                how: 'Waived',
                receipt: null,
                amount: naira(charge.waiver.amount),
                note: [charge.waiver.description, charge.waiver.reason]
                    .filter(Boolean)
                    .join(': '),
                voided: false,
                sort: key(
                    charge.waiver.on_iso ?? charge.added_on_iso,
                    charge.id,
                ),
            });
        }
        if (charge.cancelled) {
            rows.push({
                key: `c${charge.id}`,
                date: charge.cancelled.on ?? charge.added_on,
                what: charge.label,
                how: 'Cancelled',
                receipt: null,
                amount: null,
                note: charge.cancelled.reason,
                voided: false,
                sort: key(
                    charge.cancelled.on_iso ?? charge.added_on_iso,
                    charge.id,
                ),
            });
        }
    }

    return rows.sort((a, b) => b.sort.localeCompare(a.sort));
}

/** A fee with something left to pay, and its Pay online button. */
function ToPayCard({
    charge,
    canPayOnline,
    onPay,
}: {
    charge: StatementCharge;
    canPayOnline: boolean;
    onPay: () => void;
}) {
    const partPaid = charge.balance < charge.amount;

    return (
        <li className="flex flex-col gap-4 rounded-card border border-border bg-background p-5 md:p-6 @2xl:flex-row @2xl:items-center @2xl:justify-between">
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <h3 className="font-semibold">{charge.label}</h3>
                    <span
                        className={cn(
                            'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                            charge.due_now
                                ? 'bg-brand-crimson-strong text-white dark:bg-brand-crimson'
                                : 'ring-1 ring-border ring-inset',
                        )}
                    >
                        {charge.due_now ? 'Due now' : 'Not due yet'}
                    </span>
                </div>
                <p className="mt-2">
                    <span className="text-heading03 font-bold">
                        {naira(charge.balance)}
                    </span>{' '}
                    <span className="text-sm text-muted-foreground">
                        {partPaid
                            ? `left of ${naira(charge.amount)}`
                            : 'to pay'}
                    </span>
                </p>
                {charge.waiver && (
                    <p className="mt-1 text-sm text-muted-foreground">
                        {charge.waiver.description}
                    </p>
                )}
            </div>
            {canPayOnline && (
                <Button size="lg" onClick={onPay} className="shrink-0">
                    Pay online
                    <span className="sr-only"> for {charge.label}</span>
                </Button>
            )}
        </li>
    );
}

/** The ways to pay, beside the fees on wide screens and under them on a phone. */
function HowToPay({
    online,
    showRate,
}: {
    online: Online | null;
    showRate: boolean;
}) {
    const items: { title: string; body: ReactNode }[] = [
        online
            ? {
                  title: 'Online with Paystack',
                  body: "By card, bank transfer or USSD on Paystack's secure page. Paystack's charge is added to what you pay.",
              }
            : {
                  title: 'Online payment is coming',
                  body: 'Until then, pay by bank transfer.',
              },
        {
            title: 'By bank transfer',
            body: 'As the Secretariat tells you. The Registrar then records it here.',
        },
        {
            title: 'Your receipt',
            body: 'Arrives by email. Each receipt number on this page opens a copy you can print.',
        },
        {
            title: 'Paying in parts',
            body: 'Exam and induction fees are paid in one go; a yearly subscription can be paid in parts.',
        },
    ];

    return (
        <section
            aria-labelledby="how-to-pay"
            className="rounded-card border border-border bg-background p-6"
        >
            <h2 id="how-to-pay" className="text-heading03 font-semibold">
                How to pay
            </h2>
            <dl className="mt-4 grid gap-4 text-sm">
                {items.map((item) => (
                    <div key={item.title}>
                        <dt className="font-semibold">{item.title}</dt>
                        <dd className="mt-0.5 text-muted-foreground">
                            {item.body}
                        </dd>
                    </div>
                ))}
            </dl>
            {showRate && (
                <p className="mt-4 border-t border-border pt-4 text-sm text-muted-foreground">
                    Dollar amounts use the day's rate from{' '}
                    <a
                        href="https://www.exchangerate-api.com"
                        target="_blank"
                        rel="noopener"
                        className="underline decoration-border underline-offset-4 hover:decoration-current"
                    >
                        ExchangeRate-API
                    </a>{' '}
                    plus 5%, rounded up.
                </p>
            )}
        </section>
    );
}

export default function MembershipAccount({
    statement,
    member,
    notice,
    online,
    waiting,
}: {
    statement: Statement;
    member: { number: string | null; active: boolean; status: string } | null;
    notice: Notice | null;
    online: Online | null;
    waiting: Waiting[];
}) {
    const [paying, setPaying] = useState<StatementCharge | null>(null);
    const { totals } = statement;
    const description = member
        ? member.number
            ? `${member.number}, ${member.status.toLowerCase()}`
            : `${member.status}. Your membership number is issued once it is paid.`
        : 'Fees appear here once you have something to pay, such as the exam fee or, after approval, your induction fee.';
    const NoticeIcon =
        notice?.tone === 'success'
            ? CircleCheck
            : notice?.tone === 'error'
              ? CircleAlert
              : Clock;
    const toPay = statement.charges
        .filter((charge) => !charge.cancelled && charge.balance > 0)
        // Due now first, then by due date as the server listed them.
        .sort((a, b) => Number(b.due_now) - Number(a.due_now));
    const history = historyRows(statement.charges);

    return (
        <>
            <Head title="Fees and payments" />
            <div className="@container w-full max-w-7xl px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading title="Fees and payments" description={description} />

                <div className="grid items-start gap-6 @4xl:grid-cols-[minmax(0,1fr)_20rem] @4xl:gap-10 @6xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <div className="grid min-w-0 gap-8">
                        {notice && (
                            <p
                                role="status"
                                className={cn(
                                    'flex items-start gap-3 rounded-control border p-4',
                                    notice.tone === 'success' &&
                                        'border-primary/30 bg-primary/5',
                                    notice.tone === 'error' &&
                                        'border-destructive/40 bg-destructive/5',
                                    notice.tone === 'info' &&
                                        'border-border bg-muted',
                                )}
                            >
                                <NoticeIcon
                                    aria-hidden
                                    className={cn(
                                        'mt-0.5 size-5 shrink-0',
                                        notice.tone === 'error' &&
                                            'text-destructive',
                                    )}
                                />
                                {notice.message}
                            </p>
                        )}

                        <section
                            aria-label="Totals"
                            className="grid gap-6 rounded-card bg-brand-navy p-6 text-white md:p-8 @2xl:grid-cols-[minmax(0,1fr)_auto] @2xl:items-end dark:bg-brand-night-raised"
                        >
                            <div>
                                <p className="font-semibold text-brand-on-dark">
                                    {totals.due_now > 0
                                        ? 'Due now'
                                        : 'Nothing due now'}
                                </p>
                                <p className="mt-1 font-display text-heading01 font-black">
                                    {naira(totals.due_now)}
                                </p>
                                {totals.due_now_usd !== null && (
                                    <p className="mt-2 text-sm text-brand-on-dark">
                                        About {dollars(totals.due_now_usd)} if
                                        you pay from abroad
                                    </p>
                                )}
                            </div>
                            <dl className="flex flex-wrap gap-x-8 gap-y-4 @2xl:justify-end @2xl:text-right">
                                {totals.coming_up && (
                                    <div>
                                        <dt className="text-sm text-brand-on-dark">
                                            Coming up
                                        </dt>
                                        <dd className="text-heading03 font-semibold">
                                            {naira(totals.coming_up.amount)}
                                        </dd>
                                        <dd className="text-sm text-brand-on-dark">
                                            Due {totals.coming_up.on}
                                        </dd>
                                    </div>
                                )}
                                <div>
                                    <dt className="text-sm text-brand-on-dark">
                                        Paid so far
                                    </dt>
                                    <dd className="text-heading03 font-semibold">
                                        {naira(totals.paid)}
                                    </dd>
                                </div>
                                {totals.waived > 0 && (
                                    <div>
                                        <dt className="text-sm text-brand-on-dark">
                                            Waived
                                        </dt>
                                        <dd className="text-heading03 font-semibold">
                                            {naira(totals.waived)}
                                        </dd>
                                    </div>
                                )}
                            </dl>
                        </section>

                        {waiting.length > 0 && (
                            <section className="flex items-start gap-3 rounded-card border border-border bg-muted/60 p-5">
                                <Clock
                                    aria-hidden
                                    className="mt-0.5 size-5 shrink-0"
                                />
                                <div>
                                    <h2 className="font-semibold">
                                        Waiting for Paystack to confirm
                                    </h2>
                                    <ul className="mt-1 grid gap-1 text-sm">
                                        {waiting.map((item) => (
                                            <li key={item.reference}>
                                                {item.total} towards{' '}
                                                {item.charge}, started{' '}
                                                {item.started}
                                            </li>
                                        ))}
                                    </ul>
                                    <p className="mt-2 text-sm text-muted-foreground">
                                        If you paid, it shows here within a few
                                        minutes; please don't pay again. If you
                                        cancelled on Paystack, you can ignore
                                        this.
                                    </p>
                                </div>
                            </section>
                        )}

                        <section aria-labelledby="to-pay">
                            <h2
                                id="to-pay"
                                className="text-heading03 font-semibold"
                            >
                                To pay
                            </h2>
                            {toPay.length > 0 ? (
                                <ul className="mt-4 grid gap-3">
                                    {toPay.map((charge) => (
                                        <ToPayCard
                                            key={charge.id}
                                            charge={charge}
                                            canPayOnline={online !== null}
                                            onPay={() => setPaying(charge)}
                                        />
                                    ))}
                                </ul>
                            ) : (
                                <p className="mt-2 text-muted-foreground">
                                    Nothing to pay.{' '}
                                    <Link
                                        href={show()}
                                        className="font-semibold text-foreground underline decoration-border underline-offset-4 hover:decoration-current"
                                    >
                                        See your membership
                                    </Link>
                                </p>
                            )}
                        </section>

                        {history.length > 0 && (
                            <section aria-labelledby="receipts">
                                <h2
                                    id="receipts"
                                    className="text-heading03 font-semibold"
                                >
                                    Payments and receipts
                                </h2>
                                <ul className="mt-4 divide-y divide-border rounded-card border border-border bg-background">
                                    {history.map((row) => (
                                        <li
                                            key={row.key}
                                            className={cn(
                                                'grid grid-cols-[minmax(0,1fr)_auto] gap-x-4 gap-y-1 px-5 py-4 md:px-6',
                                                '@3xl:grid-cols-[7rem_minmax(0,1fr)_auto_minmax(6.5rem,auto)] @3xl:items-baseline @3xl:gap-x-6',
                                                row.voided &&
                                                    'text-muted-foreground',
                                            )}
                                        >
                                            <span className="text-sm text-muted-foreground @3xl:order-none">
                                                {row.date}
                                            </span>
                                            <span
                                                className={cn(
                                                    'row-span-2 self-center text-right font-semibold @3xl:col-start-4 @3xl:row-span-1 @3xl:row-start-1',
                                                    row.voided &&
                                                        'line-through',
                                                )}
                                            >
                                                {row.how === 'Waived' &&
                                                row.amount
                                                    ? `−${row.amount}`
                                                    : (row.amount ?? '—')}
                                            </span>
                                            <span className="min-w-0 @3xl:col-start-2 @3xl:row-start-1">
                                                <span className="block font-semibold">
                                                    {row.what}
                                                </span>
                                                <span className="block text-sm text-muted-foreground">
                                                    {row.how}
                                                    {row.receipt && (
                                                        <span className="@3xl:hidden">
                                                            {', '}
                                                            <ReceiptLink
                                                                receipt={
                                                                    row.receipt
                                                                }
                                                            />
                                                        </span>
                                                    )}
                                                </span>
                                                {row.note && (
                                                    <span className="block text-sm text-muted-foreground">
                                                        {row.note}
                                                    </span>
                                                )}
                                            </span>
                                            <span className="hidden text-sm @3xl:col-start-3 @3xl:row-start-1 @3xl:block">
                                                {row.receipt && (
                                                    <ReceiptLink
                                                        receipt={row.receipt}
                                                    />
                                                )}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        )}
                    </div>

                    <aside className="@4xl:sticky @4xl:top-6">
                        <HowToPay
                            online={online}
                            showRate={totals.due_now_usd !== null}
                        />
                    </aside>
                </div>
            </div>

            {paying && online && (
                <PayDialog
                    charge={paying}
                    online={online}
                    onClose={() => setPaying(null)}
                />
            )}
        </>
    );
}

function ReceiptLink({
    receipt,
}: {
    receipt: { number: string; url: string | null };
}) {
    return receipt.url ? (
        <a
            href={receipt.url}
            target="_blank"
            rel="noopener"
            className="font-semibold text-foreground underline decoration-border underline-offset-4 hover:decoration-current"
        >
            {receipt.number}
            <span className="sr-only"> (opens the receipt)</span>
        </a>
    ) : (
        <span>{receipt.number}</span>
    );
}

MembershipAccount.layout = {
    breadcrumbs: [{ title: 'Fees and payments', href: account() }],
};
