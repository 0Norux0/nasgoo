import { router, usePage } from '@inertiajs/react';
import type { Locale, SharedProps } from '@/types/inertia';

/**
 * Language switcher (v11A.4).
 *
 * POSTs to /locale/{code} via Inertia which:
 *   1. Stores the choice in session (and on the user model if logged in)
 *   2. App\Http\Middleware\SetLocale picks it up on the next request
 *   3. HandleInertiaRequests reloads the `translations` shared prop
 *   4. Inertia partial reload swaps strings + flips RTL on <html>
 *
 * v11A.4 §6 — display only English + Arabic.
 * Pre-v11A.4 this loop iterated marketplace.supported_locales which
 * includes 'ur' (Urdu). Urdu uses Arabic SCRIPT so its label "اردو"
 * looks like a second Arabic option to readers unfamiliar with the
 * languages. The dev reported "two Arabic options" — that was Urdu.
 * v11A.4 filters the visible list to en + ar only. Urdu translation
 * files (lang/ur.json) remain on disk for future activation by adding
 * 'ur' to the DISPLAY_LOCALES_OVERRIDE if needed.
 */
const DISPLAY_LOCALES: Locale[] = ['en', 'ar'];

export function LangSwitcher() {
    const { app, marketplace } = usePage<SharedProps>().props;

    const labels: Record<Locale, string> = {
        en: 'English',
        ar: 'العربية',
        ur: 'اردو',
    };

    const switchTo = (code: Locale) => {
        if (code === app.locale) return;
        router.post(
            `/locale/${code}`,
            {},
            {
                preserveScroll: true,
                preserveState: false, // force a full prop refresh so translations reload
            },
        );
    };

    // Only render locales that are BOTH supported by the backend AND in the
    // display list. This is intersection (not union): if backend dropped
    // 'ar' support, the selector would correctly hide it.
    const visibleLocales = marketplace.supported_locales.filter((code) =>
        DISPLAY_LOCALES.includes(code as Locale)
    );

    return (
        <div
            className="inline-flex items-center gap-1 rounded-lg border border-slate-200 bg-white p-1 text-sm"
            aria-label="Switch language"
            data-testid="lang-switcher-v11a4"
        >
            {visibleLocales.map((code) => (
                <button
                    key={code}
                    type="button"
                    onClick={() => switchTo(code)}
                    aria-pressed={app.locale === code}
                    className={
                        app.locale === code
                            ? 'rounded-md bg-brand-700 px-3 py-1.5 text-white font-semibold'
                            : 'rounded-md px-3 py-1.5 text-slate-700 hover:bg-slate-100 font-medium'
                    }
                >
                    {labels[code as Locale]}
                </button>
            ))}
        </div>
    );
}

export default LangSwitcher;
