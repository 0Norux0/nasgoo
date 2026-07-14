import { useEffect, useId, useMemo, useRef, useState, type FormEvent, type KeyboardEvent } from 'react';
import { router } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useT } from '@/lib/i18n';

/**
 * Phase 11A v11A.4 §4 — storefront SearchBar with live suggestions.
 *
 * Self-contained:
 *   - debounced input → GET /search/suggestions?q=...
 *   - 300ms debounce (within dev's 250-350ms guidance)
 *   - aborts in-flight request on new keystroke (stale-request protection)
 *   - keyboard nav: ArrowUp/ArrowDown to walk suggestions; Enter to select;
 *     Escape to close. Tab/blur outside closes after a microtask delay.
 *   - listbox + role="combobox" semantics for accessibility
 *   - mobile-friendly: full-width dropdown anchored to input
 *   - JS-disabled fallback: native <form> submit still works
 *
 * The dropdown groups results into Products/Categories/Services and ends with
 * a "View all results for X" link that routes to the standard catalog
 * /products?q=X page.
 *
 * Does NOT cache results. Each query hits the backend (which is itself fast
 * and rate-limited at the route level).
 */

type SuggestionItem = {
    id: number;
    slug: string;
    name: string;
    price?: string;
    currency?: string;
    href: string;
};

type SuggestionsPayload = {
    query: string;
    products: SuggestionItem[];
    categories: SuggestionItem[];
    services: SuggestionItem[];
    /** Phase 11B.1 — popular anonymous queries for this locale. */
    popular?: string[];
    /** Phase 11B.1 — authenticated user's recent searches. */
    recent?: string[];
    /** Phase 11B.1 — "Did you mean?" candidate when no main results. */
    did_you_mean?: string | null;
    total: number;
};

type FlatItem = SuggestionItem & { group: 'products' | 'categories' | 'services' };

const DEBOUNCE_MS = 300;
const MIN_LENGTH  = 2;

type Props = {
    /** Variant: 'desktop' (h-11 with pe-24 button) or 'mobile' (h-12, simpler) */
    variant?: 'desktop' | 'mobile';
    /** Called when the user picks a suggestion or submits — useful for the parent to close drawers */
    onNavigate?: () => void;
    /** Outer form className override for layout */
    className?: string;
    /** Initial query value (e.g. from URL) */
    initialQuery?: string;
    /**
     * Phase 11B.1 v11B.1.2 §23+§24 — unique DOM-id seed for this mount.
     * Multiple SearchBar instances on the same page (header + Catalog
     * toolbar + mobile drawer) MUST NOT share the same `id` / `aria-controls`
     * values, or screen readers and JS handlers get confused. Pass an
     * explicit `instanceId` to namespace this mount's IDs, or rely on
     * the auto-generated React useId() default.
     */
    instanceId?: string;
};

export default function SearchBar({
    variant = 'desktop',
    onNavigate,
    className = '',
    initialQuery = '',
    instanceId,
}: Props) {
    const t = useT();
    // Auto-namespace IDs when caller doesn't supply one. React's useId()
    // returns a stable, unique value that survives SSR + hydration.
    const autoId = useId();
    const namespace = instanceId ?? autoId;
    const listboxId = `search-suggestions-listbox-${namespace}`;
    const itemId = (idx: number) => `search-sugg-${namespace}-${idx}`;

    const [query, setQuery] = useState(initialQuery);
    const [data, setData] = useState<SuggestionsPayload | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const [isOpen, setIsOpen] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
    const wrapRef = useRef<HTMLFormElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const abortRef = useRef<AbortController | null>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    // Flatten the grouped suggestions for keyboard navigation
    const flatItems: FlatItem[] = useMemo(() => {
        if (!data) return [];
        return [
            ...data.products.map((it) => ({ ...it, group: 'products' as const })),
            ...data.categories.map((it) => ({ ...it, group: 'categories' as const })),
            ...data.services.map((it) => ({ ...it, group: 'services' as const })),
        ];
    }, [data]);

    // Click-outside-to-close
    useEffect(() => {
        const onDocClick = (e: MouseEvent) => {
            if (wrapRef.current && !wrapRef.current.contains(e.target as Node)) {
                setIsOpen(false);
            }
        };
        document.addEventListener('mousedown', onDocClick);
        return () => document.removeEventListener('mousedown', onDocClick);
    }, []);

    // Debounced fetch
    useEffect(() => {
        if (debounceRef.current) clearTimeout(debounceRef.current);
        const trimmed = query.trim();
        if (trimmed.length < MIN_LENGTH) {
            setData(null);
            setIsLoading(false);
            return;
        }
        debounceRef.current = setTimeout(() => {
            // Abort any in-flight request (stale-request protection)
            if (abortRef.current) abortRef.current.abort();
            const ctrl = new AbortController();
            abortRef.current = ctrl;
            setIsLoading(true);
            fetch(`/search/suggestions?q=${encodeURIComponent(trimmed)}`, {
                signal: ctrl.signal,
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            })
                .then((r) => (r.ok ? r.json() : Promise.reject(r)))
                .then((json: SuggestionsPayload) => {
                    setData(json);
                    setIsOpen(true);
                    setActiveIndex(-1);
                    setIsLoading(false);
                })
                .catch((err: unknown) => {
                    // Aborted requests should NOT clear data or stop loading;
                    // a new request is on the way.
                    if (err instanceof Error && err.name === 'AbortError') return;
                    // Network/server error → close suggestions but keep input usable
                    setData(null);
                    setIsLoading(false);
                });
        }, DEBOUNCE_MS);

        return () => {
            if (debounceRef.current) clearTimeout(debounceRef.current);
        };
    }, [query]);

    const submitFullSearch = (e?: FormEvent) => {
        if (e) e.preventDefault();
        const q = query.trim();
        setIsOpen(false);
        if (q) {
            router.visit(`/products?q=${encodeURIComponent(q)}`);
            onNavigate?.();
        }
    };

    const goTo = (href: string) => {
        setIsOpen(false);
        router.visit(href);
        onNavigate?.();
    };

    const handleKeyDown = (e: KeyboardEvent<HTMLInputElement>) => {
        if (!isOpen && (e.key === 'ArrowDown' || e.key === 'ArrowUp')) {
            if (flatItems.length > 0) setIsOpen(true);
            return;
        }
        if (e.key === 'Escape') {
            setIsOpen(false);
            return;
        }
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setActiveIndex((prev) => Math.min(prev + 1, flatItems.length - 1));
            return;
        }
        if (e.key === 'ArrowUp') {
            e.preventDefault();
            setActiveIndex((prev) => Math.max(prev - 1, -1));
            return;
        }
        if (e.key === 'Enter' && activeIndex >= 0 && activeIndex < flatItems.length) {
            e.preventDefault();
            goTo(flatItems[activeIndex].href);
        }
    };

    const heightClass = variant === 'mobile' ? 'h-12' : 'h-11';
    const showButton = variant === 'desktop';
    // Phase 11B.1 — dropdown opens when:
    //  (a) loading, OR
    //  (b) we have main-group data, OR
    //  (c) query is too short but we have popular/recent suggestions to show
    const hasMainData = !!data && (data.total > 0 || query.trim().length >= MIN_LENGTH);
    const hasStandingData = !!data && ((data.popular?.length ?? 0) + (data.recent?.length ?? 0)) > 0;
    const showDropdown = isOpen && (isLoading || hasMainData || hasStandingData);

    return (
        <form
            onSubmit={submitFullSearch}
            role="search"
            className={`relative w-full ${className}`}
            ref={wrapRef}
        >
            <div className="relative w-full">
                <Search
                    size={18}
                    className="absolute start-3 top-1/2 -translate-y-1/2 text-slate-500 pointer-events-none"
                    aria-hidden="true"
                />
                <input
                    ref={inputRef}
                    type="search"
                    value={query}
                    onChange={(e) => setQuery(e.target.value)}
                    onKeyDown={handleKeyDown}
                    onFocus={() => {
                        if (data && (data.total > 0 || (data.popular?.length ?? 0) > 0 || (data.recent?.length ?? 0) > 0)) {
                            setIsOpen(true);
                        } else if (query.trim().length === 0) {
                            // Phase 11B.1 — fetch popular/recent groups on first focus
                            fetch('/search/suggestions?q=', {
                                headers: { Accept: 'application/json' },
                                credentials: 'same-origin',
                            })
                                .then((r) => (r.ok ? r.json() : null))
                                .then((json) => {
                                    if (json) {
                                        setData(json);
                                        setIsOpen(true);
                                    }
                                })
                                .catch(() => {});
                        }
                    }}
                    placeholder={t('header.search_placeholder')}
                    aria-label={t('header.search_aria')}
                    aria-autocomplete="list"
                    aria-controls={listboxId}
                    aria-expanded={isOpen}
                    aria-activedescendant={activeIndex >= 0 ? itemId(activeIndex) : undefined}
                    role="combobox"
                    className={
                        `w-full ${heightClass} ps-10 ${showButton ? 'pe-24' : 'pe-4'} rounded-xl ` +
                        'bg-slate-50 border border-slate-200 ' +
                        'text-sm text-slate-900 placeholder:text-slate-500 ' +
                        'focus:bg-white focus:border-brand-500 ' +
                        'focus:outline-none focus:ring-2 focus:ring-brand-500/20 ' +
                        'transition-colors'
                    }
                    data-testid="search-input"
                />
                {showButton && (
                    <button
                        type="submit"
                        className="absolute end-1.5 top-1/2 -translate-y-1/2 h-8 px-4 rounded-lg bg-brand-800 text-white text-xs font-semibold hover:bg-brand-900 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                    >
                        {t('header.search_button')}
                    </button>
                )}
            </div>

            {showDropdown && (
                <div
                    id={listboxId}
                    role="listbox"
                    aria-label={t('search.suggestions.products')}
                    data-testid="search-suggestions-panel"
                    className="absolute inset-x-0 top-full mt-2 bg-white border border-slate-200 rounded-xl shadow-lg overflow-hidden z-50 max-h-[70vh] overflow-y-auto"
                >
                    {isLoading && (
                        <div className="px-4 py-3 text-sm text-slate-600" data-testid="search-loading">
                            {t('search.suggestions.loading')}
                        </div>
                    )}

                    {!isLoading && data && data.total === 0 && (
                        <div className="px-4 py-3 text-sm text-slate-700" data-testid="search-empty">
                            {t('search.suggestions.no_results')}
                        </div>
                    )}

                    {!isLoading && data && data.products.length > 0 && (
                        <Group label={t('search.suggestions.products')}>
                            {data.products.map((it) => (
                                <Item
                                    key={`p-${it.id}`}
                                    id={itemId(flatItems.findIndex((f) => f.group === 'products' && f.id === it.id))}
                                    item={it}
                                    isActive={
                                        activeIndex >= 0 &&
                                        flatItems[activeIndex]?.group === 'products' &&
                                        flatItems[activeIndex]?.id === it.id
                                    }
                                    onClick={() => goTo(it.href)}
                                    showPrice
                                />
                            ))}
                        </Group>
                    )}

                    {!isLoading && data && data.categories.length > 0 && (
                        <Group label={t('search.suggestions.categories')}>
                            {data.categories.map((it) => (
                                <Item
                                    key={`c-${it.id}`}
                                    id={itemId(flatItems.findIndex((f) => f.group === 'categories' && f.id === it.id))}
                                    item={it}
                                    isActive={
                                        activeIndex >= 0 &&
                                        flatItems[activeIndex]?.group === 'categories' &&
                                        flatItems[activeIndex]?.id === it.id
                                    }
                                    onClick={() => goTo(it.href)}
                                />
                            ))}
                        </Group>
                    )}

                    {!isLoading && data && data.services.length > 0 && (
                        <Group label={t('search.suggestions.services')}>
                            {data.services.map((it) => (
                                <Item
                                    key={`s-${it.id}`}
                                    id={itemId(flatItems.findIndex((f) => f.group === 'services' && f.id === it.id))}
                                    item={it}
                                    isActive={
                                        activeIndex >= 0 &&
                                        flatItems[activeIndex]?.group === 'services' &&
                                        flatItems[activeIndex]?.id === it.id
                                    }
                                    onClick={() => goTo(it.href)}
                                    showPrice
                                />
                            ))}
                        </Group>
                    )}

                    {/* Phase 11B.1 §9 — Did You Mean banner (only when main groups empty) */}
                    {!isLoading && data && data.did_you_mean && (
                        <div
                            className="px-4 py-3 text-sm text-slate-800 border-t border-slate-200 bg-slate-50"
                            data-testid="search-did-you-mean"
                        >
                            <span className="text-slate-600">{t('search.did_you_mean')}</span>{' '}
                            <button
                                type="button"
                                className="font-semibold text-brand-700 hover:underline focus:outline-none focus:underline"
                                onMouseDown={(e) => {
                                    e.preventDefault();
                                    setQuery(data.did_you_mean!);
                                }}
                            >
                                {data.did_you_mean}?
                            </button>
                        </div>
                    )}

                    {/* Phase 11B.1 §11 — Recent searches (authenticated users only) */}
                    {!isLoading && data && (data.recent?.length ?? 0) > 0 && (
                        <Group label={t('search.suggestions.recent')}>
                            {data.recent!.map((q, idx) => (
                                <li
                                    key={`r-${idx}`}
                                    role="option"
                                    aria-selected={false}
                                    onMouseDown={(e) => {
                                        e.preventDefault();
                                        setQuery(q);
                                    }}
                                    className="cursor-pointer px-4 py-2 text-sm text-slate-800 hover:bg-slate-50 truncate"
                                    data-testid="search-recent-item"
                                >
                                    {q}
                                </li>
                            ))}
                        </Group>
                    )}

                    {/* Phase 11B.1 §12 — Popular anonymous searches */}
                    {!isLoading && data && (data.popular?.length ?? 0) > 0 && (
                        <Group label={t('search.suggestions.popular')}>
                            {data.popular!.map((q, idx) => (
                                <li
                                    key={`pop-${idx}`}
                                    role="option"
                                    aria-selected={false}
                                    onMouseDown={(e) => {
                                        e.preventDefault();
                                        setQuery(q);
                                    }}
                                    className="cursor-pointer px-4 py-2 text-sm text-slate-800 hover:bg-slate-50 truncate"
                                    data-testid="search-popular-item"
                                >
                                    {q}
                                </li>
                            ))}
                        </Group>
                    )}

                    {!isLoading && query.trim().length >= MIN_LENGTH && (
                        <button
                            type="button"
                            onClick={() => submitFullSearch()}
                            className="w-full text-start px-4 py-3 text-sm font-medium text-brand-700 hover:bg-brand-50 border-t border-slate-200 focus:outline-none focus:bg-brand-50"
                            data-testid="search-view-all"
                        >
                            {t('search.suggestions.view_all', { query: query.trim() })}
                        </button>
                    )}
                </div>
            )}
        </form>
    );
}

function Group({ label, children }: { label: string; children: React.ReactNode }) {
    return (
        <div className="border-b border-slate-100 last:border-0">
            <div className="px-4 pt-3 pb-1 text-[11px] uppercase tracking-wider font-semibold text-slate-600">
                {label}
            </div>
            <ul role="group" aria-label={label}>
                {children}
            </ul>
        </div>
    );
}

function Item({
    id,
    item,
    isActive,
    onClick,
    showPrice = false,
}: {
    id: string;
    item: SuggestionItem;
    isActive: boolean;
    onClick: () => void;
    showPrice?: boolean;
}) {
    return (
        <li
            id={id}
            role="option"
            aria-selected={isActive}
            onMouseDown={(e) => {
                // Prevent input from blurring before click registers
                e.preventDefault();
                onClick();
            }}
            className={
                'cursor-pointer px-4 py-2 flex items-center justify-between gap-3 text-sm ' +
                (isActive ? 'bg-brand-50 text-brand-900' : 'text-slate-800 hover:bg-slate-50')
            }
        >
            <span className="truncate">{item.name}</span>
            {showPrice && item.price && (
                <span className="shrink-0 text-xs text-slate-600 tabular-nums">
                    {item.price} {item.currency}
                </span>
            )}
        </li>
    );
}
