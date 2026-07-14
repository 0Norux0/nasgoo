import { Head, useForm } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';

interface Promotion {
    id: number;
    title: string;
    description: string | null;
    promotion_type: string;
    discount_type: string;
    discount_value: number;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
    min_order_minor: number | null;
    max_discount_minor: number | null;
    usage_limit: number | null;
    per_customer_limit: number | null;
    currency: string;
}
interface Props {
    promotion: Promotion | null;
    types: string[];
    discountTypes: string[];
}

export default function VendorPromotionEdit({ promotion, types, discountTypes }: Props) {
    const editing = promotion !== null;
    const form = useForm({
        title: promotion?.title ?? '',
        description: promotion?.description ?? '',
        promotion_type: promotion?.promotion_type ?? types[0],
        discount_type: promotion?.discount_type ?? discountTypes[0],
        discount_value: promotion?.discount_value ?? 10,
        starts_at: promotion?.starts_at ?? '',
        ends_at: promotion?.ends_at ?? '',
        is_active: promotion?.is_active ?? true,
        min_order_minor: promotion?.min_order_minor ?? null,
        max_discount_minor: promotion?.max_discount_minor ?? null,
        usage_limit: promotion?.usage_limit ?? null,
        per_customer_limit: promotion?.per_customer_limit ?? null,
        currency: promotion?.currency ?? 'KWD',
    });

    const submit = (e: { preventDefault: () => void }) => {
        e.preventDefault();
        if (editing) form.patch(`/vendor/promotions/${promotion!.id}`);
        else form.post('/vendor/promotions');
    };

    return (
        <VendorLayout title={editing ? "Edit Promotion" : "New Promotion"}>
            <Head title={editing ? 'Edit Promotion' : 'New Promotion'} />
            <div className="max-w-2xl mx-auto p-6">
                <h1 className="text-2xl font-bold mb-6">{editing ? 'Edit Promotion' : 'New Promotion'}</h1>
                {!editing && (
                    <p className="bg-yellow-50 text-yellow-800 text-sm p-3 rounded mb-4">
                        Vendor-created promotions require admin approval before going live.
                    </p>
                )}
                <form onSubmit={submit} className="space-y-4 bg-white border rounded p-6">
                    <div>
                        <label className="block text-sm font-medium mb-1">Title</label>
                        <input
                            type="text"
                            className="w-full border rounded p-2"
                            value={form.data.title}
                            onChange={(e) => form.setData('title', e.target.value)}
                        />
                        {form.errors.title && <p className="text-red-600 text-sm">{form.errors.title}</p>}
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Description</label>
                        <textarea
                            rows={3}
                            className="w-full border rounded p-2"
                            value={form.data.description ?? ''}
                            onChange={(e) => form.setData('description', e.target.value)}
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium mb-1">Type</label>
                            <select
                                className="w-full border rounded p-2"
                                value={form.data.promotion_type}
                                onChange={(e) => form.setData('promotion_type', e.target.value)}
                            >
                                {types.map((t) => <option key={t} value={t}>{t}</option>)}
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Discount type</label>
                            <select
                                className="w-full border rounded p-2"
                                value={form.data.discount_type}
                                onChange={(e) => form.setData('discount_type', e.target.value)}
                            >
                                {discountTypes.map((t) => <option key={t} value={t}>{t}</option>)}
                            </select>
                        </div>
                    </div>
                    <div>
                        <label className="block text-sm font-medium mb-1">Discount value</label>
                        <input
                            type="number"
                            className="w-full border rounded p-2"
                            value={form.data.discount_value}
                            onChange={(e) => form.setData('discount_value', parseInt(e.target.value || '0', 10))}
                        />
                        <p className="text-xs text-slate-500 mt-1">
                            For percentage: 0-100. For fixed: minor units (e.g. 5000 = 5 KWD).
                        </p>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium mb-1">Starts at</label>
                            <input
                                type="datetime-local"
                                className="w-full border rounded p-2"
                                value={form.data.starts_at ?? ''}
                                onChange={(e) => form.setData('starts_at', e.target.value)}
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Ends at</label>
                            <input
                                type="datetime-local"
                                className="w-full border rounded p-2"
                                value={form.data.ends_at ?? ''}
                                onChange={(e) => form.setData('ends_at', e.target.value)}
                            />
                        </div>
                    </div>
                    <label className="inline-flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={form.data.is_active}
                            onChange={(e) => form.setData('is_active', e.target.checked)}
                        />
                        Active
                    </label>
                    <div>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700"
                        >
                            {form.processing ? 'Saving...' : 'Save'}
                        </button>
                    </div>
                </form>
            </div>
        </VendorLayout>
    );
}
