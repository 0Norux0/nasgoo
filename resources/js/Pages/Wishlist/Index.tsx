import { Link, useForm } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';

interface WishProduct {
    id: number;
    slug: string;
    name: string;
    price: string;
    currency: string;
    thumb: string | null;
    status: string;
    in_stock: boolean;
    vendor: { slug: string; business_name: string } | null;
}

interface WishRow {
    id: number;
    added_at: string | null;
    product: WishProduct | null;
}

interface Props {
    wishlist: {
        data: WishRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
}

export default function WishlistIndex({ wishlist }: Props) {
    const { delete: destroy, processing } = useForm({});

    function remove(productId: number) {
        destroy(`/wishlist/items/${productId}`, { preserveScroll: true });
    }

    return (
        <StorefrontLayout>
            <div className="max-w-6xl mx-auto px-4 py-8">
                <div className="flex items-baseline justify-between mb-6">
                    <h1 className="text-2xl font-semibold text-slate-900">Your Wishlist</h1>
                    <p className="text-sm text-slate-500">{wishlist.total} item{wishlist.total === 1 ? '' : 's'}</p>
                </div>

                {wishlist.data.length === 0 ? (
                    <div className="bg-white border border-slate-200 rounded-lg p-12 text-center">
                        <p className="text-slate-500 mb-4">Your wishlist is empty.</p>
                        <Link href="/products" className="inline-block bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-4 py-2 rounded">
                            Browse products
                        </Link>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
                        {wishlist.data.map((row) => row.product && (
                            <div key={row.id} className="bg-white border border-slate-200 rounded-lg overflow-hidden flex flex-col">
                                <Link href={`/products/${row.product.slug}`} className="aspect-square bg-slate-100 flex items-center justify-center">
                                    {row.product.thumb ? (
                                        <img src={row.product.thumb} alt="" className="w-full h-full object-cover" />
                                    ) : (
                                        <span className="text-slate-400 text-3xl">🛍️</span>
                                    )}
                                </Link>
                                <div className="p-3 flex-1 flex flex-col">
                                    <Link href={`/products/${row.product.slug}`} className="font-medium text-slate-900 hover:text-indigo-700 line-clamp-2">
                                        {row.product.name}
                                    </Link>
                                    {row.product.vendor && (
                                        <Link href={`/vendors/${row.product.vendor.slug}`} className="text-xs text-slate-500 hover:text-slate-700 mt-1">
                                            {row.product.vendor.business_name}
                                        </Link>
                                    )}
                                    <div className="mt-2 font-semibold text-slate-900">
                                        {row.product.price} {row.product.currency}
                                    </div>
                                    {! row.product.in_stock && (
                                        <span className="mt-1 text-xs text-rose-600">Out of stock</span>
                                    )}
                                    <button
                                        type="button"
                                        onClick={() => remove(row.product!.id)}
                                        disabled={processing}
                                        className="mt-3 text-xs text-slate-500 hover:text-rose-600 self-start"
                                    >
                                        Remove from wishlist
                                    </button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </StorefrontLayout>
    );
}
