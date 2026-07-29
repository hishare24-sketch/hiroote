import { useCallback, useSyncExternalStore } from 'react';

const STORAGE_KEY = 'hiroote.theme';

type Theme = 'light' | 'dark';

const listeners = new Set<() => void>();

function currentTheme(): Theme {
    return document.documentElement.classList.contains('dark') ? 'dark' : 'light';
}

function subscribe(listener: () => void): () => void {
    listeners.add(listener);
    return () => listeners.delete(listener);
}

/**
 * Light/Dark switch (وثيقة 03 §5). The <html> class is the single source of
 * truth; the blade inline script applies the stored value before first paint.
 */
export function useTheme(): { theme: Theme; toggle: () => void } {
    const theme = useSyncExternalStore(subscribe, currentTheme, () => 'light' as const);

    const toggle = useCallback(() => {
        const next: Theme = currentTheme() === 'dark' ? 'light' : 'dark';
        document.documentElement.classList.toggle('dark', next === 'dark');
        try {
            localStorage.setItem(STORAGE_KEY, next);
        } catch {
            // Storage unavailable (private mode) — the toggle still applies.
        }
        listeners.forEach((listener) => {
            listener();
        });
    }, []);

    return { theme, toggle };
}
