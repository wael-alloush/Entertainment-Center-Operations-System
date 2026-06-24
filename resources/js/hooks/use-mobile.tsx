import { useEffect, useState } from 'react';

export const MOBILE_BREAKPOINT = 1024;

function getIsMobile(): boolean {
    if (typeof window === 'undefined') {
        return false;
    }

    return window.innerWidth < MOBILE_BREAKPOINT;
}

export function useIsMobile(): boolean {
    const [isMobile, setIsMobile] = useState(getIsMobile);

    useEffect(() => {
        const mediaQuery = window.matchMedia(`(max-width: ${MOBILE_BREAKPOINT - 1}px)`);

        const onChange = () => {
            setIsMobile(mediaQuery.matches);
        };

        mediaQuery.addEventListener('change', onChange);
        setIsMobile(mediaQuery.matches);

        return () => mediaQuery.removeEventListener('change', onChange);
    }, []);

    return isMobile;
}
