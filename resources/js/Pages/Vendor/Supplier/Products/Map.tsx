import { useForm } from '@inertiajs/react';
import { type FormEvent } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';

interface SupplierProductData {
    id: number;
    title: string;
    description: string | null;
    platform: string | null;
    cost_minor: number;
    cost: string;
    currency: string;
    stock_qty: number | null;
    eta_days: number | null;
    images: string[];
    source_url: string | null;
    import_status: string;
    mapped_product_id: number | null;
}

// Phase 6 v7.3 — local function-arg type; renamed from "PageProps" to avoid
// shadowing the augmented global type from @inertiajs/core.
interface MapPageProps {
    supplier_product: SupplierProductData;
    categories: { id: number; name: string }[];
}

export default function Map({ supplier_product, categories }: MapPageProps) {
    const sp = supplier_product;
    const suggestedPrice = (sp.cost_minor * 1.4 / 100).toFixed(2); // 40% markup default

    const { data, setData, post, processing, errors } = useForm({
        name: sp.title,
        description: sp.description ?? '',
        category_id: categories[0]?.id ?? '',
        price_major: suggestedPrice,
        currency: 'KWD',
        stock: sp.stock_qty ?? 0,
        estimated_delivery_days: sp.eta_days ?? '',
        fulfillment_mode: 'dropship_manual' as 'dropship_manual' | 'dropship_admin',
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(`/vendor/supplier-products/${sp.id}/map`);
    };

    return (
        <VendorLayout title="Map supplier product → marketplace listing">
            <div className="max-w-4xl mx-auto px-4 py-6 grid lg:grid-cols-2 gap-6">
                {/* Source preview */}
                <div className="bg-slate-50 border border-slate-200 rounded p-4">
                    <h3 className="font-medium mb-2 text-slate-700">Supplier source</h3>
                    <div className="text-sm space-y-1">
                        <div><span className="text-slate-500">Platform:</span> {sp.platform ?? '—'}</div>
                        <div><span className="text-slate-500">Title:</span> {sp.title}</div>
                        <div><span className="text-slate-500">Cost:</span> {sp.cost}</div>
                        <div><span className="text-slate-500">Stock qty:</span> {sp.stock_qty ?? '—'}</div>
                        <div><span className="text-slate-500">ETA:</span> {sp.eta_days ? `${sp.eta_days} days` : '—'}</div>
                        {sp.source_url && (
                            <div><span className="text-slate-500">URL:</span>{' '}
                                <a href={sp.source_url} target="_blank" rel="noopener noreferrer" className="text-indigo-600 hover:underline break-all break-anywhere">{sp.source_url}</a>
                            </div>
                        )}
                    </div>
                    {sp.images.length > 0 && (
                        <div className="mt-3">
                            <div className="text-xs text-slate-500 mb-1">{sp.images.length} image{sp.images.length === 1 ? '' : 's'}</div>
                            <div className="grid grid-cols-3 gap-2">
                                {sp.images.slice(0, 6).map((u, i) => (
                                    // eslint-disable-next-line @next/next/no-img-element
                                    <img key={i} src={u} alt="" className="w-full h-20 object-cover rounded border border-slate-200"
                                        onError={(e) => { (e.target as HTMLImageElement).style.display = 'none'; }} />
                                ))}
                            </div>
                        </div>
                    )}
                    {sp.description && (
                        <div className="mt-3">
                            <div className="text-xs text-slate-500 mb-1">Description</div>
                            <div className="text-xs text-slate-600 whitespace-pre-wrap">{sp.description}</div>
                        </div>
                    )}
                </div>

                {/* Marketplace listing form */}
                <form onSubmit={submit} className="bg-white border border-slate-200 rounded p-4 space-y-3">
                    <h3 className="font-medium mb-2 text-slate-700">Marketplace listing</h3>

                    <label className="block">
                        <span className="text-sm text-slate-700 block mb-1">Product name</span>
                        <input value={data.name} onChange={(e) => setData('name', e.target.value)}
                            className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                        {errors.name && <span className="text-xs text-rose-600">{errors.name}</span>}
                    </label>

                    <label className="block">
                        <span className="text-sm text-slate-700 block mb-1">Description</span>
                        <textarea value={data.description} onChange={(e) => setData('description', e.target.value)}
                            rows={4} className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                    </label>

                    {categories.length > 0 && (
                        <label className="block">
                            <span className="text-sm text-slate-700 block mb-1">Category</span>
                            <select value={data.category_id} onChange={(e) => setData('category_id', Number(e.target.value))}
                                className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full">
                                {categories.map((c) => <option key={c.id} value={c.id}>{c.name}</option>)}
                            </select>
                        </label>
                    )}

                    <div className="grid grid-cols-2 gap-2">
                        <label className="block">
                            <span className="text-sm text-slate-700 block mb-1">Selling price</span>
                            <input type="number" step="0.01" min="0"
                                value={data.price_major}
                                onChange={(e) => setData('price_major', e.target.value)}
                                className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                            <span className="text-xs text-slate-500 block">cost {sp.cost} (price must be ≥ cost)</span>
                            {errors.price_major && <span className="text-xs text-rose-600">{errors.price_major}</span>}
                        </label>
                        <label className="block">
                            <span className="text-sm text-slate-700 block mb-1">Currency</span>
                            <input value={data.currency} maxLength={3}
                                onChange={(e) => setData('currency', e.target.value.toUpperCase())}
                                className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                        </label>
                    </div>

                    <div className="grid grid-cols-2 gap-2">
                        <label className="block">
                            <span className="text-sm text-slate-700 block mb-1">Stock</span>
                            <input type="number" min="0" value={data.stock}
                                onChange={(e) => setData('stock', Number(e.target.value))}
                                className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                        </label>
                        <label className="block">
                            <span className="text-sm text-slate-700 block mb-1">Est. delivery days</span>
                            <input type="number" min="0" max="365" value={data.estimated_delivery_days}
                                onChange={(e) => setData('estimated_delivery_days', e.target.value === '' ? '' : Number(e.target.value))}
                                className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                        </label>
                    </div>

                    <label className="block">
                        <span className="text-sm text-slate-700 block mb-1">Fulfillment mode</span>
                        <select value={data.fulfillment_mode}
                            onChange={(e) => setData('fulfillment_mode', e.target.value as typeof data.fulfillment_mode)}
                            className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full">
                            <option value="dropship_manual">I (vendor) place the supplier order manually</option>
                            <option value="dropship_admin">Admin places the supplier order</option>
                        </select>
                    </label>

                    <button type="submit" disabled={processing || sp.import_status !== 'pending'}
                        className="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm px-4 py-1.5 rounded">
                        {sp.import_status !== 'pending' ? `Already ${sp.import_status}` :
                          processing ? 'Submitting…' : 'Submit for admin approval'}
                    </button>
                </form>
            </div>
        </VendorLayout>
    );
}
