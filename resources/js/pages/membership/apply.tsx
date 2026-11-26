import { Head, Link, router } from '@inertiajs/react';
import { Check } from 'lucide-react';
import Heading from '@/components/heading';
import { selectClass } from '@/components/membership/form-bits';
import {
    AboutStep,
    DocumentsStep,
    ExperienceStep,
    GradeStep,
    ReviewStep,
    RouteStep,
} from '@/components/membership/steps';
import type {
    ApplicationData,
    DocumentItem,
    Options,
    StepItem,
    StepKey,
} from '@/components/membership/types';
import { cn } from '@/lib/utils';
import { show } from '@/routes/membership';
import { edit } from '@/routes/membership/apply';

const INTROS: Record<StepKey, string> = {
    about: 'How to reach you, and where you live. Your state sets your chapter.',
    grade: 'Choose the grade that fits your qualifications and experience.',
    experience: 'Your qualifications, current role and publications.',
    route: 'How you qualify, and the induction event you will attend.',
    documents: 'Your CV and the evidence for your grade.',
    review: 'Check your answers, then submit. We offer you a grade straight away.',
};

export default function Apply({
    step,
    steps,
    application,
    documents,
    options,
}: {
    step: StepKey;
    steps: StepItem[];
    missingSteps: string[];
    application: ApplicationData;
    documents: DocumentItem[];
    options: Options;
}) {
    const index = steps.findIndex((item) => item.key === step);
    const current = steps[index];
    const previous = index > 0 ? steps[index - 1].key : null;
    const stepProps = { application, options, previous };
    const done = steps.filter((item) => item.complete).length;

    return (
        <>
            <Head title={`${current.label} · Membership application`} />
            <div className="px-4 py-8 md:px-8 md:py-10 xl:px-12">
                <Heading
                    title="Apply for membership"
                    description="Your answers save at every step, so you can stop and come back later."
                />

                <div className="grid gap-8 xl:grid-cols-[17rem_minmax(0,1fr)] xl:items-start 2xl:grid-cols-[18rem_minmax(0,1fr)]">
                    {/* Up to 1280px wide: a compact "step x of 7" bar with a jump menu, so the form keeps its width. */}
                    <div className="flex flex-wrap items-center justify-between gap-3 rounded-card border border-border bg-background p-4 xl:hidden">
                        <p className="font-semibold">
                            Step {index + 1} of {steps.length}
                            <span className="ml-2 font-normal text-muted-foreground">
                                {done} of {steps.length - 1} done
                            </span>
                        </p>
                        <label htmlFor="step-jump" className="sr-only">
                            Go to a step
                        </label>
                        <select
                            id="step-jump"
                            value={step}
                            onChange={(event) =>
                                router.visit(
                                    edit.url(event.target.value as StepKey),
                                )
                            }
                            className={cn(selectClass, 'w-auto')}
                        >
                            {steps.map((item, i) => (
                                <option key={item.key} value={item.key}>
                                    {i + 1}. {item.label}
                                    {item.complete ? ' ✓' : ''}
                                </option>
                            ))}
                        </select>
                    </div>

                    {/* Wide screens: the seven steps listed beside the form, ticked as they are done. */}
                    <nav
                        aria-label="Application steps"
                        className="sticky top-6 hidden rounded-card border border-border bg-background p-3 xl:block"
                    >
                        <ol className="grid gap-1">
                            {steps.map((item, i) => {
                                const isCurrent = item.key === step;

                                return (
                                    <li key={item.key}>
                                        <Link
                                            href={edit(item.key)}
                                            aria-current={
                                                isCurrent ? 'step' : undefined
                                            }
                                            className={cn(
                                                'relative flex items-center gap-3 rounded-control px-3 py-2.5 transition-colors',
                                                'focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                                                isCurrent
                                                    ? 'bg-accent font-semibold before:absolute before:inset-y-2 before:left-0 before:w-1 before:rounded-full before:bg-brand-crimson-strong dark:before:bg-brand-crimson'
                                                    : 'hover:bg-accent/60',
                                            )}
                                        >
                                            <span
                                                aria-hidden
                                                className={cn(
                                                    'flex size-7 shrink-0 items-center justify-center rounded-full text-sm font-semibold tabular-nums',
                                                    item.complete
                                                        ? 'bg-primary text-primary-foreground'
                                                        : 'ring-1 ring-border ring-inset',
                                                )}
                                            >
                                                {item.complete ? (
                                                    <Check className="size-4" />
                                                ) : (
                                                    i + 1
                                                )}
                                            </span>
                                            <span className="flex-1">
                                                {item.label}
                                            </span>
                                            {item.complete && (
                                                <span className="sr-only">
                                                    (done)
                                                </span>
                                            )}
                                        </Link>
                                    </li>
                                );
                            })}
                        </ol>
                        <p className="mt-3 border-t border-border px-3 pt-3 text-sm text-muted-foreground">
                            <Link
                                href={show()}
                                className="font-semibold text-foreground underline decoration-border underline-offset-4 hover:decoration-current"
                            >
                                Back to my membership
                            </Link>
                        </p>
                    </nav>

                    {/* The current step's form, capped so fields and answers stay a readable width on very wide screens. */}
                    <section
                        aria-labelledby="step-heading"
                        className="@container w-full max-w-5xl min-w-0 rounded-card border border-border bg-background p-6 md:p-8"
                    >
                        <p className="text-sm font-semibold text-muted-foreground tabular-nums">
                            Step {index + 1} of {steps.length}
                        </p>
                        <h2
                            id="step-heading"
                            className="mt-1 text-heading03 font-semibold"
                        >
                            {current.label}
                        </h2>
                        <p className="mt-1 mb-8 text-muted-foreground">
                            {INTROS[step]}
                        </p>

                        {step === 'about' && <AboutStep {...stepProps} />}
                        {step === 'grade' && <GradeStep {...stepProps} />}
                        {step === 'experience' && (
                            <ExperienceStep {...stepProps} />
                        )}
                        {step === 'route' && <RouteStep {...stepProps} />}
                        {step === 'documents' && (
                            <DocumentsStep
                                {...stepProps}
                                documents={documents}
                            />
                        )}
                        {step === 'review' && (
                            <ReviewStep
                                {...stepProps}
                                steps={steps}
                                documents={documents}
                            />
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

Apply.layout = {
    breadcrumbs: [{ title: 'My membership', href: show() }],
};
