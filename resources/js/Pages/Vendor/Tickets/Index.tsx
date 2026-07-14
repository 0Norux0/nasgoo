import { Head, Link } from '@inertiajs/react';
import VendorLayout from '@/Layouts/VendorLayout';

interface Ticket {
    id: number;
    number: string;
    subject: string;
    status: string;
    priority: string;
    user: { id: number; name: string } | null;
}
interface Props { tickets: { data: Ticket[] } }

export default function VendorTicketsIndex({ tickets }: Props) {
    return (
        <VendorLayout title="Support Tickets">
            <Head title="Support Tickets" />
            <div className="max-w-5xl mx-auto p-6">
                <h1 className="text-2xl font-bold mb-6">Support Tickets</h1>
                {tickets.data.length === 0 ? (
                    <p className="text-slate-500">No tickets assigned to your vendor account.</p>
                ) : (
                    <div className="space-y-2">
                        {tickets.data.map((t) => (
                            <Link
                                key={t.id}
                                href={`/vendor/tickets/${t.id}`}
                                className="block border rounded p-4 bg-white hover:bg-slate-50"
                            >
                                <div className="flex justify-between items-start">
                                    <div>
                                        <p className="font-semibold">#{t.number} — {t.subject}</p>
                                        <p className="text-sm text-slate-500">From: {t.user?.name ?? 'Unknown'}</p>
                                    </div>
                                    <span className="text-xs px-2 py-1 rounded bg-yellow-100 text-yellow-800">{t.status}</span>
                                </div>
                            </Link>
                        ))}
                    </div>
                )}
            </div>
        </VendorLayout>
    );
}
