import type { ReactNode } from 'react';

/** White button on the navy panel. */
export const onNavy = 'bg-white text-brand-navy hover:bg-white/90';
export const outlineOnNavy =
    'border-white/40 bg-transparent text-white hover:bg-white/10 hover:text-white';

/**
 * The one thing to do now, at every stage: a heading, a sentence or two
 * and, when there is something to do, one main button.
 */
export function NextStep({
    title,
    children,
    actions,
}: {
    title: ReactNode;
    children?: ReactNode;
    actions?: ReactNode;
}) {
    return (
        <section
            aria-labelledby="next-step-heading"
            className="rounded-card bg-brand-navy p-6 text-white md:p-8 dark:bg-brand-night-raised"
        >
            <p className="text-sm font-semibold text-brand-on-dark">
                Your next step
            </p>
            <h2
                id="next-step-heading"
                className="mt-2 text-heading02 font-bold text-balance"
            >
                {title}
            </h2>
            {children && (
                <div className="mt-3 grid max-w-prose gap-3 text-brand-on-dark">
                    {children}
                </div>
            )}
            {actions && (
                <div className="mt-8 flex flex-wrap gap-3">{actions}</div>
            )}
        </section>
    );
}
