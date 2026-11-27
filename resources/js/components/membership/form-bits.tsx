import { CircleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/** Text fields and selects share the site's field look. */
export const fieldClass = [
    'w-full rounded-control border border-input bg-background px-3 py-2 text-base shadow-none outline-none md:text-sm',
    'transition-[color,box-shadow] focus-visible:border-ring focus-visible:ring-3 focus-visible:ring-ring/20',
    'aria-invalid:border-destructive aria-invalid:ring-destructive/20',
].join(' ');

export const selectClass = `${fieldClass} h-10 py-0`;

export type Option = { value: string; label: string };

export function Field({
    id,
    label,
    hint,
    error,
    optional = false,
    children,
}: {
    id: string;
    label: string;
    hint?: string;
    error?: string;
    optional?: boolean;
    children: ReactNode;
}) {
    return (
        <div className="grid content-start gap-2">
            <Label
                htmlFor={id}
                className={cn(
                    'font-semibold',
                    error && 'text-destructive-foreground',
                )}
            >
                {label}
                {optional && (
                    <span className="ml-1.5 font-normal text-muted-foreground">
                        (optional)
                    </span>
                )}
            </Label>
            {children}
            {hint && <p className="text-sm text-muted-foreground">{hint}</p>}
            <FieldError message={error} />
        </div>
    );
}

/** A field's error: crimson, with an alert mark so it doesn't rely on colour alone. */
export function FieldError({ message }: { message?: string }) {
    if (!message) {
        return null;
    }

    return (
        <p className="flex items-start gap-1.5 text-sm font-semibold text-destructive-foreground">
            <CircleAlert aria-hidden className="mt-0.5 size-4 shrink-0" />
            {message}
        </p>
    );
}

/**
 * The border and wash of a choice (a radio tile or a tick box): crimson
 * once picked, and an outline when the hidden input inside has keyboard focus.
 */
export const choiceTileClass = (chosen: boolean) =>
    cn(
        'relative flex cursor-pointer rounded-control border transition-colors',
        'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
        chosen
            ? 'border-brand-crimson-strong bg-brand-crimson/5 ring-1 ring-brand-crimson-strong dark:border-brand-crimson dark:bg-brand-crimson/10 dark:ring-brand-crimson'
            : 'border-border hover:border-foreground/40',
    );

/** The mark at the start of a choice: an empty circle or square that fills crimson when picked. */
export function ChoiceMark({
    kind,
    chosen,
}: {
    kind: 'radio' | 'check';
    chosen: boolean;
}) {
    return (
        <span
            aria-hidden
            className={cn(
                'mt-0.5 flex size-[18px] shrink-0 items-center justify-center border-2 transition-colors',
                kind === 'radio' ? 'rounded-full' : 'rounded-[5px]',
                chosen
                    ? 'border-brand-crimson-strong bg-brand-crimson-strong text-white dark:border-brand-crimson dark:bg-brand-crimson'
                    : 'border-foreground/35 bg-background',
            )}
        >
            {chosen &&
                (kind === 'radio' ? (
                    <span className="size-1.5 rounded-full bg-white" />
                ) : (
                    <svg
                        viewBox="0 0 24 24"
                        className="size-3"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth={4}
                        strokeLinecap="round"
                        strokeLinejoin="round"
                    >
                        <path d="M20 6 9 17l-5-5" />
                    </svg>
                ))}
        </span>
    );
}

/**
 * Radio choices as tiles: the chosen one gets the crimson ring and a tick,
 * as on the Staff and Appearance pages.
 */
export function ChoiceTiles({
    name,
    legend,
    options,
    value,
    onChange,
    error,
    columns = 'one',
    describe,
}: {
    name: string;
    legend: string;
    options: Option[];
    value: string;
    onChange: (value: string) => void;
    error?: string;
    columns?: 'one' | 'two';
    describe?: (option: Option) => ReactNode;
}) {
    return (
        <fieldset className="grid gap-2">
            <legend
                className={cn(
                    'mb-2 font-semibold',
                    error && 'text-destructive-foreground',
                )}
            >
                {legend}
            </legend>
            <div
                role="radiogroup"
                className={cn(
                    'grid gap-3',
                    columns === 'two' && '@2xl:grid-cols-2',
                )}
            >
                {options.map((option) => {
                    const chosen = value === option.value;

                    return (
                        <label
                            key={option.value}
                            className={cn(
                                choiceTileClass(chosen),
                                'items-start gap-3 p-4',
                            )}
                        >
                            <input
                                type="radio"
                                name={name}
                                value={option.value}
                                checked={chosen}
                                onChange={() => onChange(option.value)}
                                className="sr-only"
                            />
                            <ChoiceMark kind="radio" chosen={chosen} />
                            <span className="grid min-w-0 gap-1">
                                <span className="font-semibold">
                                    {option.label}
                                </span>
                                {describe && (
                                    <span className="text-sm text-muted-foreground">
                                        {describe(option)}
                                    </span>
                                )}
                            </span>
                        </label>
                    );
                })}
            </div>
            <FieldError message={error} />
        </fieldset>
    );
}

export function Checkbox({
    id,
    checked,
    onChange,
    children,
    error,
}: {
    id: string;
    checked: boolean;
    onChange: (checked: boolean) => void;
    children: ReactNode;
    error?: string;
}) {
    return (
        <div className="grid gap-1">
            <label
                htmlFor={id}
                className={cn(
                    choiceTileClass(checked),
                    'items-start gap-3 p-4',
                )}
            >
                <input
                    id={id}
                    type="checkbox"
                    checked={checked}
                    onChange={(event) => onChange(event.target.checked)}
                    className="sr-only"
                />
                <ChoiceMark kind="check" chosen={checked} />
                <span className="min-w-0 text-sm leading-relaxed">
                    {children}
                </span>
            </label>
            <FieldError message={error} />
        </div>
    );
}

export const naira = (amount: number) => `₦${amount.toLocaleString('en-NG')}`;

/** Whole US dollars, as the site shows a naira price to people abroad. */
export const dollars = (amount: number) =>
    `US$${amount.toLocaleString('en-US')}`;
