import { useEffect, useState } from 'react';

/** Tracks the `dark` class this app toggles on `<html>` (see use-appearance.tsx) rather than relying on prefers-color-scheme alone. */
export function useIsDark(): boolean {
    const [isDark, setIsDark] = useState(() => document.documentElement.classList.contains('dark'));

    useEffect(() => {
        const observer = new MutationObserver(() => setIsDark(document.documentElement.classList.contains('dark')));
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
        return () => observer.disconnect();
    }, []);

    return isDark;
}
