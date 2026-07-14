import { Link, usePage } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';
import type { SharedProps } from '@/types/inertia';

interface VendorService {
    id: number;
    name: string;
    slug: string;
    price: string;
    currency: string;
    service_type: string | null;
    duration_min: number | null;
    location_mode: string | null;
    is_active: boolean;
    providers: string[];
    created_at: string | null;
}

interface VendorServicesIndexPageProps extends SharedProps {
    services: { data: VendorService[]; links: Array<{ url: string | null; label: string; active: boolean }> };
}

export default function VendorServicesIndex() {
    const { props } = usePage<VendorServicesIndexPageProps>();
    const { services } = props;

    return (
        <VendorLayout title="My Services">
            <div className="flex justify-between items-center mb-6">
                <h1 className="text-2xl font-bold">My Services</h1>
                <Link href="/vendor/services/create"
                      className="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
                    + New service
                </Link>
            </div>

            {services.data.length === 0 ? (
                <div className="border rounded p-8 text-center text-gray-500">
                    No services yet. <Link href="/vendor/services/create" className="text-blue-600 hover:underline">Create your first one.</Link>
                </div>
            ) : (
                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr className="border-b text-left text-gray-600">
                            <th className="py-2">Service</th>
                            <th className="py-2">Type</th>
                            <th className="py-2">Duration</th>
                            <th className="py-2">Providers</th>
                            <th className="py-2 text-right">Price</th>
                            <th className="py-2">Active</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {services.data.map(s => (
                            <tr key={s.id} className="border-b hover:bg-gray-50">
                                <td className="py-3 font-medium">{s.name}</td>
                                <td className="py-3">{s.service_type?.replace('_', ' ')}</td>
                                <td className="py-3">{s.duration_min} min</td>
                                <td className="py-3">{s.providers.join(', ') || <span className="text-gray-400">none assigned</span>}</td>
                                <td className="py-3 text-right">{s.price} {s.currency}</td>
                                <td className="py-3">
                                    <span className={`px-2 py-1 rounded text-xs ${s.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-600'}`}>
                                        {s.is_active ? 'Yes' : 'No'}
                                    </span>
                                </td>
                                <td className="py-3 text-right">
                                    <Link href={`/vendor/services/${s.id}/edit`} className="text-blue-600 hover:underline">Edit</Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </VendorLayout>
    );
}
