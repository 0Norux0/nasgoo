import { Head, useForm } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';

interface Coupon {
    id: number;
    code: string;
    description: string | null;
    discount_type: string;
    discount_value: number;
    min_order_minor: number | null;
    max_discount_minor: number | null;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
    usage_limit: number | null;
    per_user_limit: number;
    currency: string;
}
interface Props { coupon: Coupon | null }

export default function VendorCouponEdit({ coupon }: Props) {
    const editing = coupon !== null;
    const form = useForm({
        code: coupon?.code ?? '',
        description: coupon?.description ?? '',
        discount_type: coupon?.discount_type ?? 'percentage',
        discount_value: coupon?.discount_value ?? 10,
        min_order_minor: coupon?.min_order_minor ?? null,
        max_discount_minor: coupon?.max_discount_minor ?? null,
        starts_at: coupon?.starts_at ?? '',
        ends_at: coupon?.ends_at ?? '',
        is_active: coupon?.is_active ?? true,
        usage_limit: coupon?.usage_limit ?? null,
        per_user_limit: coupon?.per_user_limit ?? 1,
        currency: coupon?.currency ?? 'KWD',
    });

    const submit = (e: { preventDefault: () => void }) => {
        e.preventDefault();
        if (editing) form.patch(`/vendor/coupons/${coupon!.id}`);
        else form.post('/vendor/coupons');
    };

    return (
        <VendorLayout title={editing ? "Edit Coupon" : "New Coupon"}>
            <Head title={editing ? 'Edit Coupon' : 'New Coupon'} />
            <div className="max-w-2xl mx-auto p-6">
                <h1 className="text-2xl font-bold mb-6">{editing ? 'Edit Coupon' : 'New Coupon'}</h1>
                <form onSubmit={submit} className="space-y-4 bg-white border rounded p-6">
                    <div>
                        <label className="block text-sm font-medium mb-1">Code</label>
                        <input
                            type="text"
                            className="w-full border rounded p-2 font-mono uppercase"
                            value={form.data.code}
                            onChange={(e) => form.setData('code', e.target.value.toUpperCase())}
                            data-testid="coupon-code"
                        />
                        {form.errors.code && <p className="text-red-600 text-sm">{form.errors.code}</p>}
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium mb-1">Discount type</label>
                            <select
                                className="w-full border rounded p-2"
                                value={form.data.discount_type}
                                onChange={(e) => form.setData('discount_type', e.target.value)}
                            >
                                <option value="percentage">Percentage</option>
                                <option value="fixed_amount">Fixed amount (minor units)</option>
                            </select>
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Value</label>
                            <input
                                type="number"
                                className="w-full border rounded p-2"
                                value={form.data.discount_value}
                                onChange={(e) => form.setData('discount_value', parseInt(e.target.value || '0', 10))}
                            />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium mb-1">Min order (minor)</label>
                            <input
                                type="number"
                                className="w-full border rounded p-2"
                                value={form.data.min_order_minor ?? ''}
                                onChange={(e) => form.setData('min_order_minor', e.target.value ? parseInt(e.target.value, 10) : null)}
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Max discount (minor)</label>
                            <input
                                type="number"
                                className="w-full border rounded p-2"
                                value={form.data.max_discount_minor ?? ''}
                                onChange={(e) => form.setData('max_discount_minor', e.target.value ? parseInt(e.target.value, 10) : null)}
                            />
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-4">
                        <div>
                            <label className="block text-sm font-medium mb-1">Usage limit (total)</label>
                            <input
                                type="number"
                                className="w-full border rounded p-2"
                                value={form.data.usage_limit ?? ''}
                                onChange={(e) => form.setData('usage_limit', e.target.value ? parseInt(e.target.value, 10) : null)}
                            />
                        </div>
                        <div>
                            <label className="block text-sm font-medium mb-1">Per user limit</label>
                            <input
                                type="number"
                                className="w-full border rounded p-2"
                                value={form.data.per_user_limit}
                                onChange={(e) => form.setData('per_user_limit', parseInt(e.target.value || '1', 10))}
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
