import { Link, usePage } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';
import type { SharedProps } from '@/types/inertia';

interface PageProduct {
    id: number;
    title: string;
    platform: string | null;
    platform_slug: string | null;
    cost: string;
    cost_minor: number;
    currency: string;
    stock_status: string;
    stock_qty: number | null;
    import_status: string;
    imported_at: string | null;
    product: { id: number; name: string; status: string } | null;
    images_count: number;
}

// Phase 6 v7.3 — must extend SharedProps to satisfy Inertia v2's
// `usePage<T extends PageProps>` constraint.
type SupplierProductsPageProps = SharedProps & {
    products: { data: PageProduct[]; links: { url: string | null; label: string; active: boolean }[] };
    platforms: { id: number; name: string; slug: string; integration_type: string }[];
};

const STATUS_COLORS: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800',
    mapped: 'bg-blue-100 text-blue-800',
    published: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-rose-100 text-rose-800',
    discontinued: 'bg-slate-200 text-slate-700',
};

export default function Index() {
    const { props } = usePage<SupplierProductsPageProps>();
    const { products, platforms, flash } = props;

    return (
        <VendorLayout title="Supplier Products">
            <div className="max-w-6xl mx-auto px-4 py-6">
                {flash?.success && <div className="mb-4 p-3 bg-emerald-50 border border-emerald-200 text-emerald-800 rounded text-sm">{flash.success}</div>}
                {flash?.error && <div className="mb-4 p-3 bg-rose-50 border border-rose-200 text-rose-800 rounded text-sm">{flash.error}</div>}

                <div className="flex items-center justify-between mb-4 flex-wrap gap-2">
                    <p className="text-sm text-slate-500">
                        Imported supplier products awaiting mapping or already mapped to your marketplace catalogue.
                    </p>
                    <div className="flex gap-2">
                        <Link href="/vendor/supplier-products/manual"
                            className="bg-slate-100 hover:bg-slate-200 text-slate-800 text-sm px-3 py-1.5 rounded">
                            + Manual entry
                        </Link>
                        <Link href="/vendor/supplier-products/csv"
                            className="bg-indigo-600 hover:bg-indigo-700 text-white text-sm px-3 py-1.5 rounded">
                            Import CSV
                        </Link>
                    </div>
                </div>

                {platforms.length === 0 ? (
                    <div className="bg-amber-50 border border-amber-200 rounded p-4 text-sm text-amber-800">
                        No supplier platforms are active yet. Ask the admin to enable platforms first.
                    </div>
                ) : null}

                <div className="bg-white border border-slate-200 rounded-lg overflow-hidden">
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 text-slate-700 text-xs uppercase">
                            <tr>
                                <th className="text-left px-3 py-2">Title</th>
                                <th className="text-left px-3 py-2">Platform</th>
                                <th className="text-left px-3 py-2">Cost</th>
                                <th className="text-left px-3 py-2">Stock</th>
                                <th className="text-left px-3 py-2">Status</th>
                                <th className="text-left px-3 py-2">Imported</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {products.data.length === 0 && (
                                <tr><td colSpan={7} className="text-center text-slate-500 py-6">
                                    No supplier products yet. Import some manually or via CSV.
                                </td></tr>
                            )}
                            {products.data.map((p) => (
                                <tr key={p.id} className="border-t border-slate-100">
                                    <td className="px-3 py-2 text-slate-900">
                                        <div>{p.title}</div>
                                        {p.images_count > 0 && <div className="text-xs text-slate-400">{p.images_count} image{p.images_count === 1 ? '' : 's'}</div>}
                                    </td>
                                    <td className="px-3 py-2 text-slate-600">{p.platform ?? '—'}</td>
                                    <td className="px-3 py-2 text-slate-700">{p.cost}</td>
                                    <td className="px-3 py-2 text-slate-700">{p.stock_qty ?? '—'} <span className="text-xs text-slate-400">({p.stock_status})</span></td>
                                    <td className="px-3 py-2">
                                        <span className={`px-2 py-0.5 rounded text-xs ${STATUS_COLORS[p.import_status] ?? 'bg-slate-100 text-slate-700'}`}>
                                            {p.import_status}
                                        </span>
                                    </td>
                                    <td className="px-3 py-2 text-xs text-slate-500">{p.imported_at}</td>
                                    <td className="px-3 py-2 text-right">
                                        {p.import_status === 'pending' && (
                                            <Link href={`/vendor/supplier-products/${p.id}/map`}
                                                className="text-indigo-600 hover:underline text-xs">
                                                Map →
                                            </Link>
                                        )}
                                        {p.product && (
                                            <span className="text-xs text-slate-500">
                                                product #{p.product.id} <span className="px-1.5 py-0.5 bg-slate-100 rounded">{p.product.status}</span>
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {products.links.length > 3 && (
                    <div className="mt-3 flex gap-1 text-sm">
                        {products.links.map((l, i) => (
                            <Link key={i} href={l.url ?? '#'} preserveScroll
                                className={`px-2 py-1 rounded ${l.active ? 'bg-indigo-600 text-white' : l.url ? 'text-slate-600 hover:bg-slate-100' : 'text-slate-300'}`}
                                dangerouslySetInnerHTML={{ __html: l.label }} />
                        ))}
                    </div>
                )}
            </div>
        </VendorLayout>
    );
}
