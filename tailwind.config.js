/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './resources/views/**/*.blade.php',
        './resources/js/**/*.{ts,tsx}',
        './app/Filament/**/*.php',
        './app/Http/Livewire/**/*.php',
        './vendor/filament/**/*.blade.php',
    ],
    /*
     * Phase 11A v11A.2 — safelist for critical container utilities.
     *
     * The dev reported in v11A.1 and again in v11A.2 that the container
     * padding had no visible effect in the running browser. Even though
     * v11A.1's <Container> component had the right classes in source, the
     * dev's compiled CSS apparently did NOT include them. Possible reasons
     * include: Tailwind JIT cache miss; dynamic class construction in the
     * v11A.1 Container (now removed); browser/service-worker cache;
     * stale bundle on the production server.
     *
     * The safelist is the Tailwind-supported escape hatch for this exact
     * scenario: it forces these classes into the compiled CSS regardless
     * of whether the scanner finds them. This is belt-and-suspenders
     * defense — v11A.2 ALSO uses literal class strings in the new
     * `resources/js/Components/Layout/Container.tsx`. If either mechanism
     * works, the padding renders. If both work, even better.
     *
     * The list below is intentionally small — only classes structurally
     * necessary to make storefront pages padded. Adding to it should be
     * rare; mostly we rely on the content scanner.
     */
    safelist: [
        // Container component classes
        'mx-auto',
        'w-full',
        'max-w-7xl',
        'px-4',
        'sm:px-6',
        'lg:px-8',
        'xl:px-10',
        // Section vertical padding pairs used across the homepage (Welcome.tsx)
        'py-10',
        'py-12',
        'py-16',
        'lg:py-14',
        'lg:py-16',
        'lg:py-28',
        'sm:py-20',
        // Mobile drawer internal padding (per dev §4 fix in v11A.1)
        'py-3',
    ],
    theme: {
        extend: {
            fontFamily: {
                sans: ['Inter', 'Cairo', 'system-ui', 'sans-serif'],
                display: ['Inter', 'Cairo', 'system-ui', 'sans-serif'],
                arabic: ['Cairo', 'system-ui', 'sans-serif'],
            },
            colors: {
                // Phase 11A — Sapphire Trust theme.
                // Primary brand = deep indigo (trust). Accent emerald (money-positive).
                // Gold (deals + ratings). See PHASE_11A_DESIGN_SYSTEM.md for full spec.
                brand: {
                    50:  '#eef2ff',
                    100: '#e0e7ff',
                    200: '#c7d2fe',
                    300: '#a5b4fc',
                    400: '#818cf8',
                    500: '#6366f1',
                    600: '#4f46e5',
                    700: '#4338ca',
                    800: '#3730a3',
                    900: '#312e81',
                    ink: '#0b1142',
                },
                accent: {
                    50:  '#ecfdf5',
                    100: '#d1fae5',
                    200: '#a7f3d0',
                    300: '#6ee7b7',
                    400: '#34d399',
                    500: '#10b981',
                    600: '#059669',
                    700: '#047857',
                    800: '#065f46',
                    900: '#064e3b',
                },
                gold: {
                    50:  '#fffbeb',
                    100: '#fef3c7',
                    200: '#fde68a',
                    300: '#fcd34d',
                    400: '#fbbf24',
                    500: '#f59e0b',
                    600: '#d97706',
                    700: '#b45309',
                    800: '#92400e',
                    900: '#78350f',
                },
            },
            boxShadow: {
                'soft':       '0 1px 2px 0 rgb(15 23 42 / 0.06)',
                'card':       '0 2px 6px -1px rgb(15 23 42 / 0.06), 0 1px 2px -1px rgb(15 23 42 / 0.04)',
                'card-hover': '0 8px 16px -4px rgb(15 23 42 / 0.10), 0 4px 8px -2px rgb(15 23 42 / 0.06)',
                'hero':       '0 20px 50px -10px rgb(55 48 163 / 0.30)',
            },
            container: {
                center: true,
                padding: {
                    DEFAULT: '1rem',
                    sm: '1.5rem',
                    lg: '2rem',
                },
            },
        },
    },
    plugins: [],
};
