import type { SVGAttributes } from 'react';

/** The lab's tooth mark, solid. Same outline as the sidebar logo and favicon. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <path d="M12 5.5c-1.5-1.8-4-2.3-6-1-2.3 1.5-2.6 4.7-1.2 7 1 1.6 1.5 3.4 1.7 5.3.2 1.6.5 3.2 1.5 3.2 1.6 0 1.2-3.6 4-3.6s2.4 3.6 4 3.6c1 0 1.3-1.6 1.5-3.2.2-1.9.7-3.7 1.7-5.3 1.4-2.3 1.1-5.5-1.2-7-2-1.3-4.5-.8-6 1z" />
        </svg>
    );
}
