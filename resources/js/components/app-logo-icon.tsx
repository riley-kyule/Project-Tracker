import { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
            <rect x="3" y="4" width="5" height="16" rx="1.5" />
            <rect x="9.5" y="8" width="5" height="12" rx="1.5" />
            <rect x="16" y="12" width="5" height="8" rx="1.5" />
        </svg>
    );
}
