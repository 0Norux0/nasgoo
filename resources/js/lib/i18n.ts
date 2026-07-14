import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types/inertia';

/**
 * Tiny client-side translator backed by the `translations` prop shared
 * by App\Http\Middleware\HandleInertiaRequests for the active locale.
 *
 * Usage:
 *   const t = useT();
 *   <button>{t('nav.sign_in')}</button>
 *   <p>{t('app.welcome', { name: 'Marketplace' })}</p>
 *
 * If a key is missing in the active locale, English wins (server merges
 * en + locale before sending). If the key is missing in BOTH, the key
 * itself is returned so the missing translation is loud, not silent.
 */
type TranslationVars = Record<string, string | number>;

export function useT(): (key: string, varsOrFallback?: TranslationVars | string) => string {
    const { translations } = usePage<SharedProps>().props;

    return (key, varsOrFallback) => {
        const fallback = typeof varsOrFallback === 'string' ? varsOrFallback : key;
        const template = (translations && translations[key]) ?? fallback;
        const vars = typeof varsOrFallback === 'string' ? undefined : varsOrFallback;
        if (!vars) return template;

        return Object.entries(vars).reduce(
            (acc, [name, value]) =>
                acc.replace(new RegExp(`\\{\\s*${name}\\s*\\}`, 'g'), String(value)),
            template,
        );
    };
}
