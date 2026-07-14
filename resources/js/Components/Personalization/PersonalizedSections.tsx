import { router } from '@inertiajs/react';
import { useT } from '@/lib/i18n';
import Container from '@/Components/Layout/Container';
import type { FC } from 'react';

interface PromotionMeta {
    id: number;
    title: string;
    type: string;
    discount_type: string;
    discount_value: string;
    badge: string;
}

interface PersonalizationItem {
    id: number;
    slug: string;
    name: string;
    price: string;
    final_price: string | null;
    discount: string | null;
    promotion: PromotionMeta | null;
    currency: string;
    thumb: string | null;
    vendor_name: string | null;
}

interface PersonalizationSection {
    type: string;
    title_key: string;
    reason_key: string;
    items: PersonalizationItem[];
}

interface Props {
    enabled: boolean;
    sections: PersonalizationSection[];
}

/**
 * Phase 11B.3 — personalized homepage band.
 *
 * Renders nothing when disabled (feature flag off, opted out, or no data).
 * When enabled, renders each section as a responsive grid with a small
 * "Not Interested" control per card and a "Clear" control per section
 * (for Recently Viewed).
 */
const PersonalizedSections: FC<Props> = ({ enabled, sections }) => {
    const t = useT();

    if (!enabled || sections.length === 0) {
        return null;
    }

    const handleFeedback = (productId: number, feedbackType: string) => {
        router.post(
            '/personalization/feedback',
            { product_id: productId, feedback_type: feedbackType },
            { preserveScroll: true },
        );
    };

    const handleClearRecent = () => {
        router.post('/personalization/recently-viewed/clear', {}, { preserveScroll: true });
    };

    return (
        <div className="bg-slate-50">
            <Container className="py-6 space-y-8">
                {sections.map((section) => (
                    <section key={section.type} aria-labelledby={`ps-${section.type}`}>
                        <div className="flex items-baseline justify-between mb-4">
                            <div>
                                <h2
                                    id={`ps-${section.type}`}
                                    className="text-xl font-semibold text-slate-900"
                                    data-testid={`personalization-section-${section.type}`}
                                >
                                    {t(section.title_key, defaultTitle(section.type))}
                                </h2>
                                <p className="text-xs text-slate-500 mt-1" data-testid={`personalization-reason-${section.type}`}>
                                    {t(section.reason_key, defaultReason(section.type))}
                                </p>
                            </div>
                            {section.type === 'recently_viewed' && (
                                <button
                                    type="button"
                                    onClick={handleClearRecent}
                                    className="text-xs text-slate-500 hover:text-slate-700 underline"
                                    data-testid="clear-recently-viewed"
                                >
                                    {t('personalization.clear_history', 'Clear')}
                                </button>
                            )}
                        </div>

                        <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
                            {section.items.map((item) => (
                                <article
                                    key={`${section.type}-${item.id}`}
                                    className="relative bg-white border border-slate-200 rounded-lg overflow-hidden group"
                                    data-testid={`personalization-card-${item.id}`}
                                >
                                    <a href={`/products/${item.slug}`} className="block">
                                        <div className="aspect-square bg-slate-100 flex items-center justify-center">
                                            {item.thumb ? (
                                                <img
                                                    src={item.thumb}
                                                    alt={item.name}
                                                    loading="lazy"
                                                    className="max-h-full max-w-full object-cover"
                                                />
                                            ) : (
                                                <span className="text-slate-300 text-xs">
                                                    {t('common.no_image', 'No image')}
                                                </span>
                                            )}
                                        </div>
                                        <div className="p-2 space-y-1">
                                            <h3 className="text-sm font-medium text-slate-800 line-clamp-2">
                                                {item.name}
                                            </h3>
                                            {item.vendor_name && (
                                                <p className="text-xs text-slate-500 line-clamp-1">
                                                    {item.vendor_name}
                                                </p>
                                            )}
                                            <div className="flex items-baseline gap-2">
                                                {item.final_price && item.final_price !== item.price ? (
                                                    <>
                                                        <span className="text-sm font-semibold text-slate-900">
                                                            {item.final_price} {item.currency}
                                                        </span>
                                                        <span className="text-xs line-through text-slate-400">
                                                            {item.price}
                                                        </span>
                                                    </>
                                                ) : (
                                                    <span className="text-sm font-semibold text-slate-900">
                                                        {item.price} {item.currency}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </a>
                                    {/* Not Interested button (dev §23) */}
                                    <button
                                        type="button"
                                        onClick={() => handleFeedback(item.id, 'not_interested')}
                                        className="absolute top-1 end-1 bg-white/90 hover:bg-white text-slate-500 hover:text-slate-800 rounded-full w-6 h-6 flex items-center justify-center text-xs opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity"
                                        aria-label={t('personalization.not_interested', 'Not interested')}
                                        data-testid={`not-interested-${item.id}`}
                                    >
                                        ×
                                    </button>
                                </article>
                            ))}
                        </div>
                    </section>
                ))}
            </Container>
        </div>
    );
};

// Default English labels — Arabic served via useT lookup
function defaultTitle(type: string): string {
    switch (type) {
        case 'continue_shopping':
            return 'Continue shopping';
        case 'recently_viewed':
            return 'Recently viewed';
        case 'recommended_for_you':
            return 'Recommended for you';
        case 'category_affinity':
            return 'Popular in categories you browse';
        case 'buy_again':
            return 'Buy again';
        default:
            return 'For you';
    }
}

function defaultReason(type: string): string {
    switch (type) {
        case 'continue_shopping':
            return 'Items still in your cart or wishlist';
        case 'recently_viewed':
            return 'Products you looked at recently';
        case 'recommended_for_you':
            return 'Based on your activity';
        case 'category_affinity':
            return 'Because you viewed this category';
        case 'buy_again':
            return 'Previously purchased';
        default:
            return '';
    }
}

export default PersonalizedSections;
