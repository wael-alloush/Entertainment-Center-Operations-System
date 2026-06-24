import { useCallback } from 'react';

export function useMobileNavigation() {
    const cleanup = useCallback(() => {
        document.body.style.removeProperty('pointer-events');
        window.dispatchEvent(new CustomEvent('mobile-navigation'));
    }, []);

    return cleanup;
}
