import { Head, Link, useForm } from '@inertiajs/react';
import { BadgeCheck, BookOpen, Check, Clock, FileCheck2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import InputError from '@/components/input-error';
import { fieldClass, naira } from '@/components/membership/form-bits';
import { knowledgeBase } from '@/routes';
import { accept, account, appeal, show } from '@/routes/membership';
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
    recommended_grade: string | null;
    decision_reason: string | null;
    decision_improvements: string | null;
    registrar_message: string | null;
    exemption_status: string | null;
    appealed: boolean;
    can_reapply: boolean;
};

type MembershipCard = {
    number: string;
    grade: string;
    chapter: string | null;
    joined_on: string;
    active: boolean;
    status: string;
    owed: number;
};

/** Starts or resumes the application; the server picks the right step. */
function ApplyButton({ label }: { label: string }) {
    const form = useForm({});
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(start.url());
    };

    return (
        <form onSubmit={submit}>
            <Button type="submit" size="lg" disabled={form.processing}>
                {label}
            </Button>
        </form>
    );
}

function Choices() {
    return (
        <div className="grid gap-5 @3xl:grid-cols-2">
            <section className="flex flex-col rounded-card bg-brand-navy p-6 text-white md:p-8 dark:bg-brand-night-raised">
                <FileCheck2 aria-hidden className="size-8 text-brand-crimson" />
                <h2 className="mt-5 text-heading03 font-semibold">
                    Apply for membership
                </h2>
                <p className="mt-2 text-brand-on-dark">
                    Join as a Fellow, Member, Graduate Member or Student Member.
                    Have your CV ready to upload; your answers save as you go.
                </p>
                <div className="mt-auto pt-8 [&_button]:bg-white [&_button]:text-brand-navy [&_button]:hover:bg-white/90">
                    <ApplyButton label="Start my application" />
                </div>
            </section>
            <section className="flex flex-col rounded-card border border-border bg-background p-6 md:p-8">
                <BookOpen
                    aria-hidden
                    className="size-8 text-muted-foreground"
                />
                <h2 className="mt-5 text-heading03 font-semibold">
                    Continue as a reader
                </h2>
                <p className="mt-2 text-muted-foreground">
                    Read and comment on the Knowledge Base. You can apply for
                    membership from here whenever you are ready.
                </p>
                <div className="mt-auto pt-8">
                    {/* The Knowledge Base is not a React page, so a plain link loads it in full. */}
                    <Button asChild size="lg" variant="outline">
                        <a href={knowledgeBase.url()}>
                            Go to the Knowledge Base
                        </a>
                    </Button>
                </div>
            </section>
        </div>
    );
}

function InProgress({ application }: { application: Application }) {
    const done = application.steps.filter((step) => step.complete).length;

    return (
        <section className="rounded-card border border-border bg-background p-6 md:p-8">
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-semibold text-muted-foreground">
                        {application.status_label}
                    </p>
                    <h2 className="mt-1 text-heading03 font-semibold">
                        Your membership application
                    </h2>
                    <p className="mt-1 text-muted-foreground tabular-nums">
                        {done} of {application.steps.length} steps done
                        {application.updated_at &&
                            `, last saved ${application.updated_at}`}
                    </p>
                </div>
                <Button asChild size="lg">
                    <Link href={edit(application.next_step)}>
                        Continue my application
                    </Link>
                </Button>
            </div>

            {application.status === 'more_information' &&
                application.registrar_message && (
                    <div className="mt-6 rounded-control border border-brand-crimson-strong/30 bg-brand-crimson-strong/5 p-4 dark:border-brand-crimson/40">
                        <p className="text-sm font-semibold">
                            The Registrar needs more from you
                        </p>
                        <p className="mt-1 max-w-prose whitespace-pre-line">
                            {application.registrar_message}
                        </p>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Update your answers or documents, then submit again
                            from the Review step.
                        </p>
                    </div>
                )}

            <div
                aria-hidden
                className="mt-6 h-2 overflow-hidden rounded-full bg-muted"
            >
                <div
                    className="h-full rounded-full bg-brand-crimson-strong dark:bg-brand-crimson"
                    style={{
                        width: `${(done / application.steps.length) * 100}%`,
                    }}
                />
            </div>

            <ol className="mt-6 grid gap-2 @2xl:grid-cols-3">
                {application.steps.map((step, i) => (
                    <li key={step.key}>
                        <Link
                            href={edit(step.key)}
                            className="flex items-center gap-3 rounded-control px-2 py-1.5 hover:bg-accent"
                        >
                            <span
                                aria-hidden
                                className={cn(
                                    'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-semibold tabular-nums',
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
        </section>
    );
}

function Submitted({ application }: { application: Application }) {
    return (
        <section className="rounded-card border border-border bg-background p-6 md:p-8">
            <div className="flex items-start gap-4">
                <span className="flex size-12 shrink-0 items-center justify-center rounded-full bg-brand-navy text-white dark:bg-brand-night-raised">
                    <Clock aria-hidden className="size-6" />
                </span>
                <div>
                    <p className="text-sm font-semibold text-muted-foreground">
                        {application.status_label}
                        {application.submitted_at &&
                            ` on ${application.submitted_at}`}
                    </p>
                    <h2 className="mt-1 text-heading03 font-semibold">
                        {application.appealed
                            ? 'The Registrar is reviewing your appeal'
                            : 'The Registrar is reviewing your application'}
                    </h2>
                    <p className="mt-2 max-w-prose text-muted-foreground">
                        We will email you when there is a decision, or if we
                        need anything more from you.
                    </p>
                </div>
            </div>
            <dl className="mt-6 grid gap-x-6 gap-y-2 border-t border-border pt-5 text-sm @xl:grid-cols-[10rem_minmax(0,1fr)]">
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
                                    refused: 'Refused; you will take the exam',
                                }[application.exemption_status]
                            }
                        </dd>
                    </>
                )}
            </dl>
        </section>
    );
}

/** A reason and "what to improve", as the Registrar wrote them. */
function DecisionNote({ application }: { application: Application }) {
    return (
        <dl className="mt-6 grid gap-4 border-t border-border pt-5">
            {application.decision_reason && (
                <div>
                    <dt className="text-sm font-semibold text-muted-foreground">
                        The Registrar's reason
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
    );
}

function Member({ membership }: { membership: MembershipCard }) {
    return (
        <section className="relative overflow-clip rounded-card bg-brand-navy p-6 text-white md:p-8 dark:bg-brand-night-raised">
            <BadgeCheck aria-hidden className="size-8 text-brand-crimson" />
            <p className="mt-5 text-sm font-semibold text-brand-on-dark">
                {membership.status}
            </p>
            <h2 className="mt-1 text-heading02 font-bold">
                {membership.grade}
            </h2>
            <dl className="mt-6 grid gap-x-6 gap-y-2 text-sm @xl:grid-cols-[10rem_minmax(0,1fr)]">
                <dt className="text-brand-on-dark">Membership number</dt>
                <dd className="font-semibold tracking-wide tabular-nums">
                    {membership.number}
                </dd>
                <dt className="text-brand-on-dark">Chapter</dt>
                <dd>{membership.chapter ?? '—'}</dd>
                <dt className="text-brand-on-dark">Member since</dt>
                <dd>{membership.joined_on}</dd>
            </dl>
            {(!membership.active || membership.owed > 0) && (
                <div className="mt-6 flex max-w-prose flex-col gap-3 rounded-control bg-white/10 p-4 text-sm @xl:flex-row @xl:items-center @xl:justify-between">
                    <p>
                        {membership.active
                            ? `You have ${naira(membership.owed)} to pay.`
                            : 'Your membership becomes active once your induction fee is received.'}{' '}
                        For now the Secretariat will tell you how to pay.
                    </p>
                    <Link
                        href={account()}
                        className="shrink-0 font-semibold underline decoration-white/40 underline-offset-4 hover:decoration-white"
                    >
                        Fees and payments
                    </Link>
                </div>
            )}
        </section>
    );
}

/** The Registrar recommended another grade: accept it, or appeal with reasons and evidence. */
function Recommended({ application }: { application: Application }) {
    const [appealing, setAppealing] = useState(false);
    const acceptForm = useForm({});
    const appealForm = useForm<{ appeal_reason: string; files: File[] }>({
        appeal_reason: '',
        files: [],
    });

    const onAccept = (event: FormEvent) => {
        event.preventDefault();
        acceptForm.post(accept.url());
    };
    const onAppeal = (event: FormEvent) => {
        event.preventDefault();
        appealForm.post(appeal.url(), { forceFormData: true });
    };
    const fileErrors = Object.entries(appealForm.errors)
        .filter(([key]) => key.startsWith('files'))
        .map(([, message]) => message);

    return (
        <section className="rounded-card border border-border bg-background p-6 md:p-8">
            <p className="text-sm font-semibold text-muted-foreground">
                {application.status_label}
            </p>
            <h2 className="mt-1 text-heading03 font-semibold">
                The Registrar recommends {application.recommended_grade}
            </h2>
            <p className="mt-2 max-w-prose text-muted-foreground">
                You applied for {application.applied_grade}. You can join now as{' '}
                {application.recommended_grade} and ask to be upgraded when you
                have the evidence, or appeal for {application.applied_grade}{' '}
                now.
            </p>
            <DecisionNote application={application} />

            <div className="mt-8 flex flex-wrap gap-3">
                <form onSubmit={onAccept}>
                    <Button
                        type="submit"
                        size="lg"
                        disabled={acceptForm.processing}
                    >
                        Join as {application.recommended_grade}
                    </Button>
                </form>
                <Button
                    type="button"
                    size="lg"
                    variant="outline"
                    aria-expanded={appealing}
                    onClick={() => setAppealing((open) => !open)}
                >
                    Appeal for {application.applied_grade}
                </Button>
            </div>

            {appealing && (
                <form
                    onSubmit={onAppeal}
                    className="mt-6 grid gap-5 rounded-control bg-muted/60 p-5"
                    noValidate
                >
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
        </section>
    );
}

function Declined({ application }: { application: Application }) {
    return (
        <section className="rounded-card border border-border bg-background p-6 md:p-8">
            <p className="text-sm font-semibold text-muted-foreground">
                {application.status_label}
            </p>
            <h2 className="mt-1 text-heading03 font-semibold">
                Your application was not approved this time
            </h2>
            <p className="mt-2 max-w-prose text-muted-foreground">
                You can apply again once you have what the Registrar asks for.
            </p>
            <DecisionNote application={application} />
            {application.can_reapply && (
                <div className="mt-8">
                    <ApplyButton label="Apply again" />
                </div>
            )}
        </section>
    );
}

export default function MembershipShow({
    application,
    membership,
}: {
    application: Application | null;
    membership: MembershipCard | null;
}) {
    return (
        <>
            <Head title="My membership" />
            <div className="@container max-w-6xl px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="My membership"
                    description={
                        membership
                            ? 'Your grade, membership number and chapter.'
                            : application
                              ? 'Your application and, once you are a member, your grade and membership number.'
                              : 'Welcome to OASIS Research Community. What would you like to do?'
                    }
                />

                {membership && <Member membership={membership} />}
                {!membership && !application && <Choices />}
                {!membership && application?.editable && (
                    <InProgress application={application} />
                )}
                {!membership && application?.status === 'submitted' && (
                    <Submitted application={application} />
                )}
                {!membership && application?.status === 'recommended' && (
                    <Recommended application={application} />
                )}
                {!membership && application?.status === 'declined' && (
                    <Declined application={application} />
                )}
            </div>
        </>
    );
}

MembershipShow.layout = {
    breadcrumbs: [{ title: 'My membership', href: show() }],
};
