import type { SVGAttributes } from 'react';

// The OASIS mark is a two-colour image, so it ignores fill and text colour.
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 256 232"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="OASIS Research Community"
        >
            <image href="/images/oasis-mark.png" width="256" height="232" />
        </svg>
    );
}
