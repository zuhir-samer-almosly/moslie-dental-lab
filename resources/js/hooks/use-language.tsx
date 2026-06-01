import { useCallback, useSyncExternalStore } from 'react';

export type Language = 'ar' | 'en';

export type UseLanguageReturn = {
    readonly language: Language;
    readonly updateLanguage: (lang: Language) => void;
};

const listeners = new Set<() => void>();
let currentLanguage: Language = 'ar';

const setCookie = (name: string, value: string, days = 365): void => {
    if (typeof document === 'undefined') return;
    const maxAge = days * 24 * 60 * 60;
    document.cookie = `${name}=${value};path=/;max-age=${maxAge};SameSite=Lax`;
};

const isLanguage = (value: string | null): value is Language =>
    value === 'ar' || value === 'en';

const getStoredLanguage = (): Language => {
    if (typeof window === 'undefined') return 'ar';

    const stored = localStorage.getItem('language');

    return isLanguage(stored) ? stored : 'ar';
};

const applyLanguage = (language: Language): void => {
    if (typeof document === 'undefined') return;

    const html = document.documentElement;

    if (language === 'ar') {
        html.lang = 'ar-SY';
        html.dir = 'rtl';
        html.style.setProperty(
            '--font-sans',
            "'IBM Plex Sans Arabic', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji'",
        );
    } else {
        html.lang = 'en';
        html.dir = 'ltr';
        html.style.setProperty(
            '--font-sans',
            "'Inter', ui-sans-serif, system-ui, sans-serif, 'Apple Color Emoji', 'Segoe UI Emoji', 'Segoe UI Symbol', 'Noto Color Emoji'",
        );
    }
};

const subscribe = (callback: () => void) => {
    listeners.add(callback);

    return () => listeners.delete(callback);
};

const notify = (): void => listeners.forEach((listener) => listener());

export function initializeLanguage(): void {
    if (typeof window === 'undefined') return;

    const storedLanguage = getStoredLanguage();

    localStorage.setItem('language', storedLanguage);
    setCookie('language', storedLanguage);

    currentLanguage = storedLanguage;
    applyLanguage(currentLanguage);
}

export function useLanguage(): UseLanguageReturn {
    const language: Language = useSyncExternalStore(
        subscribe,
        () => currentLanguage,
        () => 'ar' as Language,
    );

    const updateLanguage = useCallback((lang: Language): void => {
        currentLanguage = lang;

        // Store in localStorage for client-side persistence...
        localStorage.setItem('language', lang);

        // Store in cookie for SSR...
        setCookie('language', lang);

        applyLanguage(lang);
        notify();
    }, []);

    return { language, updateLanguage } as const;
}

// Helper to get the current language synchronously (for non-React code)
export function getCurrentLanguage(): Language {
    return currentLanguage;
}
