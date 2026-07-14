import type { PropsWithChildren } from 'react';

/**
 * Phase 11A v11A.2 — canonical Container.
 *
 * This is the dev's §4 recommended path and pattern verbatim. Three things
 * matter for v11A.2 vs v11A.1:
 *
 *  1. STATIC class string. v11A.1's Container used a template literal with
 *     a dollar-brace interpolation of the maxWidth variable. Per dev §7
 *     explicit warning: "If dynamic class construction prevents Tailwind
 *     from detecting classes, replace it with statically discoverable
 *     class strings". v11A.2 uses one literal class string — Tailwind's
 *     JIT scanner reads it directly with zero ambiguity.
 *
 *  2. Canonical path. v11A.1 placed Container at
 *     `resources/js/Components/ui/v11/Container.tsx`. v11A.2 moves it to
 *     the dev's §4 recommended path `resources/js/Components/Layout/`. The
 *     v11A.1 path file is removed in v11A.2 (no re-export shim — the
 *     migration is complete and intentional).
 *
 *  3. Safelist guarantee. v11A.2 additionally adds a Tailwind `safelist`
 *     entry in `tailwind.config.js` that explicitly includes every class
 *     this component uses. Even if Tailwind's content scanner fails to
 *     detect them, the safelist forces inclusion in the compiled CSS.
 *     This is belt-AND-suspenders defense against any build/cache failure
 *     mode.
 *
 * Padding scale (per dev §1 + §4):
 *   - mobile (default):     px-4   = 16px
 *   - small tablet (sm:):   px-6   = 24px
 *   - desktop (lg:):        px-8   = 32px
 *   - large desktop (xl:):  px-10  = 40px
 *
 * Max content width: max-w-7xl = 1280px. Beyond that, content is centered
 * via mx-auto with margins growing on both sides.
 */

type ContainerProps = PropsWithChildren<{
    className?: string;
}>;

export default function Container({
    children,
    className = '',
}: ContainerProps) {
    return (
        <div
            className={[
                'mx-auto w-full max-w-7xl px-4 sm:px-6 lg:px-8 xl:px-10',
                className,
            ].join(' ')}
        >
            {children}
        </div>
    );
}
