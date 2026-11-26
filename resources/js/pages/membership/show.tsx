import { Head, Link, useForm } from '@inertiajs/react';
import { BookOpen, Check, Clock, FileCheck2 } from 'lucide-react';
import type { FormEvent } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { knowledgeBase } from '@/routes';
import { show } from '@/routes/membership';
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
                        The Registrar is reviewing your application
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
            </dl>
        </section>
    );
}

function Decided({ application }: { application: Application }) {
    return (
        <section className="rounded-card border border-border bg-background p-6 md:p-8">
            <p className="text-sm font-semibold text-muted-foreground">
                {application.status_label}
            </p>
            <h2 className="mt-1 text-heading03 font-semibold">
                Your membership application
            </h2>
            {application.status === 'declined' && (
                <div className="mt-6">
                    <ApplyButton label="Apply again" />
                </div>
            )}
        </section>
    );
}

export default function MembershipShow({
    application,
}: {
    application: Application | null;
}) {
    return (
        <>
            <Head title="My membership" />
            <div className="@container max-w-6xl px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="My membership"
                    description={
                        application
                            ? 'Your application and, once you are a member, your grade and membership number.'
                            : 'Welcome to OASIS Research Community. What would you like to do?'
                    }
                />

                {!application && <Choices />}
                {application?.editable && (
                    <InProgress application={application} />
                )}
                {application?.status === 'submitted' && (
                    <Submitted application={application} />
                )}
                {application &&
                    ['approved', 'declined'].includes(application.status) && (
                        <Decided application={application} />
                    )}
            </div>
        </>
    );
}

MembershipShow.layout = {
    breadcrumbs: [{ title: 'My membership', href: show() }],
};
