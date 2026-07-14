/**
 * Inertia shared-props types — augments the global PageProps so every
 * page component gets typed access to auth/app/marketplace/flash/csrf
 * without each page redeclaring them.
 *
 * The actual shape is mirrored from `HandleInertiaRequests::share()`
 * in app/Http/Middleware/HandleInertiaRequests.php.
 */

export type Locale = 'en' | 'ar' | 'ur';
export type Currency = 'KWD' | 'USD' | 'AED' | 'PKR';
export type Direction = 'ltr' | 'rtl';

export interface AuthUser {
    id: number;
    name: string;
    email: string;
    email_verified: boolean;
    roles: string[];
    /** Phase 10 v10.11 §2 PERFORMANCE — permissions are NO LONGER included
     *  in the default Inertia share (was ~80-row Spatie pluck per request).
     *  Marked optional in v10.16 to match the runtime contract. Components
     *  that need the permission list must request it via a partial reload
     *  or per-page Inertia::partial. Backend authorization (policies, gates,
     *  Spatie middleware) is unaffected — `permissions` here is purely a
     *  client-side hint. Always read defensively: `user.permissions ?? []`. */
    permissions?: string[];
    is_admin: boolean;
    /** Phase 5 v6.1 — null if no vendor profile; otherwise pending|approved|rejected|suspended|closed */
    vendor_status?: string | null;
}

export interface SharedAppProps {
    name: string;
    url: string;
    locale: Locale;
    direction: Direction;
    /** Phase 10 v10.2 — visible version banner. Reads from the VERSION file. */
    version?: string;
}

export interface SharedMarketplaceProps {
    default_currency: Currency;
    supported_currencies: Currency[];
    supported_locales: Locale[];
    guest_browsing: boolean;
    guest_checkout: boolean;
}

export interface SharedFlash {
    success?: string | null;
    error?: string | null;
    info?: string | null;
    warning?: string | null;
}

/** Props shared with every Inertia page. */
export interface SharedProps {
    app: SharedAppProps;
    marketplace: SharedMarketplaceProps;
    auth: { user: AuthUser | null };
    flash: SharedFlash;
    /** Flat key → translated string map for the active locale (v3.3+). */
    translations: Record<string, string>;
    /** Phase 4 — cart summary (null when guest). */
    cart_summary: {
        items_count: number;
        subtotal: string;
        currency: string;
    } | null;
    /** Phase 10 v10.6 — categories shared globally so the storefront
     *  hamburger can render a collapsible "Categories" section. */
    top_categories: Array<{ slug: string; name: string }>;
    /** Phase 11B.3 v11B.3.1 — site settings shared on every render.
     *  Values are locale-resolved server-side. Public groups only
     *  (branding, appearance, header, homepage, footer, contact, social, seo, mobile).
     *  Payment/commission/security groups NEVER exposed here. */
    siteSettings?: Partial<{
        branding: Record<string, unknown>;
        appearance: Record<string, string>;
        header: Record<string, unknown>;
        homepage: {
            section_order?: string[];
            sections?: Record<string, { enabled?: boolean } & Record<string, unknown>>;
        };
        footer: Record<string, unknown>;
        contact: Record<string, unknown>;
        social: Record<string, string>;
        seo: Record<string, unknown>;
        mobile: Record<string, unknown>;
        vendor_intelligence: { enabled?: boolean } & Record<string, unknown>;
    }>;
    [key: string]: unknown;
}

declare module '@inertiajs/core' {
    // eslint-disable-next-line @typescript-eslint/no-empty-object-type
    interface PageProps extends SharedProps {}
}
