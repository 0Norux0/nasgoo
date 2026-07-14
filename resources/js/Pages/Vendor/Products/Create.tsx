import type { ReactNode } from "react";
import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';

interface Category { id: number; name: string; parent_id: number | null; depth: number }

interface Props {
    categories: Category[];
}

interface Form {
    name: string;
    sku: string;
    category_id: string;
    type: 'simple' | 'variable' | 'digital';
    short_description: string;
    description: string;
    // Phase 11B.1 v11B.1.1 §4 — optional Arabic translation fields
    name_ar: string;
    short_description_ar: string;
    description_ar: string;
    price_minor: number;
    compare_at_price_minor: string;
    cost_price_minor: string;
    currency: string;
    track_stock: boolean;
    stock: number;
    weight_grams: string;
    images: File[];
    [key: string]: string | number | boolean | File[];
}

export default function VendorProductCreate({ categories }: Props) {
    const { data, setData, post, processing, errors } = useForm<Form>({
        name: '',
        sku: '',
        category_id: '',
        type: 'simple',
        short_description: '',
        description: '',
        name_ar: '',
        short_description_ar: '',
        description_ar: '',
        price_minor: 0,
        compare_at_price_minor: '',
        cost_price_minor: '',
        currency: 'KWD',
        track_stock: true,
        stock: 0,
        weight_grams: '',
        images: [],
    });

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post('/vendor/products', { forceFormData: true });
    };

    const inputCls = 'w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2 border';

    return (
        <VendorLayout title="New product">
            <form onSubmit={submit} className="space-y-6">
                <Section title="Basics">
                    <Field label="Product name (English)" error={errors.name}>
                        <input value={data.name} onChange={(e) => setData('name', e.target.value)} required className={inputCls} dir="ltr" />
                    </Field>
                    <Field label="Product name (Arabic — optional)" error={errors.name_ar} help="اسم المنتج بالعربية. سيُستخدم الإنجليزي عند غيابه.">
                        <input value={data.name_ar} onChange={(e) => setData('name_ar', e.target.value)} className={inputCls} dir="rtl" lang="ar" placeholder="اسم المنتج" data-testid="vendor-product-name-ar" />
                    </Field>
                    <Field label="SKU (optional)" error={errors.sku}>
                        <input value={data.sku} onChange={(e) => setData('sku', e.target.value)} className={inputCls} />
                    </Field>
                    <Field label="Category" error={errors.category_id}>
                        <select value={data.category_id} onChange={(e) => setData('category_id', e.target.value)} className={inputCls}>
                            <option value="">— select —</option>
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {'— '.repeat(c.depth)}{c.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field label="Type" error={errors.type}>
                        <select value={data.type} onChange={(e) => setData('type', e.target.value as Form['type'])} className={inputCls}>
                            <option value="simple">Simple</option>
                            <option value="variable">Variable (with variants)</option>
                            <option value="digital">Digital</option>
                        </select>
                    </Field>
                </Section>

                <Section title="Description">
                    <Field label="Short description (English)" error={errors.short_description} className="md:col-span-2">
                        <textarea value={data.short_description} onChange={(e) => setData('short_description', e.target.value)} rows={2} maxLength={500} className={inputCls} dir="ltr" />
                    </Field>
                    <Field label="Short description (Arabic — optional)" error={errors.short_description_ar} className="md:col-span-2">
                        <textarea value={data.short_description_ar} onChange={(e) => setData('short_description_ar', e.target.value)} rows={2} maxLength={500} className={inputCls} dir="rtl" lang="ar" placeholder="وصف قصير بالعربية" data-testid="vendor-product-short-desc-ar" />
                    </Field>
                    <Field label="Full description (English)" error={errors.description} className="md:col-span-2">
                        <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={6} className={inputCls} dir="ltr" />
                    </Field>
                    <Field label="Full description (Arabic — optional)" error={errors.description_ar} className="md:col-span-2">
                        <textarea value={data.description_ar} onChange={(e) => setData('description_ar', e.target.value)} rows={6} className={inputCls} dir="rtl" lang="ar" placeholder="وصف كامل بالعربية" data-testid="vendor-product-desc-ar" />
                    </Field>
                </Section>

                <Section title="Pricing">
                    <Field label="Price (minor units)" error={errors.price_minor} help="1000 = 1.000 KWD">
                        <input type="number" min={0} value={data.price_minor} onChange={(e) => setData('price_minor', Number(e.target.value))} required className={inputCls} />
                    </Field>
                    <Field label="Compare-at price (optional)" error={errors.compare_at_price_minor} help="Shows as strikethrough">
                        <input type="number" min={0} value={data.compare_at_price_minor} onChange={(e) => setData('compare_at_price_minor', e.target.value)} className={inputCls} />
                    </Field>
                    <Field label="Cost price (optional, private)" error={errors.cost_price_minor}>
                        <input type="number" min={0} value={data.cost_price_minor} onChange={(e) => setData('cost_price_minor', e.target.value)} className={inputCls} />
                    </Field>
                    <Field label="Currency" error={errors.currency}>
                        <input value={data.currency} onChange={(e) => setData('currency', e.target.value.toUpperCase())} maxLength={3} className={inputCls} />
                    </Field>
                </Section>

                <Section title="Inventory">
                    <Field label="Track stock?">
                        <label className="flex items-center gap-2">
                            <input type="checkbox" checked={data.track_stock} onChange={(e) => setData('track_stock', e.target.checked)} className="rounded border-slate-300" />
                            <span className="text-sm text-slate-700">Reduce stock when sold</span>
                        </label>
                    </Field>
                    <Field label="Stock on hand" error={errors.stock}>
                        <input type="number" min={0} value={data.stock} onChange={(e) => setData('stock', Number(e.target.value))} className={inputCls} disabled={!data.track_stock} />
                    </Field>
                    <Field label="Weight (grams, optional)" error={errors.weight_grams}>
                        <input type="number" min={0} value={data.weight_grams} onChange={(e) => setData('weight_grams', e.target.value)} className={inputCls} />
                    </Field>
                </Section>

                <Section title="Images (max 10, 5MB each, JPG/PNG/WEBP)">
                    <Field label="Upload images" error={errors.images} className="md:col-span-2">
                        <input
                            type="file"
                            multiple
                            accept=".jpg,.jpeg,.png,.webp"
                            onChange={(e) => setData('images', Array.from(e.target.files ?? []))}
                        />
                        {data.images.length > 0 && (
                            <p className="text-xs text-slate-500 mt-1">{data.images.length} file(s) selected</p>
                        )}
                    </Field>
                </Section>

                <div className="flex justify-end gap-3">
                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-md bg-indigo-600 text-white px-5 py-2 hover:bg-indigo-700 disabled:opacity-60"
                    >
                        {processing ? 'Saving…' : 'Save as draft'}
                    </button>
                </div>
            </form>
        </VendorLayout>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="bg-white border border-slate-200 rounded-xl p-5">
            <h2 className="text-lg font-semibold text-slate-900 mb-4">{title}</h2>
            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">{children}</div>
        </div>
    );
}

function Field({ label, error, help, children, className = '' }: { label: string; error?: string; help?: string; children: ReactNode; className?: string }) {
    return (
        <div className={className}>
            <label className="block text-sm font-medium text-slate-700 mb-1">{label}</label>
            {children}
            {help && <p className="text-xs text-slate-500 mt-1">{help}</p>}
            {error && <p className="text-sm text-rose-600 mt-1">{error}</p>}
        </div>
    );
}
