import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import Container from '@/Components/Layout/Container';
import type { SharedProps } from '@/types/inertia';
import CustomizationForm, { type CustomizationFieldDef } from '@/Components/Customization/CustomizationForm';
import SimilarProducts from '@/Components/recommendations/SimilarProducts';
import FrequentlyBoughtTogether from '@/Components/recommendations/FrequentlyBoughtTogether';
import CustomersAlsoBought from '@/Components/recommendations/CustomersAlsoBought';

interface Image { id: number; path: string; url: string | null; alt: string | null; is_primary: boolean }
interface Variant { id: number; name: string | null; sku: string | null; price: string; stock: number; attributes: Record<string, string> | null }
interface AttrGroup { name: string; values: string[] }

interface ReviewItem {
    id: number;
    rating: number;
    title: string | null;
    body: string | null;
    author_name: string | null;
    is_verified_purchase: boolean;
    created_at: string | null;
}

interface Props {
    product: {
        id: number;
        slug: string;
        name: string;
        short_description: string | null;
        description: string | null;
        type: string;
        price: string;
        price_minor: number;
        currency: string;
        compare_at: string | null;
        // Phase 10 v10.8 — promotion-aware pricing
        final_price?: string;
        discount?: string;
        promotion?: {
            id: number;
            title: string;
            type: string;
            discount_type: string;
            discount_value: string;
            badge: string;
        } | null;
        stock: number | null;
        track_stock: boolean;
        rating_avg: number;
        rating_count: number;
        featured: boolean;
        images: Image[];
        variants: Variant[];
        attributes: AttrGroup[];
    };
    vendor: { business_name: string; slug: string; rating_avg: number; rating_count: number } | null;
    category: { slug: string; name: string } | null;
    is_wishlisted: boolean;
    reviews: {
        rating_avg: number;
        rating_count: number;
        items: ReviewItem[];
        reviewable_purchases: Array<{ order_item_id: number; order_id: number }>;
    };
    // Phase 7 — populated only when product.type === 'custom'
    customization_fields: CustomizationFieldDef[];
    // Phase 11B.2 §29 — recommendation sections (each is independently
    // feature-flagged; disabled sections arrive as {enabled:false, items:[]})
    recommendations: {
        similar: import('@/Components/recommendations/types').RecommendationSection;
        frequently_bought: import('@/Components/recommendations/types').RecommendationSection;
        customers_also_bought: import('@/Components/recommendations/types').RecommendationSection;
    };
}

export default function CatalogShow({ product, vendor, category, reviews, is_wishlisted, customization_fields, recommendations }: Props) {
    const { auth } = usePage<SharedProps>().props;
    const primary = product.images.find((i) => i.is_primary) ?? product.images[0];
    const [active, setActive] = useState<Image | undefined>(primary);

    const hasVariants = product.variants.length > 0;
    const [selectedVariantId, setSelectedVariantId] = useState<number | null>(
        hasVariants ? product.variants[0].id : null
    );
    const [quantity, setQuantity] = useState<number>(1);
    const [adding, setAdding] = useState(false);

    const selectedVariant = product.variants.find((v) => v.id === selectedVariantId);
    const effectivePrice = selectedVariant?.price ?? product.price;
    const effectiveStock =
        selectedVariant?.stock ??
        (product.track_stock ? (product.stock ?? 0) : null);

    const addToCart = () => {
        if (!auth.user) {
            router.visit(`/login?redirect=/products/${product.slug}`);
            return;
        }
        setAdding(true);
        router.post(
            '/cart/items',
            {
                product_id: product.id,
                variant_id: selectedVariantId ?? undefined,
                quantity,
            },
            {
                preserveScroll: true,
                onFinish: () => setAdding(false),
            }
        );
    };

    const inStock = effectiveStock === null || effectiveStock > 0;

    return (
        <StorefrontLayout title={product.name}>
            <Head title={product.name} />
            <Container className="py-4 sm:py-6 lg:py-8">

            {/* Breadcrumb */}
            <nav className="text-sm text-slate-500 mb-6">
                <Link href="/products" className="hover:text-slate-700">Products</Link>
                {category && (
                    <>
                        <span className="mx-2">/</span>
                        <Link href={`/products?category=${category.slug}`} className="hover:text-slate-700">
                            {category.name}
                        </Link>
                    </>
                )}
                <span className="mx-2">/</span>
                <span className="text-slate-700">{product.name}</span>
            </nav>

            <div className="grid grid-cols-1 lg:grid-cols-2 gap-10">
                {/* Gallery */}
                <div>
                    <div className="aspect-square bg-slate-100 border border-slate-200 rounded-xl flex items-center justify-center mb-3 overflow-hidden">
                        {active?.url ? (
                            <img
                                src={active.url}
                                alt={active.alt ?? product.name}
                                className="w-full h-full object-cover"
                                onError={(e) => {
                                    const el = e.currentTarget;
                                    el.style.display = 'none';
                                    el.nextElementSibling?.classList.remove('hidden');
                                }}
                            />
                        ) : null}
                        <span className={`text-6xl text-slate-300 ${active?.url ? 'hidden' : ''}`}>🛍️</span>
                    </div>
                    {product.images.length > 1 && (
                        <div className="grid grid-cols-6 gap-2">
                            {product.images.map((img) => (
                                <button
                                    key={img.id}
                                    type="button"
                                    onClick={() => setActive(img)}
                                    className={`aspect-square rounded border overflow-hidden flex items-center justify-center ${
                                        active?.id === img.id ? 'border-indigo-500 ring-1 ring-indigo-300' : 'border-slate-200'
                                    }`}
                                >
                                    {img.url ? (
                                        <img src={img.url} alt={img.alt ?? ''} className="w-full h-full object-cover" />
                                    ) : (
                                        <span className="text-[10px] text-slate-400">🛍️</span>
                                    )}
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Info */}
                <div>
                    {product.featured && (
                        <span className="inline-block px-2 py-0.5 bg-amber-100 text-amber-800 text-xs rounded-full mb-2">
                            Featured
                        </span>
                    )}
                    <h1 className="text-3xl font-bold text-slate-900 mb-2">{product.name}</h1>

                    {vendor && (
                        <div className="text-sm text-slate-500 mb-4">
                            Sold by{' '}
                            <Link href={`/vendors/${vendor.slug}`} className="text-indigo-600 hover:underline">
                                {vendor.business_name}
                            </Link>
                            <span className="mx-2">·</span>
                            ⭐ {vendor.rating_avg.toFixed(1)} ({vendor.rating_count})
                        </div>
                    )}

                    {/* Price */}
                    <div className="flex items-baseline gap-3 mb-4 flex-wrap">
                        {product.promotion && product.final_price ? (
                            <>
                                <span className="text-3xl font-bold text-rose-700" data-testid="product-final-price">
                                    {product.final_price} {product.currency}
                                </span>
                                <span className="text-lg text-slate-400 line-through" data-testid="product-original-price">
                                    {product.price} {product.currency}
                                </span>
                                <span
                                    className="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-1 text-xs font-semibold text-rose-700"
                                    title={product.promotion.title}
                                    data-testid="product-promo-badge"
                                >
                                    {product.promotion.badge}
                                </span>
                            </>
                        ) : (
                            <>
                                <span className="text-3xl font-bold text-slate-900">
                                    {product.price} {product.currency}
                                </span>
                                {product.compare_at && (
                                    <span className="text-lg text-slate-400 line-through">
                                        {product.compare_at} {product.currency}
                                    </span>
                                )}
                            </>
                        )}
                    </div>

                    {/* Stock */}
                    <div className="mb-6">
                        {!product.track_stock ? (
                            <span className="inline-flex items-center gap-1.5 text-sm text-emerald-700">
                                <span className="w-2 h-2 rounded-full bg-emerald-500" /> Available
                            </span>
                        ) : inStock ? (
                            <span className="inline-flex items-center gap-1.5 text-sm text-emerald-700">
                                <span className="w-2 h-2 rounded-full bg-emerald-500" /> In stock ({product.stock})
                            </span>
                        ) : (
                            <span className="inline-flex items-center gap-1.5 text-sm text-rose-700">
                                <span className="w-2 h-2 rounded-full bg-rose-500" /> Out of stock
                            </span>
                        )}
                    </div>

                    {product.short_description && (
                        <p className="text-slate-600 mb-6">{product.short_description}</p>
                    )}

                    {/* Phase 4 — variant selection + add to cart */}
                    {product.variants.length > 0 && (
                        <div className="bg-slate-50 border border-slate-200 rounded-xl p-4 mb-4">
                            <div className="text-xs font-medium text-slate-500 uppercase tracking-wide mb-2">
                                Choose variant
                            </div>
                            <div className="grid grid-cols-1 sm:grid-cols-2 gap-2">
                                {product.variants.map((v) => (
                                    <label
                                        key={v.id}
                                        className={`block border rounded-lg p-2 cursor-pointer text-sm ${
                                            selectedVariantId === v.id
                                                ? 'border-indigo-500 bg-indigo-50'
                                                : 'border-slate-200 bg-white hover:border-slate-300'
                                        } ${v.stock <= 0 ? 'opacity-50 cursor-not-allowed' : ''}`}
                                    >
                                        <input
                                            type="radio"
                                            name="variant"
                                            value={v.id}
                                            checked={selectedVariantId === v.id}
                                            onChange={() => setSelectedVariantId(v.id)}
                                            disabled={v.stock <= 0}
                                            className="mr-2"
                                        />
                                        <span className="font-medium">
                                            {v.name ?? Object.entries(v.attributes ?? {}).map(([k, val]) => `${k}: ${val}`).join(', ')}
                                        </span>
                                        <div className="text-xs text-slate-500 ml-5">
                                            {v.price} {product.currency} · {v.stock > 0 ? `${v.stock} in stock` : 'out of stock'}
                                        </div>
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="flex items-stretch gap-3 mb-4">
                        <div className="inline-flex items-center border border-slate-300 rounded-md overflow-hidden">
                            <button
                                onClick={() => setQuantity((q) => Math.max(1, q - 1))}
                                disabled={quantity <= 1}
                                className="px-3 py-2 text-slate-600 hover:bg-slate-100 disabled:opacity-50"
                            >
                                −
                            </button>
                            <span className="px-4 py-2 text-sm font-medium text-slate-900 min-w-[48px] text-center">
                                {quantity}
                            </span>
                            <button
                                onClick={() => setQuantity((q) => q + 1)}
                                className="px-3 py-2 text-slate-600 hover:bg-slate-100"
                            >
                                +
                            </button>
                        </div>
                        {/* Phase 7 — for customizable products the customization form below carries
                            its own quantity + submit; hide the regular Add to cart to avoid confusion. */}
                        {product.type !== 'custom' && (
                            <button
                                onClick={addToCart}
                                disabled={!inStock || adding}
                                className="flex-1 rounded-md bg-indigo-600 text-white px-5 py-2 font-medium hover:bg-indigo-700 disabled:opacity-60 disabled:cursor-not-allowed"
                            >
                                {!inStock
                                    ? 'Out of stock'
                                    : adding
                                      ? 'Adding…'
                                      : auth.user
                                        ? `Add to cart — ${effectivePrice} ${product.currency}`
                                        : 'Sign in to buy'}
                            </button>
                        )}

                        {/* Phase 5 — wishlist toggle */}
                        <WishlistButton productId={product.id} initialWishlisted={is_wishlisted} isAuthenticated={!!auth.user} />
                    </div>

                    {/* Phase 7 — customizable products render their customization form here.
                        Guest users see a sign-in prompt because the upload endpoint requires auth. */}
                    {product.type === 'custom' && customization_fields.length > 0 && (
                        <div className="mb-6">
                            {auth.user ? (
                                <CustomizationForm
                                    productId={product.id}
                                    variantId={selectedVariantId}
                                    fields={customization_fields}
                                    currency={product.currency}
                                />
                            ) : (
                                <div className="p-4 bg-amber-50 border border-amber-200 rounded text-sm text-amber-800">
                                    <Link href="/login" className="font-medium text-amber-900 underline">Sign in</Link> to customize and order this product.
                                </div>
                            )}
                        </div>
                    )}
                    {product.type === 'custom' && customization_fields.length === 0 && (
                        <div className="mb-6 p-3 bg-slate-50 border border-slate-200 rounded text-sm text-slate-700">
                            This product is marked as customizable but the vendor has not yet defined any customization fields.
                        </div>
                    )}

                    {/* Attributes */}
                    {product.attributes.length > 0 && (
                        <div className="border-t border-slate-200 pt-6 mb-6">
                            <h3 className="text-sm font-semibold text-slate-900 mb-3">Specifications</h3>
                            <dl className="grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm">
                                {product.attributes.map((a) => (
                                    <div key={a.name} className="flex justify-between border-b border-slate-100 pb-1">
                                        <dt className="text-slate-500">{a.name}</dt>
                                        <dd className="text-slate-900 font-medium">{a.values.join(', ')}</dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    )}
                </div>
            </div>

            {/* Description */}
            {product.description && (
                <div className="mt-12 bg-white border border-slate-200 rounded-xl p-6 prose prose-slate max-w-none">
                    <h2 className="text-lg font-semibold text-slate-900 mb-3">Description</h2>
                    <p className="whitespace-pre-line text-slate-700">{product.description}</p>
                </div>
            )}

            {/* Phase 11B.2 §29 — recommendation sections in dev's recommended
                order (FBT → Similar → Also Bought → Reviews). Each component
                is self-rendering: it returns null when its section is
                disabled or empty so the page doesn't show empty sections. */}
            <FrequentlyBoughtTogether
                sourceProductId={product.id}
                sourceProductName={product.name}
                sourceProductImage={primary?.url ?? null}
                sourceProductPrice={product.price}
                sourceProductPriceMinor={product.price_minor}
                sourceCurrency={product.currency}
                section={recommendations.frequently_bought}
            />
            <SimilarProducts
                sourceProductId={product.id}
                section={recommendations.similar}
            />
            <CustomersAlsoBought
                sourceProductId={product.id}
                section={recommendations.customers_also_bought}
            />

            {/* Phase 5 — Reviews */}
            <ReviewsBlock productSlug={product.slug} reviews={reviews} isAuthenticated={!!auth?.user} />
            </Container>
        </StorefrontLayout>
    );
}

function ReviewsBlock({ productSlug, reviews, isAuthenticated }: {
    productSlug: string;
    reviews: Props['reviews'];
    isAuthenticated: boolean;
}) {
    const [sort, setSort] = useState<'newest' | 'highest' | 'lowest'>('newest');
    const sorted = [...reviews.items].sort((a, b) => {
        if (sort === 'highest') return b.rating - a.rating;
        if (sort === 'lowest')  return a.rating - b.rating;
        return (b.created_at ?? '').localeCompare(a.created_at ?? '');
    });

    const canReview = isAuthenticated && reviews.reviewable_purchases.length > 0;

    return (
        <div className="mt-8 bg-white border border-slate-200 rounded-xl p-6">
            <div className="flex flex-wrap items-baseline justify-between gap-3 mb-4">
                <h2 className="text-lg font-semibold text-slate-900">
                    Reviews{reviews.rating_count > 0 && (
                        <span className="ml-3 text-sm font-normal text-slate-600">
                            {reviews.rating_avg.toFixed(1)} ★ ({reviews.rating_count})
                        </span>
                    )}
                </h2>
                <label className="text-sm text-slate-600">
                    Sort by:{' '}
                    <select value={sort} onChange={(e) => setSort(e.target.value as 'newest' | 'highest' | 'lowest')}
                        className="border border-slate-300 rounded px-2 py-1 text-sm">
                        <option value="newest">Newest</option>
                        <option value="highest">Highest rating</option>
                        <option value="lowest">Lowest rating</option>
                    </select>
                </label>
            </div>

            {canReview && <WriteReviewForm productSlug={productSlug} orderItemId={reviews.reviewable_purchases[0].order_item_id} />}

            {sorted.length === 0 ? (
                <p className="text-sm text-slate-500 py-6 text-center">No reviews yet. Be the first to share your experience.</p>
            ) : (
                <div className="space-y-3 mt-4">
                    {sorted.map((r) => (
                        <div key={r.id} className="border border-slate-200 rounded-lg p-3">
                            <div className="flex items-center gap-2 mb-1">
                                <span className="text-amber-500 text-sm">{'★'.repeat(r.rating)}{'☆'.repeat(5 - r.rating)}</span>
                                {r.title && <span className="font-medium text-slate-900">{r.title}</span>}
                                {r.is_verified_purchase && (
                                    <span className="text-xs text-emerald-700 bg-emerald-50 border border-emerald-200 rounded px-1.5">
                                        ✓ Verified purchase
                                    </span>
                                )}
                            </div>
                            {r.body && <p className="text-sm text-slate-700 whitespace-pre-line">{r.body}</p>}
                            <p className="text-xs text-slate-400 mt-1">{r.author_name ?? 'Anonymous'} · {r.created_at}</p>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function WishlistButton({ productId, initialWishlisted, isAuthenticated }: {
    productId: number;
    initialWishlisted: boolean;
    isAuthenticated: boolean;
}) {
    const [wishlisted, setWishlisted] = useState(initialWishlisted);
    const [pending, setPending] = useState(false);

    function toggle() {
        if (!isAuthenticated) {
            router.visit('/login');
            return;
        }
        setPending(true);
        if (wishlisted) {
            router.delete(`/wishlist/items/${productId}`, {
                preserveScroll: true,
                onFinish: () => setPending(false),
                onSuccess: () => setWishlisted(false),
            });
        } else {
            router.post('/wishlist/items', { product_id: productId }, {
                preserveScroll: true,
                onFinish: () => setPending(false),
                onSuccess: () => setWishlisted(true),
            });
        }
    }

    return (
        <button
            type="button"
            onClick={toggle}
            disabled={pending}
            title={wishlisted ? 'Remove from wishlist' : 'Add to wishlist'}
            className={`rounded-md border px-3 py-2 text-lg ${wishlisted ? 'bg-rose-50 border-rose-200 text-rose-600' : 'border-slate-300 text-slate-400 hover:text-rose-600 hover:border-rose-200'}`}
            aria-label={wishlisted ? 'Remove from wishlist' : 'Add to wishlist'}
        >
            {wishlisted ? '♥' : '♡'}
        </button>
    );
}

function WriteReviewForm({ productSlug, orderItemId }: { productSlug: string; orderItemId: number }) {
    const [open, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm({
        order_item_id: orderItemId,
        rating: 5,
        title: '',
        body: '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        post(`/products/${productSlug}/reviews`, {
            preserveScroll: true,
            onSuccess: () => { reset(); setOpen(false); },
        });
    }

    if (!open) {
        return (
            <button onClick={() => setOpen(true)}
                className="mb-4 bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-3 py-1.5 rounded">
                Write a review
            </button>
        );
    }

    return (
        <form onSubmit={submit} className="mb-4 bg-slate-50 border border-slate-200 rounded p-3 space-y-2">
            <div>
                <label className="text-xs text-slate-600 block mb-1">Your rating</label>
                <select value={data.rating} onChange={(e) => setData('rating', Number(e.target.value))}
                    className="border border-slate-300 rounded px-2 py-1 text-sm">
                    {[5, 4, 3, 2, 1].map((n) => <option key={n} value={n}>{n} ★</option>)}
                </select>
                {errors.rating && <p className="text-xs text-rose-600 mt-1">{errors.rating}</p>}
            </div>
            <div>
                <label className="text-xs text-slate-600 block mb-1">Title (optional)</label>
                <input value={data.title} onChange={(e) => setData('title', e.target.value)} maxLength={200}
                    className="border border-slate-300 rounded px-2 py-1 text-sm w-full" />
                {errors.title && <p className="text-xs text-rose-600 mt-1">{errors.title}</p>}
            </div>
            <div>
                <label className="text-xs text-slate-600 block mb-1">Your review (optional)</label>
                <textarea value={data.body} onChange={(e) => setData('body', e.target.value)} rows={3} maxLength={5000}
                    className="border border-slate-300 rounded px-2 py-1 text-sm w-full" />
                {errors.body && <p className="text-xs text-rose-600 mt-1">{errors.body}</p>}
            </div>
            <div className="flex gap-2">
                <button type="submit" disabled={processing}
                    className="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm px-4 py-1.5 rounded">
                    {processing ? 'Submitting…' : 'Submit review'}
                </button>
                <button type="button" onClick={() => setOpen(false)} className="text-slate-600 text-sm px-3 py-1.5">
                    Cancel
                </button>
            </div>
            <p className="text-xs text-slate-500">Your review will appear after admin approval.</p>
        </form>
    );
}
