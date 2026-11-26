import { Head, Link, useForm } from '@inertiajs/react';
import { CircleAlert, CircleCheck, Clock } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { BalanceStrip, ChargeList } from '@/components/account/statement';
import type { Statement, StatementCharge } from '@/components/account/types';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Field, naira } from '@/components/membership/form-bits';
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
                        <dl className="grid grid-cols-[1fr_auto] gap-x-6 gap-y-2 rounded-control bg-muted p-4 text-sm">
                            <dt>
                                {inParts && amount < charge.balance
                                    ? 'Towards the subscription'
                                    : 'Fee'}
                            </dt>
                            <dd className="text-right">
                                {money(figures.fee, data.currency)}
                            </dd>
                            <dt>Paystack's charge</dt>
                            <dd className="text-right">
                                {money(figures.charge, data.currency)}
                            </dd>
                            <div
                                aria-hidden
                                className="col-span-2 border-t border-border"
                            />
                            <dt className="font-semibold">You pay</dt>
                            <dd className="text-right font-semibold">
                                {money(figures.total, data.currency)}
                            </dd>
                        </dl>
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

export default function MembershipAccount({
    statement,
    member,
    notice,
    online,
    waiting,
}: {
    statement: Statement;
    member: { number: string; active: boolean; status: string } | null;
    notice: Notice | null;
    online: Online | null;
    waiting: Waiting[];
}) {
    const [paying, setPaying] = useState<StatementCharge | null>(null);
    const description = member
        ? `${member.number} · ${member.status}`
        : 'Fees appear here once you have something to pay, such as the exam fee or, after approval, your induction fee.';
    const NoticeIcon =
        notice?.tone === 'success'
            ? CircleCheck
            : notice?.tone === 'error'
              ? CircleAlert
              : Clock;

    return (
        <>
            <Head title="Fees and payments" />
            <div className="@container px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading title="Fees and payments" description={description} />

                <div className="grid max-w-5xl gap-6">
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

                    <BalanceStrip
                        totals={statement.totals}
                        owedLabel="You owe"
                        showDollars
                    />

                    {waiting.length > 0 && (
                        <section className="rounded-card border border-border bg-background p-4 md:px-6">
                            <h2 className="font-semibold">
                                Waiting for Paystack to confirm
                            </h2>
                            <ul className="mt-2 grid gap-1 text-sm">
                                {waiting.map((item) => (
                                    <li key={item.reference}>
                                        {item.total} towards {item.charge},
                                        started {item.started}
                                    </li>
                                ))}
                            </ul>
                            <p className="mt-2 text-sm text-muted-foreground">
                                If you paid, it shows here within a few minutes;
                                please don't pay again. If you cancelled on
                                Paystack, you can ignore this.
                            </p>
                        </section>
                    )}

                    <ChargeList
                        charges={statement.charges}
                        empty={
                            <>
                                Nothing to pay yet.{' '}
                                <Link
                                    href={show()}
                                    className="font-semibold text-foreground underline decoration-border underline-offset-4 hover:decoration-current"
                                >
                                    See your membership
                                </Link>
                            </>
                        }
                        chargeActions={
                            online
                                ? (charge) =>
                                      charge.balance > 0 && (
                                          <Button
                                              size="sm"
                                              onClick={() => setPaying(charge)}
                                          >
                                              Pay online
                                              <span className="sr-only">
                                                  {' '}
                                                  for {charge.label}
                                              </span>
                                          </Button>
                                      )
                                : undefined
                        }
                    />
                    <p className="max-w-prose text-sm text-muted-foreground">
                        {online
                            ? "Pay online with Paystack, or by bank transfer as the Secretariat tells you; the Registrar then records it here. Paystack's charge is added to online payments. "
                            : 'Online payment is coming. Until then, pay by bank transfer as the Secretariat tells you; the Registrar records it here. '}
                        Your receipt arrives by email, and each receipt number
                        above opens a copy you can print. Exam and induction
                        fees are paid in one go; a yearly subscription can be
                        paid in parts.
                        {statement.totals.owed_usd !== null && (
                            <>
                                {' '}
                                The dollar amount uses the day's rate from{' '}
                                <a
                                    href="https://www.exchangerate-api.com"
                                    target="_blank"
                                    rel="noopener"
                                    className="underline decoration-border underline-offset-4 hover:decoration-current"
                                >
                                    ExchangeRate-API
                                </a>{' '}
                                plus 5%, rounded up.
                            </>
                        )}
                    </p>
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

MembershipAccount.layout = {
    breadcrumbs: [{ title: 'Fees and payments', href: account() }],
};
