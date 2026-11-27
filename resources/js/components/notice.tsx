import { Check, Info } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

export type NoticeTone = 'success' | 'info' | 'warning' | 'error';

/**
 * The round badge every message on the site starts with: a tick when
 * something is done, a crimson "!" for a problem, an "i" for information.
 * `onNavy` is for the navy toast; otherwise it sits on a light card.
 */
export function NoticeBadge({
    tone,
    onNavy = false,
}: {
    tone: NoticeTone;
    onNavy?: boolean;
}) {
    return (
        <span
            aria-hidden
            className={cn(
                'flex size-6 shrink-0 items-center justify-center rounded-full text-xs font-black',
                tone === 'success' &&
                    (onNavy
                        ? 'bg-white text-brand-navy'
                        : 'bg-brand-navy text-white dark:bg-brand-on-dark dark:text-brand-navy'),
                (tone === 'error' || tone === 'warning') &&
                    'bg-brand-crimson-strong text-white dark:bg-brand-crimson',
                tone === 'info' &&
                    (onNavy
                        ? 'bg-white/15 text-white'
                        : 'bg-muted text-foreground ring-1 ring-border'),
            )}
        >
            {tone === 'success' ? (
                <Check className="size-3.5" strokeWidth={3.5} />
            ) : tone === 'info' ? (
                <Info className="size-3.5" strokeWidth={3} />
            ) : (
                '!'
            )}
        </span>
    );
}

/**
 * A message that stays on the page (a payment result, a log-in status, a
 * list of errors): the same badge as the toast, on a light card.
 */
export function Notice({
    tone,
    title,
    children,
    className,
}: {
    tone: NoticeTone;
    title?: ReactNode;
    children?: ReactNode;
    className?: string;
}) {
    const problem = tone === 'error' || tone === 'warning';

    return (
        <div
            role={problem ? 'alert' : 'status'}
            className={cn(
                'flex items-start gap-3 rounded-control border p-4 text-sm',
                problem
                    ? 'border-destructive/40 bg-destructive/5'
                    : 'border-border bg-background',
                className,
            )}
        >
            <NoticeBadge tone={tone} />
            <div className="grid min-w-0 gap-1 pt-0.5 leading-relaxed">
                {title && <p className="font-semibold">{title}</p>}
                {children && (
                    <div className={cn(title && 'text-muted-foreground')}>
                        {children}
                    </div>
                )}
            </div>
        </div>
    );
}
