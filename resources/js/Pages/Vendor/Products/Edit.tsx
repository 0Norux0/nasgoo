import type { ReactNode } from "react";
import { useForm, Link, router } from '@inertiajs/react';
import type { FormEvent } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import ProductQualityBadge from '@/Components/VendorIntelligence/ProductQualityBadge';

interface Category { id: number; name: string; parent_id: number | null; depth: number }
interface ImageT { id: number; path: string; url: string | null; is_primary: boolean }

interface Props {
    product: {
        id: number;
        name: string;
        slug: string;
        sku: string | null;
        category_id: number | null;
        type: string;
        status: 'draft' | 'pending_review' | 'published' | 'rejected' | 'archived';
        short_description: string | null;
        description: string | null;
        // Phase 11B.1 v11B.1.1 §4 — surfaced flat from JSON columns
        name_ar: string;
        short_description_ar: string;
        description_ar: string;
        translation_status: { name: boolean; short_description: boolean; description: boolean };
        price_minor: number;
        compare_at_price_minor: number | null;
        cost_price_minor: number | null;
        currency: string;
        track_stock: boolean;
        stock: number;
        weight_grams: number | null;
        meta_title: string | null;
        meta_description: string | null;
        rejection_reason: string | null;
        images: ImageT[];
    };
    categories: Category[];
    // Phase 11B.4 v11B.4.2 Defect 9 fix — quality score from
    // vendor_product_quality_scores. Null when not yet generated.
    quality_score: {
        score: number;
        missing_fields: string[];
        breakdown: Record<string, number>;
        computed_at: string | null;
    } | null;
}

interface Form {
    name: string;
    sku: string;
    category_id: string;
    short_description: string;
    description: string;
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
    meta_title: string;
    meta_description: string;
    images: File[];
    [key: string]: string | number | boolean | File[];
}

export default function VendorProductEdit({ product, categories, quality_score }: Props) {
    const { data, setData, post, processing, errors } = useForm<Form>({
        name: product.name,
        sku: product.sku ?? '',
        category_id: product.category_id?.toString() ?? '',
        short_description: product.short_description ?? '',
        description: product.description ?? '',
        name_ar: product.name_ar ?? '',
        short_description_ar: product.short_description_ar ?? '',
        description_ar: product.description_ar ?? '',
        price_minor: product.price_minor,
        compare_at_price_minor: product.compare_at_price_minor?.toString() ?? '',
        cost_price_minor: product.cost_price_minor?.toString() ?? '',
        currency: product.currency,
        track_stock: product.track_stock,
        stock: product.stock,
        weight_grams: product.weight_grams?.toString() ?? '',
        meta_title: product.meta_title ?? '',
        meta_description: product.meta_description ?? '',
        images: [],
    });

    const editable = product.status === 'draft' || product.status === 'rejected';

    const submit = (e: FormEvent) => {
        e.preventDefault();
        post(`/vendor/products/${product.id}`, { forceFormData: true });
    };

    const submitForReview = () => {
        if (!confirm('Submit this product for admin review? You won\'t be able to edit it until a decision is made.')) return;
        router.post(`/vendor/products/${product.id}/submit`);
    };

    const inputCls = 'w-full rounded-md border-slate-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 px-3 py-2 border';

    return (
        <VendorLayout title={`Edit: ${product.name}`}>
            {/* Status banner */}
            <StatusBanner status={product.status} reason={product.rejection_reason} />

            {/* Phase 11B.4 v11B.4.2 Defect 9 fix — quality score badge */}
            <ProductQualityBadge qualityScore={quality_score} />

            <form onSubmit={submit} className="space-y-6">
                <Section title="Basics">
                    <Field label="Name (English)" error={errors.name}>
                        <input value={data.name} onChange={(e) => setData('name', e.target.value)} required disabled={!editable} className={inputCls} dir="ltr" />
                    </Field>
                    <Field label="Name (Arabic — optional)" error={errors.name_ar}>
                        <input value={data.name_ar} onChange={(e) => setData('name_ar', e.target.value)} disabled={!editable} className={inputCls} dir="rtl" lang="ar" placeholder="اسم المنتج" data-testid="vendor-product-name-ar" />
                    </Field>
                    <Field label="SKU" error={errors.sku}>
                        <input value={data.sku} onChange={(e) => setData('sku', e.target.value)} disabled={!editable} className={inputCls} />
                    </Field>
                    <Field label="Category" error={errors.category_id}>
                        <select value={data.category_id} onChange={(e) => setData('category_id', e.target.value)} disabled={!editable} className={inputCls}>
                            <option value="">— select —</option>
                            {categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {'— '.repeat(c.depth)}{c.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                </Section>

                {/* Phase 11B.1 v11B.1.1 §4 — translation completeness indicator */}
                <div className="rounded-lg border border-slate-200 bg-slate-50 p-3 text-sm" data-testid="vendor-product-translation-status">
                    <div className="font-semibold text-slate-900 mb-1">Arabic translation status</div>
                    <ul className="space-y-0.5 text-slate-700">
                        <li>{product.translation_status.name ? '✅' : '⚠️'} Arabic name: {product.translation_status.name ? 'Complete' : 'Missing'}</li>
                        <li>{product.translation_status.short_description ? '✅' : '⚠️'} Arabic short description: {product.translation_status.short_description ? 'Complete' : 'Missing'}</li>
                        <li>{product.translation_status.description ? '✅' : '⚠️'} Arabic description: {product.translation_status.description ? 'Complete' : 'Missing'}</li>
                    </ul>
                </div>

                <Section title="Description">
                    <Field label="Short description (English)" error={errors.short_description} className="md:col-span-2">
                        <textarea value={data.short_description} onChange={(e) => setData('short_description', e.target.value)} rows={2} maxLength={500} disabled={!editable} className={inputCls} dir="ltr" />
                    </Field>
                    <Field label="Short description (Arabic — optional)" error={errors.short_description_ar} className="md:col-span-2">
                        <textarea value={data.short_description_ar} onChange={(e) => setData('short_description_ar', e.target.value)} rows={2} maxLength={500} disabled={!editable} className={inputCls} dir="rtl" lang="ar" placeholder="وصف قصير بالعربية" data-testid="vendor-product-short-desc-ar" />
                    </Field>
                    <Field label="Full description (English)" error={errors.description} className="md:col-span-2">
                        <textarea value={data.description} onChange={(e) => setData('description', e.target.value)} rows={6} disabled={!editable} className={inputCls} dir="ltr" />
                    </Field>
                    <Field label="Full description (Arabic — optional)" error={errors.description_ar} className="md:col-span-2">
                        <textarea value={data.description_ar} onChange={(e) => setData('description_ar', e.target.value)} rows={6} disabled={!editable} className={inputCls} dir="rtl" lang="ar" placeholder="وصف كامل بالعربية" data-testid="vendor-product-desc-ar" />
                    </Field>
                </Section>

                <Section title="Pricing">
                    <Field label="Price (minor units)" error={errors.price_minor} help="1000 = 1.000 KWD">
                        <input type="number" min={0} value={data.price_minor} onChange={(e) => setData('price_minor', Number(e.target.value))} disabled={!editable} className={inputCls} />
                    </Field>
                    <Field label="Compare-at price" error={errors.compare_at_price_minor}>
                        <input type="number" min={0} value={data.compare_at_price_minor} onChange={(e) => setData('compare_at_price_minor', e.target.value)} disabled={!editable} className={inputCls} />
                    </Field>
                    <Field label="Cost price (private)" error={errors.cost_price_minor}>
                        <input type="number" min={0} value={data.cost_price_minor} onChange={(e) => setData('cost_price_minor', e.target.value)} disabled={!editable} className={inputCls} />
                    </Field>
                    <Field label="Currency">
                        <input value={data.currency} onChange={(e) => setData('currency', e.target.value.toUpperCase())} maxLength={3} disabled={!editable} className={inputCls} />
                    </Field>
                </Section>

                <Section title="Inventory">
                    <Field label="Track stock?">
                        <label className="flex items-center gap-2">
                            <input type="checkbox" checked={data.track_stock} onChange={(e) => setData('track_stock', e.target.checked)} disabled={!editable} className="rounded border-slate-300" />
                            <span className="text-sm text-slate-700">Reduce stock when sold</span>
                        </label>
                    </Field>
                    <Field label="Stock" error={errors.stock}>
                        <input type="number" min={0} value={data.stock} onChange={(e) => setData('stock', Number(e.target.value))} disabled={!editable || !data.track_stock} className={inputCls} />
                    </Field>
                </Section>

                <Section title={`Images (${product.images.length} on file)`}>
                    {product.images.length > 0 && (
                        <div className="md:col-span-2 grid grid-cols-4 sm:grid-cols-6 gap-2 mb-3">
                            {product.images.map((img) => (
                                <div key={img.id} className={`aspect-square rounded border overflow-hidden flex items-center justify-center relative ${img.is_primary ? 'border-indigo-500 ring-1 ring-indigo-200' : 'border-slate-200'}`}>
                                    {img.url ? (
                                        <img src={img.url} alt="" className="w-full h-full object-cover" />
                                    ) : (
                                        <span className="text-[10px] text-slate-400">🛍️</span>
                                    )}
                                    {img.is_primary && (
                                        <span className="absolute top-0.5 left-0.5 bg-indigo-600 text-white text-[9px] px-1 rounded">★</span>
                                    )}
                                </div>
                            ))}
                        </div>
                    )}
                    <Field label="Add more images" error={errors.images} className="md:col-span-2">
                        <input
                            type="file"
                            multiple
                            accept=".jpg,.jpeg,.png,.webp"
                            disabled={!editable}
                            onChange={(e) => setData('images', Array.from(e.target.files ?? []))}
                        />
                    </Field>
                </Section>

                <div className="flex justify-between items-center">
                    <Link href="/vendor/products" className="text-sm text-slate-600 hover:underline">
                        ← Back to products
                    </Link>
                    <div className="flex gap-3">
                        {editable && (
                            <>
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="rounded-md bg-slate-200 text-slate-700 px-5 py-2 hover:bg-slate-300"
                                >
                                    {processing ? 'Saving…' : 'Save changes'}
                                </button>
                                <button
                                    type="button"
                                    onClick={submitForReview}
                                    className="rounded-md bg-indigo-600 text-white px-5 py-2 hover:bg-indigo-700"
                                >
                                    Submit for review
                                </button>
                            </>
                        )}
                    </div>
                </div>
            </form>
        </VendorLayout>
    );
}

function StatusBanner({ status, reason }: { status: string; reason: string | null }) {
    const config: Record<string, { color: string; title: string; body: string }> = {
        draft: { color: 'bg-slate-100 border-slate-200 text-slate-700', title: 'Draft', body: 'Edit freely. Submit for review when ready.' },
        pending_review: { color: 'bg-amber-50 border-amber-200 text-amber-800', title: 'Pending review', body: 'Awaiting admin decision. Editing is locked.' },
        published: { color: 'bg-emerald-50 border-emerald-200 text-emerald-800', title: 'Published', body: 'Live on the storefront. Some fields can only be changed via the admin.' },
        rejected: { color: 'bg-rose-50 border-rose-200 text-rose-800', title: 'Rejected', body: reason ?? 'Update the issues and re-submit.' },
        archived: { color: 'bg-slate-100 border-slate-200 text-slate-600', title: 'Archived', body: 'Removed from the storefront.' },
    };
    const c = config[status] ?? config.draft;
    return (
        <div className={`border rounded-xl px-4 py-3 mb-6 ${c.color}`}>
            <div className="font-semibold">{c.title}</div>
            <div className="text-sm mt-1">{c.body}</div>
        </div>
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
