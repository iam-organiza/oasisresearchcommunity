import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

export type JourneyStep = {
    key: string;
    label: string;
    state: 'done' | 'current' | 'upcoming' | 'skipped';
    detail: string | null;
};

const stateText: Record<JourneyStep['state'], string> = {
    done: 'done',
    current: 'you are here',
    upcoming: 'to come',
    skipped: 'not needed',
};

/**
 * The whole way to membership as a vertical rail: what is done (with its
 * date or score), where they are now, and what comes after.
 */
export function JourneyTimeline({ steps }: { steps: JourneyStep[] }) {
    return (
        <section aria-labelledby="journey-heading">
            <h2 id="journey-heading" className="text-heading03 font-semibold">
                Your journey
            </h2>
            <ol className="mt-5">
                {steps.map((step, i) => {
                    const last = i === steps.length - 1;
                    const filled = step.state === 'done';

                    return (
                        <li
                            key={step.key}
                            aria-current={
                                step.state === 'current' ? 'step' : undefined
                            }
                            className="relative grid grid-cols-[1.75rem_minmax(0,1fr)] gap-x-4 pb-6 last:pb-0"
                        >
                            {!last && (
                                <span
                                    aria-hidden
                                    className={cn(
                                        'absolute top-8 bottom-1 left-[0.8125rem] w-0.5 rounded-full',
                                        filled
                                            ? 'bg-brand-navy dark:bg-brand-on-dark'
                                            : 'bg-brand-line dark:bg-white/15',
                                    )}
                                />
                            )}
                            <span
                                aria-hidden
                                className={cn(
                                    'relative flex size-7 items-center justify-center rounded-full',
                                    step.state === 'done' &&
                                        'bg-brand-navy text-white dark:bg-brand-on-dark dark:text-brand-navy',
                                    step.state === 'current' &&
                                        'bg-brand-crimson-strong ring-4 ring-brand-crimson-strong/20 dark:bg-brand-crimson dark:ring-brand-crimson/25',
                                    step.state === 'upcoming' &&
                                        'bg-background ring-2 ring-brand-line ring-inset dark:ring-white/25',
                                    step.state === 'skipped' &&
                                        'bg-background ring-2 ring-brand-line [--tw-ring-style:dashed] ring-inset dark:ring-white/20',
                                )}
                            >
                                {step.state === 'done' && (
                                    <Check className="size-4" strokeWidth={3} />
                                )}
                                {step.state === 'current' && (
                                    <span className="size-2.5 rounded-full bg-white" />
                                )}
                            </span>
                            <div className="min-w-0 pt-0.5">
                                <p
                                    className={cn(
                                        'font-semibold',
                                        step.state === 'skipped' &&
                                            'font-medium text-muted-foreground line-through decoration-1',
                                        step.state === 'upcoming' &&
                                            'font-medium text-muted-foreground',
                                    )}
                                >
                                    {step.label}
                                    <span className="sr-only">
                                        , {stateText[step.state]}
                                    </span>
                                </p>
                                {step.detail && (
                                    <p
                                        className={cn(
                                            'mt-0.5 text-sm',
                                            step.state === 'current'
                                                ? 'font-medium text-brand-crimson-strong dark:text-brand-crimson'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {step.detail}
                                    </p>
                                )}
                            </div>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}
