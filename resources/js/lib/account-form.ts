/**
 * Shared looks for the account pages' forms (log in, sign up, password pages), so every field,
 * error, tick box and button matches. They are passed as className to the starter kit's components.
 */
export const accountForm = {
    label: 'font-semibold',
    field: 'h-12 rounded-control border-brand-line bg-brand-sheet px-4 text-base shadow-none focus-visible:border-brand-navy focus-visible:ring-brand-navy/20',
    error: 'font-semibold text-brand-crimson-strong',
    checkbox:
        'size-5 rounded-[5px] border-brand-line bg-brand-sheet data-[state=checked]:border-brand-navy data-[state=checked]:bg-brand-navy',
    submit: 'btn btn-ink mt-2 min-h-14 w-full text-body01 disabled:opacity-60',
    footer: 'border-t border-brand-line pt-6 text-brand-navy/70',
    status: 'rounded-control bg-brand-sheet px-4 py-3 font-semibold text-green-700 ring-1 ring-brand-line',
} as const;
