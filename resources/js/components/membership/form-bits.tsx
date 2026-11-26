import { Check } from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
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
            <Label htmlFor={id} className="font-semibold">
                {label}
                {optional && (
                    <span className="ml-1.5 font-normal text-muted-foreground">
                        (optional)
                    </span>
                )}
            </Label>
            {children}
            {hint && <p className="text-sm text-muted-foreground">{hint}</p>}
            <InputError
                className="font-semibold text-destructive"
                message={error}
            />
        </div>
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
            <legend className="mb-2 font-semibold">{legend}</legend>
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
                                'relative flex cursor-pointer flex-col gap-1 rounded-control border p-4 transition-colors',
                                'has-focus-visible:outline-2 has-focus-visible:outline-offset-2 has-focus-visible:outline-ring',
                                chosen
                                    ? 'border-brand-crimson-strong ring-1 ring-brand-crimson-strong dark:border-brand-crimson dark:ring-brand-crimson'
                                    : 'border-border hover:border-foreground/40',
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
                            <span className="flex items-start justify-between gap-3 font-semibold">
                                {option.label}
                                {chosen && (
                                    <Check
                                        aria-hidden
                                        className="mt-0.5 size-4 shrink-0 text-brand-crimson-strong dark:text-brand-crimson"
                                    />
                                )}
                            </span>
                            {describe && (
                                <span className="text-sm text-muted-foreground">
                                    {describe(option)}
                                </span>
                            )}
                        </label>
                    );
                })}
            </div>
            <InputError
                className="font-semibold text-destructive"
                message={error}
            />
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
                className="flex cursor-pointer items-start gap-3 rounded-control border border-border p-4 hover:border-foreground/40"
            >
                <input
                    id={id}
                    type="checkbox"
                    checked={checked}
                    onChange={(event) => onChange(event.target.checked)}
                    className="mt-1 size-4 shrink-0 accent-primary"
                />
                <span className="text-sm leading-relaxed">{children}</span>
            </label>
            <InputError
                className="font-semibold text-destructive"
                message={error}
            />
        </div>
    );
}

export const naira = (amount: number) => `₦${amount.toLocaleString('en-NG')}`;

/** Whole US dollars, as the site shows a naira price to people abroad. */
export const dollars = (amount: number) =>
    `US$${amount.toLocaleString('en-US')}`;
