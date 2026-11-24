import type { SVGProps } from 'react';

/**
 * The OASIS mark (ring and three pages) drawn in code, so it can sit on navy or white.
 * `ink` colours the ring and the lowest page; the two upper pages are always crimson.
 */
export default function OasisMark({
    ink = 'var(--color-brand-navy)',
    ...props
}: SVGProps<SVGSVGElement> & { ink?: string }) {
    return (
        <svg viewBox="0 0 900 815" aria-hidden="true" {...props}>
            <path
                d="M475 138 A290 290 0 1 1 304 551"
                fill="none"
                stroke={ink}
                strokeWidth="80"
            />
            <path
                d="M68 305 L612 440 L626 456 L482 577 L30 577 Z"
                fill={ink}
                stroke={ink}
                strokeWidth="18"
                strokeLinejoin="round"
            />
            <path
                d="M182 103 L690 392 L684 410 L652 430 L158 300 Z"
                fill="var(--color-brand-crimson)"
                stroke="var(--color-brand-crimson)"
                strokeWidth="18"
                strokeLinejoin="round"
            />
            <path
                d="M330 12 L698 380 L690 394 L312 148 Z"
                fill="var(--color-brand-crimson-strong)"
                stroke="var(--color-brand-crimson-strong)"
                strokeWidth="18"
                strokeLinejoin="round"
            />
        </svg>
    );
}
