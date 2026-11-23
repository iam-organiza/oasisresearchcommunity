import type { LucideIcon } from 'lucide-react';
import { Check, Monitor, Moon, Sun } from 'lucide-react';
import type { Appearance, ResolvedAppearance } from '@/hooks/use-appearance';
import { useAppearance } from '@/hooks/use-appearance';
import { cn } from '@/lib/utils';

const options: { value: Appearance; icon: LucideIcon; label: string }[] = [
    { value: 'light', icon: Sun, label: 'Light' },
    { value: 'dark', icon: Moon, label: 'Dark' },
    { value: 'system', icon: Monitor, label: 'System' },
];

// Fixed colours per look, so each drawing shows that theme whatever theme is on now.
const frameTones: Record<
    ResolvedAppearance,
    { desk: string; rail: string; sheet: string; line: string; ink: string }
> = {
    light: {
        desk: 'bg-brand-desk',
        rail: 'bg-brand-sheet border-brand-line',
        sheet: 'bg-brand-sheet',
        line: 'bg-brand-line',
        ink: 'bg-brand-navy',
    },
    dark: {
        desk: 'bg-brand-night-deep',
        rail: 'bg-brand-night border-brand-night-line',
        sheet: 'bg-brand-night',
        line: 'bg-brand-night-line',
        ink: 'bg-brand-on-dark',
    },
};

// A small drawing of the signed-in frame: rail on the left, page on the desk.
function FramePreview({ tone }: { tone: ResolvedAppearance }) {
    const t = frameTones[tone];

    return (
        <div aria-hidden className={cn('flex size-full', t.desk)}>
            <div
                className={cn(
                    'flex w-1/4 flex-col gap-1.5 border-r p-2',
                    t.rail,
                )}
            >
                <span className={cn('size-2.5 rounded-full', t.ink)} />
                <span className="mt-1 h-1.5 w-full rounded-full bg-brand-crimson" />
                <span className={cn('h-1.5 w-3/4 rounded-full', t.line)} />
                <span className={cn('h-1.5 w-2/3 rounded-full', t.line)} />
            </div>
            <div className="flex-1 p-2">
                <div
                    className={cn(
                        'flex size-full flex-col gap-1.5 rounded-sm p-2',
                        t.sheet,
                    )}
                >
                    <span className={cn('h-2 w-1/2 rounded-full', t.ink)} />
                    <span className={cn('h-1.5 w-5/6 rounded-full', t.line)} />
                    <span className={cn('h-1.5 w-2/3 rounded-full', t.line)} />
                </div>
            </div>
        </div>
    );
}

export default function AppearanceTabs({ className }: { className?: string }) {
    const { appearance, updateAppearance } = useAppearance();

    return (
        <div
            role="radiogroup"
            aria-label="Appearance"
            className={cn('grid gap-3 @lg:grid-cols-3 @lg:gap-4', className)}
        >
            {options.map(({ value, icon: Icon, label }) => {
                const chosen = appearance === value;

                return (
                    <label
                        key={value}
                        className={[
                            'group relative flex cursor-pointer items-center gap-4 @lg:flex-col @lg:items-stretch @lg:gap-3',
                            'rounded-card border border-border bg-background p-2 @lg:p-3',
                            'transition-colors hover:border-foreground/30',
                            'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                            chosen
                                ? 'border-brand-crimson ring-1 ring-brand-crimson hover:border-brand-crimson'
                                : '',
                        ].join(' ')}
                    >
                        <input
                            type="radio"
                            name="appearance"
                            value={value}
                            checked={chosen}
                            onChange={() => updateAppearance(value)}
                            className="sr-only"
                        />

                        {/* System shows light on the left and dark on the right. */}
                        <span className="relative block aspect-video w-28 shrink-0 overflow-clip rounded-control ring-1 ring-border @lg:w-full">
                            {value === 'system' ? (
                                <>
                                    <FramePreview tone="light" />
                                    <span
                                        className="absolute inset-0"
                                        style={{ clipPath: 'inset(0 0 0 50%)' }}
                                    >
                                        <FramePreview tone="dark" />
                                    </span>
                                </>
                            ) : (
                                <FramePreview tone={value} />
                            )}
                        </span>

                        <span className="flex flex-1 items-center gap-2 px-1 pb-0.5 font-semibold">
                            <Icon className="size-4 text-muted-foreground" />
                            {label}
                            {chosen && (
                                <span className="ml-auto flex size-5 items-center justify-center rounded-full bg-brand-crimson text-white">
                                    <Check
                                        className="size-3.5"
                                        strokeWidth={3}
                                    />
                                </span>
                            )}
                        </span>
                    </label>
                );
            })}
        </div>
    );
}
