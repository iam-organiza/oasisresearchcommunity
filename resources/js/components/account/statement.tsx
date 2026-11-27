import type { ReactNode } from 'react';
import { ChevronDown } from 'lucide-react';
import { naira } from '@/components/membership/form-bits';
import { cn } from '@/lib/utils';
import type {
    Statement as StatementData,
    StatementCharge,
    StatementPayment,
} from './types';

/**
 * The totals strip: what is due now, large, then what is coming up (a
 * subscription added ahead of its due date), what has been paid and what
 * has been waived.
 */
export function BalanceStrip({ totals }: { totals: StatementData['totals'] }) {
    return (
        <section
            aria-label="Totals"
            className="grid gap-6 rounded-card bg-brand-navy p-6 text-white md:p-8 @3xl:grid-cols-[minmax(0,1fr)_auto] @3xl:items-end dark:bg-brand-night-raised"
        >
            <div>
                <p className="font-semibold text-brand-on-dark">
                    {totals.due_now > 0 ? 'Due now' : 'Nothing due now'}
                </p>
                <p className="mt-1 font-display text-heading01 font-black">
                    {naira(totals.due_now)}
                </p>
            </div>
            <dl className="flex flex-wrap gap-x-8 gap-y-4 @3xl:justify-end @3xl:text-right">
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
                    <dt className="text-sm text-brand-on-dark">Paid</dt>
                    <dd className="text-heading03 font-semibold">
                        {naira(totals.paid)}
                    </dd>
                </div>
                <div>
                    <dt className="text-sm text-brand-on-dark">Waived</dt>
                    <dd className="text-heading03 font-semibold">
                        {naira(totals.waived)}
                    </dd>
                </div>
            </dl>
        </section>
    );
}

function StatusPill({ status }: { status: string }) {
    return (
        <span
            className={cn(
                'shrink-0 rounded-full px-2.5 py-0.5 text-sm font-semibold',
                (status === 'Paid' || status === 'Waived') &&
                    'bg-primary text-primary-foreground',
                status === 'Part paid' &&
                    'text-brand-crimson-strong ring-1 ring-brand-crimson-strong ring-inset dark:text-brand-crimson dark:ring-brand-crimson',
                status === 'Due' && 'ring-1 ring-border ring-inset',
                status === 'Cancelled' && 'bg-muted text-muted-foreground',
            )}
        >
            {status}
        </span>
    );
}

/**
 * One line of a charge's ledger: what it is on the left, an amount on the
 * right, in the same order and signs as the printed receipt.
 */
function LedgerLine({
    children,
    amount,
    muted = false,
    total = false,
    struck = false,
}: {
    children: ReactNode;
    amount?: string;
    muted?: boolean;
    total?: boolean;
    struck?: boolean;
}) {
    return (
        <li
            className={cn(
                'grid grid-cols-[minmax(0,1fr)_auto] items-baseline gap-x-4 py-2',
                muted && 'text-muted-foreground',
                total && 'mt-1 border-t border-border pt-3 font-semibold',
            )}
        >
            <div className="min-w-0">{children}</div>
            {amount && (
                <span className={cn('text-right', struck && 'line-through')}>
                    {amount}
                </span>
            )}
        </li>
    );
}

function PaymentLine({
    payment,
    actions,
    forStaff,
}: {
    payment: StatementPayment;
    actions?: ReactNode;
    forStaff: boolean;
}) {
    const voided = payment.voided !== null;
    const notes = [
        payment.reference && `Reference ${payment.reference}`,
        forStaff && payment.recorded_by && `Recorded by ${payment.recorded_by}`,
        forStaff && payment.note,
    ].filter(Boolean);

    return (
        <LedgerLine
            amount={`−${naira(payment.amount)}`}
            muted={voided}
            struck={voided}
        >
            {/* Date, method and receipt in columns once there is room, so rows line up. */}
            <div className="grid gap-x-4 @xl:grid-cols-[6.5rem_8rem_minmax(0,1fr)]">
                {/* On a phone the date and method share a line. */}
                <span className="flex flex-wrap gap-x-2 @xl:contents">
                    <span className="tabular-nums">{payment.paid_on}</span>
                    <span>{payment.method}</span>
                </span>
                <span className="flex flex-wrap items-baseline gap-x-2 gap-y-1">
                    {payment.receipt_url ? (
                        <a
                            href={payment.receipt_url}
                            target="_blank"
                            rel="noopener"
                            className="font-semibold tabular-nums underline decoration-border underline-offset-4 hover:decoration-current"
                        >
                            {payment.receipt_number}
                            <span className="sr-only">
                                {' '}
                                (opens the receipt)
                            </span>
                        </a>
                    ) : (
                        <span className="tabular-nums">
                            {payment.receipt_number}
                        </span>
                    )}
                    {voided && (
                        <span className="rounded-full bg-muted px-2 py-px text-xs font-semibold text-muted-foreground">
                            Voided
                        </span>
                    )}
                    {actions}
                </span>
                {notes.length > 0 && (
                    <p className="text-muted-foreground @xl:col-span-2 @xl:col-start-2">
                        {notes.join('. ')}
                    </p>
                )}
                {payment.voided && (
                    <p className="text-muted-foreground @xl:col-span-2 @xl:col-start-2">
                        Voided {payment.voided.on}: {payment.voided.reason}
                    </p>
                )}
            </div>
        </LedgerLine>
    );
}

function ChargeRow({
    charge,
    forStaff,
    chargeActions,
    paymentActions,
}: {
    charge: StatementCharge;
    forStaff: boolean;
    chargeActions?: (charge: StatementCharge) => ReactNode;
    paymentActions?: (payment: StatementPayment) => ReactNode;
}) {
    const cancelled = charge.cancelled !== null;

    return (
        <li className="p-4 md:px-6 md:py-5">
            <div className="flex flex-wrap items-start justify-between gap-x-6 gap-y-2">
                <div className="min-w-0">
                    <h3
                        className={cn(
                            'font-semibold',
                            cancelled && 'text-muted-foreground line-through',
                        )}
                    >
                        {charge.label}
                    </h3>
                    <p className="text-sm text-muted-foreground">
                        Added {charge.added_on}
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <span
                        className={cn(
                            'font-semibold',
                            cancelled && 'text-muted-foreground line-through',
                        )}
                    >
                        {naira(charge.amount)}
                    </span>
                    <StatusPill status={charge.status} />
                </div>
            </div>

            {/* The charge as a small ledger, as the receipt prints it: charge, waiver, payments, then what is left. */}
            <ul className="mt-4 rounded-control bg-muted/50 px-4 py-1 text-sm">
                <LedgerLine amount={naira(charge.amount)} muted={cancelled}>
                    Charge
                </LedgerLine>
                {charge.waiver && (
                    <LedgerLine amount={`−${naira(charge.waiver.amount)}`}>
                        <p>
                            {charge.waiver.description}
                            {charge.waiver.reason &&
                                `: ${charge.waiver.reason}`}
                        </p>
                        {forStaff && charge.waiver.by && (
                            <p className="text-muted-foreground">
                                By {charge.waiver.by}, {charge.waiver.on}
                            </p>
                        )}
                    </LedgerLine>
                )}
                {charge.payments.map((payment) => (
                    <PaymentLine
                        key={payment.id}
                        payment={payment}
                        forStaff={forStaff}
                        actions={!payment.voided && paymentActions?.(payment)}
                    />
                ))}
                {cancelled ? (
                    <LedgerLine total muted>
                        Cancelled {charge.cancelled?.on}:{' '}
                        {charge.cancelled?.reason}
                    </LedgerLine>
                ) : (
                    <LedgerLine
                        total
                        amount={
                            charge.balance > 0 ? naira(charge.balance) : '₦0'
                        }
                    >
                        {charge.balance > 0 ? 'Still to pay' : 'Settled'}
                    </LedgerLine>
                )}
            </ul>

            {chargeActions && !cancelled && (
                <div className="mt-3 flex flex-wrap gap-2">
                    {chargeActions(charge)}
                </div>
            )}
        </li>
    );
}

/**
 * The account as a statement, grouped: charges with something left to pay,
 * then settled ones, then cancelled ones folded away. Each charge keeps
 * its payments and waiver under it.
 */
export function ChargeList({
    charges,
    forStaff = false,
    empty,
    chargeActions,
    paymentActions,
}: {
    charges: StatementCharge[];
    forStaff?: boolean;
    empty: ReactNode;
    chargeActions?: (charge: StatementCharge) => ReactNode;
    paymentActions?: (payment: StatementPayment) => ReactNode;
}) {
    if (charges.length === 0) {
        return (
            <section
                aria-label="Charges"
                className="rounded-card border border-border bg-background"
            >
                <p className="p-10 text-center text-muted-foreground">
                    {empty}
                </p>
            </section>
        );
    }

    const open = charges.filter(
        (charge) => !charge.cancelled && charge.balance > 0,
    );
    const settled = charges.filter(
        (charge) => !charge.cancelled && charge.balance === 0,
    );
    const cancelled = charges.filter((charge) => charge.cancelled);
    const rows = (list: StatementCharge[]) => (
        <ul className="divide-y divide-border rounded-card border border-border bg-background">
            {list.map((charge) => (
                <ChargeRow
                    key={charge.id}
                    charge={charge}
                    forStaff={forStaff}
                    chargeActions={chargeActions}
                    paymentActions={paymentActions}
                />
            ))}
        </ul>
    );

    return (
        <div className="grid gap-6">
            {open.length > 0 && (
                <section aria-labelledby="charges-open" className="grid gap-3">
                    <h2
                        id="charges-open"
                        className="text-heading03 font-semibold"
                    >
                        Open
                    </h2>
                    {rows(open)}
                </section>
            )}
            {settled.length > 0 && (
                <section
                    aria-labelledby="charges-settled"
                    className="grid gap-3"
                >
                    <h2
                        id="charges-settled"
                        className="text-heading03 font-semibold"
                    >
                        Settled
                    </h2>
                    {rows(settled)}
                </section>
            )}
            {cancelled.length > 0 && (
                <details className="group grid gap-3">
                    <summary className="flex w-fit cursor-pointer list-none items-center gap-2 rounded-control text-heading03 font-semibold focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none [&::-webkit-details-marker]:hidden">
                        <ChevronDown
                            aria-hidden
                            className="size-5 -rotate-90 transition-transform group-open:rotate-0"
                        />
                        Cancelled ({cancelled.length})
                    </summary>
                    <div className="mt-3">{rows(cancelled)}</div>
                </details>
            )}
        </div>
    );
}
