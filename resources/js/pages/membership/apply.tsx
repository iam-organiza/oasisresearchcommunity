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

                <div className="grid gap-8 xl:grid-cols-[16rem_minmax(0,1fr)] xl:items-start 2xl:grid-cols-[17rem_minmax(0,1fr)]">
                    {/* Up to 1280px wide: six progress segments and a jump menu, so the form keeps its width. */}
                    <div className="grid gap-3 rounded-card border border-border bg-background p-4 xl:hidden">
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="font-semibold">
                                {done} of {steps.length - 1} parts done
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
                        <div aria-hidden className="grid grid-cols-6 gap-1">
                            {steps.map((item) => (
                                <span
                                    key={item.key}
                                    className={cn(
                                        'h-1.5 rounded-full',
                                        item.key === step
                                            ? 'bg-brand-crimson-strong dark:bg-brand-crimson'
                                            : item.complete
                                              ? 'bg-brand-navy dark:bg-brand-on-dark'
                                              : 'bg-brand-line dark:bg-white/15',
                                    )}
                                />
                            ))}
                        </div>
                    </div>

                    {/* Wide screens: the steps as a rail beside the form, as on My membership. */}
                    <nav
                        aria-label="Application steps"
                        className="sticky top-6 hidden rounded-card border border-border bg-background p-5 xl:block"
                    >
                        <ol>
                            {steps.map((item, i) => {
                                const isCurrent = item.key === step;
                                const last = i === steps.length - 1;

                                return (
                                    <li
                                        key={item.key}
                                        className="relative pb-2 last:pb-0"
                                    >
                                        {!last && (
                                            <span
                                                aria-hidden
                                                className={cn(
                                                    'absolute top-9 bottom-0 left-[1.3125rem] w-0.5 rounded-full',
                                                    item.complete
                                                        ? 'bg-brand-navy dark:bg-brand-on-dark'
                                                        : 'bg-brand-line dark:bg-white/15',
                                                )}
                                            />
                                        )}
                                        <Link
                                            href={edit(item.key)}
                                            aria-current={
                                                isCurrent ? 'step' : undefined
                                            }
                                            className={cn(
                                                'relative flex items-center gap-3 rounded-control px-2 py-1.5 transition-colors',
                                                'focus-visible:ring-3 focus-visible:ring-ring/50 focus-visible:outline-none',
                                                isCurrent
                                                    ? 'font-semibold'
                                                    : 'hover:bg-accent/60',
                                            )}
                                        >
                                            <span
                                                aria-hidden
                                                className={cn(
                                                    'flex size-7 shrink-0 items-center justify-center rounded-full text-xs font-semibold',
                                                    isCurrent
                                                        ? 'bg-brand-crimson-strong text-white ring-4 ring-brand-crimson-strong/20 dark:bg-brand-crimson dark:ring-brand-crimson/25'
                                                        : item.complete
                                                          ? 'bg-brand-navy text-white dark:bg-brand-on-dark dark:text-brand-navy'
                                                          : 'bg-background text-muted-foreground ring-2 ring-brand-line ring-inset dark:ring-white/25',
                                                )}
                                            >
                                                {item.complete && !isCurrent ? (
                                                    <Check
                                                        className="size-4"
                                                        strokeWidth={3}
                                                    />
                                                ) : (
                                                    i + 1
                                                )}
                                            </span>
                                            <span
                                                className={cn(
                                                    'flex-1',
                                                    !isCurrent &&
                                                        !item.complete &&
                                                        'text-muted-foreground',
                                                )}
                                            >
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
                        <p className="mt-4 border-t border-border pt-4 text-sm">
                            <Link
                                href={show()}
                                className="font-semibold underline decoration-border underline-offset-4 hover:decoration-current"
                            >
                                Back to my membership
                            </Link>
                        </p>
                    </nav>

                    {/* The current step's form, capped so fields and answers stay a readable width on very wide screens. */}
                    <section
                        aria-labelledby="step-heading"
                        className="@container w-full max-w-5xl min-w-0 rounded-card border border-border bg-background"
                    >
                        <header className="rounded-t-card bg-brand-navy px-6 py-6 text-white md:px-8 md:py-7 dark:bg-brand-night-raised">
                            <p className="text-sm font-semibold text-brand-on-dark">
                                Step {index + 1} of {steps.length}
                            </p>
                            <h2
                                id="step-heading"
                                className="mt-1 text-heading02 font-bold"
                            >
                                {current.label}
                            </h2>
                            <p className="mt-1 max-w-prose text-brand-on-dark">
                                {INTROS[step]}
                            </p>
                        </header>
                        <div className="p-6 md:p-8">
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
                        </div>
                    </section>
                </div>
            </div>
        </>
    );
}

Apply.layout = {
    breadcrumbs: [{ title: 'My membership', href: show() }],
};
