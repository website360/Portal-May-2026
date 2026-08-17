import { SVGAttributes } from 'react';

/** Marca da Agencia May: um "M" angular desenhado em traco. */
export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth={2.4}
            strokeLinecap="round"
            strokeLinejoin="round"
            xmlns="http://www.w3.org/2000/svg"
            {...props}
        >
            <path d="M4 19V6l8 7 8-7v13" />
        </svg>
    );
}
