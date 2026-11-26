import { Head, Link, router, useForm } from '@inertiajs/react';
import { Plus } from 'lucide-react';
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
    ChoiceTiles,
    Field,
    fieldClass,
    naira,
    selectClass,
} from '@/components/membership/form-bits';
import type { Option } from '@/components/membership/form-bits';
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

type Action =
    | { type: 'charge' }
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

export default function AccountShow({
    person,
    statement,
    kinds,
    defaults,
    waiverTypes,
    methods,
    year,
}: {
    person: Person;
    statement: Statement;
    kinds: Option[];
    defaults: Defaults;
    waiverTypes: Option[];
    methods: Option[];
    year: number;
}) {
    const [action, setAction] = useState<Action | null>(null);
    const close = () => setAction(null);

    return (
        <>
            <Head title={`${person.name} · Fees and payments`} />
            <div className="@container px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <div className="flex flex-wrap items-start justify-between gap-4">
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
                    <div className="mb-8 flex flex-wrap gap-2">
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
                        <BalanceStrip
                            totals={statement.totals}
                            owedLabel="Still to pay"
                        />
                        <ChargeList
                            charges={statement.charges}
                            forStaff
                            empty="No charges yet. The induction fee is added when they join the register; add an exam fee or subscription here."
                            chargeActions={(charge) => (
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
                                    {(charge.balance > 0 || charge.waiver) && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                setAction({
                                                    type: 'waive',
                                                    charge,
                                                })
                                            }
                                        >
                                            {charge.waiver
                                                ? 'Change the waiver'
                                                : 'Waive'}
                                            <span className="sr-only">
                                                {' '}
                                                for {charge.label}
                                            </span>
                                        </Button>
                                    )}
                                    {charge.waiver && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                router.delete(
                                                    waiver.url(charge.id),
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Remove the waiver
                                            <span className="sr-only">
                                                {' '}
                                                for {charge.label}
                                            </span>
                                        </Button>
                                    )}
                                    {charge.paid === 0 && (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            onClick={() =>
                                                setAction({
                                                    type: 'cancel',
                                                    charge,
                                                })
                                            }
                                        >
                                            Cancel the charge
                                            <span className="sr-only">
                                                {' '}
                                                for {charge.label}
                                            </span>
                                        </Button>
                                    )}
                                </>
                            )}
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

                    <section className="rounded-card border border-border bg-background p-5">
                        <h2 className="font-semibold">History</h2>
                        {statement.history.length === 0 ? (
                            <p className="mt-2 text-sm text-muted-foreground">
                                Nothing has happened on this account yet.
                            </p>
                        ) : (
                            <ol className="mt-3 grid gap-3">
                                {statement.history.map((event) => (
                                    <li key={event.id} className="text-sm">
                                        <p className="font-semibold">
                                            {event.label}
                                            {event.amount !== null &&
                                                `, ${naira(event.amount)}`}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {[
                                                event.charge,
                                                event.receipt_number,
                                                event.note,
                                            ]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {event.who ?? 'The website'},{' '}
                                            {event.when}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>
                </div>
            </div>

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
