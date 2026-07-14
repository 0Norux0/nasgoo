import { Link, router } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';

interface ProductRow {
    id: number;
    slug: string;
    name: string;
    sku: string | null;
    type: string;
    status: 'draft' | 'pending_review' | 'published' | 'rejected' | 'archived';
    price: string;
    stock: number;
    category: string | null;
    thumb: string | null;
    created_at: string | null;
}

interface Props {
    vendor: { id: number; business_name: string };
    products: {
        data: ProductRow[];
        links: { url: string | null; label: string; active: boolean }[];
        total: number;
    };
    limits: { max_products: number | null; current_count: number };
}

export default function VendorProductsIndex({ products, limits }: Props) {
    const atLimit = limits.max_products !== null && limits.current_count >= limits.max_products;

    const submitForReview = (id: number) => {
        if (!confirm('Submit this product for admin review?')) return;
        router.post(`/vendor/products/${id}/submit`, {}, { preserveScroll: true });
    };

    const remove = (id: number) => {
        if (!confirm('Delete this product? This cannot be undone.')) return;
        router.delete(`/vendor/products/${id}`);
    };

    return (
        <VendorLayout title="Products">
            <div className="flex items-center justify-between mb-6">
                <div>
                    <p className="text-sm text-slate-500">
                        {limits.current_count} of {limits.max_products ?? '∞'} products used
                    </p>
                </div>
                {atLimit ? (
                    <button
                        disabled
                        className="rounded-md bg-slate-200 text-slate-500 px-4 py-2 cursor-not-allowed"
                        title="Upgrade your package to add more products"
                    >
                        Limit reached
                    </button>
                ) : (
                    <Link
                        href="/vendor/products/create"
                        className="rounded-md bg-indigo-600 text-white px-4 py-2 hover:bg-indigo-700"
                    >
                        + New product
                    </Link>
                )}
            </div>

            {products.data.length === 0 ? (
                <div className="bg-white border border-slate-200 border-dashed rounded-xl p-12 text-center text-slate-500">
                    No products yet. Click <strong>New product</strong> to create your first one.
                </div>
            ) : (
                <div className="bg-white border border-slate-200 rounded-xl overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-xs uppercase text-slate-500">
                            <tr>
                                <th className="text-left py-2 px-3">Product</th>
                                <th className="text-left py-2 px-3">Category</th>
                                <th className="text-left py-2 px-3">Status</th>
                                <th className="text-right py-2 px-3">Price</th>
                                <th className="text-right py-2 px-3">Stock</th>
                                <th className="text-right py-2 px-3">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {products.data.map((p) => (
                                <tr key={p.id} className="border-t border-slate-100">
                                    <td className="py-3 px-3">
                                        <div className="flex items-center gap-3">
                                            <div className="w-10 h-10 rounded bg-slate-100 flex items-center justify-center text-slate-400">
                                                {p.thumb ? '🛍️' : '·'}
                                            </div>
                                            <div>
                                                <div className="font-medium text-slate-900">{p.name}</div>
                                                <div className="text-xs text-slate-500">
                                                    {p.sku ?? 'no SKU'} · {p.type}
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                    <td className="py-3 px-3 text-slate-600">{p.category ?? '—'}</td>
                                    <td className="py-3 px-3"><StatusBadge status={p.status} /></td>
                                    <td className="py-3 px-3 text-right text-slate-900">{p.price}</td>
                                    <td className="py-3 px-3 text-right text-slate-600">{p.stock}</td>
                                    <td className="py-3 px-3 text-right space-x-2 whitespace-nowrap">
                                        {(p.status === 'draft' || p.status === 'rejected') && (
                                            <button
                                                onClick={() => submitForReview(p.id)}
                                                className="text-indigo-600 hover:underline text-xs"
                                            >
                                                Submit
                                            </button>
                                        )}
                                        <Link
                                            href={`/vendor/products/${p.id}/edit`}
                                            className="text-slate-600 hover:underline text-xs"
                                        >
                                            Edit
                                        </Link>
                                        {/* Phase 7 — quick access to customization field builder for custom products */}
                                        {p.type === 'custom' && (
                                            <Link
                                                href={`/vendor/products/${p.id}/customization-fields`}
                                                className="text-indigo-600 hover:underline text-xs"
                                            >
                                                Customize
                                            </Link>
                                        )}
                                        {p.status === 'draft' && (
                                            <button
                                                onClick={() => remove(p.id)}
                                                className="text-rose-600 hover:underline text-xs"
                                            >
                                                Delete
                                            </button>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {products.links.length > 3 && (
                <nav className="mt-6 flex flex-wrap gap-1 justify-center">
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
        </VendorLayout>
    );
}

function StatusBadge({ status }: { status: string }) {
    const map: Record<string, string> = {
        draft:          'bg-slate-100 text-slate-700',
        pending_review: 'bg-amber-100 text-amber-800',
        published:      'bg-emerald-100 text-emerald-800',
        rejected:       'bg-rose-100 text-rose-800',
        archived:       'bg-slate-200 text-slate-600',
    };
    return (
        <span className={`px-2 py-0.5 rounded text-xs font-medium ${map[status] ?? 'bg-slate-100'}`}>
            {status.replace('_', ' ')}
        </span>
    );
}
