import { Link } from '@inertiajs/react';
import { Sparkles, AlertCircle, ArrowRight, CheckCircle2 } from 'lucide-react';
import { useT } from '@/lib/i18n';

/**
 * Phase 11B.4 v11B.4.2 Defect 9 fix — product quality badge for
 * the vendor product edit page. Uses the pre-generated
 * vendor_product_quality_scores row (no runtime scoring).
 *
 * Renders nothing if the score hasn't been computed yet — vendor
 * sees the badge appear after the next scheduled
 * `vendor-intelligence:generate --stale-only`.
 */

interface QualityScoreProp {
    score: number;
    missing_fields: string[];
    breakdown: Record<string, number>;
    computed_at: string | null;
}

interface Props {
    qualityScore: QualityScoreProp | null;
}

const MISSING_LABEL_MAP: Record<string, string> = {
    'core.title':               'Product title',
    'core.category':            'Category',
    'core.price':               'Price',
    'core.active':              'Publish status',
    'media.no_image':           'At least one image',
    'media.additional_images':  'Additional images',
    'i18n.arabic_title':        'Arabic title',
    'i18n.arabic_description':  'Arabic description',
    'inventory.stock_tracking': 'Stock tracking',
    'inventory.out_of_stock':   'Restock',
    'seo.slug':                 'Slug',
    'seo.short_description':    'Short description',
    'policy.description':       'Full description',
};

export default function ProductQualityBadge({ qualityScore }: Props) {
    const t = useT();

    if (!qualityScore) {
        return (
            <div
                className="bg-slate-50 border border-slate-200 rounded-lg p-3 text-xs text-slate-500 mb-4"
                data-testid="product-quality-badge-empty"
            >
                {t('vendor_intelligence.product_edit.not_computed',
                    'Quality score not yet calculated. It will appear after the next scheduled refresh.')}
            </div>
        );
    }

    const scoreTone = qualityScore.score >= 80 ? 'emerald'
                    : qualityScore.score >= 50 ? 'amber' : 'rose';
    const toneCls = {
        emerald: 'bg-emerald-50 border-emerald-200 text-emerald-800',
        amber:   'bg-amber-50 border-amber-200 text-amber-800',
        rose:    'bg-rose-50 border-rose-200 text-rose-800',
    }[scoreTone];

    return (
        <div
            className={`border rounded-lg p-3 mb-4 ${toneCls}`}
            data-testid="product-quality-badge"
        >
            <div className="flex items-center gap-2 mb-2 flex-wrap">
                <Sparkles size={16} aria-hidden="true" />
                <span className="text-sm font-semibold whitespace-nowrap">
                    {t('vendor_intelligence.product_edit.badge_title', 'Product quality')}
                </span>
                <span className="text-lg font-bold" data-testid="product-quality-badge-score">
                    {qualityScore.score}%
                </span>
                <Link
                    href="/vendor"
                    className="text-xs underline ms-auto whitespace-nowrap"
                    data-testid="product-quality-badge-link"
                >
                    {t('common.dashboard', 'Dashboard')} <ArrowRight size={10} className="inline" />
                </Link>
            </div>
            {qualityScore.missing_fields.length > 0 ? (
                <div>
                    <div className="text-xs mb-1 opacity-80 flex items-center gap-1">
                        <AlertCircle size={12} aria-hidden="true" />
                        {t('vendor_intelligence.product_edit.missing_title', 'Missing:')}
                    </div>
                    <ul className="flex flex-wrap gap-1.5" data-testid="product-quality-badge-missing">
                        {qualityScore.missing_fields.slice(0, 8).map((f) => (
                            <li
                                key={f}
                                className="text-xs bg-white/60 border border-current/30 rounded px-2 py-0.5 whitespace-nowrap"
                            >
                                {MISSING_LABEL_MAP[f] ?? f}
                            </li>
                        ))}
                    </ul>
                </div>
            ) : (
                <div className="flex items-center gap-1 text-xs" data-testid="product-quality-badge-complete">
                    <CheckCircle2 size={12} aria-hidden="true" />
                    {t('vendor_intelligence.product_edit.complete', 'This product looks complete.')}
                </div>
            )}
        </div>
    );
}
