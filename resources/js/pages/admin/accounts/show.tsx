import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowUpCircle, ChevronDown, Plus } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import { BalanceStrip, ChargeList } from '@/components/account/statement';
import type {
    Statement,
    StatementCharge,
    StatementPayment,
} from '@/components/account/types';
import Heading from '@/components/heading';
import {
    Checkbox,
    ChoiceTiles,
    Field,
    fieldClass,
    naira,
    selectClass,
} from '@/components/membership/form-bits';
import type { Option } from '@/components/membership/form-bits';
import { useConfirm } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { upgrade as upgradeRoute } from '@/routes/admin/accounts';
import { store as storeCharge } from '@/routes/admin/accounts/charges';
import { show as showApplication } from '@/routes/admin/applications';
import { cancel, waiver } from '@/routes/admin/charges';
import { store as storePayment } from '@/routes/admin/charges/payments';
import { index as register } from '@/routes/admin/members';
import { voidMethod as voidPayment } from '@/routes/admin/payments';

type Person = {
    id: number;
    name: string;
    email: string;
    number: string | null;
    grade: string | null;
    status: string | null;
    active: boolean;
    application_id: number | null;
};

type Defaults = {
    exam: number;
    induction: number | null;
    subscription: number | null;
};

type Quote = { amount: number; rule: string };

type UpgradeOptions = {
    grade_since: string;
    years: number;
    grades: (Option & { standard: Quote; promotion: Quote })[];
};

type Action =
    | { type: 'charge' }
    | { type: 'upgrade' }
    | { type: 'payment'; charge: StatementCharge }
    | { type: 'waive'; charge: StatementCharge }
    | { type: 'cancel'; charge: StatementCharge }
    | { type: 'void'; payment: StatementPayment };

const today = () => new Date().toISOString().slice(0, 10);

/** A dialog holding one form; it closes itself once the save succeeds. */
function FormDialog({
    title,
    description,
    submitLabel,
    destructive = false,
    processing,
    onSubmit,
    onClose,
    children,
}: {
    title: string;
    description: string;
    submitLabel: string;
    destructive?: boolean;
    processing: boolean;
    onSubmit: () => void;
    onClose: () => void;
    children: ReactNode;
}) {
    const submit = (event: FormEvent) => {
        event.preventDefault();
        onSubmit();
    };

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="@container max-h-[90dvh] overflow-y-auto sm:max-w-lg">
                <form onSubmit={submit} noValidate className="grid gap-5">
                    <DialogHeader>
                        <DialogTitle>{title}</DialogTitle>
                        <DialogDescription>{description}</DialogDescription>
                    </DialogHeader>
                    {children}
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
                            variant={destructive ? 'destructive' : 'default'}
                            disabled={processing}
                        >
                            {submitLabel}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function UpgradeDialog({
    person,
    upgrade,
    onClose,
}: {
    person: Person;
    upgrade: UpgradeOptions;
    onClose: () => void;
}) {
    const form = useForm({
        grade: upgrade.grades[upgrade.grades.length - 1]?.value ?? '',
        promotion: false,
    });
    const { data, setData, errors } = form;
    const chosen = upgrade.grades.find((grade) => grade.value === data.grade);
    const quote = chosen
        ? data.promotion
            ? chosen.promotion
            : chosen.standard
        : null;

    return (
        <FormDialog
            title="Offer an upgrade"
            description={`${person.name} has been a ${person.grade} since ${upgrade.grade_since}. They move up once the upgrade fee is paid, and their next yearly subscription is due 12 months after that.`}
            submitLabel="Offer the upgrade"
            processing={form.processing}
            onClose={onClose}
            onSubmit={() =>
                form.post(upgradeRoute.url(person.id), {
                    preserveScroll: true,
                    onSuccess: onClose,
                })
            }
        >
            <ChoiceTiles
                name="grade"
                legend="Move up to"
                options={upgrade.grades}
                value={data.grade}
                onChange={(value) => setData('grade', value)}
                error={errors.grade}
            />
            <Checkbox
                id="promotion"
                checked={data.promotion}
                onChange={(checked) => setData('promotion', checked)}
            >
                A promotion for three years of consistent training and
                professional development
            </Checkbox>
            {quote && (
                <div className="rounded-control bg-muted p-4">
                    <p className="text-sm text-muted-foreground">
                        {quote.rule}
                    </p>
                    <p className="mt-1 text-heading03 font-semibold">
                        {naira(quote.amount)}
                    </p>
                </div>
            )}
        </FormDialog>
    );
}

function AddChargeDialog({
    person,
    kinds,
    defaults,
    year,
    onClose,
}: {
    person: Person;
    kinds: Option[];
    defaults: Defaults;
    year: number;
    onClose: () => void;
}) {
    const form = useForm({ kind: '', amount: '', year: String(year) });
    const { data, setData, errors } = form;
    const choose = (kind: string) => {
        const amount = defaults[kind as keyof Defaults];
        form.setData({ ...data, kind, amount: amount ? String(amount) : '' });
    };
    // An induction fee is added when someone joins the register, so it is not offered here.
    const options = kinds.filter(
        (kind) => kind.value !== 'induction' || person.number,
    );

    return (
        <FormDialog
            title="Add a charge"
            description={`A fee ${person.name} owes. The amount stays as entered even if the fees change later.`}
            submitLabel="Add the charge"
            processing={form.processing}
            onClose={onClose}
            onSubmit={() =>
                form.post(storeCharge.url(person.id), {
                    preserveScroll: true,
                    onSuccess: onClose,
                })
            }
        >
            <ChoiceTiles
                name="kind"
                legend="What is it for?"
                options={options}
                value={data.kind}
                onChange={choose}
                error={errors.kind}
            />
            <div className="grid gap-5 @sm:grid-cols-2">
                <Field
                    id="amount"
                    label="Amount in naira"
                    hint={
                        data.kind && defaults[data.kind as keyof Defaults]
                            ? `The current fee is ${naira(defaults[data.kind as keyof Defaults] ?? 0)}.`
                            : undefined
                    }
                    error={errors.amount}
                >
                    <Input
                        id="amount"
                        inputMode="numeric"
                        value={data.amount}
                        aria-invalid={errors.amount ? true : undefined}
                        onChange={(e) =>
                            setData('amount', e.target.value.replace(/\D/g, ''))
                        }
                    />
                </Field>
                {data.kind === 'subscription' && (
                    <Field id="year" label="Year it covers" error={errors.year}>
                        <Input
                            id="year"
                            inputMode="numeric"
                            value={data.year}
                            aria-invalid={errors.year ? true : undefined}
                            onChange={(e) =>
                                setData(
                                    'year',
                                    e.target.value
                                        .replace(/\D/g, '')
                                        .slice(0, 4),
                                )
                            }
                        />
                    </Field>
                )}
            </div>
        </FormDialog>
    );
}

function PaymentDialog({
    charge,
    methods,
    email,
    onClose,
}: {
    charge: StatementCharge;
    methods: Option[];
    email: string;
    onClose: () => void;
}) {
    const form = useForm({
        amount: String(charge.balance),
        method: methods[0]?.value ?? '',
        paid_on: today(),
        reference: '',
        note: '',
    });
    const { data, setData, errors } = form;
    // Only subscriptions are paid in parts; exam and induction fees in one go.
    const inParts = charge.kind === 'subscription';

    return (
        <FormDialog
            title="Record a payment"
            description={`Towards ${charge.label}. ${naira(charge.balance)} is still to pay. A receipt is emailed to ${email}.`}
            submitLabel="Record the payment"
            processing={form.processing}
            onClose={onClose}
            onSubmit={() =>
                form.post(storePayment.url(charge.id), {
                    preserveScroll: true,
                    onSuccess: onClose,
                })
            }
        >
            <div className="grid gap-5 @sm:grid-cols-2">
                <Field
                    id="amount"
                    label="Amount received, in naira"
                    hint={
                        inParts
                            ? 'Subscriptions can be paid in parts.'
                            : 'Paid in one go, so this is the full amount.'
                    }
                    error={errors.amount}
                >
                    <Input
                        id="amount"
                        inputMode="numeric"
                        readOnly={!inParts}
                        value={data.amount}
                        aria-invalid={errors.amount ? true : undefined}
                        onChange={(e) =>
                            setData('amount', e.target.value.replace(/\D/g, ''))
                        }
                    />
                </Field>
                <Field id="paid_on" label="Date paid" error={errors.paid_on}>
                    <Input
                        id="paid_on"
                        type="date"
                        max={today()}
                        value={data.paid_on}
                        aria-invalid={errors.paid_on ? true : undefined}
                        onChange={(e) => setData('paid_on', e.target.value)}
                    />
                </Field>
                <Field id="method" label="Paid by" error={errors.method}>
                    <select
                        id="method"
                        value={data.method}
                        onChange={(e) => setData('method', e.target.value)}
                        className={selectClass}
                    >
                        {methods.map((method) => (
                            <option key={method.value} value={method.value}>
                                {method.label}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field
                    id="reference"
                    label="Bank reference"
                    error={errors.reference}
                    optional
                >
                    <Input
                        id="reference"
                        value={data.reference}
                        onChange={(e) => setData('reference', e.target.value)}
                    />
                </Field>
            </div>
            <Field
                id="note"
                label="Note for staff"
                hint="Only staff see this."
                error={errors.note}
                optional
            >
                <textarea
                    id="note"
                    rows={2}
                    value={data.note}
                    onChange={(e) => setData('note', e.target.value)}
                    className={fieldClass}
                />
            </Field>
        </FormDialog>
    );
}

function WaiveDialog({
    charge,
    waiverTypes,
    onClose,
}: {
    charge: StatementCharge;
    waiverTypes: Option[];
    onClose: () => void;
}) {
    const form = useForm({
        type: charge.waiver?.type ?? 'full',
        value: charge.waiver?.value ? String(charge.waiver.value) : '',
        reason: charge.waiver?.reason ?? '',
    });
    const { data, setData, errors } = form;
    const value = Number(data.value || 0);
    const takesOff =
        data.type === 'full'
            ? charge.amount
            : data.type === 'percent'
              ? Math.round((charge.amount * Math.min(100, value)) / 100)
              : Math.min(charge.amount, value);

    return (
        <FormDialog
            title={charge.waiver ? 'Change the waiver' : 'Waive this charge'}
            description={`${charge.label}, ${naira(charge.amount)}. For a scholarship, for example. The member sees the reason.`}
            submitLabel="Save the waiver"
            processing={form.processing}
            onClose={onClose}
            onSubmit={() =>
                form.post(waiver.url(charge.id), {
                    preserveScroll: true,
                    onSuccess: onClose,
                })
            }
        >
            <ChoiceTiles
                name="type"
                legend="How much?"
                options={waiverTypes}
                value={data.type}
                onChange={(type) => form.setData({ ...data, type, value: '' })}
                error={errors.type}
            />
            {data.type !== 'full' && (
                <Field
                    id="value"
                    label={
                        data.type === 'percent'
                            ? 'Percentage to take off'
                            : 'Amount to take off, in naira'
                    }
                    hint={
                        value > 0
                            ? `Takes off ${naira(takesOff)}. ${
                                  charge.paid > 0
                                      ? `With ${naira(charge.paid)} already paid, ${naira(Math.max(0, charge.amount - takesOff - charge.paid))} would be left to pay.`
                                      : `${naira(charge.amount - takesOff)} would be left to pay.`
                              }`
                            : undefined
                    }
                    error={errors.value}
                >
                    <Input
                        id="value"
                        inputMode="numeric"
                        value={data.value}
                        aria-invalid={errors.value ? true : undefined}
                        onChange={(e) =>
                            setData('value', e.target.value.replace(/\D/g, ''))
                        }
                    />
                </Field>
            )}
            {data.type === 'full' && errors.value && (
                <p className="text-sm font-semibold text-destructive">
                    {errors.value}
                </p>
            )}
            <Field id="reason" label="Reason" error={errors.reason}>
                <textarea
                    id="reason"
                    rows={2}
                    value={data.reason}
                    aria-invalid={errors.reason ? true : undefined}
                    onChange={(e) => setData('reason', e.target.value)}
                    className={fieldClass}
                />
            </Field>
        </FormDialog>
    );
}

function ReasonDialog({
    title,
    description,
    submitLabel,
    url,
    onClose,
}: {
    title: string;
    description: string;
    submitLabel: string;
    url: string;
    onClose: () => void;
}) {
    const form = useForm({ reason: '' });

    return (
        <FormDialog
            title={title}
            description={description}
            submitLabel={submitLabel}
            destructive
            processing={form.processing}
            onClose={onClose}
            onSubmit={() =>
                form.post(url, { preserveScroll: true, onSuccess: onClose })
            }
        >
            <Field id="reason" label="Reason" error={form.errors.reason}>
                <textarea
                    id="reason"
                    rows={2}
                    value={form.data.reason}
                    aria-invalid={form.errors.reason ? true : undefined}
                    onChange={(e) => form.setData('reason', e.target.value)}
                    className={fieldClass}
                />
            </Field>
        </FormDialog>
    );
}

/** How many history lines show before "Show all". */
const HISTORY_SHOWN = 8;

/** What happened on the account, newest first, grouped by day. */
function AccountHistory({ history }: { history: Statement['history'] }) {
    const [all, setAll] = useState(false);
    const shown = all ? history : history.slice(0, HISTORY_SHOWN);
    const days = shown.reduce<{ day: string; events: typeof history }[]>(
        (groups, event) => {
            const day = event.when.split(',')[0];
            const last = groups[groups.length - 1];
            if (last && last.day === day) {
                last.events.push(event);
            } else {
                groups.push({ day, events: [event] });
            }

            return groups;
        },
        [],
    );

    return (
        <section
            aria-labelledby="history-heading"
            className="rounded-card border border-border bg-background p-6 xl:sticky xl:top-6"
        >
            <h2 id="history-heading" className="text-heading03 font-semibold">
                History
            </h2>
            {history.length === 0 ? (
                <p className="mt-2 text-sm text-muted-foreground">
                    Nothing has happened on this account yet.
                </p>
            ) : (
                <>
                    <div className="mt-4 grid gap-5">
                        {days.map((group) => (
                            <div key={group.day}>
                                <p className="text-xs font-semibold text-muted-foreground">
                                    {group.day}
                                </p>
                                <ol className="mt-2 grid gap-3 border-l-2 border-border pl-4">
                                    {group.events.map((event) => (
                                        <li
                                            key={event.id}
                                            className="relative text-sm"
                                        >
                                            <span
                                                aria-hidden
                                                className="absolute top-1.5 -left-[1.3125rem] size-2 rounded-full bg-brand-navy dark:bg-brand-on-dark"
                                            />
                                            <p className="font-semibold">
                                                {event.label}
                                                {event.amount !== null &&
                                                    `, ${naira(event.amount)}`}
                                            </p>
                                            {[
                                                event.charge,
                                                event.receipt_number,
                                                event.note,
                                            ].some(Boolean) && (
                                                <p className="text-muted-foreground">
                                                    {[
                                                        event.charge,
                                                        event.receipt_number,
                                                        event.note,
                                                    ]
                                                        .filter(Boolean)
                                                        .join('. ')}
                                                </p>
                                            )}
                                            <p className="text-xs text-muted-foreground">
                                                {event.who ?? 'The website'},{' '}
                                                {event.when.split(', ')[1] ??
                                                    event.when}
                                            </p>
                                        </li>
                                    ))}
                                </ol>
                            </div>
                        ))}
                    </div>
                    {history.length > HISTORY_SHOWN && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="mt-5"
                            aria-expanded={all}
                            onClick={() => setAll((open) => !open)}
                        >
                            {all ? 'Show fewer' : `Show all ${history.length}`}
                        </Button>
                    )}
                </>
            )}
        </section>
    );
}

export default function AccountShow({
    person,
    statement,
    kinds,
    defaults,
    waiverTypes,
    methods,
    year,
    upgrade,
}: {
    person: Person;
    statement: Statement;
    kinds: Option[];
    defaults: Defaults;
    waiverTypes: Option[];
    methods: Option[];
    year: number;
    upgrade: UpgradeOptions | null;
}) {
    const [action, setAction] = useState<Action | null>(null);
    const { confirm, dialog } = useConfirm();
    const close = () => setAction(null);

    return (
        <>
            <Head title={`${person.name} · Fees and payments`} />
            {dialog}
            <div className="@container px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <div className="mb-8 flex flex-col gap-4 @3xl:flex-row @3xl:items-start @3xl:justify-between">
                    <div className="min-w-0 [&>header]:mb-0">
                        <Heading
                            title={person.name}
                            description={[
                                person.email,
                                person.number,
                                person.grade,
                                person.status,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        />
                    </div>
                    <div className="flex shrink-0 flex-wrap gap-2">
                        {person.application_id && (
                            <Button asChild size="lg" variant="outline">
                                <Link
                                    href={showApplication(
                                        person.application_id,
                                    )}
                                >
                                    Application
                                </Link>
                            </Button>
                        )}
                        {upgrade && upgrade.grades.length > 0 && (
                            <Button
                                size="lg"
                                variant="outline"
                                onClick={() => setAction({ type: 'upgrade' })}
                            >
                                <ArrowUpCircle />
                                Offer an upgrade
                            </Button>
                        )}
                        <Button
                            size="lg"
                            onClick={() => setAction({ type: 'charge' })}
                        >
                            <Plus />
                            Add a charge
                        </Button>
                    </div>
                </div>

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem] xl:items-start">
                    <div className="@container grid gap-6">
                        <BalanceStrip totals={statement.totals} />
                        <ChargeList
                            charges={statement.charges}
                            forStaff
                            empty="No charges yet. The induction fee is added when they join the register; add an exam fee or subscription here."
                            chargeActions={(charge) => {
                                const more = [
                                    (charge.balance > 0 || charge.waiver) && {
                                        label: charge.waiver
                                            ? 'Change the waiver'
                                            : 'Waive',
                                        run: () =>
                                            setAction({
                                                type: 'waive',
                                                charge,
                                            }),
                                    },
                                    charge.waiver && {
                                        label: 'Remove the waiver',
                                        run: async () => {
                                            const yes = await confirm({
                                                title: 'Remove the waiver?',
                                                description: `${charge.label}: the ${naira(charge.waiver?.amount ?? 0)} waived is owed again.`,
                                                confirmLabel:
                                                    'Remove the waiver',
                                                cancelLabel: 'Keep it',
                                                tone: 'danger',
                                            });
                                            if (yes) {
                                                router.delete(
                                                    waiver.url(charge.id),
                                                    { preserveScroll: true },
                                                );
                                            }
                                        },
                                    },
                                    charge.paid === 0 && {
                                        label: 'Cancel the charge',
                                        run: () =>
                                            setAction({
                                                type: 'cancel',
                                                charge,
                                            }),
                                    },
                                ].filter(Boolean) as {
                                    label: string;
                                    run: () => void;
                                }[];

                                return (
                                    <>
                                        {charge.balance > 0 && (
                                            <Button
                                                size="sm"
                                                onClick={() =>
                                                    setAction({
                                                        type: 'payment',
                                                        charge,
                                                    })
                                                }
                                            >
                                                Record a payment
                                                <span className="sr-only">
                                                    {' '}
                                                    for {charge.label}
                                                </span>
                                            </Button>
                                        )}
                                        {more.length > 0 && (
                                            <DropdownMenu>
                                                <DropdownMenuTrigger asChild>
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                    >
                                                        More
                                                        <span className="sr-only">
                                                            {' '}
                                                            for {charge.label}
                                                        </span>
                                                        <ChevronDown
                                                            aria-hidden
                                                        />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="start">
                                                    {more.map((item) => (
                                                        <DropdownMenuItem
                                                            key={item.label}
                                                            onSelect={item.run}
                                                        >
                                                            {item.label}
                                                        </DropdownMenuItem>
                                                    ))}
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        )}
                                    </>
                                );
                            }}
                            paymentActions={(payment) => (
                                <button
                                    type="button"
                                    onClick={() =>
                                        setAction({ type: 'void', payment })
                                    }
                                    className="rounded-sm text-muted-foreground underline decoration-border underline-offset-4 hover:text-foreground hover:decoration-current focus-visible:outline-2 focus-visible:outline-ring"
                                >
                                    Void
                                    <span className="sr-only">
                                        {' '}
                                        {payment.receipt_number}
                                    </span>
                                </button>
                            )}
                        />
                    </div>

                    <AccountHistory history={statement.history} />
                </div>
            </div>

            {action?.type === 'upgrade' && upgrade && (
                <UpgradeDialog
                    person={person}
                    upgrade={upgrade}
                    onClose={close}
                />
            )}
            {action?.type === 'charge' && (
                <AddChargeDialog
                    person={person}
                    kinds={kinds}
                    defaults={defaults}
                    year={year}
                    onClose={close}
                />
            )}
            {action?.type === 'payment' && (
                <PaymentDialog
                    charge={action.charge}
                    methods={methods}
                    email={person.email}
                    onClose={close}
                />
            )}
            {action?.type === 'waive' && (
                <WaiveDialog
                    charge={action.charge}
                    waiverTypes={waiverTypes}
                    onClose={close}
                />
            )}
            {action?.type === 'cancel' && (
                <ReasonDialog
                    title="Cancel this charge?"
                    description={`${action.charge.label}, ${naira(action.charge.amount)}. Use this for a charge added by mistake. It stays on the account, crossed out, with your reason.`}
                    submitLabel="Cancel the charge"
                    url={cancel.url(action.charge.id)}
                    onClose={close}
                />
            )}
            {action?.type === 'void' && (
                <ReasonDialog
                    title="Void this payment?"
                    description={`${action.payment.receipt_number}, ${naira(action.payment.amount)}. Use this for a payment recorded by mistake. It stays on the account, crossed out, and the amount is owed again.`}
                    submitLabel="Void the payment"
                    url={voidPayment.url(action.payment.id)}
                    onClose={close}
                />
            )}
        </>
    );
}

AccountShow.layout = {
    breadcrumbs: [{ title: 'Member register', href: register() }],
};
