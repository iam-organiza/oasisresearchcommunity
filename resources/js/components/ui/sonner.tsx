import { X } from 'lucide-react';
import type { CSSProperties } from 'react';
import { toast, Toaster as Sonner, type ToasterProps } from 'sonner';
import { NoticeBadge } from '@/components/notice';
import type { NoticeTone } from '@/components/notice';
import { useAppearance } from '@/hooks/use-appearance';
import { useFlashToast } from '@/hooks/use-flash-toast';

/** How long a toast stays: problems, and toasts with an Undo, longer, so there is time to act. */
const durationFor = (tone: NoticeTone, hasAction = false) =>
    tone === 'error' || tone === 'warning' || hasAction ? 8000 : 5000;

/**
 * The navy slip: the tone's badge, the message, a close button, and a thin
 * line that shrinks as it times out (it pauses while the pointer is over it,
 * as the toast itself does).
 */
export type ToastAction = { label: string; onClick: () => void };

function ToastSlip({
    id,
    tone,
    message,
    action,
}: {
    id: string | number;
    tone: NoticeTone;
    message: string;
    action?: ToastAction;
}) {
    return (
        <div
            className="relative w-[min(26rem,calc(100vw-2rem))] overflow-hidden rounded-card bg-brand-navy text-white shadow-lg ring-1 ring-white/10 dark:bg-brand-night-raised"
            style={
                {
                    '--toast-life': `${durationFor(tone, Boolean(action))}ms`,
                } as CSSProperties
            }
        >
            <div className="flex items-start gap-3 py-3.5 pr-2 pl-4">
                <div className="pt-0.5">
                    <NoticeBadge tone={tone} onNavy />
                </div>
                <p className="min-w-0 flex-1 pt-0.5 text-sm leading-relaxed font-semibold break-words">
                    {message}
                </p>
                {action && (
                    <button
                        type="button"
                        onClick={() => {
                            toast.dismiss(id);
                            action.onClick();
                        }}
                        className="h-8 shrink-0 rounded-control px-2.5 text-sm font-bold text-white underline underline-offset-4 transition-colors hover:bg-white/10 focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:outline-none"
                    >
                        {action.label}
                    </button>
                )}
                <button
                    type="button"
                    onClick={() => toast.dismiss(id)}
                    className="flex size-8 shrink-0 items-center justify-center rounded-control text-white/70 transition-colors hover:bg-white/10 hover:text-white focus-visible:ring-2 focus-visible:ring-white/60 focus-visible:outline-none"
                >
                    <X className="size-4" />
                    <span className="sr-only">Close</span>
                </button>
            </div>
            <span
                aria-hidden
                className={`toast-timer absolute inset-x-0 bottom-0 h-0.5 origin-left ${tone === 'error' || tone === 'warning' ? 'bg-brand-crimson' : 'bg-white/40'}`}
            />
        </div>
    );
}

/** Shows a toast in the site's style; the flash toasts from the server come through here. */
export function showToast(
    tone: NoticeTone,
    message: string,
    action?: ToastAction,
) {
    toast.custom(
        (id) => (
            <ToastSlip id={id} tone={tone} message={message} action={action} />
        ),
        { duration: durationFor(tone, Boolean(action)) },
    );
}

function Toaster({ ...props }: ToasterProps) {
    const { appearance } = useAppearance();

    useFlashToast();

    return (
        <Sonner
            theme={appearance}
            className="toaster group"
            position="bottom-right"
            mobileOffset={16}
            {...props}
        />
    );
}

export { Toaster };
