import { Head, Link } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';

interface Promotion {
    id: number;
    title: string;
    promotion_type: string;
    discount_type: string;
    discount_value: number;
    is_active: boolean;
    approval_status: string;
    starts_at: string | null;
    ends_at: string | null;
}
interface Props { promotions: { data: Promotion[] } }

export default function VendorPromotionsIndex({ promotions }: Props) {
    return (
        <VendorLayout title="My Promotions">
            <Head title="My Promotions" />
            <div className="max-w-5xl mx-auto p-6">
                <div className="flex justify-between items-center mb-6">
                    <h1 className="text-2xl font-bold">My Promotions</h1>
                    <Link
                        href="/vendor/promotions/create"
                        className="bg-indigo-600 text-white px-4 py-2 rounded hover:bg-indigo-700"
                        data-testid="vendor-create-promotion"
                    >
                        + New Promotion
                    </Link>
                </div>
                {promotions.data.length === 0 ? (
                    <p className="text-slate-500">No promotions yet. Create your first one.</p>
                ) : (
                    <table className="w-full bg-white border rounded">
                        <thead className="bg-slate-50 text-sm">
                            <tr>
                                <th className="text-left p-3">Title</th>
                                <th className="text-left p-3">Type</th>
                                <th className="text-left p-3">Discount</th>
                                <th className="text-left p-3">Status</th>
                                <th className="text-left p-3">Approval</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            {promotions.data.map((p) => (
                                <tr key={p.id} className="border-t text-sm">
                                    <td className="p-3 font-medium">{p.title}</td>
                                    <td className="p-3">{p.promotion_type}</td>
                                    <td className="p-3">
                                        {p.discount_type === 'percentage' ? `${p.discount_value}%` :
                                         p.discount_type === 'free_shipping' ? 'Free shipping' :
                                         `${(p.discount_value / 1000).toFixed(3)} KWD`}
                                    </td>
                                    <td className="p-3">{p.is_active ? 'Active' : 'Inactive'}</td>
                                    <td className="p-3">
                                        <span className={`text-xs px-2 py-1 rounded ${
                                            p.approval_status === 'approved' ? 'bg-green-100 text-green-800' :
                                            p.approval_status === 'rejected' ? 'bg-red-100 text-red-800' :
                                            'bg-yellow-100 text-yellow-800'
                                        }`}>{p.approval_status}</span>
                                    </td>
                                    <td className="p-3 text-right">
                                        <Link href={`/vendor/promotions/${p.id}/edit`} className="text-indigo-600 hover:underline">Edit</Link>
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
