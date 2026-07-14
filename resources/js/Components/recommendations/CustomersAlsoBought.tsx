import { useEffect, useRef } from 'react';
import { Link } from '@inertiajs/react';
import { useT } from '@/lib/i18n';
import type { RecommendationSection } from './types';
import { trackRecommendationEvent } from './types';

interface Props {
    sourceProductId: number;
    section: RecommendationSection;
}

/**
 * Phase 11B.2 §11 — Customers Also Bought.
 *
 * Hidden entirely when below the privacy threshold (the backend already
 * returns an empty items[] in that case per dev §12). The fallback label
 * "You May Also Like" is shown when evidence==='none' on the also_bought
 * service (currently we just hide the section in that case; see dev §14
 * — only show truthful labels).
 */
export default function CustomersAlsoBought({ sourceProductId, section }: Props) {
    const t = useT();
    const recordedRef = useRef<Set<number>>(new Set());

    useEffect(() => {
        if (!section.enabled) return;
        for (const item of section.items) {
            if (recordedRef.current.has(item.id)) continue;
            recordedRef.current.add(item.id);
            trackRecommendationEvent({
                event_type: 'impression',
                product_id: sourceProductId,
                recommended_product_id: item.id,
                recommendation_type: 'also_bought',
            });
        }
    }, [section, sourceProductId]);

    if (!section.enabled || section.items.length === 0) return null;

    return (
        <section
            className="mt-10"
            aria-labelledby="rec-also-bought-heading"
            data-testid="rec-also-bought"
        >
            <h2
                id="rec-also-bought-heading"
                className="text-xl font-semibold text-slate-900 mb-4"
            >
                {t('recommendations.also_bought.title')}
            </h2>

            <ul
                className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4"
                role="list"
            >
                {section.items.map((item) => (
                    <li
                        key={item.id}
                        className="rounded-lg border border-slate-200 bg-white overflow-hidden hover:shadow-md transition-shadow"
                        data-testid={`rec-also-bought-item-${item.id}`}
                    >
                        <Link
                            href={`/products/${item.slug}`}
                            className="block focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500"
                            onClick={() =>
                                trackRecommendationEvent({
                                    event_type: 'click',
                                    product_id: sourceProductId,
                                    recommended_product_id: item.id,
                                    recommendation_type: 'also_bought',
                                })
                            }
                        >
                            {item.image ? (
                                <img
                                    src={item.image}
                                    alt=""
                                    className="w-full aspect-square object-cover"
                                    loading="lazy"
                                />
                            ) : (
                                <div className="w-full aspect-square bg-slate-100" />
                            )}
                            <div className="p-3">
                                <h3 className="text-sm font-medium text-slate-900 line-clamp-2 mb-1">
                                    {item.display_name}
                                </h3>
                                <div className="text-xs text-slate-500 line-clamp-1 mb-1">
                                    {item.vendor_name}
                                </div>
                                <div className="text-sm font-semibold text-slate-900">
                                    {item.price} {item.currency}
                                </div>
                                <div className="text-[10px] uppercase tracking-wide text-slate-400 mt-1">
                                    {t('recommendations.explanation.popular_with_buyers')}
                                </div>
                                {!item.in_stock && (
                                    <div className="text-xs text-amber-600 mt-1">
                                        {t('recommendations.unavailable')}
                                    </div>
                                )}
                            </div>
                        </Link>
                    </li>
                ))}
            </ul>
        </section>
    );
}
