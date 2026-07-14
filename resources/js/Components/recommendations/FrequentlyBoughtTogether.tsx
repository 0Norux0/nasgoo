import { useEffect, useRef, useState, useMemo } from 'react';
import { router, Link } from '@inertiajs/react';
import { useT } from '@/lib/i18n';
import type { RecommendationSection } from './types';
import { trackRecommendationEvent } from './types';

interface Props {
    sourceProductId: number;
    sourceProductName: string;
    sourceProductImage: string | null;
    sourceProductPrice: string;
    sourceProductPriceMinor: number;
    sourceCurrency: string;
    section: RecommendationSection;
}

/**
 * Phase 11B.2 §10 — Frequently Bought Together.
 *
 * Displays: source product (locked checkbox) + companion items (toggleable).
 * Combined price updates as the user toggles checkboxes. "Add Selected to
 * Cart" POSTs to /cart/items/batch which re-validates server-side.
 *
 * Per dev §10:
 *   - source product is locked (always added when user clicks Add Selected)
 *   - unavailable items cannot be selected
 *   - combined price reflects current selection
 *   - multi-vendor combinations are handled (existing CartService routes)
 *   - accessibility labels provided
 *   - no fake bundle discount unless real promotion (we ONLY display
 *     individual prices summed; no claim of bundle discount)
 *
 * Section is hidden entirely when evidence==='none' (per dev §14
 * fallback — don't show a section with no meaningful result).
 */
export default function FrequentlyBoughtTogether({
    sourceProductId,
    sourceProductName,
    sourceProductImage,
    sourceProductPrice,
    sourceProductPriceMinor,
    sourceCurrency,
    section,
}: Props) {
    const t = useT();
    const recordedRef = useRef<Set<number>>(new Set());

    // Track selection. Source is always selected (hidden checkbox).
    // Companion items start checked if in_stock; unavailable items cannot be checked.
    const [selected, setSelected] = useState<Record<number, boolean>>(() => {
        const initial: Record<number, boolean> = {};
        for (const item of section.items) {
            initial[item.id] = item.in_stock;
        }
        return initial;
    });

    useEffect(() => {
        if (!section.enabled) return;
        for (const item of section.items) {
            if (recordedRef.current.has(item.id)) continue;
            recordedRef.current.add(item.id);
            trackRecommendationEvent({
                event_type: 'impression',
                product_id: sourceProductId,
                recommended_product_id: item.id,
                recommendation_type: 'fbt',
            });
        }
    }, [section, sourceProductId]);

    const combinedTotalMinor = useMemo(() => {
        let total = sourceProductPriceMinor;
        for (const item of section.items) {
            if (selected[item.id] && item.in_stock) {
                total += item.price_minor;
            }
        }
        return total;
    }, [selected, section.items, sourceProductPriceMinor]);

    const decimals = sourceCurrency === 'KWD' ? 3 : 2;
    const divisor = sourceCurrency === 'KWD' ? 1000 : 100;
    const combinedTotal = (combinedTotalMinor / divisor).toFixed(decimals);

    const handleAddSelected = () => {
        const items: { product_id: number; quantity: number }[] = [
            { product_id: sourceProductId, quantity: 1 },
        ];
        for (const item of section.items) {
            if (selected[item.id] && item.in_stock) {
                items.push({ product_id: item.id, quantity: 1 });
                trackRecommendationEvent({
                    event_type: 'add_to_cart',
                    product_id: sourceProductId,
                    recommended_product_id: item.id,
                    recommendation_type: 'fbt',
                });
            }
        }
        router.post('/cart/items/batch', { items });
    };

    if (!section.enabled || section.evidence === 'none' || section.items.length === 0) {
        return null;
    }

    // Per dev §14 — only label as "Frequently Bought Together" when the
    // evidence is real co-occurrence; admin-complementary fallback gets the
    // truthful "You May Also Like" / Related label.
    const headingKey =
        section.evidence === 'co_occurrence'
            ? 'recommendations.frequently_bought.title'
            : 'recommendations.also_bought.fallback_title';

    return (
        <section
            className="mt-10 rounded-xl border border-slate-200 bg-white p-5"
            aria-labelledby="rec-fbt-heading"
            data-testid="rec-fbt"
        >
            <h2 id="rec-fbt-heading" className="text-xl font-semibold text-slate-900 mb-4">
                {t(headingKey)}
            </h2>

            <div className="grid grid-cols-1 lg:grid-cols-[1fr_auto] gap-6">
                {/* Item list with checkboxes */}
                <ul role="list" className="space-y-3" data-testid="rec-fbt-items">
                    {/* Source product — always selected, locked */}
                    <li className="flex items-center gap-3 p-2 rounded-md bg-slate-50">
                        <input
                            type="checkbox"
                            checked
                            disabled
                            aria-label={`${sourceProductName} (this item, always included)`}
                            className="h-4 w-4 rounded border-slate-400"
                        />
                        {sourceProductImage ? (
                            <img
                                src={sourceProductImage}
                                alt=""
                                className="w-12 h-12 rounded object-cover"
                            />
                        ) : (
                            <div className="w-12 h-12 rounded bg-slate-200" />
                        )}
                        <div className="flex-1 min-w-0">
                            <div className="text-sm font-medium text-slate-900 truncate">
                                {sourceProductName}
                            </div>
                            <div className="text-xs text-slate-500">
                                {t('recommendations.this_item')}
                            </div>
                        </div>
                        <div className="text-sm font-semibold text-slate-900">
                            {sourceProductPrice} {sourceCurrency}
                        </div>
                    </li>

                    {/* Companion items */}
                    {section.items.map((item) => (
                        <li
                            key={item.id}
                            className={`flex items-center gap-3 p-2 rounded-md ${
                                item.in_stock ? 'hover:bg-slate-50' : 'opacity-60'
                            }`}
                            data-testid={`rec-fbt-item-${item.id}`}
                        >
                            <input
                                type="checkbox"
                                checked={selected[item.id] && item.in_stock}
                                disabled={!item.in_stock}
                                onChange={(e) =>
                                    setSelected((prev) => ({ ...prev, [item.id]: e.target.checked }))
                                }
                                aria-label={`Include ${item.display_name}`}
                                className="h-4 w-4 rounded border-slate-400"
                                data-testid={`rec-fbt-checkbox-${item.id}`}
                            />
                            {item.image ? (
                                <img src={item.image} alt="" className="w-12 h-12 rounded object-cover" />
                            ) : (
                                <div className="w-12 h-12 rounded bg-slate-200" />
                            )}
                            <Link
                                href={`/products/${item.slug}`}
                                className="flex-1 min-w-0 group focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 rounded"
                                onClick={() =>
                                    trackRecommendationEvent({
                                        event_type: 'click',
                                        product_id: sourceProductId,
                                        recommended_product_id: item.id,
                                        recommendation_type: 'fbt',
                                    })
                                }
                            >
                                <div className="text-sm font-medium text-slate-900 truncate group-hover:underline">
                                    {item.display_name}
                                </div>
                                <div className="text-xs text-slate-500 truncate">
                                    {item.vendor_name}
                                </div>
                            </Link>
                            <div className="text-sm font-semibold text-slate-900 whitespace-nowrap">
                                {item.in_stock ? (
                                    <>{item.price} {item.currency}</>
                                ) : (
                                    <span className="text-amber-600 text-xs">
                                        {t('recommendations.unavailable')}
                                    </span>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>

                {/* Combined total + Add Selected */}
                <div className="lg:w-56 lg:border-s lg:ps-6 lg:border-slate-200 flex flex-col justify-between gap-3">
                    <div>
                        <div className="text-sm text-slate-600">
                            {t('recommendations.combined_price')}
                        </div>
                        <div
                            className="text-2xl font-bold text-slate-900 mt-1"
                            data-testid="rec-fbt-combined-total"
                        >
                            {combinedTotal} {sourceCurrency}
                        </div>
                    </div>
                    <button
                        type="button"
                        onClick={handleAddSelected}
                        className="w-full rounded-lg bg-brand-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-brand-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2"
                        data-testid="rec-fbt-add-selected"
                    >
                        {t('recommendations.add_selected')}
                    </button>
                </div>
            </div>
        </section>
    );
}
