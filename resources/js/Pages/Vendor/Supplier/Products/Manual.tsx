import { useForm } from '@inertiajs/react';
import { useState, type FormEvent, type ReactNode } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';

interface Platform {
    id: number;
    name: string;
    slug: string;
    default_currency: string;
    default_delivery_days: number | null;
}

// Phase 6 v7.3 — local function-arg type; renamed from "PageProps" to avoid
// shadowing the augmented global type from @inertiajs/core.
interface ManualPageProps { platforms: Platform[]; }

export default function Manual({ platforms }: ManualPageProps) {
    const [imageUrl, setImageUrl] = useState('');
    const { data, setData, post, processing, errors } = useForm<{
        supplier_platform_id: number | '';
        title: string;
        description: string;
        supplier_sku: string;
        source_url: string;
        supplier_cost_major: string;
        supplier_currency: string;
        supplier_stock_status: 'in_stock' | 'out_of_stock' | 'unknown';
        supplier_stock_qty: string;
        estimated_delivery_days: string;
        images: string[];
    }>({
        supplier_platform_id: platforms[0]?.id ?? '',
        title: '',
        description: '',
        supplier_sku: '',
        source_url: '',
        supplier_cost_major: '',
        supplier_currency: platforms[0]?.default_currency ?? 'USD',
        supplier_stock_status: 'unknown',
        supplier_stock_qty: '',
        estimated_delivery_days: platforms[0]?.default_delivery_days?.toString() ?? '',
        images: [],
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/vendor/supplier-products/manual');
    };

    const addImage = () => {
        const trimmed = imageUrl.trim();
        if (!trimmed) return;
        setData('images', [...data.images, trimmed]);
        setImageUrl('');
    };

    const Field = ({ label, error, children }: { label: string; error?: string; children: ReactNode }) => (
        <label className="block">
            <span className="text-sm text-slate-700 block mb-1">{label}</span>
            {children}
            {error && <span className="text-xs text-rose-600 block mt-0.5">{error}</span>}
        </label>
    );

    return (
        <VendorLayout title="Manual supplier product entry">
            <div className="max-w-3xl mx-auto px-4 py-6">
                <p className="text-sm text-slate-500 mb-4">
                    Add a single supplier product manually. After saving, you'll map it to a marketplace listing and submit for admin approval.
                </p>

                <form onSubmit={submit} className="bg-white border border-slate-200 rounded-lg p-4 space-y-3">
                    <Field label="Supplier platform" error={errors.supplier_platform_id}>
                        <select
                            value={data.supplier_platform_id}
                            onChange={(e) => {
                                const id = Number(e.target.value);
                                const p = platforms.find((x) => x.id === id);
                                setData('supplier_platform_id', id);
                                if (p) setData('supplier_currency', p.default_currency);
                            }}
                            className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full">
                            {platforms.map((p) => <option key={p.id} value={p.id}>{p.name}</option>)}
                        </select>
                    </Field>

                    <Field label="Product title" error={errors.title}>
                        <input value={data.title} onChange={(e) => setData('title', e.target.value)}
                            className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                    </Field>

                    <Field label="Description" error={errors.description}>
                        <textarea value={data.description} onChange={(e) => setData('description', e.target.value)}
                            rows={3} className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                    </Field>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Field label="Supplier SKU" error={errors.supplier_sku}>
                            <input value={data.supplier_sku} onChange={(e) => setData('supplier_sku', e.target.value)}
                                className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                        </Field>
                        <Field label="Source URL" error={errors.source_url}>
                            <input value={data.source_url} onChange={(e) => setData('source_url', e.target.value)}
                                placeholder="https://supplier.example/p/12345"
                                className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                        </Field>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <Field label="Cost (per unit)" error={errors.supplier_cost_major}>
                            <input type="number" step="0.01" min="0"
                                value={data.supplier_cost_major}
                                onChange={(e) => setData('supplier_cost_major', e.target.value)}
                                className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                        </Field>
                        <Field label="Currency" error={errors.supplier_currency}>
                            <input value={data.supplier_currency} maxLength={3}
                                onChange={(e) => setData('supplier_currency', e.target.value.toUpperCase())}
                                className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                        </Field>
                        <Field label="Est. delivery (days)" error={errors.estimated_delivery_days}>
                            <input type="number" min="0" max="365"
                                value={data.estimated_delivery_days}
                                onChange={(e) => setData('estimated_delivery_days', e.target.value)}
                                className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                        </Field>
                    </div>

                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <Field label="Stock status" error={errors.supplier_stock_status}>
                            <select value={data.supplier_stock_status}
                                onChange={(e) => setData('supplier_stock_status', e.target.value as typeof data.supplier_stock_status)}
                                className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full">
                                <option value="unknown">Unknown</option>
                                <option value="in_stock">In stock</option>
                                <option value="out_of_stock">Out of stock</option>
                            </select>
                        </Field>
                        <Field label="Stock quantity" error={errors.supplier_stock_qty}>
                            <input type="number" min="0" value={data.supplier_stock_qty}
                                onChange={(e) => setData('supplier_stock_qty', e.target.value)}
                                className="border border-slate-300 rounded px-2 py-1.5 text-sm w-full" />
                        </Field>
                    </div>

                    <Field label={`Image URLs (${data.images.length} added)`} error={errors['images.0']}>
                        <div className="flex gap-2">
                            <input value={imageUrl} onChange={(e) => setImageUrl(e.target.value)}
                                placeholder="https://..."
                                className="border border-slate-300 rounded px-2 py-1.5 text-sm flex-1" />
                            <button type="button" onClick={addImage}
                                className="text-sm px-3 py-1.5 bg-slate-100 hover:bg-slate-200 rounded">Add</button>
                        </div>
                        {data.images.length > 0 && (
                            <ul className="mt-2 text-xs text-slate-500 space-y-0.5">
                                {data.images.map((u, i) => (
                                    <li key={i} className="flex items-center gap-2">
                                        <span className="truncate flex-1">{u}</span>
                                        <button type="button" onClick={() => setData('images', data.images.filter((_, j) => j !== i))}
                                            className="text-rose-600 hover:underline">remove</button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Field>

                    <button type="submit" disabled={processing}
                        className="bg-indigo-600 hover:bg-indigo-700 disabled:opacity-60 text-white text-sm px-4 py-1.5 rounded">
                        {processing ? 'Saving…' : 'Save supplier product'}
                    </button>
                </form>
            </div>
        </VendorLayout>
    );
}
