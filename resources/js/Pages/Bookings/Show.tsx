import { Link, useForm, usePage } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import type { SharedProps } from '@/types/inertia';

interface BookingDetail {
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
    rejection_reason: string | null;
    service: { id: number; name: string; slug: string; description: string | null } | null;
    provider: { id: number; name: string; specialization: string | null } | null;
    vendor: { name: string; slug: string } | null;
    order: { number: string; payment_status: string; total: string } | null;
    can_cancel: boolean;
}

interface BookingsShowPageProps extends SharedProps {
    booking: BookingDetail;
}

export default function BookingsShow() {
    const { props } = usePage<BookingsShowPageProps>();
    const { booking } = props;

    const [showReschedule, setShowReschedule] = useState(false);
    const cancelForm = useForm({ reason: '' });
    const rescheduleForm = useForm({ date: '', time: '', customer_notes: booking.customer_notes ?? '' });

    // Phase 8 v8.4 — typed access to backend validation errors that don't map
    // to a useForm() field. The reschedule endpoint can return a server-level
    // 'status' error (eg. ValidationException(['status' => 'Cannot reschedule
    // a terminal booking'])) which isn't in the form's data shape. useForm's
    // .errors is typed as Partial<Record<keyof TForm, string>>, so accessing
    // .errors.status would fail strict-mode TS2339. The cast widens it to a
    // string-keyed record without weakening to `any` — the value type is
    // still `string | undefined` so all downstream usages stay type-safe.
    const rescheduleErrors = rescheduleForm.errors as Record<string, string | undefined>;

    const onCancel = () => {
        if (!confirm('Cancel this booking?')) return;
        cancelForm.post(`/bookings/${booking.id}/cancel`);
    };

    const onReschedule = (e: FormEvent) => {
        e.preventDefault();
        rescheduleForm.post(`/bookings/${booking.id}/reschedule`,
            { onSuccess: () => setShowReschedule(false) });
    };

    return (
        <StorefrontLayout>
            <div className="max-w-3xl mx-auto p-6">
                <Link href="/bookings" className="text-sm text-blue-600 hover:underline">&larr; My bookings</Link>

                <div className="mt-4 border rounded-lg p-6">
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
                            <div className="font-medium">{booking.date}</div>
                        </div>
                        <div>
                            <div className="text-gray-500">Time</div>
                            <div className="font-medium">{booking.time} ({booking.duration_min} min)</div>
                        </div>
                        <div>
                            <div className="text-gray-500">Provider</div>
                            <div className="font-medium">{booking.provider?.name ?? '—'}</div>
                        </div>
                        <div>
                            <div className="text-gray-500">Location</div>
                            <div className="font-medium">{booking.location_mode.replace('_', ' ')}</div>
                        </div>
                        <div>
                            <div className="text-gray-500">Vendor</div>
                            <div className="font-medium">{booking.vendor?.name ?? '—'}</div>
                        </div>
                        <div>
                            <div className="text-gray-500">Price</div>
                            <div className="font-medium">{booking.price} {booking.currency}</div>
                        </div>
                    </div>

                    {booking.customer_notes && (
                        <div className="mt-4">
                            <div className="text-gray-500 text-sm">Your notes</div>
                            <p className="whitespace-pre-line">{booking.customer_notes}</p>
                        </div>
                    )}

                    {booking.rejection_reason && (
                        <div className="mt-4 p-3 bg-red-50 border border-red-200 rounded">
                            <div className="text-red-800 font-medium">Reason:</div>
                            <p className="text-red-700 text-sm">{booking.rejection_reason}</p>
                        </div>
                    )}

                    {booking.service_address && (
                        <div className="mt-4 p-3 bg-gray-50 rounded text-sm">
                            <div className="font-medium mb-1">Service address</div>
                            <div>{Object.values(booking.service_address).filter(v => v).join(', ')}</div>
                        </div>
                    )}

                    {booking.order && (
                        <div className="mt-4 p-3 bg-gray-50 rounded text-sm">
                            <div className="font-medium">Linked order: <Link href={`/orders/${booking.order.number}`} className="text-blue-600 hover:underline">{booking.order.number}</Link></div>
                            <div className="text-gray-600">Payment: {booking.order.payment_status} · Total {booking.order.total}</div>
                        </div>
                    )}

                    {booking.can_cancel && (
                        <div className="mt-6 pt-4 border-t flex gap-3">
                            <button onClick={() => setShowReschedule(!showReschedule)} type="button"
                                    className="px-4 py-2 bg-amber-500 text-white rounded hover:bg-amber-600"
                                    data-testid="customer-reschedule-button">
                                {showReschedule ? 'Cancel reschedule' : 'Reschedule'}
                            </button>
                            <button onClick={onCancel} disabled={cancelForm.processing}
                                    className="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 disabled:opacity-50"
                                    data-testid="customer-cancel-button">
                                {cancelForm.processing ? 'Cancelling…' : 'Cancel booking'}
                            </button>
                        </div>
                    )}

                    {showReschedule && (
                        <form onSubmit={onReschedule} className="mt-4 border-t pt-4 space-y-3" data-testid="reschedule-form">
                            <h3 className="font-semibold">Reschedule to a new slot</h3>
                            <p className="text-sm text-slate-600">Pick a new date and time. Availability is checked when you submit.</p>
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <label className="block text-sm font-medium mb-1">New date</label>
                                    <input type="date" value={rescheduleForm.data.date}
                                           min={new Date().toISOString().slice(0, 10)}
                                           onChange={e => rescheduleForm.setData('date', e.target.value)}
                                           className="border rounded w-full px-3 py-2" required />
                                </div>
                                <div>
                                    <label className="block text-sm font-medium mb-1">New time</label>
                                    <input type="time" value={rescheduleForm.data.time}
                                           onChange={e => rescheduleForm.setData('time', e.target.value)}
                                           className="border rounded w-full px-3 py-2" required />
                                </div>
                            </div>
                            <div>
                                <label className="block text-sm font-medium mb-1">Notes (optional)</label>
                                <textarea value={rescheduleForm.data.customer_notes}
                                          onChange={e => rescheduleForm.setData('customer_notes', e.target.value)}
                                          className="border rounded w-full px-3 py-2" rows={2} />
                            </div>
                            <button type="submit" disabled={rescheduleForm.processing}
                                    className="px-4 py-2 bg-amber-500 text-white rounded hover:bg-amber-600 disabled:opacity-50">
                                {rescheduleForm.processing ? 'Rescheduling…' : 'Submit reschedule'}
                            </button>
                            {rescheduleForm.errors.time && <p className="text-red-600 text-sm">{rescheduleForm.errors.time}</p>}
                            {rescheduleForm.errors.date && <p className="text-red-600 text-sm">{rescheduleForm.errors.date}</p>}
                            {rescheduleErrors.status && <p className="text-red-600 text-sm">{rescheduleErrors.status}</p>}
                        </form>
                    )}
                </div>
            </div>
        </StorefrontLayout>
    );
}
