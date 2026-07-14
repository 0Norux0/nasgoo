import { Link } from '@inertiajs/react';
import { Heart, Star } from 'lucide-react';
import { Badge } from './primitives';
import { useT } from '@/lib/i18n';

/**
 * Phase 11A — ProductCard.
 *
 * Used on:
 * - Homepage "Featured products" section
 * - Catalog/Index grid
 * - "Similar products" section (Phase 11B will populate)
 * - Customer wishlist
 *
 * Design rules:
 * - Fixed aspect-square image (no layout jump while images load)
 * - 2-line title clamp via line-clamp-2
 * - Vendor name muted, link to vendor page
 * - Rating + count if available, else hidden (no "0 reviews" noise)
 * - Promo badge top-start, wishlist top-end
 * - Dual price hierarchy: final price prominent, original strikethrough
 * - "Add to cart" CTA at bottom — primary action
 * - All interactive elements have visible focus, ARIA labels
 * - LTR + RTL safe (uses logical start/end via flex order)
 */

export interface ProductCardProduct {
    id: number;
    slug: string;
    title: string;
    /** Either a full URL or null/undefined; fallback handled internally. */
    image_url?: string | null;
    /** Vendor display name (optional — hidden if absent). */
    vendor_name?: string | null;
    /** Optional vendor slug for the vendor link. */
    vendor_slug?: string | null;
    /** Average rating 0-5 (optional). */
    rating?: number | null;
    /** Number of approved reviews (optional). */
    review_count?: number | null;
    /** Final price the customer pays (already includes promo discount), formatted. */
    final_price?: string | null;
    /** Original (pre-promo) price, formatted. Only renders if different from final_price. */
    original_price?: string | null;
    /** Currency code/symbol for the price column. */
    currency?: string | null;
    /** "% OFF" badge text — populated by backend when a promotion applies. */
    promo_label?: string | null;
    /** Stock state — controls the CTA copy. */
    in_stock?: boolean;
    /** Optional "New" / first-N-days flag from backend. */
    is_new?: boolean;
}

interface ProductCardProps {
    product: ProductCardProduct;
    /** Compact layout for narrow grids (4-col desktop). Default true. */
    compact?: boolean;
    /** Optional onWishlistToggle handler — fires Inertia POST. */
    onWishlistToggle?: (productId: number) => void;
    wishlistActive?: boolean;
}

function Rating({ value, count }: { value: number; count: number }) {
    const filled = Math.round(value);
    return (
        <div className="flex items-center gap-1 text-xs text-slate-600">
            <div className="flex" aria-label={`Rated ${value.toFixed(1)} out of 5`}>
                {[0, 1, 2, 3, 4].map((i) => (
                    <Star
                        key={i}
                        size={14}
                        className={i < filled ? 'fill-gold-400 text-gold-400' : 'text-slate-300'}
                        aria-hidden="true"
                    />
                ))}
            </div>
            <span className="tabular-nums">({count})</span>
        </div>
    );
}

export function ProductCard({
    product,
    onWishlistToggle,
    wishlistActive = false,
}: ProductCardProps) {
    const t = useT();
    const href = `/products/${product.slug}`;
    const showRating =
        product.rating !== null &&
        product.rating !== undefined &&
        product.review_count !== null &&
        product.review_count !== undefined &&
        product.review_count > 0;
    const showOriginal =
        product.original_price &&
        product.final_price &&
        product.original_price !== product.final_price;

    return (
        <article
            className={
                'group bg-white border border-slate-200 rounded-2xl ' +
                'shadow-card hover:shadow-card-hover hover:border-slate-300 ' +
                'transition-shadow duration-200 ease-out overflow-hidden flex flex-col'
            }
            data-testid="product-card"
        >
            {/* Image — fixed aspect ratio to prevent layout shift */}
            <div className="relative aspect-square bg-slate-50">
                <Link
                    href={href}
                    className="block size-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-inset"
                    aria-label={product.title}
                >
                    {product.image_url ? (
                        <img
                            src={product.image_url}
                            alt=""
                            loading="lazy"
                            decoding="async"
                            className="size-full object-cover"
                            width={300}
                            height={300}
                        />
                    ) : (
                        <div className="size-full grid place-items-center text-slate-300">
                            <svg
                                width="48"
                                height="48"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="1.5"
                                aria-hidden="true"
                            >
                                <path d="M19 5H5C3.9 5 3 5.9 3 7v10c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V7c0-1.1-.9-2-2-2zm-1 2v8.5L14 12l-3 3-5-5V7h12z" />
                            </svg>
                        </div>
                    )}
                </Link>

                {/* Badges (top-start) */}
                <div className="absolute top-2 start-2 flex flex-col gap-1.5">
                    {product.promo_label && (
                        <Badge variant="promo" size="sm">
                            {product.promo_label}
                        </Badge>
                    )}
                    {product.is_new && (
                        <Badge variant="new" size="sm">
                            New
                        </Badge>
                    )}
                </div>

                {/* Wishlist (top-end) */}
                {onWishlistToggle && (
                    <button
                        type="button"
                        onClick={() => onWishlistToggle(product.id)}
                        className={
                            'absolute top-2 end-2 size-9 rounded-full bg-white/95 backdrop-blur ' +
                            'shadow-sm grid place-items-center ' +
                            'hover:bg-white hover:scale-105 ' +
                            'transition-all duration-150 ease-out ' +
                            'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 ' +
                            (wishlistActive ? 'text-rose-600' : 'text-slate-600 hover:text-rose-600')
                        }
                        aria-label={wishlistActive ? 'Remove from wishlist' : 'Add to wishlist'}
                        aria-pressed={wishlistActive}
                    >
                        <Heart
                            size={18}
                            className={wishlistActive ? 'fill-current' : ''}
                            aria-hidden="true"
                        />
                    </button>
                )}
            </div>

            {/* Body — v11A.3 padding upgrade.
                Per dev §2: card body ~16px mobile, ~16-20px tablet/desktop.
                Pre-v11A.3 the compact variant compressed to 12/16px which
                fell below the dev's spec — vertical product cards need
                breathing room. v11A.3 uses 16/20px always via the literal
                padding string on the body div below. The `compact` prop
                is preserved for API stability but no longer changes
                padding; if a future variant needs tighter spacing (e.g.
                horizontal cards per dev §2), add it as a separate `dense`
                flag rather than re-compressing this one. */}
            <div className="flex flex-col flex-1 p-4 sm:p-5">
                <h3 className="font-semibold text-sm sm:text-base text-slate-900 line-clamp-2 min-h-[2.5rem]">
                    <Link
                        href={href}
                        className="hover:text-brand-700 focus-visible:outline-none focus-visible:underline"
                    >
                        {product.title}
                    </Link>
                </h3>

                {product.vendor_name && (
                    <p className="mt-2 text-xs text-slate-600 truncate">
                        {product.vendor_slug ? (
                            <Link
                                href={`/vendors/${product.vendor_slug}`}
                                className="hover:text-slate-800 hover:underline"
                            >
                                {product.vendor_name}
                            </Link>
                        ) : (
                            product.vendor_name
                        )}
                    </p>
                )}

                {showRating && (
                    <div className="mt-1.5">
                        <Rating value={product.rating ?? 0} count={product.review_count ?? 0} />
                    </div>
                )}

                {/* Spacer to push price to bottom */}
                <div className="flex-1" />

                {/* Price block */}
                <div className="mt-3 flex items-baseline gap-2 flex-wrap">
                    {product.final_price && (
                        <span className="text-lg font-bold text-slate-900 tabular-nums">
                            {product.currency} {product.final_price}
                        </span>
                    )}
                    {showOriginal && (
                        <span className="text-sm text-slate-500 line-through tabular-nums">
                            {product.original_price}
                        </span>
                    )}
                </div>

                {/* Stock indicator (only show if explicitly out of stock) */}
                {product.in_stock === false && (
                    <p className="mt-1 text-xs text-rose-600 font-medium">{t('common.out_of_stock')}</p>
                )}
            </div>
        </article>
    );
}
