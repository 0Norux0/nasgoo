import { Link, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import VendorLayout from '@/Layouts/VendorLayout';
import type { SharedProps } from '@/types/inertia';

interface VendorBookingDetail {
    id: number;
    number: string;
    status: string;
    date: string | null;
    time: string;
    duration_min: number;
    location_mode: string;
    service_address: Record<string, string> | null;
    price: string;
    currency: string;
    customer_notes: string | null;
    vendor_notes: string | null;
    rejection_reason: string | null;
    service: { id: number; name: string } | null;
    provider: { id: number; name: string } | null;
    customer: { name: string; email: string; phone: string | null } | null;
    order: { number: string; payment_status: string } | null;
    confirmed_at: string | null;
    accepted_at: string | null;
    completed_at: string | null;
    cancelled_at: string | null;
}

interface VendorBookingsShowPageProps extends SharedProps {
    booking: VendorBookingDetail;
}

export default function VendorBookingsShow() {
    const { props } = usePage<VendorBookingsShowPageProps>();
    const { booking } = props;
    const [showRejectForm, setShowRejectForm] = useState(false);
    const [showRescheduleForm, setShowRescheduleForm] = useState(false);

    const acceptForm = useForm({ vendor_note: '' });
    const rejectForm = useForm({ reason: '' });
    const completeForm = useForm({});
    const rescheduleForm = useForm({ date: '', time: '', vendor_note: '' });

    const isTerminal = ['rejected', 'cancelled', 'completed', 'no_show', 'refunded'].includes(booking.status);
    const canAccept = ['pending', 'confirmed'].includes(booking.status);
    const canComplete = ['accepted', 'confirmed'].includes(booking.status);
    const canReschedule = !isTerminal;

    const submitAccept = (e: FormEvent) => {
        e.preventDefault();
        acceptForm.post(`/vendor/bookings/${booking.id}/accept`);
    };
    const submitReject = (e: FormEvent) => {
        e.preventDefault();
        rejectForm.post(`/vendor/bookings/${booking.id}/reject`,
            { onSuccess: () => setShowRejectForm(false) });
    };
    const submitComplete = (e: FormEvent) => {
        e.preventDefault();
        completeForm.post(`/vendor/bookings/${booking.id}/complete`);
    };
    const submitReschedule = (e: FormEvent) => {
        e.preventDefault();
        rescheduleForm.post(`/vendor/bookings/${booking.id}/reschedule`,
            { onSuccess: () => setShowRescheduleForm(false) });
    };

    return (
        <VendorLayout title={`Booking ${booking.number}`}>
            <Link href="/vendor/bookings" className="text-sm text-blue-600 hover:underline">&larr; Bookings</Link>

            <div className="mt-4 grid grid-cols-1 md:grid-cols-3 gap-6">
                <div className="md:col-span-2 border rounded-lg p-6">
                    <div className="flex justify-between items-start">
                        <div>
                            <h1 className="text-2xl font-bold">Booking <span className="font-mono">{booking.number}</span></h1>
                            <p className="text-gray-600 mt-1">{booking.service?.name}</p>
                        </div>
                        <span className="px-3 py-1 rounded bg-blue-100 text-blue-800 text-sm">{booking.status}</span>
                    </div>

                    <div className="mt-6 grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <div className="text-gray-500">Date</div>
                            <div className="font-medium">{booking.date} {booking.time}</div>
                        </div>
                        <div>
                            <div className="text-gray-500">Duration</div>
                            <div className="font-medium">{booking.duration_min} min</div>
                        </div>
                        <div>
                            <div className="text-gray-500">Provider</div>
                            <div className="font-medium">{booking.provider?.name ?? 'unassigned'}</div>
                        </div>
                        <div>
                            <div className="text-gray-500">Location</div>
                            <div className="font-medium">{booking.location_mode.replace('_', ' ')}</div>
                        </div>
                        <div>
                            <div className="text-gray-500">Price</div>
                            <div className="font-medium">{booking.price} {booking.currency}</div>
                        </div>
                        <div>
                            <div className="text-gray-500">Customer</div>
                            <div className="font-medium">{booking.customer?.name}</div>
                            <div className="text-xs text-gray-600">{booking.customer?.email}</div>
                            {booking.customer?.phone && <div className="text-xs text-gray-600">{booking.customer.phone}</div>}
                        </div>
                    </div>

                    {booking.service_address && (
                        <div className="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded text-sm">
                            <div className="font-medium mb-1">Service address</div>
                            <div>{Object.entries(booking.service_address).filter(([, v]) => v).map(([k, v]) => `${k}: ${v}`).join(' · ')}</div>
                        </div>
                    )}

                    {booking.customer_notes && (
                        <div className="mt-4">
                            <div className="text-gray-500 text-sm">Customer notes</div>
                            <p className="whitespace-pre-line">{booking.customer_notes}</p>
                        </div>
                    )}

                    {booking.rejection_reason && (
                        <div className="mt-4 p-3 bg-red-50 border border-red-200 rounded">
                            <div className="text-red-800 font-medium">Reason</div>
                            <p className="text-red-700 text-sm">{booking.rejection_reason}</p>
                        </div>
                    )}

                    {booking.order && (
                        <div className="mt-4 p-3 bg-gray-50 rounded text-sm">
                            <span className="font-medium">Order #{booking.order.number}</span>
                            <span className="text-gray-600 ml-2">Payment: {booking.order.payment_status}</span>
                        </div>
                    )}
                </div>

                <aside className="border rounded-lg p-4 bg-white shadow-sm space-y-3">
                    <h2 className="font-semibold">Actions</h2>

                    {isTerminal ? (
                        <p className="text-sm text-gray-500">Booking is terminal (status: {booking.status}). No further actions available.</p>
                    ) : (
                        <>
                            {canAccept && (
                                <form onSubmit={submitAccept}>
                                    <textarea value={acceptForm.data.vendor_note}
                                              onChange={e => acceptForm.setData('vendor_note', e.target.value)}
                                              placeholder="Optional note for customer…"
                                              className="border rounded w-full px-2 py-1 mb-2 text-sm" rows={2} />
                                    <button type="submit" disabled={acceptForm.processing}
                                            className="w-full bg-green-600 text-white py-2 rounded hover:bg-green-700 disabled:opacity-50"
                                            data-testid="vendor-accept-button">
                                        {acceptForm.processing ? 'Accepting…' : 'Accept booking'}
                                    </button>
                                </form>
                            )}

                            {canComplete && (
                                <form onSubmit={submitComplete}>
                                    <button type="submit" disabled={completeForm.processing}
                                            className="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700 disabled:opacity-50"
                                            data-testid="vendor-complete-button">
                                        {completeForm.processing ? 'Marking…' : 'Mark completed'}
                                    </button>
                                </form>
                            )}

                            {!showRejectForm ? (
                                <button onClick={() => setShowRejectForm(true)}
                                        className="w-full bg-red-600 text-white py-2 rounded hover:bg-red-700"
                                        data-testid="vendor-reject-button">
                                    Reject booking
                                </button>
                            ) : (
                                <form onSubmit={submitReject}>
                                    <textarea value={rejectForm.data.reason}
                                              onChange={e => rejectForm.setData('reason', e.target.value)}
                                              placeholder="Reason for rejection (required)…"
                                              className="border rounded w-full px-2 py-1 mb-2 text-sm" rows={2} required />
                                    <button type="submit" disabled={rejectForm.processing}
                                            className="w-full bg-red-600 text-white py-2 rounded hover:bg-red-700 disabled:opacity-50">
                                        {rejectForm.processing ? 'Rejecting…' : 'Confirm rejection'}
                                    </button>
                                </form>
                            )}

                            {/* Phase 8 v8.1 — vendor-initiated reschedule */}
                            {canReschedule && !showRescheduleForm && (
                                <button onClick={() => setShowRescheduleForm(true)}
                                        className="w-full bg-amber-500 text-white py-2 rounded hover:bg-amber-600"
                                        data-testid="vendor-reschedule-button">
                                    Reschedule booking
                                </button>
                            )}
                            {showRescheduleForm && (
                                <form onSubmit={submitReschedule} data-testid="vendor-reschedule-form">
                                    <input type="date" value={rescheduleForm.data.date}
                                           min={new Date().toISOString().slice(0, 10)}
                                           onChange={e => rescheduleForm.setData('date', e.target.value)}
                                           className="border rounded w-full px-2 py-1 mb-2 text-sm" required />
                                    <input type="time" value={rescheduleForm.data.time}
                                           onChange={e => rescheduleForm.setData('time', e.target.value)}
                                           className="border rounded w-full px-2 py-1 mb-2 text-sm" required />
                                    <textarea value={rescheduleForm.data.vendor_note}
                                              onChange={e => rescheduleForm.setData('vendor_note', e.target.value)}
                                              placeholder="Note for customer (optional)…"
                                              className="border rounded w-full px-2 py-1 mb-2 text-sm" rows={2} />
                                    <button type="submit" disabled={rescheduleForm.processing}
                                            className="w-full bg-amber-500 text-white py-2 rounded hover:bg-amber-600 disabled:opacity-50">
                                        {rescheduleForm.processing ? 'Rescheduling…' : 'Submit reschedule'}
                                    </button>
                                    {rescheduleForm.errors.time && <p className="text-red-600 text-xs mt-1">{rescheduleForm.errors.time}</p>}
                                </form>
                            )}
                        </>
                    )}

                    {(booking.confirmed_at || booking.accepted_at || booking.completed_at || booking.cancelled_at) && (
                        <div className="pt-3 border-t mt-3 text-xs text-gray-500 space-y-1">
                            {booking.accepted_at && <div>Accepted: {booking.accepted_at}</div>}
                            {booking.confirmed_at && <div>Confirmed: {booking.confirmed_at}</div>}
                            {booking.completed_at && <div>Completed: {booking.completed_at}</div>}
                            {booking.cancelled_at && <div>Cancelled: {booking.cancelled_at}</div>}
                        </div>
                    )}
                </aside>
            </div>
        </VendorLayout>
    );
}
