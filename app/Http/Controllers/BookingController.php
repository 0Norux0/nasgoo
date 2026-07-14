<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Service\ServiceBookingService;
use App\Models\Product;
use App\Models\ServiceBooking;
use App\Models\ServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class BookingController extends Controller
{
    public function __construct(private readonly ServiceBookingService $bookings) {}

    /**
     * Customer's bookings dashboard.
     */
    public function index(Request $request): Response
    {
        $user = $request->user();

        $bookings = ServiceBooking::where('user_id', $user->id)
            ->with(['product:id,name,slug', 'provider:id,name', 'vendor:id,business_name'])
            ->orderByDesc('booked_for_date')
            ->orderByDesc('booked_for_time')
            ->paginate(20);

        return Inertia::render('Bookings/Index', [
            'bookings' => $bookings->through(fn (ServiceBooking $b) => [
                'id'             => $b->id,
                'number'         => $b->number,
                'status'         => $b->status,
                'service_name'   => $b->product?->name,
                'service_slug'   => $b->product?->slug,
                'vendor_name'    => $b->vendor?->business_name,
                'provider_name'  => $b->provider?->name,
                'date'           => $b->booked_for_date?->toDateString(),
                'time'           => substr((string) $b->booked_for_time, 0, 5),
                'duration_min'   => $b->duration_minutes,
                'price'          => number_format($b->price_minor / 100, 2),
                'currency'       => $b->currency,
                'is_active'      => $b->isActive(),
            ]),
        ]);
    }

    public function show(Request $request, int $id): Response
    {
        $user = $request->user();
        $booking = ServiceBooking::where('user_id', $user->id)
            ->with(['product:id,name,slug,description', 'provider', 'vendor:id,business_name,slug',
                    'order:id,number,payment_status,total_minor,currency'])
            ->findOrFail($id);

        return Inertia::render('Bookings/Show', [
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
                'rejection_reason' => $booking->rejection_reason,
                'service'         => $booking->product ? [
                    'id'   => $booking->product->id, 'name' => $booking->product->name,
                    'slug' => $booking->product->slug, 'description' => $booking->product->description,
                ] : null,
                'provider'        => $booking->provider ? ['id' => $booking->provider->id, 'name' => $booking->provider->name, 'specialization' => $booking->provider->specialization] : null,
                'vendor'          => $booking->vendor ? ['name' => $booking->vendor->business_name, 'slug' => $booking->vendor->slug] : null,
                'order'           => $booking->order ? ['number' => $booking->order->number, 'payment_status' => $booking->order->payment_status, 'total' => number_format($booking->order->total_minor / 100, 2)] : null,
                'can_cancel'      => $booking->canBeCancelledBy($user),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'service_id'           => ['required', 'integer', 'exists:products,id'],
            'service_provider_id'  => ['nullable', 'integer', 'exists:service_providers,id'],
            'date'                 => ['required', 'date', 'after_or_equal:today'],
            'time'                 => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'customer_notes'       => ['nullable', 'string', 'max:2000'],
            'service_address'      => ['nullable', 'array'],
        ]);

        $service = Product::where('type', Product::TYPE_SERVICE)
            ->with('serviceDetail')
            ->findOrFail($data['service_id']);

        $provider = $data['service_provider_id']
            ? ServiceProvider::find($data['service_provider_id'])
            : null;

        try {
            $booking = $this->bookings->createBooking($user, $service, $provider, [
                'date'            => $data['date'],
                'time'            => $data['time'],
                'customer_notes'  => $data['customer_notes'] ?? null,
                'service_address' => $data['service_address'] ?? null,
            ]);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not create booking: ' . $e->getMessage())->withInput();
        }

        return redirect("/bookings/{$booking->id}/confirmation")
            ->with('success', "Booking #{$booking->number} created — awaiting vendor confirmation.");
    }

    /**
     * Phase 8 v8.1 — booking confirmation page shown immediately after
     * successful creation. Distinct from bookings.show: this is the
     * post-checkout "thank you" surface with next-steps copy, the
     * booking reference for the customer to screenshot, and a link
     * onward to /bookings (My Bookings) for the list view.
     *
     * Uses the same eager-load set as show() since the renderer touches
     * the same relations (v7.6 lesson).
     */
    public function confirmation(Request $request, int $id): Response
    {
        $user = $request->user();
        $booking = ServiceBooking::where('user_id', $user->id)
            ->with(['product:id,name,slug,description', 'provider', 'vendor:id,business_name,slug',
                    'order:id,number,payment_status,total_minor,currency'])
            ->findOrFail($id);

        return Inertia::render('Bookings/Confirmation', [
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
                'service'         => $booking->product ? [
                    'name'        => $booking->product->name,
                    'slug'        => $booking->product->slug,
                    'description' => $booking->product->description,
                ] : null,
                'provider'        => $booking->provider ? ['name' => $booking->provider->name, 'specialization' => $booking->provider->specialization] : null,
                'vendor'          => $booking->vendor ? ['name' => $booking->vendor->business_name, 'slug' => $booking->vendor->slug] : null,
                'order'           => $booking->order ? ['number' => $booking->order->number, 'payment_status' => $booking->order->payment_status] : null,
                'customer_notes'  => $booking->customer_notes,
            ],
        ]);
    }

    /**
     * Phase 8 v8.1 — customer reschedule.
     *
     * Customer picks a new date+time. The service layer handles the
     * concurrency-safe swap: cancels the old slot's hold, creates a new
     * booking row with the same reference, marks the old one as
     * RESCHEDULED. Bound to a booking the customer owns; pre-checks
     * canBeCancelledBy (same permission gates a reschedule).
     */
    public function reschedule(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $booking = ServiceBooking::where('user_id', $user->id)->findOrFail($id);

        $data = $request->validate([
            'date'           => ['required', 'date', 'after_or_equal:today'],
            'time'           => ['required', 'regex:/^\d{2}:\d{2}(:\d{2})?$/'],
            'customer_notes' => ['nullable', 'string', 'max:2000'],
        ]);

        if (! $booking->canBeCancelledBy($user)) {
            return back()->with('error', 'This booking cannot be rescheduled in its current state.');
        }

        try {
            $this->bookings->reschedule($booking, $data['date'], $data['time'],
                $data['customer_notes'] ?? null);
        } catch (ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        } catch (\Throwable $e) {
            return back()->with('error', 'Could not reschedule: ' . $e->getMessage());
        }

        return redirect("/bookings/{$booking->id}")
            ->with('success', "Booking #{$booking->number} rescheduled.");
    }

    public function cancel(Request $request, int $id): RedirectResponse
    {
        $user = $request->user();
        $booking = ServiceBooking::where('user_id', $user->id)->findOrFail($id);

        $data = $request->validate(['reason' => ['nullable', 'string', 'max:2000']]);

        if (! $booking->canBeCancelledBy($user)) {
            return back()->with('error', 'This booking cannot be cancelled.');
        }

        try {
            $this->bookings->cancel($booking, $data['reason'] ?? 'Customer cancelled');
        } catch (\Throwable $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Booking #{$booking->number} cancelled.");
    }
}
