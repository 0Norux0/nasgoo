/**
 * Phase 11B.2 — shared recommendation types.
 *
 * Matches the backend payload shape produced by RecommendationManager::shapeItem.
 * No `any` — keeps the contract explicit per dev §6 of v11A.4 / preserved.
 */

export interface RecommendationItem {
    id: number;
    slug: string;
    display_name: string;
    display_short_description: string | null;
    price_minor: number;
    price: string;
    currency: string;
    image: string | null;
    in_stock: boolean;
    vendor_name: string;
    explanation:
        | 'similar_category'
        | 'similar_price'
        | 'same_vendor'
        | 'frequently_bought'
        | 'popular_with_buyers'
        | 'related'
        | 'pinned';
    score: number;
}

export interface RecommendationSection {
    enabled: boolean;
    evidence: 'algorithmic' | 'co_occurrence' | 'complementary' | 'none';
    items: RecommendationItem[];
    metrics?: Record<number, { pair_count: number; confidence: number }>;
}

/**
 * Fire-and-forget analytics ping. Failures are silent (analytics must never
 * affect UX per dev §21).
 */
export function trackRecommendationEvent(payload: {
    event_type: 'impression' | 'click' | 'add_to_cart';
    product_id: number;
    recommended_product_id: number;
    recommendation_type: 'similar' | 'fbt' | 'also_bought' | 'similar_service';
    locale?: string;
}): void {
    try {
        const csrf =
            document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') ?? '';
        fetch('/recommendations/events', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                Accept: 'application/json',
            },
            body: JSON.stringify(payload),
            keepalive: true,
        }).catch(() => {
            /* silent */
        });
    } catch {
        /* silent */
    }
}

/**
 * Format a minor-units price + currency for display. Mirrors the backend's
 * number_format(p/1000, 3) for KWD (which has 3 decimal places); other
 * currencies use 2.
 */
export function formatPrice(priceMinor: number, currency: string): string {
    const decimals = currency === 'KWD' ? 3 : 2;
    const divisor = currency === 'KWD' ? 1000 : 100;
    return (priceMinor / divisor).toFixed(decimals);
}
