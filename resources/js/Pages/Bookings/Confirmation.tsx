import { Link, usePage } from '@inertiajs/react';
import StorefrontLayout from '@/Layouts/StorefrontLayout';
import type { SharedProps } from '@/types/inertia';

interface ConfirmationBooking {
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
    service: { name: string; slug: string; description: string | null } | null;
    provider: { name: string; specialization: string | null } | null;
    vendor: { name: string; slug: string } | null;
    order: { number: string; payment_status: string } | null;
    customer_notes: string | null;
}

interface BookingsConfirmationPageProps extends SharedProps {
    booking: ConfirmationBooking;
}

/**
 * Phase 8 v8.1 — booking confirmation page.
 *
 * Shown immediately after POST /bookings succeeds. Separate from the
 * regular /bookings/{id} detail page because it has post-action UI
 * (success banner, "what happens next" copy, screenshot-friendly
 * reference card) instead of an action-oriented "manage this booking"
 * view. The customer is encouraged to head to "My Bookings" next.
 */
export default function BookingsConfirmation() {
    const { props } = usePage<BookingsConfirmationPageProps>();
    const { booking } = props;

    return (
        <StorefrontLayout>
            <div className="max-w-2xl mx-auto p-6">
                {/* Success banner */}
                <div className="rounded-lg bg-green-50 border border-green-200 p-4 mb-6 text-center">
                    <div className="text-4xl mb-2" aria-hidden>✓</div>
                    <h1 className="text-2xl font-bold text-green-800">Booking confirmed!</h1>
                    <p className="text-green-700 mt-1">Your appointment has been reserved. The vendor will review and confirm shortly.</p>
                </div>

                {/* Reference card — screenshot-friendly */}
                <div className="border-2 border-dashed border-slate-300 rounded-lg p-6 bg-white">
                    <div className="flex justify-between items-start mb-4">
                        <div>
                            <div className="text-xs uppercase tracking-wide text-slate-500">Booking reference</div>
                            <div className="font-mono text-lg font-bold">{booking.number}</div>
                        </div>
                        <span className="px-3 py-1 rounded bg-blue-100 text-blue-800 text-sm font-medium">
                            {booking.status}
                        </span>
                    </div>

                    <div className="border-t border-slate-200 pt-4">
                        <h2 className="text-xl font-semibold">{booking.service?.name}</h2>
                        {booking.vendor && (
                            <p className="text-slate-600 text-sm mt-1">
                                by <Link href={`/vendors/${booking.vendor.slug}`} className="text-blue-600 hover:underline">{booking.vendor.name}</Link>
                            </p>
                        )}
                    </div>

                    <div className="grid grid-cols-2 gap-4 mt-6 text-sm">
                        <div>
                            <div className="text-slate-500 text-xs uppercase tracking-wide">Date</div>
                            <div className="font-medium text-lg">{booking.date}</div>
                        </div>
                        <div>
                            <div className="text-slate-500 text-xs uppercase tracking-wide">Time</div>
                            <div className="font-medium text-lg">{booking.time}</div>
                        </div>
                        <div>
                            <div className="text-slate-500 text-xs uppercase tracking-wide">Duration</div>
                            <div className="font-medium">{booking.duration_min} minutes</div>
                        </div>
                        <div>
                            <div className="text-slate-500 text-xs uppercase tracking-wide">Provider</div>
                            <div className="font-medium">{booking.provider?.name ?? 'To be assigned'}</div>
                        </div>
                        <div>
                            <div className="text-slate-500 text-xs uppercase tracking-wide">Location</div>
                            <div className="font-medium">{booking.location_mode.replace('_', ' ')}</div>
                        </div>
                        <div>
                            <div className="text-slate-500 text-xs uppercase tracking-wide">Price</div>
                            <div className="font-medium">{booking.price} {booking.currency}</div>
                        </div>
                    </div>

                    {booking.service_address && (
                        <div className="mt-4 p-3 bg-slate-50 rounded text-sm">
                            <div className="text-slate-500 text-xs uppercase tracking-wide mb-1">Service address</div>
                            <div>{Object.values(booking.service_address).filter(v => v).join(', ')}</div>
                        </div>
                    )}

                    {booking.customer_notes && (
                        <div className="mt-4 p-3 bg-yellow-50 border border-yellow-200 rounded text-sm">
                            <div className="text-yellow-800 text-xs uppercase tracking-wide mb-1">Your notes to the vendor</div>
                            <div>{booking.customer_notes}</div>
                        </div>
                    )}

                    {booking.order && (
                        <div className="mt-4 p-3 bg-blue-50 border border-blue-200 rounded text-sm">
                            <div className="text-blue-800 text-xs uppercase tracking-wide mb-1">Payment</div>
                            <div>Order <span className="font-mono">{booking.order.number}</span> — status: {booking.order.payment_status}</div>
                        </div>
                    )}
                </div>

                {/* What happens next */}
                <div className="mt-6 border rounded-lg p-4 bg-slate-50">
                    <h3 className="font-semibold mb-2">What happens next</h3>
                    <ol className="list-decimal list-inside space-y-1 text-sm text-slate-700">
                        <li>The vendor will review your booking and confirm it within their typical response time.</li>
                        <li>You&apos;ll see status changes in <Link href="/bookings" className="text-blue-600 hover:underline">My Bookings</Link>.</li>
                        <li>You can cancel or request a reschedule from the booking detail page.</li>
                        {booking.location_mode === 'customer_location' && (
                            <li>Please ensure someone is available at the service address on the booked date.</li>
                        )}
                        {booking.location_mode === 'online' && (
                            <li>Connection details will be shared by the vendor closer to your appointment time.</li>
                        )}
                    </ol>
                </div>

                {/* Next-step CTAs */}
                <div className="mt-6 flex gap-3 justify-center">
                    <Link href="/bookings"
                          className="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                        View My Bookings
                    </Link>
                    <Link href={`/bookings/${booking.id}`}
                          className="px-4 py-2 border border-slate-300 rounded hover:bg-slate-100">
                        Manage this booking
                    </Link>
                    <Link href="/services"
                          className="px-4 py-2 border border-slate-300 rounded hover:bg-slate-100">
                        Browse more services
                    </Link>
                </div>
            </div>
        </StorefrontLayout>
    );
}
