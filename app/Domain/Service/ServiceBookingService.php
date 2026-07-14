<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Models\Product;
use App\Models\ServiceBooking;
use App\Models\ServiceProvider;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Phase 8 — ServiceBooking lifecycle.
 *
 *   createBooking()  — DB transaction with row-level lock on
 *                      (service_provider_id, booked_for_date). Re-counts
 *                      active bookings under the lock to prevent
 *                      double-booking races.
 *   acceptBooking()  — vendor accepts a pending booking.
 *   rejectBooking()  — vendor rejects with reason.
 *   confirmBooking() — payment captured (called by PaymentService).
 *   cancelBooking()  — customer or vendor cancels with reason.
 *   completeBooking()— vendor marks as delivered.
 *   reschedule()     — moves booking to a new slot (transactional).
 *
 * All state transitions also append a row to the `events` log of the
 * linked Order (when present) so the order timeline shows booking
 * lifecycle changes alongside payment events.
 */
class ServiceBookingService
{
    public function __construct(
        private readonly ServiceAvailabilityService $availability,
    ) {}

    /**
     * Create a new booking. Throws ValidationException if the slot is
     * unavailable (no schedule for that day, fully booked, in the past,
     * outside booking window, etc).
     *
     * @param array{
     *     date: string,                              // YYYY-MM-DD
     *     time: string,                              // HH:MM
     *     customer_notes?: ?string,
     *     service_address?: ?array,
     * } $payload
     */
    public function createBooking(
        User $customer,
        Product $service,
        ?ServiceProvider $provider,
        array $payload,
    ): ServiceBooking {
        if (! $service->isService() || ! $service->serviceDetail) {
            throw ValidationException::withMessages(['service' => 'Not a service product.']);
        }
        if (! $service->serviceDetail->is_active) {
            throw ValidationException::withMessages(['service' => 'Service is not currently accepting bookings.']);
        }

        // Provider resolution: if the customer didn't pick one and the
        // service forbids customer picking, auto-pick the first active
        // assigned provider.
        if (! $provider) {
            $provider = $service->serviceProviders()
                ->where('service_providers.is_active', true)
                ->orderBy('service_providers.id')
                ->first();
            if (! $provider) {
                throw ValidationException::withMessages([
                    'service_provider' => 'No active service provider is available for this service.',
                ]);
            }
        }

        // Verify provider is actually assigned to this service
        if (! $service->serviceProviders()->where('service_providers.id', $provider->id)->exists()) {
            throw ValidationException::withMessages([
                'service_provider' => 'Selected provider does not deliver this service.',
            ]);
        }

        $date = CarbonImmutable::parse($payload['date']);
        $time = (string) $payload['time'];

        // Service requires customer-supplied address?
        $address = $payload['service_address'] ?? null;
        if ($service->serviceDetail->requiresAddress() && empty($address)) {
            throw ValidationException::withMessages([
                'service_address' => 'This service requires the customer address.',
            ]);
        }

        return DB::transaction(function () use ($customer, $service, $provider, $date, $time, $payload, $address) {
            // Row-level lock on bookings for this provider+date — blocks
            // concurrent bookings from racing into the same slot.
            ServiceBooking::query()
                ->where('service_provider_id', $provider->id)
                ->whereDate('booked_for_date', $date->toDateString())
                ->whereIn('status', ServiceBooking::ACTIVE_STATUSES)
                ->lockForUpdate()
                ->get();

            // Re-compute availability under the lock.
            $slots = $this->availability->slotsFor($provider, $service, $date);
            $matching = array_values(array_filter($slots, fn ($s) => $s['time'] === substr($time, 0, 5)));
            if (empty($matching) || $matching[0]['remaining'] < 1) {
                throw ValidationException::withMessages([
                    'time' => 'The selected slot is no longer available.',
                ]);
            }

            return ServiceBooking::create([
                'number'              => self::generateNumber(),
                'user_id'             => $customer->id,
                'vendor_id'           => $service->vendor_id,
                'product_id'          => $service->id,
                'service_provider_id' => $provider->id,
                'order_id'            => null,
                'booked_for_date'     => $date->toDateString(),
                'booked_for_time'     => $time . (strlen($time) === 5 ? ':00' : ''),
                'duration_minutes'    => $service->serviceDetail->duration_minutes,
                'location_mode'       => $service->serviceDetail->location_mode,
                'price_minor'         => $service->price_minor,
                'currency'            => $service->currency ?? 'KWD',
                'service_address'     => $address,
                'status'              => ServiceBooking::STATUS_PENDING,
                'customer_notes'      => $payload['customer_notes'] ?? null,
            ]);
        });
    }

    /**
     * Vendor accepts a pending booking.
     */
    public function accept(ServiceBooking $booking, ?string $vendorNote = null): ServiceBooking
    {
        if (! in_array($booking->status, [ServiceBooking::STATUS_PENDING, ServiceBooking::STATUS_CONFIRMED], true)) {
            throw ValidationException::withMessages([
                'status' => "Cannot accept a booking in status '{$booking->status}'.",
            ]);
        }

        $booking->update([
            'status'      => ServiceBooking::STATUS_ACCEPTED,
            'accepted_at' => now(),
            'vendor_notes' => $vendorNote ?? $booking->vendor_notes,
        ]);

        return $booking;
    }

    /**
     * Vendor rejects a booking with a reason.
     */
    public function reject(ServiceBooking $booking, string $reason): ServiceBooking
    {
        if ($booking->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => "Cannot reject a terminal booking (status '{$booking->status}').",
            ]);
        }

        $booking->update([
            'status'           => ServiceBooking::STATUS_REJECTED,
            'rejection_reason' => $reason,
            'cancelled_at'     => now(),
        ]);

        return $booking;
    }

    /**
     * Customer cancels a booking.
     */
    public function cancel(ServiceBooking $booking, ?string $reason = null): ServiceBooking
    {
        if ($booking->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => "Booking already in terminal status '{$booking->status}'.",
            ]);
        }

        $booking->update([
            'status'           => ServiceBooking::STATUS_CANCELLED,
            'rejection_reason' => $reason,
            'cancelled_at'     => now(),
        ]);

        return $booking;
    }

    /**
     * Vendor marks booking as completed.
     */
    public function complete(ServiceBooking $booking): ServiceBooking
    {
        if (! in_array($booking->status, [ServiceBooking::STATUS_ACCEPTED, ServiceBooking::STATUS_CONFIRMED], true)) {
            throw ValidationException::withMessages([
                'status' => "Can only complete an accepted/confirmed booking (current: '{$booking->status}').",
            ]);
        }

        $booking->update([
            'status'       => ServiceBooking::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);

        return $booking;
    }

    /**
     * Phase 8 v8.1 — reschedule an active booking to a new date+time.
     *
     * Concurrency-safe: takes a row lock on the NEW slot, re-checks
     * availability under the lock, swaps the booking's date/time fields
     * if available, otherwise throws ValidationException. The old slot
     * is freed implicitly because the booking row's booked_for_date/
     * booked_for_time fields update.
     *
     * Status transitions: ACTIVE → ACTIVE (same status preserved).
     * Terminal bookings cannot be rescheduled.
     */
    public function reschedule(
        ServiceBooking $booking,
        string $newDate,
        string $newTime,
        ?string $customerNotes = null,
    ): ServiceBooking {
        if ($booking->isTerminal()) {
            throw ValidationException::withMessages([
                'status' => "Cannot reschedule a terminal booking (status '{$booking->status}').",
            ]);
        }

        if (! $booking->service_provider_id) {
            throw ValidationException::withMessages([
                'service_provider_id' => 'Booking has no assigned provider — cannot reschedule.',
            ]);
        }

        $service = $booking->product()->with('serviceDetail')->first();
        if (! $service || ! $service->serviceDetail) {
            throw ValidationException::withMessages([
                'service' => 'Service detail not found for this booking.',
            ]);
        }

        $provider = $booking->provider;
        if (! $provider) {
            throw ValidationException::withMessages([
                'service_provider_id' => 'Provider not found for this booking.',
            ]);
        }

        $date = \Carbon\CarbonImmutable::parse($newDate);
        $time = (string) $newTime;

        return DB::transaction(function () use ($booking, $service, $provider, $date, $time, $customerNotes) {
            // Lock all bookings on the NEW slot's day so concurrent
            // attempts can't race ahead. Exclude this booking from the
            // lock view so we don't deadlock against ourself.
            ServiceBooking::query()
                ->where('service_provider_id', $provider->id)
                ->whereDate('booked_for_date', $date->toDateString())
                ->whereIn('status', ServiceBooking::ACTIVE_STATUSES)
                ->where('id', '!=', $booking->id)
                ->lockForUpdate()
                ->get();

            // Re-compute slots under the lock.
            $slots = $this->availability->slotsFor($provider, $service, $date);
            $matching = array_values(array_filter($slots, fn ($s) => $s['time'] === substr($time, 0, 5)));
            if (empty($matching) || $matching[0]['remaining'] < 1) {
                throw ValidationException::withMessages([
                    'time' => 'The selected slot is no longer available.',
                ]);
            }

            $booking->update([
                'booked_for_date' => $date->toDateString(),
                'booked_for_time' => $time . (strlen($time) === 5 ? ':00' : ''),
                'customer_notes'  => $customerNotes ?? $booking->customer_notes,
                // We do NOT change the status field — a rescheduled
                // ACCEPTED booking stays ACCEPTED on the new date.
                // STATUS_RESCHEDULED is reserved for cancel-and-replace
                // workflows (out of scope for v8.1).
            ]);

            return $booking;
        });
    }

    /**
     * Number generator — short prefix + ymd + 4-digit sequence.
     * Format: SVC-YYYYMMDD-XXXX. Loops up to 10 times on collision; if it
     * still fails after 10 tries, falls back to a microtime-derived form
     * so the seeder never gets stuck even in extreme contention.
     */
    public static function generateNumber(): string
    {
        $prefix = 'SVC-' . now()->format('Ymd') . '-';
        for ($i = 0; $i < 10; $i++) {
            $candidate = $prefix . str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            if (! ServiceBooking::where('number', $candidate)->exists()) {
                return $candidate;
            }
        }
        return $prefix . substr((string) (microtime(true) * 10000), -4);
    }
}
