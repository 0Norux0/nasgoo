import type { FormEvent } from "react";
import { Head, Link, router, usePage } from '@inertiajs/react';
import Container from '@/Components/Layout/Container';
import SearchBar from '@/Components/common/SearchBar';
import { useT } from '@/lib/i18n';
import { useState } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import type { SharedProps } from '@/types/inertia';

interface PromotionDto {
    id: number;
    title: string;
    type: string;
    discount_type: string;
    discount_value: string;
    badge: string;
}

interface ProductCard {
    id: number;
    slug: string;
    name: string;
    price: string;
    currency: string;
    compare_at: string | null;
    thumb: string | null;
    featured: boolean;
    vendor_name: string | null;
    vendor_slug: string | null;
    category: string | null;
    // Phase 10 v10.8 — promotion-aware pricing fields
    final_price: string | null;
    discount: string | null;
    promotion: PromotionDto | null;
}

interface CategoryCount {
    slug: string;
    name: string;
    count: number;
}

interface Props {
    products: {
        data: ProductCard[];
        links: { url: string | null; label: string; active: boolean }[];
        from: number | null;
        to: number | null;
        total: number;
    };
    categories: CategoryCount[];
    /** Phase 11B.1 — extended filter shape */
    filters: {
        q: string | null;
        category: string | null;
        vendor: string | null;
        price_min: number | null;
        price_max: number | null;
        rating_min: number | null;
        in_stock: boolean | null;
        on_sale: boolean | null;
        sort: string;
    };
    active_category: { slug: string; name: string } | null;
    /** Phase 11B.1 — active vendor (when ?vendor= filter present) */
    active_vendor?: { slug: string; name: string } | null;
    /** Phase 11B.1 — faceted counts for filter options */
    facets?: {
        in_stock: number;
        out_of_stock: number;
        on_sale: number;
        rating_4plus: number;
        rating_3plus: number;
    };
    /** Phase 11B.1 — "Did you mean?" suggestion when results are sparse */
    did_you_mean?: string | null;
}

export default function CatalogIndex({
    products,
    categories,
    filters,
    active_category,
    active_vendor = null,
    facets = undefined,
    did_you_mean = null,
}: Props) {
    const t = useT();
    const { app } = usePage<SharedProps>().props;
    // Phase 11B.1 v11B.1.2 — initialQuery only; SearchBar manages input state
    const q = filters.q ?? '';
    const [priceMin, setPriceMin] = useState<string>(filters.price_min?.toString() ?? '');
    const [priceMax, setPriceMax] = useState<string>(filters.price_max?.toString() ?? '');

    // Helper to navigate with merged filters (clears nullish/empty values)
    const applyFilters = (patch: Record<string, string | number | boolean | null | undefined>) => {
        const merged = { ...filters, ...patch };
        // Strip null/undefined/empty values so URL stays clean
        const clean: Record<string, string | number | boolean> = {};
        Object.entries(merged).forEach(([k, v]) => {
            if (v !== null && v !== undefined && v !== '' && v !== false) {
                clean[k] = v as string | number | boolean;
            }
        });
        router.get('/products', clean, { preserveState: true });
    };

    const setSort = (sort: string) => {
        applyFilters({ sort });
    };

    const applyPriceRange = (e: FormEvent) => {
        e.preventDefault();
        applyFilters({
            price_min: priceMin && Number(priceMin) > 0 ? Number(priceMin) : null,
            price_max: priceMax && Number(priceMax) > 0 ? Number(priceMax) : null,
        });
    };

    const setRating = (r: number | null) => applyFilters({ rating_min: r });
    const toggleInStock = () => applyFilters({ in_stock: filters.in_stock ? null : true });
    const toggleOnSale  = () => applyFilters({ on_sale:  filters.on_sale  ? null : true });

    const clearAllFilters = () => {
        router.get('/products', { q: filters.q || undefined }, { preserveState: true });
    };

    const removeFilter = (key: string) => {
        applyFilters({ [key]: null });
    };

    // Count active filters (excluding q + sort)
    const activeFilterCount =
        (filters.category ? 1 : 0) +
        (filters.vendor ? 1 : 0) +
        (filters.price_min ? 1 : 0) +
        (filters.price_max ? 1 : 0) +
        (filters.rating_min ? 1 : 0) +
        (filters.in_stock ? 1 : 0) +
        (filters.on_sale ? 1 : 0);

    return (
        <StorefrontLayout title="Products">
            <Head title="Products" />

            {/* Phase 11A v11A.4 §2 — wrap catalog content in Container so the
                sidebar has the same responsive gutter as the storefront chrome.
                Previously the page content was edge-to-edge (no horizontal
                padding wrapper) — sidebar was flush against viewport. */}
            <Container className="py-6 lg:py-10">
                <div className="grid grid-cols-1 lg:grid-cols-[260px_minmax(0,1fr)] gap-6 lg:gap-8" dir={app.direction}>
                {/*
                  Phase 10 v10.6 — category sidebar is DESKTOP-ONLY.
                  On mobile (< lg) categories live inside the storefront
                  hamburger drawer in a collapsible Categories section. The
                  pre-v10.6 aside was visible at mobile widths because the
                  grid collapsed to one column, putting categories above
                  the product grid (outside the hamburger). The dev's §3
                  demand: no categories should appear outside the hamburger
                  on mobile.
                */}
                <aside className="hidden lg:block" data-testid="catalog-desktop-categories">
                    <h2 className="text-sm font-semibold text-slate-900 uppercase tracking-wide mb-3">
                        {t('catalog.filter.category')}
                    </h2>
                    <ul className="space-y-1">
                        <li>
                            <Link
                                href="/products"
                                className={`block rounded px-3 py-2 text-sm ${
                                    !filters.category
                                        ? 'bg-indigo-50 text-indigo-700 font-medium'
                                        : 'text-slate-600 hover:bg-slate-100'
                                }`}
                            >
                                {t('catalog.title_all')}
                            </Link>
                        </li>
                        {categories.map((c) => (
                            <li key={c.slug}>
                                <Link
                                    href={`/products?category=${c.slug}`}
                                    className={`flex items-center justify-between rounded px-3 py-2 text-sm ${
                                        filters.category === c.slug
                                            ? 'bg-indigo-50 text-indigo-700 font-medium'
                                            : 'text-slate-600 hover:bg-slate-100'
                                    }`}
                                >
                                    <span>{c.name}</span>
                                    <span className="text-xs text-slate-600">{c.count}</span>
                                </Link>
                            </li>
                        ))}
                    </ul>

                    {/* Phase 11B.1 §16 — Price range filter */}
                    <div className="mt-6 pt-6 border-t border-slate-200" data-testid="catalog-filter-price">
                        <h3 className="text-sm font-semibold text-slate-900 uppercase tracking-wide mb-3">
                            {t('catalog.filter.price')}
                        </h3>
                        <form onSubmit={applyPriceRange} className="space-y-2">
                            <div className="flex items-center gap-2">
                                <input
                                    type="number"
                                    min="0"
                                    inputMode="numeric"
                                    value={priceMin}
                                    onChange={(e) => setPriceMin(e.target.value)}
                                    placeholder={t('catalog.price.min_placeholder')}
                                    className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    aria-label={t('catalog.price.min_placeholder')}
                                />
                                <span className="text-slate-500">—</span>
                                <input
                                    type="number"
                                    min="0"
                                    inputMode="numeric"
                                    value={priceMax}
                                    onChange={(e) => setPriceMax(e.target.value)}
                                    placeholder={t('catalog.price.max_placeholder')}
                                    className="w-full rounded-md border border-slate-300 px-2 py-1.5 text-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    aria-label={t('catalog.price.max_placeholder')}
                                />
                            </div>
                            <button
                                type="submit"
                                className="w-full rounded-md bg-slate-100 px-3 py-1.5 text-sm font-semibold text-slate-700 hover:bg-slate-200 focus:outline-none focus:ring-2 focus:ring-indigo-500"
                            >
                                {t('common.apply')}
                            </button>
                        </form>
                    </div>

                    {/* Phase 11B.1 §17 — Rating filter */}
                    <div className="mt-6 pt-6 border-t border-slate-200" data-testid="catalog-filter-rating">
                        <h3 className="text-sm font-semibold text-slate-900 uppercase tracking-wide mb-3">
                            {t('catalog.filter.rating')}
                        </h3>
                        <ul className="space-y-1">
                            <li>
                                <button
                                    type="button"
                                    onClick={() => setRating(4)}
                                    className={`w-full flex items-center justify-between rounded px-3 py-2 text-sm ${
                                        filters.rating_min === 4
                                            ? 'bg-indigo-50 text-indigo-700 font-medium'
                                            : 'text-slate-600 hover:bg-slate-100'
                                    }`}
                                >
                                    <span>★ {t('catalog.rating.4_plus')}</span>
                                    {facets && (
                                        <span className="text-xs text-slate-600">{facets.rating_4plus}</span>
                                    )}
                                </button>
                            </li>
                            <li>
                                <button
                                    type="button"
                                    onClick={() => setRating(3)}
                                    className={`w-full flex items-center justify-between rounded px-3 py-2 text-sm ${
                                        filters.rating_min === 3
                                            ? 'bg-indigo-50 text-indigo-700 font-medium'
                                            : 'text-slate-600 hover:bg-slate-100'
                                    }`}
                                >
                                    <span>★ {t('catalog.rating.3_plus')}</span>
                                    {facets && (
                                        <span className="text-xs text-slate-600">{facets.rating_3plus}</span>
                                    )}
                                </button>
                            </li>
                            {filters.rating_min && (
                                <li>
                                    <button
                                        type="button"
                                        onClick={() => setRating(null)}
                                        className="w-full text-start rounded px-3 py-2 text-sm text-slate-600 hover:bg-slate-100"
                                    >
                                        {t('catalog.rating.any')}
                                    </button>
                                </li>
                            )}
                        </ul>
                    </div>

                    {/* Phase 11B.1 §18 — Availability toggles */}
                    <div className="mt-6 pt-6 border-t border-slate-200" data-testid="catalog-filter-availability">
                        <h3 className="text-sm font-semibold text-slate-900 uppercase tracking-wide mb-3">
                            {t('common.featured')}
                        </h3>
                        <ul className="space-y-1">
                            <li>
                                <button
                                    type="button"
                                    onClick={toggleInStock}
                                    aria-pressed={!!filters.in_stock}
                                    className={`w-full flex items-center justify-between rounded px-3 py-2 text-sm ${
                                        filters.in_stock
                                            ? 'bg-indigo-50 text-indigo-700 font-medium'
                                            : 'text-slate-600 hover:bg-slate-100'
                                    }`}
                                    data-testid="catalog-filter-in-stock"
                                >
                                    <span>{t('catalog.filter.in_stock')}</span>
                                    {facets && (
                                        <span className="text-xs text-slate-600">{facets.in_stock}</span>
                                    )}
                                </button>
                            </li>
                            <li>
                                <button
                                    type="button"
                                    onClick={toggleOnSale}
                                    aria-pressed={!!filters.on_sale}
                                    className={`w-full flex items-center justify-between rounded px-3 py-2 text-sm ${
                                        filters.on_sale
                                            ? 'bg-indigo-50 text-indigo-700 font-medium'
                                            : 'text-slate-600 hover:bg-slate-100'
                                    }`}
                                    data-testid="catalog-filter-on-sale"
                                >
                                    <span>{t('catalog.filter.on_sale')}</span>
                                    {facets && (
                                        <span className="text-xs text-slate-600">{facets.on_sale}</span>
                                    )}
                                </button>
                            </li>
                        </ul>
                    </div>
                </aside>

                {/* Main */}
                <section>
                    {/* Toolbar */}
                    <div className="flex flex-wrap items-center justify-between gap-3 mb-4">
                        <h1 className="text-2xl font-bold text-slate-900">
                            {active_category ? active_category.name : t('catalog.title_all')}
                            <span className="text-slate-600 text-base font-normal ml-2">
                                ({products.total})
                            </span>
                        </h1>

                        {/* Phase 11B.1 v11B.1.2 §18-§22 — Products-page search
                            now uses the same SearchBar component as the desktop
                            header and mobile drawer. Previously this was a plain
                            <input> with form-submit only — no /search/suggestions
                            XHR was ever dispatched from the Products page, which
                            is exactly the defect dev reported in v11B.1.2.

                            instanceId="catalog-toolbar" namespaces this mount's
                            listbox / option DOM IDs (per dev §23+§24) so the
                            three concurrent SearchBar mounts (header, drawer,
                            catalog toolbar) never share IDs — fixes duplicate-ID
                            ARIA violations and stops mobile-keyboard arrow keys
                            from controlling the wrong instance. */}
                        <div className="flex items-center gap-2 flex-wrap">
                            <div className="min-w-[200px] sm:min-w-[260px]">
                                <SearchBar
                                    variant="desktop"
                                    instanceId="catalog-toolbar"
                                    initialQuery={q}
                                />
                            </div>
                            <label className="text-sm text-slate-700 hidden sm:inline">{t('catalog.sort_by')}:</label>
                            <select
                                value={filters.sort}
                                onChange={(e) => setSort(e.target.value)}
                                className="rounded-md border-slate-300 px-3 py-1.5 text-sm border focus:border-indigo-500 focus:ring-indigo-500"
                                data-testid="catalog-sort-select"
                            >
                                {filters.q && <option value="relevance">{t('catalog.sort.relevance')}</option>}
                                <option value="newest">{t('catalog.sort.newest')}</option>
                                <option value="featured">{t('catalog.sort.featured')}</option>
                                <option value="price_asc">{t('catalog.sort.price_asc')}</option>
                                <option value="price_desc">{t('catalog.sort.price_desc')}</option>
                                <option value="rating">{t('catalog.sort.rating_desc')}</option>
                                <option value="popular">{t('catalog.sort.popular')}</option>
                                <option value="best_selling">{t('catalog.sort.best_selling')}</option>
                            </select>
                        </div>
                    </div>

                    {/* Phase 11B.1 §15 — Active filter chips */}
                    {activeFilterCount > 0 && (
                        <div
                            className="flex flex-wrap items-center gap-2 mb-4 pb-4 border-b border-slate-200"
                            data-testid="catalog-active-chips"
                        >
                            <span className="text-xs uppercase tracking-wide font-semibold text-slate-700">
                                {t('catalog.active_filters')}:
                            </span>
                            {filters.category && active_category && (
                                <Chip label={active_category.name} onRemove={() => removeFilter('category')} />
                            )}
                            {filters.vendor && active_vendor && (
                                <Chip label={active_vendor.name} onRemove={() => removeFilter('vendor')} />
                            )}
                            {filters.price_min && (
                                <Chip label={`≥ ${filters.price_min}`} onRemove={() => removeFilter('price_min')} />
                            )}
                            {filters.price_max && (
                                <Chip label={`≤ ${filters.price_max}`} onRemove={() => removeFilter('price_max')} />
                            )}
                            {filters.rating_min && (
                                <Chip label={`★ ${filters.rating_min}+`} onRemove={() => removeFilter('rating_min')} />
                            )}
                            {filters.in_stock && (
                                <Chip label={t('catalog.filter.in_stock')} onRemove={() => removeFilter('in_stock')} />
                            )}
                            {filters.on_sale && (
                                <Chip label={t('catalog.filter.on_sale')} onRemove={() => removeFilter('on_sale')} />
                            )}
                            <button
                                type="button"
                                onClick={clearAllFilters}
                                className="text-xs font-semibold text-brand-700 hover:underline ml-2"
                                data-testid="catalog-clear-all"
                            >
                                {t('catalog.filters_clear_all')}
                            </button>
                        </div>
                    )}

                    {/* Phase 11B.1 §9 — Did You Mean banner */}
                    {did_you_mean && (
                        <div
                            className="mb-4 px-4 py-3 bg-slate-50 border border-slate-200 rounded-lg text-sm text-slate-800"
                            data-testid="catalog-did-you-mean"
                        >
                            {t('search.did_you_mean')}{' '}
                            <Link
                                href={`/products?q=${encodeURIComponent(did_you_mean)}`}
                                className="font-semibold text-brand-700 hover:underline"
                            >
                                {did_you_mean}?
                            </Link>
                        </div>
                    )}

                    {/* Grid */}
                    {products.data.length === 0 ? (
                        <div
                            className="text-center py-16 bg-white border border-slate-200 rounded-xl"
                            data-testid="catalog-no-results"
                        >
                            <h2 className="text-lg font-semibold text-slate-900 mb-2">
                                {filters.q
                                    ? t('search.no_results_for', { query: filters.q })
                                    : t('catalog.no_results_title')}
                            </h2>
                            <p className="text-sm text-slate-700 max-w-md mx-auto mb-4">
                                {filters.q ? t('search.try_different') : t('catalog.no_results_body')}
                            </p>
                            {activeFilterCount > 0 && (
                                <button
                                    type="button"
                                    onClick={clearAllFilters}
                                    className="inline-block px-4 py-2 rounded-lg bg-brand-700 text-white text-sm font-semibold hover:bg-brand-800"
                                >
                                    {t('catalog.filters_clear_all')}
                                </button>
                            )}
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                            {products.data.map((p) => {
                                const card = <ProductCardView product={p} />;
                                return <div key={p.id} className="contents">{card}</div>;
                            })}
                        </div>
                    )}

                    {/* Pagination */}
                    {products.links.length > 3 && (
                        <nav className="mt-8 flex flex-wrap gap-1 justify-center">
                            {products.links.map((link, i) => (
                                <Link
                                    key={i}
                                    href={link.url ?? '#'}
                                    className={`px-3 py-1.5 rounded border text-sm ${
                                        link.active
                                            ? 'bg-indigo-600 text-white border-indigo-600'
                                            : link.url
                                              ? 'border-slate-300 text-slate-700 hover:bg-slate-50'
                                              : 'border-slate-200 text-slate-400 cursor-not-allowed'
                                    }`}
                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                />
                            ))}
                        </nav>
                    )}
                </section>
            </div>
            </Container>
        </StorefrontLayout>
    );
}

function ProductCardView({ product }: { product: ProductCard }) {
    const t = useT();
    return (
        <Link
            href={`/products/${product.slug}`}
            className="bg-white border border-slate-200 rounded-xl overflow-hidden hover:border-indigo-300 hover:shadow-sm transition"
        >
            <div className="aspect-square bg-slate-100 flex items-center justify-center overflow-hidden">
                {product.thumb ? (
                    <img
                        src={product.thumb}
                        alt={product.name}
                        loading="lazy"
                        className="w-full h-full object-cover"
                        onError={(e) => {
                            // Clean fallback if the file is missing — swap to the emoji placeholder
                            const el = e.currentTarget;
                            el.style.display = 'none';
                            el.nextElementSibling?.classList.remove('hidden');
                        }}
                    />
                ) : null}
                <span className={`text-4xl text-slate-300 ${product.thumb ? 'hidden' : ''}`}>🛍️</span>
            </div>
            <div className="p-4 sm:p-5">
                {product.featured && (
                    <span className="inline-block mb-2 px-1.5 py-0.5 bg-amber-100 text-amber-900 text-[10px] rounded font-medium">
                        {t('common.featured')}
                    </span>
                )}
                {product.category && (
                    <div className="text-xs text-slate-600 mb-1">{product.category}</div>
                )}
                <div className="text-sm font-medium text-slate-900 line-clamp-2 min-h-[2.5rem]">
                    {product.name}
                </div>
                {product.vendor_name && (
                    <div className="text-xs text-slate-600 mt-2 truncate">
                        {t('product.by_vendor', { vendor: product.vendor_name })}
                    </div>
                )}
                <div className="mt-3 flex items-baseline gap-2 flex-wrap">
                    {product.promotion && product.final_price ? (
                        <>
                            <span className="text-base font-semibold text-rose-700" data-testid={`catalog-card-final-${product.id}`}>
                                {product.final_price} {product.currency}
                            </span>
                            <span className="text-xs text-slate-600 line-through" data-testid={`catalog-card-original-${product.id}`}>
                                {product.price}
                            </span>
                            <span
                                className="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700"
                                title={product.promotion.title}
                                data-testid={`catalog-card-promo-badge-${product.id}`}
                            >
                                {product.promotion.badge}
                            </span>
                        </>
                    ) : (
                        <>
                            <span className="text-base font-semibold text-slate-900">
                                {product.price} {product.currency}
                            </span>
                            {product.compare_at && (
                                <span className="text-xs text-slate-600 line-through">{product.compare_at}</span>
                            )}
                        </>
                    )}
                </div>
            </div>
        </Link>
    );
}

/**
 * Phase 11B.1 §15 — active-filter chip.
 *
 * Compact pill with a remove button. Click anywhere on the chip removes
 * that filter. Keyboard-accessible (button, visible focus), RTL-safe
 * (uses logical spacing).
 */
function Chip({ label, onRemove }: { label: string; onRemove: () => void }) {
    return (
        <button
            type="button"
            onClick={onRemove}
            className="inline-flex items-center gap-1.5 rounded-full bg-indigo-50 text-indigo-700 px-3 py-1 text-xs font-medium hover:bg-indigo-100 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-1"
            aria-label={`Remove filter: ${label}`}
        >
            <span>{label}</span>
            <span aria-hidden="true" className="text-indigo-500 hover:text-indigo-900">×</span>
        </button>
    );
}
