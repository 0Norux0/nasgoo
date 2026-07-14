import { Link, usePage } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';
import type { SharedProps } from '@/types/inertia';

interface VendorBookingRow {
    id: number;
    number: string;
    status: string;
    customer_name: string | null;
    customer_email: string | null;
    service_name: string | null;
    provider_name: string | null;
    date: string | null;
    time: string;
    duration_min: number;
    location_mode: string;
    price: string;
    currency: string;
}

interface VendorBookingsIndexPageProps extends SharedProps {
    bookings: { data: VendorBookingRow[]; links: Array<{ url: string | null; label: string; active: boolean }> };
}

function statusColor(status: string): string {
    if (['confirmed', 'accepted', 'completed'].includes(status)) return 'bg-green-100 text-green-800';
    if (['pending', 'pending_payment'].includes(status)) return 'bg-yellow-100 text-yellow-800';
    if (['rejected', 'cancelled', 'no_show'].includes(status)) return 'bg-red-100 text-red-800';
    return 'bg-gray-100 text-gray-800';
}

export default function VendorBookingsIndex() {
    const { props } = usePage<VendorBookingsIndexPageProps>();
    const { bookings } = props;

    return (
        <VendorLayout title="Bookings">
            <h1 className="text-2xl font-bold mb-6">Bookings</h1>

            {bookings.data.length === 0 ? (
                <div className="border rounded p-8 text-center text-gray-500">
                    No bookings yet.
                </div>
            ) : (
                <table className="w-full border-collapse text-sm">
                    <thead>
                        <tr className="border-b text-left text-gray-600">
                            <th className="py-2">Booking #</th>
                            <th className="py-2">Customer</th>
                            <th className="py-2">Service</th>
                            <th className="py-2">Provider</th>
                            <th className="py-2">Date / Time</th>
                            <th className="py-2">Status</th>
                            <th className="py-2 text-right">Price</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        {bookings.data.map(b => (
                            <tr key={b.id} className="border-b hover:bg-gray-50">
                                <td className="py-3 font-mono text-xs">{b.number}</td>
                                <td className="py-3">
                                    <div>{b.customer_name}</div>
                                    <div className="text-xs text-gray-500">{b.customer_email}</div>
                                </td>
                                <td className="py-3">{b.service_name}</td>
                                <td className="py-3">{b.provider_name ?? '—'}</td>
                                <td className="py-3">{b.date} {b.time}<br /><span className="text-xs text-gray-500">{b.duration_min} min · {b.location_mode.replace('_', ' ')}</span></td>
                                <td className="py-3">
                                    <span className={`px-2 py-1 rounded text-xs ${statusColor(b.status)}`}>{b.status}</span>
                                </td>
                                <td className="py-3 text-right">{b.price} {b.currency}</td>
                                <td className="py-3 text-right">
                                    <Link href={`/vendor/bookings/${b.id}`} className="text-blue-600 hover:underline">View</Link>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            )}
        </VendorLayout>
    );
}
