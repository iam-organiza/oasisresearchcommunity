import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

export type PasswordRequirement = { check: string; label: string };

/** Whether the typed password already meets one requirement; the breach check is the server's. */
function meets(check: string, value: string): boolean | null {
    if (check.startsWith('length:')) {
        return value.length >= Number(check.slice('length:'.length));
    }

    switch (check) {
        case 'mixed':
            return /\p{Ll}/u.test(value) && /\p{Lu}/u.test(value);
        case 'number':
            return /\p{N}/u.test(value);
        case 'symbol':
            return /[\p{Z}\p{S}\p{P}]/u.test(value);
        default:
            return null;
    }
}

/**
 * The password rules, shown before anyone types, each ticked off as it is met,
 * so nobody finds them one error at a time.
 */
export function PasswordChecklist({
    id,
    items,
    value,
}: {
    id: string;
    items: PasswordRequirement[];
    value: string;
}) {
    return (
        <ul id={id} className="grid gap-1 text-sm">
            {items.map((item) => {
                const met = value !== '' && meets(item.check, value) === true;

                return (
                    <li
                        key={item.check}
                        className={cn(
                            'flex items-start gap-2',
                            met ? 'text-foreground' : 'text-muted-foreground',
                        )}
                    >
                        <span
                            aria-hidden
                            className={cn(
                                'mt-0.5 flex size-4 shrink-0 items-center justify-center rounded-full border',
                                met
                                    ? 'border-brand-navy bg-brand-navy text-white dark:border-brand-on-dark dark:bg-brand-on-dark dark:text-brand-navy'
                                    : 'border-current opacity-60',
                            )}
                        >
                            {met && (
                                <Check className="size-3" strokeWidth={3.5} />
                            )}
                        </span>
                        <span>
                            {item.label}
                            {met && <span className="sr-only"> (done)</span>}
                        </span>
                    </li>
                );
            })}
        </ul>
    );
}
