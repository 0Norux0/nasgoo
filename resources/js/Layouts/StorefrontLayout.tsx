import { type PropsWithChildren, useState } from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import {
    Menu, X, ShoppingBag, Heart,
    ChevronDown, Package, Sparkles, MessageCircle, Store,
} from 'lucide-react';
import type { SharedProps } from '@/types/inertia';
import { LangSwitcher } from '@/Components/common/LangSwitcher';
import Container from '@/Components/Layout/Container';
import { useT } from '@/lib/i18n';
import SearchBar from '@/Components/common/SearchBar';

interface Props {
    title?: string;
}

/**
 * Phase 11A — Storefront chrome redesign.
 *
 * Premium marketplace header with:
 * - Logo + brand name (high contrast, hover state)
 * - Prominent search bar (dev §5 — central feature of a marketplace)
 * - Inline category nav (desktop)
 * - User cluster (cart with badge, wishlist, account, sign-in/register)
 * - Vendor CTA (becomes "Vendor dashboard" for vendors)
 * - Language switcher
 * - Mobile drawer with collapsible Categories (Phase 10 v10.6 logic preserved)
 *
 * Footer with 4 columns: Customer help / Vendor / Marketplace / Legal.
 * All data-testid hooks from v10.x preserved (nav-services, nav-deals,
 * nav-my-bookings, nav-my-tickets) for regression continuity.
 *
 * v11A design tokens: brand-800 (deep indigo), accent-600 (emerald),
 * gold-500 (deals/ratings), rounded-2xl cards. See PHASE_11A_DESIGN_SYSTEM.md.
 */
export function StorefrontLayout({ title, children }: PropsWithChildren<Props>) {
    const t = useT();
    const { app, auth, cart_summary, top_categories, siteSettings } = usePage<SharedProps>().props;
    const user = auth.user;
    const [mobileOpen, setMobileOpen] = useState(false);

    // Phase 11B.3 v11B.3.3 §7 §8 — consume v11B.3.1 siteSettings shared prop.
    // Falls back to app.name / hardcoded strings when settings are absent or
    // the specific key hasn't been set in admin.
    //
    // Pre-v11B.3.3 this file ignored siteSettings entirely — the admin's
    // "site name" change in /admin/site-settings had no effect on the live
    // storefront. This wires the actual values into the render.
    const brand = siteSettings?.branding ?? {};
    const brandName    = (brand.site_name as string) ?? app.name;
    const brandLogoUrl = (brand.logo_url as string) || null;
    const footer = siteSettings?.footer ?? {};
    const footerDescription = (footer.description as string) || null;
    const footerCopyright   = (footer.copyright as string) || null;
    const social = siteSettings?.social ?? {};
    // Phase 10 v10.6 — collapsible Categories section in mobile drawer.
    // Preserved exactly in v11A: tapping Categories expands the full list;
    // default collapsed; selecting a category closes the whole drawer.
    const [categoriesOpen, setCategoriesOpen] = useState(false);
    // Phase 11B.1 v11B.1.1 §10 — searchQuery/handleSearch removed.
    // SearchBar (used by both desktop AND mobile drawer now) owns its own
    // input state and dispatches /products?q=… on submit.

    const vendorCtaHref = !user
        ? '/login?redirect=/vendor/apply'
        : user.roles.includes('vendor')
            ? '/vendor'
            : '/vendor/apply';

    const vendorCtaLabel = user?.roles.includes('vendor')
        ? t('nav.vendor_dashboard')
        : t('nav.become_a_vendor');

    const cartCount = cart_summary?.items_count ?? 0;

    return (
        <>
            <Head title={title} />

            {/* ───────────── HEADER ───────────── */}
            <header
                className="bg-white border-b border-slate-200 sticky top-0 z-30"
                data-testid="storefront-header-v11a"
            >
                {/* Top utility bar (desktop only) */}
                <div className="hidden md:block bg-brand-ink text-brand-100 text-xs">
                    <Container className="h-9 flex items-center justify-between">
                        <div className="flex items-center gap-4">
                            <span className="font-medium text-brand-200">
                                {t('header.welcome', { name: app.name })}
                            </span>
                            <span aria-hidden="true" className="text-brand-700">·</span>
                            <span className="flex items-center gap-1.5">
                                <span aria-hidden="true">🌍</span>
                                {t('header.global_shipping')}
                            </span>
                        </div>
                        <div className="flex items-center gap-4">
                            <Link
                                href="/tickets"
                                className="hover:text-white inline-flex items-center gap-1"
                                data-testid="utility-help"
                            >
                                <MessageCircle size={12} aria-hidden="true" />
                                {t('header.help')}
                            </Link>
                            <LangSwitcher />
                        </div>
                    </Container>
                </div>

                {/* Main header */}
                <Container>
                    <div className="flex h-16 lg:h-20 items-center gap-3 lg:gap-6">
                        {/* Logo */}
                        <Link
                            href="/"
                            className="flex items-center gap-2.5 shrink-0 group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded-lg"
                            aria-label={brandName}
                        >
                            {brandLogoUrl ? (
                                <img
                                    src={brandLogoUrl}
                                    alt=""
                                    className="size-9 lg:size-10 rounded-xl object-contain"
                                    aria-hidden="true"
                                    data-testid="storefront-brand-logo"
                                />
                            ) : (
                                <div
                                    className="size-9 lg:size-10 rounded-xl bg-gradient-to-br from-brand-700 to-brand-900 grid place-items-center text-white font-bold text-lg shadow-soft"
                                    aria-hidden="true"
                                >
                                    {brandName.charAt(0).toUpperCase()}
                                </div>
                            )}
                            <span
                                className="hidden sm:block font-display text-lg lg:text-xl font-bold text-slate-900 group-hover:text-brand-800 transition-colors"
                                data-testid="storefront-brand-name"
                            >
                                {brandName}
                            </span>
                        </Link>

                        {/* Search bar (desktop) — v11A.4 uses SearchBar with live suggestions */}
                        <div className="hidden md:flex flex-1 max-w-2xl mx-auto" data-testid="storefront-search">
                            <SearchBar variant="desktop" />
                        </div>

                        {/* Desktop user cluster */}
                        <div className="hidden md:flex items-center gap-1 shrink-0">
                            {user && (
                                <Link
                                    href="/wishlist"
                                    className="size-10 grid place-items-center rounded-xl text-slate-600 hover:bg-slate-100 hover:text-rose-600 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                                    title={t('header.wishlist')}
                                    data-testid="nav-wishlist"
                                >
                                    <Heart size={20} aria-hidden="true" />
                                </Link>
                            )}

                            <Link
                                href="/cart"
                                className="size-10 grid place-items-center rounded-xl text-slate-600 hover:bg-slate-100 hover:text-brand-800 transition-colors relative focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                                title={t('cart.title')}
                                data-testid="nav-cart"
                            >
                                <ShoppingBag size={20} aria-hidden="true" />
                                {cartCount > 0 && (
                                    <span className="absolute -top-0.5 -end-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-accent-600 text-white text-[10px] font-bold grid place-items-center">
                                        {cartCount > 99 ? '99+' : cartCount}
                                    </span>
                                )}
                            </Link>

                            {user ? (
                                <div className="relative group ms-1">
                                    <button
                                        type="button"
                                        className="h-10 ps-2 pe-3 flex items-center gap-2 rounded-xl hover:bg-slate-100 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                                        aria-haspopup="menu"
                                    >
                                        <div className="size-7 rounded-full bg-brand-100 text-brand-800 grid place-items-center font-semibold text-xs" aria-hidden="true">
                                            {user.name.charAt(0).toUpperCase()}
                                        </div>
                                        <span className="text-sm font-medium text-slate-700 max-w-[100px] truncate">{user.name}</span>
                                        <ChevronDown size={14} className="text-slate-500" aria-hidden="true" />
                                    </button>

                                    {/* Account dropdown (CSS hover; respects reduced-motion) */}
                                    <div className="absolute top-full end-0 w-56 mt-1 rounded-xl bg-white border border-slate-200 shadow-card-hover opacity-0 invisible group-hover:opacity-100 group-hover:visible focus-within:opacity-100 focus-within:visible transition-all duration-150 py-1.5">
                                        <Link href="/orders" className="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                            <span className="font-medium">{t('orders.my_orders')}</span>
                                        </Link>
                                        <Link href="/bookings" className="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" data-testid="nav-my-bookings">
                                            My Bookings
                                        </Link>
                                        <Link href="/tickets" className="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50" data-testid="nav-my-tickets">
                                            Support
                                        </Link>
                                        <div className="my-1 h-px bg-slate-100" />
                                        {user.is_admin && (
                                            <Link href="/admin" className="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                                                {t('nav.admin')}
                                            </Link>
                                        )}
                                        <Link href="/logout" method="post" as="button" className="block w-full text-start px-4 py-2 text-sm text-rose-600 hover:bg-rose-50">
                                            {t('nav.sign_out')}
                                        </Link>
                                    </div>
                                </div>
                            ) : (
                                <div className="flex items-center gap-2 ms-2">
                                    <Link
                                        href="/login"
                                        className="h-10 px-3 inline-flex items-center text-sm font-medium text-slate-700 hover:text-slate-900 rounded-xl hover:bg-slate-100 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                                    >
                                        {t('nav.sign_in')}
                                    </Link>
                                    <Link
                                        href="/register"
                                        className="h-10 px-4 inline-flex items-center text-sm font-semibold text-white bg-brand-800 hover:bg-brand-900 rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                                    >
                                        {t('nav.register')}
                                    </Link>
                                </div>
                            )}
                        </div>

                        {/* Mobile cluster: cart icon + hamburger */}
                        <div className="md:hidden flex items-center gap-1 shrink-0 ms-auto">
                            <Link
                                href="/cart"
                                className="size-10 grid place-items-center rounded-xl text-slate-600 hover:bg-slate-100 relative"
                                aria-label={t('cart.title')}
                                data-testid="nav-cart-mobile"
                            >
                                <ShoppingBag size={20} aria-hidden="true" />
                                {cartCount > 0 && (
                                    <span className="absolute -top-0.5 -end-0.5 min-w-[18px] h-[18px] px-1 rounded-full bg-accent-600 text-white text-[10px] font-bold grid place-items-center">
                                        {cartCount > 99 ? '99+' : cartCount}
                                    </span>
                                )}
                            </Link>
                            <button
                                type="button"
                                onClick={() => setMobileOpen(true)}
                                className="size-10 grid place-items-center rounded-xl text-slate-700 hover:bg-slate-100"
                                aria-label="Open navigation"
                                aria-expanded={mobileOpen}
                                aria-controls="mobile-drawer"
                                data-testid="hamburger-toggle"
                            >
                                <Menu size={22} aria-hidden="true" />
                            </button>
                        </div>
                    </div>

                    {/* Secondary nav (desktop) — categories + features */}
                    <nav
                        className="hidden md:flex items-center gap-1 h-11 -mt-1 border-t border-slate-100"
                        aria-label="Primary"
                    >
                        <Link href="/products" className="px-3 py-2 text-sm font-medium text-slate-700 hover:text-brand-800 hover:bg-brand-50 rounded-lg transition-colors flex items-center gap-1.5">
                            <Package size={16} aria-hidden="true" />
                            {t('nav.products')}
                        </Link>
                        <Link href="/services" className="px-3 py-2 text-sm font-medium text-slate-700 hover:text-brand-800 hover:bg-brand-50 rounded-lg transition-colors flex items-center gap-1.5" data-testid="nav-services">
                            {t('nav.services')}
                        </Link>
                        <Link href="/deals" className="px-3 py-2 text-sm font-medium text-gold-700 hover:text-gold-900 hover:bg-gold-50 rounded-lg transition-colors flex items-center gap-1.5" data-testid="nav-deals">
                            <Sparkles size={16} aria-hidden="true" />
                            {t('nav.deals')}
                        </Link>
                        <span aria-hidden="true" className="mx-1 h-5 w-px bg-slate-200" />
                        {top_categories.slice(0, 5).map((c) => (
                            <Link
                                key={c.slug}
                                href={`/products?category=${c.slug}`}
                                className="px-3 py-2 text-sm text-slate-600 hover:text-slate-900 hover:bg-slate-50 rounded-lg transition-colors truncate max-w-[140px]"
                                data-testid="nav-category-link"
                            >
                                {c.name}
                            </Link>
                        ))}
                        <span className="flex-1" />
                        <Link
                            href={vendorCtaHref}
                            className="h-9 px-4 inline-flex items-center text-sm font-semibold text-accent-700 bg-accent-50 hover:bg-accent-100 rounded-lg transition-colors gap-1.5"
                            data-testid="nav-vendor-cta"
                        >
                            <Store size={16} aria-hidden="true" />
                            {vendorCtaLabel}
                        </Link>
                    </nav>
                </Container>

                {/* ─────── Mobile drawer (Phase 10 v10.6 logic preserved) ─────── */}
                {mobileOpen && (
                    <>
                        <button
                            type="button"
                            className="md:hidden fixed inset-0 z-40 bg-slate-900/40 backdrop-blur-sm"
                            onClick={() => setMobileOpen(false)}
                            aria-label="Close navigation"
                        />
                        <aside
                            id="mobile-drawer"
                            className="md:hidden fixed inset-y-0 end-0 z-50 w-[85%] max-w-sm bg-white shadow-xl overflow-y-auto"
                            data-testid="mobile-drawer-v11a"
                        >
                            <div className="flex items-center justify-between h-16 px-4 border-b border-slate-200">
                                <span className="font-display text-lg font-bold text-slate-900">{t('nav.menu')}</span>
                                <button
                                    type="button"
                                    onClick={() => setMobileOpen(false)}
                                    className="size-10 grid place-items-center rounded-xl text-slate-600 hover:bg-slate-100"
                                    aria-label="Close navigation"
                                >
                                    <X size={22} aria-hidden="true" />
                                </button>
                            </div>

                            {/* Phase 11B.1 v11B.1.1 §10 — mobile search now uses
                                the same SearchBar component as desktop. The plain
                                <input> + form here previously dispatched a regular
                                GET /products?q=… on submit but never called the
                                /search/suggestions endpoint, so live suggestions,
                                popular/recent groups, and did-you-mean were never
                                shown on mobile. SearchBar's variant="mobile" sets
                                the appropriate input height and hides the inline
                                Search button so the layout matches the drawer.
                                Dropdown z-index is z-50 (matches drawer), positioned
                                absolute below the input — fits inside the drawer's
                                overflow-y-auto scroll area. */}
                            <div className="p-4 border-b border-slate-100" data-testid="mobile-drawer-search">
                                <SearchBar variant="mobile" />
                            </div>

                            <div className="px-4 py-3">
                                <Link href="/products" className="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-800 hover:bg-slate-100" onClick={() => setMobileOpen(false)}>
                                    <Package size={18} aria-hidden="true" className="text-slate-500" />
                                    <span className="font-medium">{t('common.products')}</span>
                                </Link>
                                <Link href="/services" className="flex items-center gap-3 px-3 py-2.5 rounded-lg text-slate-800 hover:bg-slate-100" onClick={() => setMobileOpen(false)} data-testid="mobile-nav-services">
                                    <Sparkles size={18} aria-hidden="true" className="text-slate-500" />
                                    <span className="font-medium">{t('nav.services')}</span>
                                </Link>
                                <Link href="/deals" className="flex items-center gap-3 px-3 py-2.5 rounded-lg text-gold-800 hover:bg-gold-50" onClick={() => setMobileOpen(false)} data-testid="mobile-nav-deals">
                                    <Sparkles size={18} aria-hidden="true" className="text-gold-600" />
                                    <span className="font-medium">{t('nav.deals')}</span>
                                </Link>

                                {/* Phase 10 v10.6 — collapsible Categories (PRESERVED) */}
                                <button
                                    type="button"
                                    onClick={() => setCategoriesOpen(!categoriesOpen)}
                                    className="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-slate-800 hover:bg-slate-100"
                                    aria-expanded={categoriesOpen}
                                    data-testid="mobile-categories-toggle"
                                >
                                    <span className="flex items-center gap-3">
                                        <Menu size={18} aria-hidden="true" className="text-slate-500" />
                                        <span className="font-medium">{t('nav.categories')}</span>
                                    </span>
                                    <ChevronDown size={16} className={'text-slate-500 transition-transform ' + (categoriesOpen ? 'rotate-180' : '')} aria-hidden="true" />
                                </button>
                                {categoriesOpen && (
                                    <div className="ps-12 pe-2 pb-2 space-y-0.5" data-testid="mobile-categories-list">
                                        {top_categories.length === 0 ? (
                                            <p className="px-3 py-2 text-sm text-slate-600">{t('home.featured.empty_title')}</p>
                                        ) : (
                                            top_categories.map((c) => (
                                                <Link
                                                    key={c.slug}
                                                    href={`/products?category=${c.slug}`}
                                                    className="block px-3 py-2 text-sm text-slate-700 hover:text-brand-800 hover:bg-brand-50 rounded-lg"
                                                    onClick={() => setMobileOpen(false)}
                                                >
                                                    {c.name}
                                                </Link>
                                            ))
                                        )}
                                    </div>
                                )}

                                <div className="my-2 h-px bg-slate-100" />

                                {user ? (
                                    <>
                                        <div className="px-3 py-2 text-xs text-slate-500">Signed in as <span className="font-semibold text-slate-700">{user.name}</span></div>
                                        <Link href="/orders" className="block px-3 py-2.5 rounded-lg text-slate-800 hover:bg-slate-100 font-medium" onClick={() => setMobileOpen(false)}>{t('orders.my_orders')}</Link>
                                        <Link href="/bookings" className="block px-3 py-2.5 rounded-lg text-slate-800 hover:bg-slate-100 font-medium" onClick={() => setMobileOpen(false)} data-testid="mobile-nav-my-bookings">{t('footer.my_bookings')}</Link>
                                        <Link href="/tickets" className="block px-3 py-2.5 rounded-lg text-slate-800 hover:bg-slate-100 font-medium" onClick={() => setMobileOpen(false)} data-testid="mobile-nav-my-tickets">Support</Link>
                                        <Link href="/wishlist" className="block px-3 py-2.5 rounded-lg text-slate-800 hover:bg-slate-100 font-medium" onClick={() => setMobileOpen(false)}>{t('header.wishlist')}</Link>
                                        {user.is_admin && (
                                            <Link href="/admin" className="block px-3 py-2.5 rounded-lg text-slate-800 hover:bg-slate-100 font-medium" onClick={() => setMobileOpen(false)}>{t('nav.admin')}</Link>
                                        )}
                                        <Link href={vendorCtaHref} className="block px-3 py-2.5 rounded-lg text-accent-700 hover:bg-accent-50 font-semibold" onClick={() => setMobileOpen(false)}>{vendorCtaLabel}</Link>
                                        <Link href="/logout" method="post" as="button" className="block w-full text-start px-3 py-2.5 rounded-lg text-rose-600 hover:bg-rose-50 font-medium">{t('nav.sign_out')}</Link>
                                    </>
                                ) : (
                                    <>
                                        <Link href="/login" className="block px-3 py-2.5 rounded-lg text-slate-800 hover:bg-slate-100 font-medium" onClick={() => setMobileOpen(false)}>{t('nav.sign_in')}</Link>
                                        <Link href="/register" className="block px-3 py-2.5 rounded-lg bg-brand-800 text-white hover:bg-brand-900 font-semibold text-center mt-2" onClick={() => setMobileOpen(false)}>{t('nav.register')}</Link>
                                        <Link href={vendorCtaHref} className="block px-3 py-2.5 rounded-lg text-accent-700 hover:bg-accent-50 font-semibold mt-1" onClick={() => setMobileOpen(false)}>{vendorCtaLabel}</Link>
                                    </>
                                )}

                                <div className="my-3 h-px bg-slate-100" />
                                <div className="px-3 py-2 flex items-center justify-between">
                                    <span className="text-xs text-slate-600 font-medium">{t('nav.menu')}</span>
                                    <LangSwitcher />
                                </div>
                            </div>
                        </aside>
                    </>
                )}
            </header>

            {/* ───────────── MAIN ───────────── */}
            <main className="min-h-[calc(100vh-20rem)] bg-slate-50">{children}</main>

            {/* ───────────── FOOTER ───────────── */}
            <footer className="bg-brand-ink text-slate-300" data-testid="storefront-footer-v11a">
                <Container className="py-12 lg:py-16">
                    {/* Top — newsletter / call-to-action band (only when working signup exists; */}
                    {/* keeping it as a simple help band per dev §5 "Add only if a working subscription mechanism exists") */}
                    <div className="grid gap-8 lg:grid-cols-4">
                        {/* Column 1 — brand */}
                        <div className="lg:col-span-1">
                            <div className="flex items-center gap-2.5 mb-4">
                                {brandLogoUrl ? (
                                    <img src={brandLogoUrl} alt="" className="size-9 rounded-xl object-contain" aria-hidden="true" data-testid="storefront-footer-logo" />
                                ) : (
                                    <div className="size-9 rounded-xl bg-gradient-to-br from-brand-600 to-brand-800 grid place-items-center text-white font-bold" aria-hidden="true">
                                        {brandName.charAt(0).toUpperCase()}
                                    </div>
                                )}
                                <span className="font-display text-lg font-bold text-white" data-testid="storefront-footer-brand-name">
                                    {brandName}
                                </span>
                            </div>
                            <p className="text-sm text-slate-400 leading-relaxed" data-testid="storefront-footer-description">
                                {footerDescription ?? t('footer.intro')}
                            </p>
                            {/* v11B.3.3 §11 — social icons from siteSettings.social when configured */}
                            {Object.values(social).some((v) => typeof v === 'string' && v.length > 0) && (
                                <ul className="flex items-center gap-3 mt-4" data-testid="storefront-footer-social">
                                    {(['facebook','instagram','tiktok','youtube','linkedin','twitter'] as const).map((k) => {
                                        const url = social[k] as string | undefined;
                                        if (!url) return null;
                                        return (
                                            <li key={k}>
                                                <a
                                                    href={url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-slate-400 hover:text-white text-xs capitalize"
                                                    data-testid={`storefront-social-${k}`}
                                                >
                                                    {k}
                                                </a>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </div>

                        {/* Column 2 — Customer */}
                        <div>
                            <h4 className="font-display font-semibold text-white mb-4 text-sm uppercase tracking-wider">{t('footer.customer')}</h4>
                            <ul className="space-y-2.5 text-sm">
                                <li><Link href="/orders" className="text-slate-400 hover:text-white">{t('footer.my_orders')}</Link></li>
                                <li><Link href="/bookings" className="text-slate-400 hover:text-white">{t('footer.my_bookings')}</Link></li>
                                <li><Link href="/wishlist" className="text-slate-400 hover:text-white">{t('header.wishlist')}</Link></li>
                                <li><Link href="/tickets" className="text-slate-400 hover:text-white">{t('footer.support_tickets')}</Link></li>
                                <li><Link href="/login" className="text-slate-400 hover:text-white">{t('nav.sign_in')}</Link></li>
                            </ul>
                        </div>

                        {/* Column 3 — Vendor */}
                        <div>
                            <h4 className="font-display font-semibold text-white mb-4 text-sm uppercase tracking-wider">{t('footer.sell_on', { name: app.name })}</h4>
                            <ul className="space-y-2.5 text-sm">
                                <li><Link href="/vendor/apply" className="text-slate-400 hover:text-white">{t('nav.become_vendor')}</Link></li>
                                <li><Link href="/vendor" className="text-slate-400 hover:text-white">{t('footer.vendor_dashboard')}</Link></li>
                            </ul>
                        </div>

                        {/* Column 4 — Marketplace */}
                        <div>
                            <h4 className="font-display font-semibold text-white mb-4 text-sm uppercase tracking-wider">{t('footer.marketplace')}</h4>
                            <ul className="space-y-2.5 text-sm">
                                <li><Link href="/products" className="text-slate-400 hover:text-white">{t('footer.all_products')}</Link></li>
                                <li><Link href="/services" className="text-slate-400 hover:text-white">{t('nav.services')}</Link></li>
                                <li><Link href="/deals" className="text-slate-400 hover:text-white">{t('footer.todays_deals')}</Link></li>
                                <li><a href="/sitemap.xml" className="text-slate-400 hover:text-white">{t('footer.sitemap')}</a></li>
                            </ul>
                        </div>
                    </div>

                    <div className="mt-12 pt-8 border-t border-brand-900 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 text-xs text-slate-500">
                        <p data-testid="storefront-footer-copyright">
                            {footerCopyright ?? (
                                <>© {new Date().getFullYear()} {brandName}. {t('footer.rights', { year: new Date().getFullYear(), name: brandName }).replace(/^©.*?\d{4}\s*/, '')}</>
                            )}
                        </p>
                        <div className="flex items-center gap-4">
                            <span>{t('footer.secure_payments')}</span>
                            <span aria-hidden="true">·</span>
                            <span>{t('home.trust.verified_title')}</span>
                            <span aria-hidden="true">·</span>
                            <span>{t('footer.buyer_protection')}</span>
                        </div>
                    </div>
                </Container>
            </footer>
        </>
    );
}

// Phase 11A — default export for backwards compatibility with pages that
// import as 'import StorefrontLayout from ...'. The named export above is
// the preferred form for new code.
export default StorefrontLayout;
