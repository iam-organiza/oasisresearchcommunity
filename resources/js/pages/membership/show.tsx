import { Head, Link, useForm } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent, ReactNode } from 'react';
import Heading from '@/components/heading';
import { useConfirm } from '@/components/confirm-dialog';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import InputError from '@/components/input-error';
import {
    Field,
    fieldClass,
    naira,
    selectClass,
} from '@/components/membership/form-bits';
import {
    NextStep,
    onNavy,
    outlineOnNavy,
} from '@/components/membership/next-step';
import { JourneyTimeline } from '@/components/membership/timeline';
import type { JourneyStep } from '@/components/membership/timeline';
import { knowledgeBase } from '@/routes';
import {
    accept,
    account,
    appeal,
    exam,
    induction as inductionRoute,
    show,
} from '@/routes/membership';
import { payAgain } from '@/routes/membership/exam';
import { edit, start } from '@/routes/membership/apply';

type Application = {
    status: string;
    status_label: string;
    editable: boolean;
    grade: string | null;
    chapter: string | null;
    submitted_at: string | null;
    updated_at: string | null;
    steps: { key: string; label: string; complete: boolean }[];
    next_step: string;
    applied_grade: string | null;
    offered_grade: string | null;
    offer_reasons: string[];
    can_appeal: boolean;
    needs_exam: boolean;
    exam_fee: number;
    induction_after_exam: number | null;
    exam_fee_paid: boolean | null;
    exam_status: string | null;
    exam_best_percent: number | null;
    decision_reason: string | null;
    decision_improvements: string | null;
    registrar_message: string | null;
    exemption_status: string | null;
    appealed: boolean;
    can_reapply: boolean;
};

type Induction = {
    choice: 'ceremony' | 'post' | null;
    event: string | null;
    address: string | null;
    city: string | null;
    state_id: number | null;
    phone: string | null;
    posted: string | null;
    delivered: string | null;
    postage: number;
};

type Region = { name: string; states: { value: string; label: string }[] };

type MembershipCard = {
    number: string | null;
    grade: string;
    chapter: string | null;
    joined_on: string;
    active: boolean;
    status: string;
    owed: number;
    next_due: { amount: number; on: string } | null;
    induction: Induction;
};

/** A quieter card under the next step. */
function Card({
    title,
    children,
    className,
}: {
    title: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section
            className={cn(
                'rounded-card border border-border bg-background p-6 md:p-8',
                className,
            )}
        >
            <h2 className="text-heading03 font-semibold">{title}</h2>
            {children}
        </section>
    );
}

/** Starts or resumes the application; the server picks the right step. */
function ApplyButton({
    label,
    className,
}: {
    label: string;
    className?: string;
}) {
    const form = useForm({});
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(start.url());
    };

    return (
        <form onSubmit={submit}>
            <Button
                type="submit"
                size="lg"
                disabled={form.processing}
                className={className}
            >
                {label}
            </Button>
        </form>
    );
}

function Choices() {
    return (
        <>
            <NextStep
                title="Apply for membership"
                actions={
                    <ApplyButton
                        label="Start my application"
                        className={onNavy}
                    />
                }
            >
                <p>
                    Join as a Fellow, Member, Graduate Member or Student Member.
                    Have your CV ready to upload; your answers save as you go.
                </p>
            </NextStep>
            <Card title="Or continue as a reader">
                <p className="mt-2 max-w-prose text-muted-foreground">
                    Read and comment on the Knowledge Base. You can apply for
                    membership from here whenever you are ready.
                </p>
                <div className="mt-6">
                    {/* The Knowledge Base is not a React page, so a plain link loads it in full. */}
                    <Button asChild size="lg" variant="outline">
                        <a href={knowledgeBase.url()}>
                            Go to the Knowledge Base
                        </a>
                    </Button>
                </div>
            </Card>
        </>
    );
}

function InProgress({ application }: { application: Application }) {
    const done = application.steps.filter((step) => step.complete).length;
    const moreInformation =
        application.status === 'more_information' &&
        application.registrar_message;

    return (
        <>
            <NextStep
                title={
                    moreInformation
                        ? 'The Registrar needs more from you'
                        : 'Finish your application'
                }
                actions={
                    <Button asChild size="lg" className={onNavy}>
                        <Link href={edit(application.next_step)}>
                            Continue my application
                        </Link>
                    </Button>
                }
            >
                {moreInformation ? (
                    <>
                        <blockquote className="border-l-2 border-brand-crimson pl-4 whitespace-pre-line text-white">
                            {application.registrar_message}
                        </blockquote>
                        <p>
                            Update your answers or documents, then submit again
                            from the Review step.
                        </p>
                    </>
                ) : (
                    <p>
                        {done} of {application.steps.length} parts done
                        {application.updated_at &&
                            `, last saved ${application.updated_at}`}
                        . Submit it and your grade offer comes straight back.
                    </p>
                )}
                {!moreInformation && (
                    <div
                        aria-hidden
                        className="mt-2 h-1.5 overflow-hidden rounded-full bg-white/15"
                    >
                        <div
                            className="h-full rounded-full bg-brand-crimson"
                            style={{
                                width: `${(done / application.steps.length) * 100}%`,
                            }}
                        />
                    </div>
                )}
            </NextStep>

            <Card title="Your application">
                <ol className="mt-4 grid gap-1 @2xl:grid-flow-col @2xl:grid-rows-3">
                    {application.steps.map((step, i) => (
                        <li key={step.key}>
                            <Link
                                href={edit(step.key)}
                                className="flex items-center gap-3 rounded-control px-2 py-2 hover:bg-accent"
                            >
                                <span
                                    aria-hidden
                                    className={cn(
                                        'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                        step.complete
                                            ? 'bg-primary text-primary-foreground'
                                            : 'ring-1 ring-border ring-inset',
                                    )}
                                >
                                    {step.complete ? (
                                        <Check className="size-3.5" />
                                    ) : (
                                        i + 1
                                    )}
                                </span>
                                {step.label}
                                {step.complete && (
                                    <span className="sr-only">(done)</span>
                                )}
                            </Link>
                        </li>
                    ))}
                </ol>
            </Card>
        </>
    );
}

function Submitted({ application }: { application: Application }) {
    return (
        <>
            <NextStep
                title={
                    application.appealed
                        ? 'The Registrar is reviewing your appeal'
                        : 'The Registrar is reviewing your application'
                }
            >
                <p>
                    Nothing to do for now. We will email you when there is a
                    decision, or if we need anything more from you.
                </p>
            </NextStep>
            <Card title="What you sent">
                <dl className="mt-4 grid gap-x-6 gap-y-2 text-sm @xl:grid-cols-[10rem_minmax(0,1fr)]">
                    {application.submitted_at && (
                        <>
                            <dt className="text-muted-foreground">Submitted</dt>
                            <dd>{application.submitted_at}</dd>
                        </>
                    )}
                    <dt className="text-muted-foreground">Grade applied for</dt>
                    <dd>{application.grade ?? '—'}</dd>
                    <dt className="text-muted-foreground">Chapter</dt>
                    <dd>{application.chapter ?? '—'}</dd>
                    {application.exemption_status && (
                        <>
                            <dt className="text-muted-foreground">
                                Exam exemption
                            </dt>
                            <dd>
                                {
                                    {
                                        requested:
                                            'Requested, waiting for the Registrar',
                                        approved: 'Approved',
                                        refused:
                                            'Refused; you will take the exam',
                                    }[application.exemption_status]
                                }
                            </dd>
                        </>
                    )}
                </dl>
            </Card>
        </>
    );
}

/** The member's card: grade, number, chapter and standing. */
function MemberCard({ membership }: { membership: MembershipCard }) {
    return (
        <section
            aria-label="Your membership card"
            className={cn(
                'relative overflow-clip rounded-card border border-brand-line bg-brand-paper p-6',
                'dark:border-white/10 dark:bg-brand-night-raised',
            )}
        >
            <span
                aria-hidden
                className="absolute inset-y-0 left-0 w-1.5 bg-brand-crimson-strong dark:bg-brand-crimson"
            />
            <p className="text-sm font-semibold text-brand-navy/70 dark:text-brand-on-dark">
                OASIS Research Community
            </p>
            <div className="mt-5 flex flex-wrap items-center gap-x-3 gap-y-2">
                <p className="text-heading02 font-bold text-brand-navy dark:text-white">
                    {membership.grade}
                </p>
                <span
                    className={cn(
                        'shrink-0 rounded-full px-2.5 py-0.5 text-xs font-semibold',
                        membership.active
                            ? 'bg-brand-navy text-white dark:bg-brand-on-dark dark:text-brand-navy'
                            : 'ring-1 ring-brand-navy/30 ring-inset dark:ring-white/30',
                    )}
                >
                    {membership.status}
                </span>
            </div>
            <p
                className={cn(
                    'mt-1 font-semibold tracking-wide',
                    membership.number
                        ? 'text-brand-navy dark:text-white'
                        : 'text-sm font-normal tracking-normal text-muted-foreground',
                )}
            >
                {membership.number ??
                    'Your number is issued when the induction fee is paid'}
            </p>
            <dl className="mt-6 grid grid-cols-2 gap-4 border-t border-brand-line pt-4 text-sm dark:border-white/10">
                <div>
                    <dt className="text-muted-foreground">Chapter</dt>
                    <dd className="mt-0.5">{membership.chapter ?? '—'}</dd>
                </div>
                <div>
                    <dt className="text-muted-foreground">Member since</dt>
                    <dd className="mt-0.5">{membership.joined_on}</dd>
                </div>
                {membership.next_due && (
                    <div className="col-span-2">
                        <dt className="text-muted-foreground">
                            Next subscription
                        </dt>
                        <dd className="mt-0.5">
                            {naira(membership.next_due.amount)}, due{' '}
                            {membership.next_due.on}
                        </dd>
                    </div>
                )}
            </dl>
        </section>
    );
}

/** Left column for someone admitted: pay what is owed, or nothing to do. */
function MemberNextStep({ membership }: { membership: MembershipCard }) {
    const payButton = (
        <Button asChild size="lg" className={onNavy}>
            <Link href={account()}>Go to fees and payments</Link>
        </Button>
    );

    if (!membership.active) {
        return (
            <NextStep
                title={
                    membership.owed > 0
                        ? `Pay your induction fee of ${naira(membership.owed)}`
                        : 'Pay your induction fee'
                }
                actions={payButton}
            >
                <p>
                    You are admitted as {membership.grade}. Your membership
                    becomes active, and your number is issued, once the
                    induction fee is paid. Pay online or by bank transfer.
                </p>
            </NextStep>
        );
    }
    if (membership.owed > 0) {
        return (
            <NextStep
                title={`You have ${naira(membership.owed)} to pay`}
                actions={payButton}
            >
                <p>Pay online or by bank transfer.</p>
            </NextStep>
        );
    }

    return (
        <NextStep
            title="You are a full member"
            actions={
                <Button asChild size="lg" className={onNavy}>
                    {/* The Knowledge Base is not a React page, so a plain link loads it in full. */}
                    <a href={knowledgeBase.url()}>Go to the Knowledge Base</a>
                </Button>
            }
        >
            <p>
                Nothing to pay for now.
                {membership.induction.choice === null &&
                    ' Choose below how you will receive your induction.'}
            </p>
        </NextStep>
    );
}

/**
 * How the new member receives their induction: at the ceremony, or with the
 * pack posted to them. The address is asked only for posting.
 */
function InductionChoice({
    induction,
    regions,
}: {
    induction: Induction;
    regions: Region[];
}) {
    const form = useForm({
        choice: induction.choice ?? '',
        address: induction.address ?? '',
        city: induction.city ?? '',
        state_id: induction.state_id ? String(induction.state_id) : '',
        phone: induction.phone ?? '',
    });
    const { data, setData, errors } = form;
    const locked = induction.posted !== null;

    if (induction.delivered || locked) {
        return (
            <section className="rounded-card border border-border bg-background p-6 md:p-8">
                <h2 className="text-heading03 font-semibold">Your induction</h2>
                <p className="mt-2 text-muted-foreground">
                    {induction.delivered
                        ? `Your induction pack was delivered on ${induction.delivered}. We have deleted the delivery address.`
                        : `Your induction pack was posted on ${induction.posted}. To change anything, contact the Secretariat.`}
                </p>
            </section>
        );
    }

    const save = (event: FormEvent) => {
        event.preventDefault();
        form.put(inductionRoute.url(), { preserveScroll: true });
    };
    const tile = (value: 'ceremony' | 'post', title: string, body: string) => (
        <label
            className={cn(
                'relative flex cursor-pointer flex-col gap-1 rounded-control border p-4',
                'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                data.choice === value
                    ? 'border-brand-crimson-strong ring-1 ring-brand-crimson-strong dark:border-brand-crimson dark:ring-brand-crimson'
                    : 'border-border hover:border-foreground/40',
            )}
        >
            <input
                type="radio"
                name="choice"
                value={value}
                checked={data.choice === value}
                onChange={() => setData('choice', value)}
                className="sr-only"
            />
            <span className="flex items-start justify-between gap-3 font-semibold">
                {title}
                {data.choice === value && (
                    <Check
                        aria-hidden
                        className="mt-0.5 size-4 shrink-0 text-brand-crimson-strong dark:text-brand-crimson"
                    />
                )}
            </span>
            <span className="text-sm text-muted-foreground">{body}</span>
        </label>
    );

    return (
        <form
            onSubmit={save}
            noValidate
            className="@container grid gap-5 rounded-card border border-border bg-background p-6 md:p-8"
        >
            <div>
                <h2 className="text-heading03 font-semibold">Your induction</h2>
                <p className="mt-1 max-w-prose text-muted-foreground">
                    Attending is optional. If you cannot come, we post your
                    induction pack: your certificate, award and professional
                    gown.
                </p>
            </div>
            <fieldset className="grid gap-2">
                <legend className="sr-only">How you will receive it</legend>
                <div className="grid gap-3 @xl:grid-cols-2">
                    {tile(
                        'ceremony',
                        'I will attend the ceremony',
                        induction.event ??
                            'We will tell you the date and place.',
                    )}
                    {tile(
                        'post',
                        'Post my induction pack',
                        induction.postage > 0
                            ? `Postage of ${naira(induction.postage)} goes on your account.`
                            : 'Sent to the address you give below.',
                    )}
                </div>
                <InputError
                    className="font-semibold text-destructive"
                    message={errors.choice}
                />
            </fieldset>

            {data.choice === 'post' && (
                <div className="grid gap-5 rounded-control bg-muted/60 p-5">
                    <p className="font-semibold">Delivery address</p>
                    <Field
                        id="address"
                        label="Street address"
                        error={errors.address}
                    >
                        <input
                            id="address"
                            value={data.address}
                            aria-invalid={errors.address ? true : undefined}
                            onChange={(e) => setData('address', e.target.value)}
                            className={fieldClass}
                        />
                    </Field>
                    <div className="grid gap-5 @xl:grid-cols-3">
                        <Field
                            id="city"
                            label="City or town"
                            error={errors.city}
                        >
                            <input
                                id="city"
                                value={data.city}
                                aria-invalid={errors.city ? true : undefined}
                                onChange={(e) =>
                                    setData('city', e.target.value)
                                }
                                className={fieldClass}
                            />
                        </Field>
                        <Field
                            id="state_id"
                            label="State"
                            error={errors.state_id}
                        >
                            <select
                                id="state_id"
                                value={data.state_id}
                                aria-invalid={
                                    errors.state_id ? true : undefined
                                }
                                onChange={(e) =>
                                    setData('state_id', e.target.value)
                                }
                                className={selectClass}
                            >
                                <option value="">Choose a state</option>
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
                            id="phone"
                            label="Phone for the courier"
                            error={errors.phone}
                        >
                            <input
                                id="phone"
                                type="tel"
                                value={data.phone}
                                aria-invalid={errors.phone ? true : undefined}
                                onChange={(e) =>
                                    setData('phone', e.target.value)
                                }
                                className={fieldClass}
                            />
                        </Field>
                    </div>
                    <p className="text-sm text-muted-foreground">
                        We use this address only to post your pack, and delete
                        it once the pack is delivered.
                    </p>
                </div>
            )}

            <div>
                <Button
                    type="submit"
                    size="lg"
                    disabled={form.processing || !data.choice}
                >
                    Save my choice
                </Button>
            </div>
        </form>
    );
}

/** The grade offer: accept it and go on, or appeal for the grade applied for. */
function Offer({ application }: { application: Application }) {
    const [appealing, setAppealing] = useState(false);
    const acceptForm = useForm({});
    const payAgainForm = useForm({});
    const appealForm = useForm<{ appeal_reason: string; files: File[] }>({
        appeal_reason: '',
        files: [],
    });

    const { confirm, dialog } = useConfirm();
    const offered = application.offered_grade ?? 'this grade';
    const applied = application.applied_grade ?? 'the grade you applied for';
    const onAccept = async (event: FormEvent) => {
        event.preventDefault();
        const yes = await confirm({
            title: `Accept ${offered}?`,
            description:
                application.exam_status === 'band'
                    ? `You are admitted as ${offered}, and its full induction fee goes on your account. You can no longer take the exam for ${applied}.`
                    : (application.offered_grade !== application.applied_grade
                          ? `You can no longer appeal for ${applied}. `
                          : '') +
                      (application.needs_exam
                          ? `Next, the exam fee of ${naira(application.exam_fee)} goes on your account; paying it opens the prep materials and the exam.`
                          : 'The Registrar then checks your route to membership and lets you know.'),
            confirmLabel: `Accept ${offered}`,
            cancelLabel: 'Not yet',
        });
        if (yes) {
            acceptForm.post(accept.url());
        }
    };
    const onPayAgainInstead = async () => {
        const yes = await confirm({
            title: 'Pay the exam fee again instead?',
            description: `The ${offered} offer is withdrawn, and a new exam fee of ${naira(application.exam_fee)} goes on your account for another try at ${applied}.`,
            confirmLabel: 'Pay the exam fee again',
            cancelLabel: `Keep the ${offered} offer`,
            tone: 'danger',
        });
        if (yes) {
            payAgainForm.post(payAgain.url());
        }
    };
    const onAppeal = async (event: FormEvent) => {
        event.preventDefault();
        // Ask only once there is a reason; the form shows what is missing otherwise.
        if (appealForm.data.appeal_reason.trim() !== '') {
            const yes = await confirm({
                title: 'Send your appeal?',
                description: `It goes to the Registrar with your reasons${appealForm.data.files.length ? ' and files' : ''}. You cannot change it once it is sent.`,
                confirmLabel: 'Send my appeal',
                cancelLabel: 'Keep editing',
            });
            if (!yes) {
                return;
            }
        }
        appealForm.post(appeal.url(), { forceFormData: true });
    };
    const fileErrors = Object.entries(appealForm.errors)
        .filter(([key]) => key.startsWith('files'))
        .map(([, message]) => message);
    const lower = application.offered_grade !== application.applied_grade;
    const band = application.exam_status === 'band';

    return (
        <>
            {dialog}
            <NextStep
                title={
                    band
                        ? `Your score offers you ${application.offered_grade}`
                        : `We offer you ${application.offered_grade}`
                }
                actions={
                    <>
                        <form onSubmit={onAccept}>
                            <Button
                                type="submit"
                                size="lg"
                                disabled={acceptForm.processing}
                                className={onNavy}
                            >
                                Accept and continue
                            </Button>
                        </form>
                        {band && (
                            <Button
                                type="button"
                                size="lg"
                                variant="outline"
                                disabled={payAgainForm.processing}
                                onClick={onPayAgainInstead}
                                className={outlineOnNavy}
                            >
                                Pay the exam fee again instead
                            </Button>
                        )}
                        {application.can_appeal && !band && (
                            <Button
                                type="button"
                                size="lg"
                                variant="outline"
                                aria-expanded={appealing}
                                onClick={() => setAppealing((open) => !open)}
                                className={outlineOnNavy}
                            >
                                Appeal for {application.applied_grade}
                            </Button>
                        )}
                    </>
                }
            >
                {lower && <p>You applied for {application.applied_grade}.</p>}
                {application.offer_reasons.map((reason) => (
                    <p key={reason}>{reason}</p>
                ))}
                <div className="mt-2 rounded-control bg-white/10 p-4 text-sm text-white">
                    <p className="font-semibold">What happens next</p>
                    {band ? (
                        <p className="mt-2">
                            Accept to join as {application.offered_grade}: the
                            Registrar approves you and its full induction fee
                            goes on your account. Or pay the exam fee again and
                            try for {application.applied_grade} after 24 hours.
                        </p>
                    ) : application.needs_exam ? (
                        <ol className="mt-2 grid list-decimal gap-1 pl-5">
                            <li>
                                Pay the exam fee of{' '}
                                {naira(application.exam_fee)}. That opens the
                                prep materials.
                            </li>
                            <li>
                                Take the exam: 50 questions in 60 minutes, with
                                up to three tries.
                            </li>
                            <li>
                                Pass, then pay the rest of the induction fee
                                {application.induction_after_exam !== null &&
                                    `, ${naira(application.induction_after_exam)}`}
                                . Your account then goes live.
                            </li>
                        </ol>
                    ) : (
                        <p className="mt-2">
                            Accept the offer. The Registrar then checks your
                            route to membership and lets you know about the
                            induction fee.
                        </p>
                    )}
                </div>
            </NextStep>

            {appealing && (
                <form
                    onSubmit={onAppeal}
                    className="grid gap-5 rounded-card border border-border bg-background p-6 md:p-8"
                    noValidate
                >
                    <div>
                        <h2 className="text-heading03 font-semibold">
                            Appeal for {application.applied_grade}
                        </h2>
                        <p className="mt-1 max-w-prose text-muted-foreground">
                            The Registrar reads your reasons and evidence and
                            either offers you {application.applied_grade} or
                            explains why not.
                        </p>
                    </div>
                    <div className="grid gap-2">
                        <label
                            htmlFor="appeal_reason"
                            className="font-semibold"
                        >
                            Why you should be {application.applied_grade}
                        </label>
                        <textarea
                            id="appeal_reason"
                            rows={5}
                            value={appealForm.data.appeal_reason}
                            aria-invalid={
                                appealForm.errors.appeal_reason
                                    ? true
                                    : undefined
                            }
                            onChange={(e) =>
                                appealForm.setData(
                                    'appeal_reason',
                                    e.target.value,
                                )
                            }
                            className={fieldClass}
                        />
                        <InputError
                            className="font-semibold text-destructive"
                            message={appealForm.errors.appeal_reason}
                        />
                    </div>
                    <div className="grid gap-2">
                        <label htmlFor="appeal_files" className="font-semibold">
                            More evidence{' '}
                            <span className="font-normal text-muted-foreground">
                                (optional, up to 3 files: PDF, JPG or PNG, 5 MB
                                each)
                            </span>
                        </label>
                        <input
                            id="appeal_files"
                            type="file"
                            multiple
                            accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png"
                            onChange={(e) =>
                                appealForm.setData(
                                    'files',
                                    Array.from(e.target.files ?? []),
                                )
                            }
                            className={fieldClass}
                        />
                        {fileErrors.map((message) => (
                            <InputError
                                key={message}
                                className="font-semibold text-destructive"
                                message={message}
                            />
                        ))}
                    </div>
                    <div>
                        <Button
                            type="submit"
                            size="lg"
                            disabled={appealForm.processing}
                        >
                            Send my appeal
                        </Button>
                    </div>
                </form>
            )}
        </>
    );
}

/** The offer is accepted: the exam fee, then the exam; or the Registrar's check. */
function Accepted({ application }: { application: Application }) {
    const paid = application.exam_fee_paid === true;

    if (application.exam_status === 'passed') {
        return (
            <NextStep
                title={`You passed the exam${application.exam_best_percent !== null ? ` with ${application.exam_best_percent}%` : ''}`}
                actions={
                    <Button asChild size="lg" className={onNavy}>
                        <Link href={account()}>Go to fees and payments</Link>
                    </Button>
                }
            >
                <p>
                    You are admitted. Your induction fee, less the exam fee, is
                    on your fees and payments page.
                </p>
            </NextStep>
        );
    }
    if (application.needs_exam && !paid) {
        return (
            <NextStep
                title={`Pay the exam fee of ${naira(application.exam_fee)}`}
                actions={
                    <Button asChild size="lg" className={onNavy}>
                        <Link href={account()}>Go to fees and payments</Link>
                    </Button>
                }
            >
                <p>
                    You accepted {application.offered_grade}. Once the exam fee
                    is paid, the prep materials and the exam open here.
                </p>
            </NextStep>
        );
    }
    if (application.needs_exam) {
        return (
            <NextStep
                title="Take your exam"
                actions={
                    <Button asChild size="lg" className={onNavy}>
                        <Link href={exam()}>Go to my exam</Link>
                    </Button>
                }
            >
                <p>
                    Read the prep materials, then take the exam when you are
                    ready: 50 questions in 60 minutes, with three attempts on
                    your exam fee.
                </p>
            </NextStep>
        );
    }

    return (
        <NextStep title="The Registrar is checking your route to membership">
            <p>
                {application.exemption_status === 'requested'
                    ? 'They are looking at your request to skip the exam. '
                    : ''}
                Nothing to do for now. We will email you when there is a
                decision, or if we need anything more.
            </p>
        </NextStep>
    );
}

function Declined({ application }: { application: Application }) {
    const hasNote =
        application.decision_reason || application.decision_improvements;

    return (
        <>
            <NextStep
                title="Your application was not approved this time"
                actions={
                    application.can_reapply && (
                        <ApplyButton label="Apply again" className={onNavy} />
                    )
                }
            >
                <p>
                    You can apply again once you have what the Registrar asks
                    for.
                </p>
            </NextStep>
            {hasNote && (
                <Card title="From the Registrar">
                    <dl className="mt-4 grid gap-4">
                        {application.decision_reason && (
                            <div>
                                <dt className="text-sm font-semibold text-muted-foreground">
                                    The reason
                                </dt>
                                <dd className="mt-1 max-w-prose whitespace-pre-line">
                                    {application.decision_reason}
                                </dd>
                            </div>
                        )}
                        {application.decision_improvements && (
                            <div>
                                <dt className="text-sm font-semibold text-muted-foreground">
                                    What to improve or provide evidence of
                                </dt>
                                <dd className="mt-1 max-w-prose whitespace-pre-line">
                                    {application.decision_improvements}
                                </dd>
                            </div>
                        )}
                    </dl>
                </Card>
            )}
        </>
    );
}

export default function MembershipShow({
    application,
    membership,
    regions,
    journey,
}: {
    application: Application | null;
    membership: MembershipCard | null;
    regions: Region[];
    journey: JourneyStep[];
}) {
    const hasAside = membership !== null || journey.length > 0;

    return (
        <>
            <Head title="My membership" />
            <div className="@container w-full max-w-7xl px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="My membership"
                    description={
                        membership
                            ? 'Your grade, membership number and chapter.'
                            : application
                              ? 'Where your application stands and what comes next.'
                              : 'Welcome to OASIS Research Community. What would you like to do?'
                    }
                />

                <div
                    className={cn(
                        'grid items-start gap-6 @4xl:gap-10',
                        hasAside &&
                            '@4xl:grid-cols-[minmax(0,1fr)_20rem] @6xl:grid-cols-[minmax(0,1fr)_23rem]',
                    )}
                >
                    <div className="grid gap-6">
                        {membership && (
                            <>
                                <MemberNextStep membership={membership} />
                                {/* On a phone the card comes straight after the next step. */}
                                <div className="@4xl:hidden">
                                    <MemberCard membership={membership} />
                                </div>
                                <InductionChoice
                                    induction={membership.induction}
                                    regions={regions}
                                />
                            </>
                        )}
                        {!membership && !application && <Choices />}
                        {!membership && application?.editable && (
                            <InProgress application={application} />
                        )}
                        {!membership && application?.status === 'submitted' && (
                            <Submitted application={application} />
                        )}
                        {!membership && application?.status === 'offered' && (
                            <Offer application={application} />
                        )}
                        {!membership && application?.status === 'accepted' && (
                            <Accepted application={application} />
                        )}
                        {!membership && application?.status === 'declined' && (
                            <Declined application={application} />
                        )}
                    </div>

                    {hasAside && (
                        <aside className="grid gap-8 @4xl:sticky @4xl:top-6">
                            {membership && (
                                <div className="hidden @4xl:block">
                                    <MemberCard membership={membership} />
                                </div>
                            )}
                            {journey.length > 0 && (
                                <div className="rounded-card border border-border bg-background p-6">
                                    <JourneyTimeline steps={journey} />
                                </div>
                            )}
                        </aside>
                    )}
                </div>
            </div>
        </>
    );
}

MembershipShow.layout = {
    breadcrumbs: [{ title: 'My membership', href: show() }],
};
