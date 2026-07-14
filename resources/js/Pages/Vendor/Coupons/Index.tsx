import { Head, Link } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';

interface Coupon {
    id: number;
    code: string;
    discount_type: string;
    discount_value: number;
    is_active: boolean;
    usage_limit: number | null;
    starts_at: string | null;
    ends_at: string | null;
}
interface Props { coupons: { data: Coupon[] } }

export default function VendorCouponsIndex({ coupons }: Props) {
    return (
        <VendorLayout title="My Coupons">
            <Head title="My Coupons" />
            <div className="max-w-5xl mx-auto p-6">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-2xl font-bold">My Coupons</h1>
                    <Link
                        href="/vendor/coupons/create"
                        className="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700"
                        data-testid="vendor-create-coupon"
                    >
                        + New Coupon
                    </Link>
                </div>
                {coupons.data.length === 0 ? (
                    <p className="text-slate-500">No coupons yet.</p>
                ) : (
                    <table className="w-full bg-white border rounded">
                        <thead className="bg-slate-50 text-sm">
                            <tr>
                                <th className="text-left p-3">Code</th>
                                <th className="text-left p-3">Discount</th>
                                <th className="text-left p-3">Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {coupons.data.map((c) => (
                                <tr key={c.id} className="border-t text-sm">
                                    <td className="p-3 font-mono font-semibold">{c.code}</td>
                                    <td className="p-3">
                                        {c.discount_type === 'percentage'
                                            ? `${c.discount_value}%`
                                            : `${(c.discount_value / 1000).toFixed(3)} KWD`}
                                    </td>
                                    <td className="p-3">{c.is_active ? 'Active' : 'Inactive'}</td>
                                    <td className="p-3 text-right">
                                        <Link href={`/vendor/coupons/${c.id}/edit`} className="text-indigo-600 hover:underline">Edit</Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </VendorLayout>
    );
}
