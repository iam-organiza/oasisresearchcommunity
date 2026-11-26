import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, ExternalLink, FileText } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import Heading from '@/components/heading';
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
    appeal_reason: string | null;
    decided_grade: string | null;
    decision_reason: string | null;
    decision_improvements: string | null;
    registrar_message: string | null;
    fee_waived: boolean;
    membership: { number: string; grade: string; status: string } | null;
    events: {
        id: number;
        label: string;
        who: string | null;
        grade: string | null;
        note: string | null;
        when: string;
    }[];
    can_decide: boolean;
};

const ACTIONS = [
    { value: 'approve', label: 'Approve' },
    { value: 'recommend', label: 'Approve at a different grade' },
    { value: 'more_information', label: 'Ask for more information' },
    { value: 'decline', label: 'Decline' },
];

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <section className="border-t border-border pt-5 first:border-t-0 first:pt-0">
            <h2 className="font-semibold">{title}</h2>
            <dl className="mt-3 grid gap-x-6 gap-y-2 text-sm @2xl:grid-cols-[11rem_minmax(0,1fr)]">
                {children}
            </dl>
        </section>
    );
}

function Row({ label, children }: { label: string; children: ReactNode }) {
    return (
        <>
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="max-w-prose break-words whitespace-pre-line">
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
    const needsReason =
        data.action === 'recommend' || data.action === 'decline';

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
                    {application.grade_label ?? 'No grade chosen'}
                </h2>
                <p className="text-sm text-muted-foreground">
                    Grade applied for
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

                {application.status === 'recommended' && (
                    <p className="mt-4 rounded-control bg-muted/60 p-4 text-sm">
                        You recommended{' '}
                        <strong>{application.decided_grade}</strong>. Waiting
                        for the applicant to accept or appeal.
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
                            options={ACTIONS}
                            value={data.action}
                            onChange={(value) => setData('action', value)}
                            error={errors.action}
                        />
                        {data.action === 'recommend' && (
                            <Field
                                id="grade"
                                label="Grade to recommend"
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
                                                application.grade,
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
                                    hint="The applicant sees this."
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
                    !application.membership &&
                    application.status !== 'recommended' && (
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
                        <span className="font-semibold">Waive the fees</span>
                        <span className="block text-sm text-muted-foreground">
                            For scholarships, for example students or graduates.
                            Their membership is active without the induction
                            fee.
                        </span>
                    </span>
                </label>
            </section>
        </div>
    );
}

export default function ApplicationShow({
    application,
    grades,
}: {
    application: Application;
    grades: { value: string; label: string }[];
}) {
    return (
        <>
            <Head title={`${application.name} · Application`} />
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title={application.name}
                    description={`${application.email}${application.submitted_at ? ` · Submitted ${application.submitted_at}` : ''}`}
                />

                <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem] xl:items-start">
                    {/* The application, read top to bottom. */}
                    <div className="@container grid min-w-0 gap-5 rounded-card border border-border bg-background p-6 md:p-8">
                        {application.appeal_reason &&
                            application.status === 'submitted' && (
                                <section className="rounded-control border border-brand-crimson-strong/30 bg-brand-crimson-strong/5 p-4 dark:border-brand-crimson/40">
                                    <h2 className="font-semibold">
                                        Appeal for {application.grade_label}
                                    </h2>
                                    <p className="mt-1 text-sm whitespace-pre-line">
                                        {application.appeal_reason}
                                    </p>
                                </section>
                            )}

                        <Section title="About">
                            <Row label="Title">{application.title}</Row>
                            <Row label="Phone">{application.phone}</Row>
                            <Row label="Lives in">{application.chapter}</Row>
                        </Section>

                        <Section title="Grade">
                            <Row label="Applying for">
                                {application.grade_label}
                            </Row>
                            {application.fellow_basis && (
                                <Row label="Fellow, based on">
                                    {application.fellow_basis}
                                </Row>
                            )}
                            {application.grade_evidence && (
                                <Row label="Evidence described">
                                    {application.grade_evidence}
                                </Row>
                            )}
                        </Section>

                        <Section title="Experience">
                            <Row label="Qualification">
                                {application.qualification}
                            </Row>
                            <Row label="Role and rank">
                                {application.current_role}
                            </Row>
                            <Row label="Institution">
                                {application.institution}
                            </Row>
                            <Row label="Research experience">
                                {application.years_experience !== null &&
                                    `${application.years_experience} years`}
                            </Row>
                            <Row label="Claims experience in">
                                {application.claimed_areas.join(', ')}
                            </Row>
                            <Row
                                label={`Publications (${application.publications.length})`}
                            >
                                {application.publications.length > 0 && (
                                    <ol className="grid list-decimal gap-1.5 pl-5">
                                        {application.publications.map(
                                            (pub, i) => (
                                                <li key={i}>
                                                    {pub.link ? (
                                                        <a
                                                            href={pub.link}
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
                                                    , {pub.venue}, {pub.year}
                                                </li>
                                            ),
                                        )}
                                    </ol>
                                )}
                            </Row>
                        </Section>

                        <Section title="Route">
                            <Row label="Qualifying by">
                                {application.route_label}
                            </Row>
                            {application.exam && (
                                <Row label="Exam">{application.exam}</Row>
                            )}
                            {application.route === 'exemption' && (
                                <>
                                    <Row label="Exemption">
                                        {application.exemption_status ??
                                            'requested'}
                                    </Row>
                                    <Row label="Reason">
                                        {application.exemption_reason}
                                    </Row>
                                    <Row label="Evidence">
                                        <ul className="grid gap-1">
                                            {application.exemption_links.map(
                                                (link) => (
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
                                                ),
                                            )}
                                        </ul>
                                    </Row>
                                </>
                            )}
                            <Row label="Induction">{application.induction}</Row>
                        </Section>

                        <Section title="Documents">
                            <Row label="Files">
                                <ul className="grid gap-2">
                                    {application.documents.map((document) => (
                                        <li
                                            key={document.id}
                                            className="flex flex-wrap items-center gap-2"
                                        >
                                            <FileText
                                                aria-hidden
                                                className="size-4 text-muted-foreground"
                                            />
                                            <span className="font-semibold">
                                                {document.kind}:
                                            </span>
                                            <span className="break-all">
                                                {document.name}
                                            </span>
                                            <span className="text-muted-foreground">
                                                ({fileSize(document.size)})
                                            </span>
                                            <a
                                                href={document.url}
                                                className="inline-flex items-center gap-1 font-semibold underline decoration-border underline-offset-4 hover:decoration-current"
                                            >
                                                <Download
                                                    aria-hidden
                                                    className="size-3.5"
                                                />
                                                Download
                                            </a>
                                        </li>
                                    ))}
                                </ul>
                            </Row>
                        </Section>

                        <Section title="Referee">
                            {application.referee ? (
                                <>
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
                                </>
                            ) : (
                                <Row label="Referee">None named</Row>
                            )}
                        </Section>

                        <Section title="Consents">
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

                        <section className="border-t border-border pt-5">
                            <h2 className="font-semibold">History</h2>
                            {application.events.length === 0 ? (
                                <p className="mt-2 text-sm text-muted-foreground">
                                    Nothing yet.
                                </p>
                            ) : (
                                <ol className="mt-3 grid gap-3">
                                    {application.events.map((event) => (
                                        <li
                                            key={event.id}
                                            className="grid gap-0.5 border-l-2 border-border pl-4 text-sm"
                                        >
                                            <span className="font-semibold">
                                                {event.label}
                                                {event.grade &&
                                                    `: ${event.grade}`}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {event.who ?? 'Someone'} ·{' '}
                                                {event.when}
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

                    {/* The decision panel stays in view beside the answers on wide screens. */}
                    <aside className={cn('xl:sticky xl:top-6')}>
                        <DecisionPanel
                            application={application}
                            grades={grades}
                        />
                    </aside>
                </div>

                <p className="mt-8">
                    <Link
                        href={index()}
                        className="text-sm font-semibold underline decoration-border underline-offset-4 hover:decoration-current"
                    >
                        Back to applications
                    </Link>
                </p>
            </div>
        </>
    );
}

ApplicationShow.layout = {
    breadcrumbs: [{ title: 'Applications', href: index() }],
};
