import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, ExternalLink, FileText } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import {
    ChoiceTiles,
    Field,
    fieldClass,
    selectClass,
} from '@/components/membership/form-bits';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    decide,
    exemption,
    feeWaiver,
    index,
} from '@/routes/admin/applications';

type Application = {
    id: number;
    status: string;
    status_label: string;
    name: string;
    email: string;
    title: string | null;
    phone: string | null;
    chapter: string | null;
    grade: string | null;
    grade_label: string | null;
    fellow_basis: string | null;
    grade_evidence: string | null;
    qualification: string | null;
    current_role: string | null;
    role_type: string | null;
    studying: string | null;
    other_evidence: string | null;
    offered_grade: string | null;
    offered_label: string | null;
    qualifying_label: string | null;
    offer_reasons: string[];
    flags: string[];
    reviewed: boolean;
    needs_exam: boolean;
    exam_attempts: {
        id: number;
        label: string;
        percent: number | null;
        passed: boolean | null;
        date: string | null;
        areas: string;
    }[];
    institution: string;
    years_experience: number | null;
    claimed_areas: string[];
    publications: {
        title: string;
        venue: string;
        year: number;
        link: string | null;
    }[];
    route: string | null;
    route_label: string | null;
    exam: string | null;
    exemption_reason: string | null;
    exemption_links: string[];
    exemption_status: string | null;
    induction: string;
    referee: {
        name: string;
        email: string;
        relationship: string;
        number: string | null;
        status: string;
    } | null;
    documents: {
        id: number;
        name: string;
        kind: string;
        size: number;
        url: string;
    }[];
    directory_consent: boolean;
    keep_documents_two_years: boolean;
    submitted_at: string | null;
    waiting_days: number | null;
    appeal_reason: string | null;
    decided_grade: string | null;
    decision_reason: string | null;
    decision_improvements: string | null;
    registrar_message: string | null;
    fee_waived: boolean;
    membership: {
        number: string;
        grade: string;
        status: string;
        account_url: string;
    } | null;
    events: {
        id: number;
        label: string;
        who: string | null;
        grade: string | null;
        note: string | null;
        when: string;
    }[];
    can_decide: boolean;
    can_approve: boolean;
};

/** The decisions open to the Registrar at this point. */
function actionsFor(application: Application) {
    return [
        application.flags.length > 0 &&
            !application.reviewed &&
            ['offered', 'accepted'].includes(application.status) && {
                value: 'confirm',
                label: 'Checked: the offer stands',
            },
        {
            value: 'correct',
            label:
                application.status === 'submitted'
                    ? 'Answer the appeal with an offer'
                    : 'Correct the offer',
        },
        application.can_approve && {
            value: 'approve',
            label: 'Approve and put on the register',
        },
        { value: 'more_information', label: 'Ask for more information' },
        { value: 'decline', label: 'Decline' },
    ].filter(Boolean) as { value: string; label: string }[];
}

function Section({
    id,
    title,
    children,
}: {
    id: string;
    title: string;
    children: ReactNode;
}) {
    return (
        <section
            id={id}
            aria-labelledby={`${id}-heading`}
            className="scroll-mt-20 border-t border-border pt-6 first:border-t-0 first:pt-0"
        >
            <h2 id={`${id}-heading`} className="font-semibold">
                {title}
            </h2>
            <dl className="mt-3 grid gap-x-6 gap-y-1 text-sm @xl:grid-cols-[11rem_minmax(0,1fr)] @xl:gap-y-2.5">
                {children}
            </dl>
        </section>
    );
}

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="mb-2 max-w-prose break-words whitespace-pre-line @xl:mb-0">
                {children || '—'}
            </dd>
        </>
    );
}

const fileSize = (bytes: number) =>
    bytes >= 1024 * 1024
        ? `${(bytes / 1024 / 1024).toFixed(1)} MB`
        : `${Math.max(1, Math.round(bytes / 1024))} KB`;

function DecisionPanel({
    application,
    grades,
}: {
    application: Application;
    grades: { value: string; label: string }[];
}) {
    const form = useForm({
        action: '',
        grade: '',
        reason: '',
        improvements: '',
        message: '',
    });
    const exemptionForm = useForm({ decision: '', note: '' });
    const { data, setData, errors } = form;
    const needsReason = data.action === 'correct' || data.action === 'decline';

    const onDecide = (event: FormEvent) => {
        event.preventDefault();
        if (
            data.action === 'decline' &&
            !window.confirm(`Decline ${application.name}'s application?`)
        ) {
            return;
        }
        form.post(decide.url(application.id), { preserveScroll: true });
    };

    const onExemption = (decision: 'approved' | 'refused') => {
        exemptionForm.transform((values) => ({ ...values, decision }));
        exemptionForm.post(exemption.url(application.id), {
            preserveScroll: true,
        });
    };

    return (
        <div className="grid gap-5">
            {application.route === 'exemption' &&
                application.exemption_status === 'requested' &&
                application.can_decide && (
                    <section className="rounded-card border border-brand-crimson-strong/40 bg-background p-5 dark:border-brand-crimson/50">
                        <h2 className="font-semibold">
                            Exam exemption requested
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Check the reason and the evidence links, then
                            decide. If you refuse, they take the exam instead.
                        </p>
                        <div className="mt-4">
                            <Field
                                id="exemption_note"
                                label="Note to the applicant"
                                hint="Required if you refuse."
                                error={exemptionForm.errors.note}
                            >
                                <textarea
                                    id="exemption_note"
                                    rows={3}
                                    value={exemptionForm.data.note}
                                    onChange={(e) =>
                                        exemptionForm.setData(
                                            'note',
                                            e.target.value,
                                        )
                                    }
                                    className={fieldClass}
                                />
                            </Field>
                        </div>
                        <div className="mt-4 flex flex-wrap gap-2">
                            <Button
                                type="button"
                                disabled={exemptionForm.processing}
                                onClick={() => onExemption('approved')}
                            >
                                Approve exemption
                            </Button>
                            <Button
                                type="button"
                                variant="outline"
                                disabled={exemptionForm.processing}
                                onClick={() => onExemption('refused')}
                            >
                                Refuse
                            </Button>
                        </div>
                    </section>
                )}

            <section className="@container rounded-card border border-border bg-background p-5">
                <p className="text-sm font-semibold text-muted-foreground">
                    {application.status_label}
                </p>
                <h2 className="mt-1 text-heading03 font-semibold">
                    {application.offered_label
                        ? `Offered ${application.offered_label}`
                        : (application.grade_label ?? 'No grade chosen')}
                </h2>
                <p className="text-sm text-muted-foreground">
                    Asked for {application.grade_label ?? '—'}
                    {application.qualifying_label &&
                        application.qualifying_label !==
                            application.offered_label &&
                        `; answers meet ${application.qualifying_label}`}
                </p>
                {application.membership && (
                    <dl className="mt-4 grid gap-1 rounded-control bg-muted/60 p-4 text-sm">
                        <dt className="text-muted-foreground">
                            Membership number
                        </dt>
                        <dd className="font-semibold tabular-nums">
                            {application.membership.number}
                        </dd>
                        <dd>
                            {application.membership.grade} ·{' '}
                            {application.membership.status}
                        </dd>
                    </dl>
                )}

                {application.status === 'offered' && (
                    <p className="mt-4 rounded-control bg-muted/60 p-4 text-sm">
                        Waiting for the applicant to accept or appeal.
                    </p>
                )}
                {application.status === 'accepted' && (
                    <p className="mt-4 rounded-control bg-muted/60 p-4 text-sm">
                        {application.needs_exam
                            ? 'Accepted. Next for them: the exam fee and the exam.'
                            : 'Accepted, with no exam to take. Approve them once you have checked their route.'}
                    </p>
                )}

                {application.can_decide ? (
                    <form
                        onSubmit={onDecide}
                        className="mt-5 grid gap-5"
                        noValidate
                    >
                        <ChoiceTiles
                            name="action"
                            legend="Decision"
                            options={actionsFor(application)}
                            value={data.action}
                            onChange={(value) => setData('action', value)}
                            error={errors.action}
                        />
                        {data.action === 'correct' && (
                            <Field
                                id="grade"
                                label="Grade to offer"
                                error={errors.grade}
                            >
                                <select
                                    id="grade"
                                    value={data.grade}
                                    onChange={(e) =>
                                        setData('grade', e.target.value)
                                    }
                                    className={selectClass}
                                >
                                    <option value="">Choose a grade</option>
                                    {grades
                                        .filter(
                                            (grade) =>
                                                grade.value !==
                                                application.offered_grade,
                                        )
                                        .map((grade) => (
                                            <option
                                                key={grade.value}
                                                value={grade.value}
                                            >
                                                {grade.label}
                                            </option>
                                        ))}
                                </select>
                            </Field>
                        )}
                        {needsReason && (
                            <>
                                <Field
                                    id="reason"
                                    label="Reason"
                                    hint={
                                        data.action === 'correct'
                                            ? 'The applicant sees this with the new offer. For example: your CV shows two supervised theses, so we offer Fellow.'
                                            : 'The applicant sees this.'
                                    }
                                    error={errors.reason}
                                >
                                    <textarea
                                        id="reason"
                                        rows={3}
                                        value={data.reason}
                                        onChange={(e) =>
                                            setData('reason', e.target.value)
                                        }
                                        className={fieldClass}
                                    />
                                </Field>
                                {data.action === 'decline' && (
                                    <Field
                                        id="improvements"
                                        label="What to improve or provide evidence of"
                                        hint="For example: Fellow needs 10 publications; you listed 8. Add evidence of 2 more."
                                        error={errors.improvements}
                                    >
                                        <textarea
                                            id="improvements"
                                            rows={3}
                                            value={data.improvements}
                                            onChange={(e) =>
                                                setData(
                                                    'improvements',
                                                    e.target.value,
                                                )
                                            }
                                            className={fieldClass}
                                        />
                                    </Field>
                                )}
                            </>
                        )}
                        {data.action === 'more_information' && (
                            <Field
                                id="message"
                                label="What you need from them"
                                hint="They can change their answers and documents, then submit again."
                                error={errors.message}
                            >
                                <textarea
                                    id="message"
                                    rows={4}
                                    value={data.message}
                                    onChange={(e) =>
                                        setData('message', e.target.value)
                                    }
                                    className={fieldClass}
                                />
                            </Field>
                        )}
                        {data.action && (
                            <p className="rounded-control bg-muted/60 p-3 text-sm">
                                <span className="font-semibold">
                                    What happens:{' '}
                                </span>
                                {
                                    {
                                        confirm:
                                            'Nothing changes for them; the offer stands and the flag is cleared.',
                                        correct: `They get a new offer${data.grade ? ` of ${grades.find((grade) => grade.value === data.grade)?.label}` : ''} with your reason, by email and on My membership.`,
                                        approve:
                                            'They go on the register and their induction fee goes on their account.',
                                        more_information:
                                            'Their application opens for changes, with your message at the top; they submit again.',
                                        decline:
                                            'They are told by email, with your reason and what to improve, and can apply again.',
                                    }[data.action]
                                }
                            </p>
                        )}
                        <div>
                            <Button
                                type="submit"
                                size="lg"
                                disabled={form.processing || !data.action}
                            >
                                Save decision
                            </Button>
                        </div>
                    </form>
                ) : (
                    !application.membership && (
                        <p className="mt-4 text-sm text-muted-foreground">
                            {application.status === 'more_information'
                                ? 'Waiting for the applicant to send more information.'
                                : application.status === 'draft'
                                  ? 'The applicant has not submitted yet.'
                                  : 'A decision has been made.'}
                        </p>
                    )
                )}
            </section>

            {application.membership ? (
                <section className="rounded-card border border-border bg-background p-5">
                    <p className="font-semibold">Fees and payments</p>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Waive a fee, record a bank transfer or add an exam fee
                        or subscription on their account.
                    </p>
                    <Button asChild variant="outline" className="mt-4">
                        <Link href={application.membership.account_url}>
                            Open their fees and payments
                        </Link>
                    </Button>
                </section>
            ) : (
                <section className="rounded-card border border-border bg-background p-5">
                    <label className="flex cursor-pointer items-start gap-3">
                        <input
                            type="checkbox"
                            checked={application.fee_waived}
                            onChange={(e) =>
                                router.patch(
                                    feeWaiver.url(application.id),
                                    { fee_waived: e.target.checked },
                                    { preserveScroll: true },
                                )
                            }
                            className="mt-1 size-4 shrink-0 accent-primary"
                        />
                        <span>
                            <span className="font-semibold">
                                Waive the induction fee
                            </span>
                            <span className="block text-sm text-muted-foreground">
                                For scholarships, for example students or
                                graduates. Their membership is active without
                                paying it. Waivers for the exam fee and yearly
                                subscriptions come with online payments.
                            </span>
                        </span>
                    </label>
                </section>
            )}
        </div>
    );
}

const SECTIONS = [
    ['about', 'About'],
    ['grade', 'Grade'],
    ['experience', 'Experience'],
    ['route', 'Route'],
    ['exam', 'Exam'],
    ['consents', 'Consents'],
    ['history', 'History'],
] as const;

const waited = (days: number) =>
    days === 0 ? 'Since today' : days === 1 ? '1 day' : `${days} days`;

/** Name, status, the grade asked for and offered, the wait and the chapter. */
function SummaryBand({
    application,
    lower,
}: {
    application: Application;
    lower: boolean;
}) {
    const changed =
        application.offered_label &&
        application.offered_label !== application.grade_label;

    return (
        <header className="rounded-card bg-brand-navy p-6 text-white md:p-8 dark:bg-brand-night-raised">
            <div className="flex flex-wrap items-center gap-3">
                <h1 className="text-heading02 font-bold">{application.name}</h1>
                <span className="rounded-full bg-white/15 px-3 py-1 text-sm font-semibold">
                    {application.status_label}
                </span>
            </div>
            <p className="mt-1 text-brand-on-dark">
                {[application.email, application.phone]
                    .filter(Boolean)
                    .join(', ')}
            </p>
            <dl className="mt-6 flex flex-wrap gap-x-10 gap-y-4">
                <div>
                    <dt className="text-sm text-brand-on-dark">Grade</dt>
                    <dd className="text-heading03 font-semibold">
                        {changed ? (
                            <>
                                {application.grade_label}
                                <span aria-hidden className="mx-2 opacity-60">
                                    →
                                </span>
                                <span className="sr-only">
                                    {' '}
                                    applied, offered{' '}
                                </span>
                                <span
                                    className={cn(
                                        lower &&
                                            'rounded-sm bg-brand-crimson px-1.5',
                                    )}
                                >
                                    {application.offered_label}
                                </span>
                            </>
                        ) : (
                            (application.offered_label ??
                            application.grade_label ??
                            '—')
                        )}
                    </dd>
                </div>
                {application.waiting_days !== null && (
                    <div>
                        <dt className="text-sm text-brand-on-dark">Waiting</dt>
                        <dd className="text-heading03 font-semibold">
                            {waited(application.waiting_days)}
                        </dd>
                    </div>
                )}
                {application.submitted_at && (
                    <div>
                        <dt className="text-sm text-brand-on-dark">
                            Submitted
                        </dt>
                        <dd className="text-heading03 font-semibold">
                            {application.submitted_at.split(',')[0]}
                        </dd>
                    </div>
                )}
                {application.chapter && (
                    <div className="min-w-0">
                        <dt className="text-sm text-brand-on-dark">Lives in</dt>
                        <dd className="text-heading03 font-semibold">
                            {application.chapter}
                        </dd>
                    </div>
                )}
            </dl>
        </header>
    );
}

/** What put this application in front of a person, read first. */
function WhyItsHere({ application }: { application: Application }) {
    const appeal =
        application.status === 'submitted' && application.appeal_reason;
    const exemption =
        application.route === 'exemption' &&
        application.exemption_status === 'requested';
    const flags = application.flags.filter((flag) => !flag.startsWith('Asks'));

    if (
        !appeal &&
        !exemption &&
        flags.length === 0 &&
        application.offer_reasons.length === 0
    ) {
        return null;
    }

    return (
        <section
            aria-labelledby="why-heading"
            className="rounded-card border border-brand-crimson-strong/40 bg-background p-6 md:p-8 dark:border-brand-crimson/50"
        >
            <h2 id="why-heading" className="text-heading03 font-semibold">
                {application.reviewed && !appeal && !exemption
                    ? 'Flagged, and checked'
                    : "Why it's here"}
            </h2>
            <div className="mt-4 grid gap-5">
                {appeal && (
                    <div>
                        <p className="text-sm font-semibold">
                            Appeal for {application.grade_label}
                        </p>
                        <blockquote className="mt-1.5 border-l-2 border-brand-crimson-strong pl-4 whitespace-pre-line dark:border-brand-crimson">
                            {application.appeal_reason}
                        </blockquote>
                    </div>
                )}
                {exemption && (
                    <div>
                        <p className="text-sm font-semibold">
                            Asks to skip the exam
                        </p>
                        {application.exemption_reason && (
                            <blockquote className="mt-1.5 border-l-2 border-brand-crimson-strong pl-4 whitespace-pre-line dark:border-brand-crimson">
                                {application.exemption_reason}
                            </blockquote>
                        )}
                        {application.exemption_links.length > 0 && (
                            <ul className="mt-2 grid gap-1 text-sm">
                                {application.exemption_links.map((link) => (
                                    <li key={link}>
                                        <a
                                            href={link}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="inline-flex items-center gap-1 break-all underline decoration-border underline-offset-4 hover:decoration-current"
                                        >
                                            {link}
                                            <ExternalLink
                                                aria-hidden
                                                className="size-3.5 shrink-0"
                                            />
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}
                {flags.length > 0 && (
                    <div>
                        <p className="text-sm font-semibold">Flags</p>
                        <ul className="mt-1.5 flex flex-wrap gap-1.5">
                            {flags.map((flag) => (
                                <li
                                    key={flag}
                                    className="rounded-full bg-muted px-2.5 py-0.5 text-sm font-semibold"
                                >
                                    {flag}
                                </li>
                            ))}
                        </ul>
                        {application.other_evidence && (
                            <blockquote className="mt-3 border-l-2 border-border pl-4 text-sm whitespace-pre-line">
                                <span className="mb-1 block font-semibold">
                                    Anything else relevant, in their words
                                </span>
                                {application.other_evidence}
                            </blockquote>
                        )}
                    </div>
                )}
                {application.offer_reasons.length > 0 && (
                    <div>
                        <p className="text-sm font-semibold">
                            Why the site offered{' '}
                            {application.offered_label ?? 'this grade'}
                        </p>
                        <ul className="mt-1.5 grid list-disc gap-1 pl-5 text-sm">
                            {application.offer_reasons.map((reason) => (
                                <li key={reason}>{reason}</li>
                            ))}
                        </ul>
                    </div>
                )}
            </div>
        </section>
    );
}

function Documents({ documents }: { documents: Application['documents'] }) {
    return (
        <section
            aria-labelledby="documents-heading"
            className="rounded-card border border-border bg-background p-6 md:p-8"
        >
            <h2 id="documents-heading" className="text-heading03 font-semibold">
                Documents
                <span className="ml-2 text-base font-normal text-muted-foreground">
                    {documents.length}
                </span>
            </h2>
            {documents.length === 0 ? (
                <p className="mt-3 text-sm text-muted-foreground">
                    No documents uploaded.
                </p>
            ) : (
                <ul className="mt-4 grid gap-3 @2xl:grid-cols-2">
                    {documents.map((document) => (
                        <li key={document.id}>
                            <a
                                href={document.url}
                                className="group flex items-center gap-3 rounded-control border border-border p-3 transition-colors hover:border-foreground/40 focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                            >
                                <FileText
                                    aria-hidden
                                    className="size-8 shrink-0 text-muted-foreground"
                                />
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-semibold">
                                        {document.kind}
                                    </span>
                                    <span className="block truncate text-sm text-muted-foreground">
                                        {document.name},{' '}
                                        {fileSize(document.size)}
                                    </span>
                                </span>
                                <Download
                                    aria-hidden
                                    className="size-4 shrink-0 text-muted-foreground group-hover:text-foreground"
                                />
                                <span className="sr-only">Download</span>
                            </a>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

export default function ApplicationShow({
    application,
    grades,
}: {
    application: Application;
    grades: { value: string; label: string }[];
}) {
    const rank = (value: string | null) =>
        grades.findIndex((grade) => grade.value === value);
    const lower =
        application.offered_grade !== null &&
        application.grade !== null &&
        rank(application.offered_grade) > rank(application.grade);
    const sections = SECTIONS.filter(
        ([id]) => id !== 'exam' || application.exam_attempts.length > 0,
    );

    return (
        <>
            <Head title={`${application.name} · Application`} />
            <div className="w-full px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <p className="mb-4">
                    <Link
                        href={index()}
                        className="text-sm font-semibold underline decoration-border underline-offset-4 hover:decoration-current"
                    >
                        Back to applications
                    </Link>
                </p>
                <SummaryBand application={application} lower={lower} />

                <div className="mt-6 grid gap-6 *:min-w-0 xl:grid-cols-[minmax(0,1fr)_24rem] xl:items-start">
                    <div className="@container grid w-full gap-6 *:min-w-0">
                        <WhyItsHere application={application} />
                        <Documents documents={application.documents} />

                        {/* The answers, with a jump list that stays in view. */}
                        <div className="rounded-card border border-border bg-background">
                            <nav
                                aria-label="Parts of the application"
                                className="sticky top-0 z-10 flex gap-1 overflow-x-auto rounded-t-card border-b border-border bg-background/95 px-4 py-2 backdrop-blur md:px-6"
                            >
                                {sections.map(([id, label]) => (
                                    <a
                                        key={id}
                                        href={`#${id}`}
                                        className="shrink-0 rounded-full px-3 py-1.5 text-sm font-semibold text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none"
                                    >
                                        {label}
                                    </a>
                                ))}
                            </nav>
                            <div className="grid gap-6 p-6 md:p-8">
                                <Section id="about" title="About">
                                    <Row label="Title">{application.title}</Row>
                                    <Row label="Phone">{application.phone}</Row>
                                    <Row label="Lives in">
                                        {application.chapter}
                                    </Row>
                                </Section>

                                <Section id="grade" title="Grade">
                                    <Row label="Applying for">
                                        {application.grade_label}
                                    </Row>
                                    {application.fellow_basis && (
                                        <Row label="Fellow, based on (old form)">
                                            {application.fellow_basis}
                                        </Row>
                                    )}
                                    {application.grade_evidence && (
                                        <Row label="Evidence described">
                                            {application.grade_evidence}
                                        </Row>
                                    )}
                                </Section>

                                <Section id="experience" title="Experience">
                                    <Row label="Qualification">
                                        {application.qualification}
                                    </Row>
                                    <Row label="Studying now">
                                        {application.studying}
                                    </Row>
                                    <Row label="Kind of work">
                                        {application.role_type}
                                    </Row>
                                    <Row label="Role and rank">
                                        {application.current_role}
                                    </Row>
                                    <Row label="Institution">
                                        {application.institution}
                                    </Row>
                                    <Row label="Research experience">
                                        {application.years_experience !==
                                            null &&
                                            `${application.years_experience} years`}
                                    </Row>
                                    <Row label="Claims experience in">
                                        {application.claimed_areas.join(', ')}
                                    </Row>
                                    {application.other_evidence && (
                                        <Row label="Anything else relevant">
                                            {application.other_evidence}
                                        </Row>
                                    )}
                                    <Row
                                        label={`Publications (${application.publications.length})`}
                                    >
                                        {application.publications.length >
                                            0 && (
                                            <ol className="grid list-decimal gap-1.5 pl-5">
                                                {application.publications.map(
                                                    (pub, i) => (
                                                        <li key={i}>
                                                            {pub.link ? (
                                                                <a
                                                                    href={
                                                                        pub.link
                                                                    }
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    className="font-semibold underline decoration-border underline-offset-4 hover:decoration-current"
                                                                >
                                                                    {pub.title}
                                                                </a>
                                                            ) : (
                                                                <span className="font-semibold">
                                                                    {pub.title}
                                                                </span>
                                                            )}
                                                            , {pub.venue},{' '}
                                                            {pub.year}
                                                        </li>
                                                    ),
                                                )}
                                            </ol>
                                        )}
                                    </Row>
                                </Section>

                                <Section id="route" title="Route">
                                    <Row label="Qualifying by">
                                        {application.route_label}
                                    </Row>
                                    {application.exam && (
                                        <Row label="Exam">
                                            {application.exam}
                                        </Row>
                                    )}
                                    {application.route === 'exemption' && (
                                        <Row label="Exemption">
                                            {application.exemption_status ??
                                                'requested'}
                                        </Row>
                                    )}
                                    <Row label="Induction">
                                        {application.induction}
                                    </Row>
                                </Section>

                                {application.referee && (
                                    <Section
                                        id="referee"
                                        title="Referee (named before referees were dropped)"
                                    >
                                        <Row label="Name">
                                            {application.referee.name}
                                        </Row>
                                        <Row label="Email">
                                            {application.referee.email}
                                        </Row>
                                        <Row label="Relationship">
                                            {application.referee.relationship}
                                        </Row>
                                        <Row label="Their answer">
                                            {application.referee.status}
                                        </Row>
                                        {application.referee.number && (
                                            <Row label="Membership number">
                                                {application.referee.number}
                                            </Row>
                                        )}
                                    </Section>
                                )}

                                {application.exam_attempts.length > 0 && (
                                    <Section id="exam" title="Exam attempts">
                                        {application.exam_attempts.map(
                                            (attempt) => (
                                                <Row
                                                    key={attempt.id}
                                                    label={attempt.label}
                                                >
                                                    <span className="font-semibold">
                                                        {attempt.percent}%
                                                        {attempt.passed &&
                                                            ', passed'}
                                                    </span>
                                                    <span className="block text-muted-foreground">
                                                        {attempt.date}
                                                    </span>
                                                    <span className="block text-muted-foreground">
                                                        {attempt.areas}
                                                    </span>
                                                </Row>
                                            ),
                                        )}
                                    </Section>
                                )}

                                <Section id="consents" title="Consents">
                                    <Row label="Member directory">
                                        {application.directory_consent
                                            ? 'Yes, list them'
                                            : 'No'}
                                    </Row>
                                    <Row label="Keep documents 2 years">
                                        {application.keep_documents_two_years
                                            ? 'Yes, if declined'
                                            : 'No (6 months)'}
                                    </Row>
                                </Section>

                                <section
                                    id="history"
                                    aria-labelledby="history-heading"
                                    className="scroll-mt-20 border-t border-border pt-6"
                                >
                                    <h2
                                        id="history-heading"
                                        className="font-semibold"
                                    >
                                        History
                                    </h2>
                                    {application.events.length === 0 ? (
                                        <p className="mt-2 text-sm text-muted-foreground">
                                            Nothing yet.
                                        </p>
                                    ) : (
                                        <ol className="mt-3 grid gap-4">
                                            {application.events.map((event) => (
                                                <li
                                                    key={event.id}
                                                    className="relative grid gap-0.5 pl-6 text-sm"
                                                >
                                                    <span
                                                        aria-hidden
                                                        className="absolute top-1.5 left-0 size-2.5 rounded-full bg-brand-navy dark:bg-brand-on-dark"
                                                    />
                                                    <span className="font-semibold">
                                                        {event.label}
                                                        {event.grade &&
                                                            `: ${event.grade}`}
                                                    </span>
                                                    <span className="text-muted-foreground">
                                                        {event.who ?? 'Someone'}
                                                        , {event.when}
                                                    </span>
                                                    {event.note && (
                                                        <span className="mt-1 whitespace-pre-line">
                                                            {event.note}
                                                        </span>
                                                    )}
                                                </li>
                                            ))}
                                        </ol>
                                    )}
                                </section>
                            </div>
                        </div>
                    </div>

                    {/* The decision panel stays in view beside the answers on wide screens. */}
                    <aside className="xl:sticky xl:top-6 xl:max-h-[calc(100dvh-3rem)] xl:overflow-y-auto xl:overscroll-contain">
                        <DecisionPanel
                            application={application}
                            grades={grades}
                        />
                    </aside>
                </div>
            </div>
        </>
    );
}

ApplicationShow.layout = {
    breadcrumbs: [{ title: 'Applications', href: index() }],
};
