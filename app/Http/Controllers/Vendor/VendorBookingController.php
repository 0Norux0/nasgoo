<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Domain\Service\ServiceBookingService;
use App\Http\Controllers\Controller;
use App\Models\ServiceBooking;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorBookingController extends Controller
{
    public function __construct(private readonly ServiceBookingService $bookings) {}

    public function index(Request $request): Response
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);

        // v7.6 lesson — eager-load every relation the presenter touches.
        $bookings = ServiceBooking::where('vendor_id', $vendor->id)
            ->with(['product:id,name', 'provider:id,name', 'customer:id,name,email'])
            ->orderByDesc('booked_for_date')
            ->orderByDesc('booked_for_time')
            ->paginate(20);

        return Inertia::render('Vendor/Bookings/Index', [
            'bookings' => $bookings->through(fn (ServiceBooking $b) => [
                'id'             => $b->id,
                'number'         => $b->number,
                'status'         => $b->status,
                'customer_name'  => $b->customer?->name,
                'customer_email' => $b->customer?->email,
                'service_name'   => $b->product?->name,
                'provider_name'  => $b->provider?->name,
                'date'           => $b->booked_for_date?->toDateString(),
                'time'           => substr((string) $b->booked_for_time, 0, 5),
                'duration_min'   => $b->duration_minutes,
                'location_mode'  => $b->location_mode,
                'price'          => number_format($b->price_minor / 100, 2),
                'currency'       => $b->currency,
            ]),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);

        $booking = ServiceBooking::where('vendor_id', $vendor->id)
            ->with(['product:id,name,slug', 'provider:id,name,specialization,phone',
                    'customer:id,name,email,phone', 'order:id,number,payment_status'])
            ->findOrFail($id);

        return Inertia::render('Vendor/Bookings/Show', [
            'booking' => [
                'id'              => $booking->id,
                'number'          => $booking->number,
                'status'          => $booking->status,
                'date'            => $booking->booked_for_date?->toDateString(),
                'time'            => substr((string) $booking->booked_for_time, 0, 5),
                'duration_min'    => $booking->duration_minutes,
                'location_mode'   => $booking->location_mode,
                'service_address' => $booking->service_address,
                'price'           => number_format($booking->price_minor / 100, 2),
                'currency'        => $booking->currency,
                'customer_notes'  => $booking->customer_notes,
                'vendor_notes'    => $booking->vendor_notes,
                'rejection_reason' => $booking->rejection_reason,
                'service'         => $booking->product ? ['id' => $booking->product->id, 'name' => $booking->product->name] : null,
                'provider'        => $booking->provider ? ['id' => $booking->provider->id, 'name' => $booking->provider->name] : null,
                'customer'        => $booking->customer ? ['name' => $booking->customer->name, 'email' => $booking->customer->email, 'phone' => $booking->customer->phone] : null,
                'order'           => $booking->order ? ['number' => $booking->order->number, 'payment_status' => $booking->order->payment_status] : null,
                'confirmed_at'    => $booking->confirmed_at?->toDateTimeString(),
                'accepted_at'     => $booking->accepted_at?->toDateTimeString(),
                'completed_at'    => $booking->completed_at?->toDateTimeString(),
                'cancelled_at'    => $booking->cancelled_at?->toDateTimeString(),
            ],
        ]);
    }

    public function accept(Request $request, int $id): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);

        $booking = ServiceBooking::where('vendor_id', $vendor->id)->findOrFail($id);
        $data = $request->validate(['vendor_note' => ['nullable', 'string', 'max:2000']]);

        try {
            $this->bookings->accept($booking, $data['vendor_note'] ?? null);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Booking #{$booking->number} accepted.");
    }

    public function reject(Request $request, int $id): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);

        $booking = ServiceBooking::where('vendor_id', $vendor->id)->findOrFail($id);
        $data = $request->validate(['reason' => ['required', 'string', 'max:2000']]);

        try {
            $this->bookings->reject($booking, $data['reason']);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Booking #{$booking->number} rejected.");
    }

    public function complete(Request $request, int $id): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);

        $booking = ServiceBooking::where('vendor_id', $vendor->id)->findOrFail($id);

        try {
            $this->bookings->complete($booking);
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Booking #{$booking->number} marked completed.");
    }

    /**
     * Phase 8 v8.1 — vendor-initiated reschedule.
     *
     * Vendor can reschedule any booking on their own services (eg. when
     * a customer calls in to move their appointment). Uses the same
     * ServiceBookingService::reschedule path as the customer endpoint
     * for consistent slot-availability guarantees.
     */
    public function reschedule(Request $request, int $id): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);

        $booking = ServiceBooking::where('vendor_id', $vendor->id)->findOrFail($id);

        $data = $request->validate([
            'date'        => ['required', 'date', 'after_or_equal:today'],
            'time'        => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'vendor_note' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $this->bookings->reschedule($booking, $data['date'], $data['time'], null);
            if (! empty($data['vendor_note'])) {
                $booking->update(['vendor_notes' => $data['vendor_note']]);
            }
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not reschedule: ' . $e->getMessage());
        }

        return back()->with('success', "Booking #{$booking->number} rescheduled to {$data['date']} {$data['time']}.");
    }
}
