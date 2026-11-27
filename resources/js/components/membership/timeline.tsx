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
    // Skipped steps aren't part of their way, so they don't count either way.
    const counted = steps.filter((step) => step.state !== 'skipped');
    const done = counted.filter((step) => step.state === 'done').length;

    return (
        <section aria-labelledby="journey-heading">
            <h2 id="journey-heading" className="text-heading03 font-semibold">
                Your journey
            </h2>
            <p className="mt-1 text-sm text-muted-foreground">
                {done === counted.length
                    ? 'All done'
                    : `${done} of ${counted.length} done`}
            </p>
            <div aria-hidden className="mt-3 flex gap-1">
                {counted.map((step) => (
                    <span
                        key={step.key}
                        className={cn(
                            'h-1.5 flex-1 rounded-full',
                            step.state === 'done' &&
                                'bg-brand-navy dark:bg-brand-on-dark',
                            step.state === 'current' &&
                                'bg-brand-crimson-strong dark:bg-brand-crimson',
                            step.state === 'upcoming' &&
                                'bg-brand-line dark:bg-white/15',
                        )}
                    />
                ))}
            </div>
            <ol className="mt-6">
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
                            <div
                                className={cn(
                                    'min-w-0',
                                    step.state === 'current'
                                        ? '-mt-1.5 rounded-control bg-brand-crimson/5 px-3 py-2 dark:bg-brand-crimson/10'
                                        : 'pt-0.5',
                                )}
                            >
                                <p
                                    className={cn(
                                        'flex flex-wrap items-baseline gap-x-2 font-semibold',
                                        step.state === 'skipped' &&
                                            'font-medium text-muted-foreground line-through decoration-1',
                                        step.state === 'upcoming' &&
                                            'font-medium text-muted-foreground',
                                    )}
                                >
                                    {step.label}
                                    {step.state === 'current' ? (
                                        <span className="text-xs font-semibold text-brand-crimson-strong dark:text-destructive-foreground">
                                            <span className="sr-only">, </span>
                                            You are here
                                        </span>
                                    ) : (
                                        <span className="sr-only">
                                            , {stateText[step.state]}
                                        </span>
                                    )}
                                </p>
                                {step.detail && (
                                    <p
                                        className={cn(
                                            'mt-0.5 text-sm',
                                            step.state === 'current'
                                                ? 'font-medium text-brand-crimson-strong dark:text-destructive-foreground'
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
