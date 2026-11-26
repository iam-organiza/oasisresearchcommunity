import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

const JOURNEY = [
    'Apply',
    'Grade offer',
    'Exam fee',
    'Exam',
    'Induction fee',
    'Member',
] as const;

/**
 * Where the applicant is on the way to membership. Steps that do not apply
 * (no exam for an exempted applicant) say so rather than disappearing.
 */
export function Journey({
    current,
    skipExam = false,
}: {
    current: number;
    skipExam?: boolean;
}) {
    return (
        <nav aria-label="Your way to membership" className="mb-6">
            <ol className="flex flex-wrap gap-x-1 gap-y-2 text-sm">
                {JOURNEY.map((label, i) => {
                    const skipped = skipExam && (i === 2 || i === 3);
                    const done = i < current;
                    const here = i === current;

                    return (
                        <li key={label} className="flex items-center gap-1">
                            <span
                                aria-current={here ? 'step' : undefined}
                                className={cn(
                                    'flex items-center gap-1.5 rounded-full px-2.5 py-1',
                                    here &&
                                        'bg-brand-navy font-semibold text-white dark:bg-brand-night-raised',
                                    done && 'text-foreground',
                                    !here && !done && 'text-muted-foreground',
                                    skipped && 'line-through',
                                )}
                            >
                                {done && !skipped && (
                                    <Check aria-hidden className="size-3.5" />
                                )}
                                {label}
                                <span className="sr-only">
                                    {skipped
                                        ? ' (not needed)'
                                        : done
                                          ? ' (done)'
                                          : here
                                            ? ' (you are here)'
                                            : ''}
                                </span>
                            </span>
                            {i < JOURNEY.length - 1 && (
                                <span
                                    aria-hidden
                                    className="h-px w-3 bg-border"
                                />
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
