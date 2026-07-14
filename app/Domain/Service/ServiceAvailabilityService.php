<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Models\Product;
use App\Models\ServiceAvailability;
use App\Models\ServiceBooking;
use App\Models\ServiceProvider;
use Carbon\CarbonImmutable;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

/**
 * Phase 8 — computes available booking slots for a provider on a date.
 *
 * Inputs:
 *   - provider's weekly availability (ServiceAvailability rows)
 *   - provider's blocked dates (ServiceBlockedDate rows)
 *   - existing active bookings for that provider+date (ServiceBooking)
 *   - service's duration (snapshot from ServiceDetail)
 *   - service's min_lead_time_minutes + max_advance_days
 *
 * Outputs: an ordered list of slot strings (eg. "10:00", "10:30", "11:00")
 * where each slot has capacity remaining (active_bookings <
 * max_bookings_per_slot).
 */
class ServiceAvailabilityService
{
    /**
     * Slots available for $provider on $date for the given $service.
     *
     * Returns ['time' => '10:00', 'remaining' => 1] entries. Empty array
     * = no availability (date blocked, weekly schedule missing, or all
     * slots full).
     *
     * @return array<int, array{time: string, remaining: int}>
     */
    public function slotsFor(ServiceProvider $provider, Product $service, CarbonImmutable $date): array
    {
        if (! $service->isService() || ! $service->serviceDetail) {
            return [];
        }

        $today = CarbonImmutable::today();
        $maxAdvanceDays = $service->serviceDetail->max_advance_days;

        // Out of range — too far in the future, or in the past.
        if ($date->lt($today) || $date->gt($today->addDays($maxAdvanceDays))) {
            return [];
        }

        // Provider is blocked this entire date — no slots.
        if ($provider->blockedDates()->whereDate('date', $date->toDateString())->exists()) {
            return [];
        }

        // 0 = Sunday, 6 = Saturday (Carbon convention matches our migration).
        $dayOfWeek = $date->dayOfWeek;
        $availability = $provider->availabilities()
            ->where('day_of_week', $dayOfWeek)
            ->where('is_active', true)
            ->first();

        if (! $availability) {
            return [];
        }

        $duration = $service->serviceDetail->duration_minutes;
        $slotMinutes = max($availability->slot_duration_minutes, $duration);

        $slots = $this->generateSlots($availability, $slotMinutes);

        // Filter out slots before the min_lead_time cutoff if date is today.
        if ($date->isSameDay($today)) {
            $cutoff = CarbonImmutable::now()->addMinutes($service->serviceDetail->min_lead_time_minutes);
            $slots = array_filter($slots, fn ($s) => $s->format('H:i') >= $cutoff->format('H:i'));
        }

        if (empty($slots)) {
            return [];
        }

        // Count active bookings per slot for this provider+date.
        $activeBookings = ServiceBooking::query()
            ->where('service_provider_id', $provider->id)
            ->whereDate('booked_for_date', $date->toDateString())
            ->whereIn('status', ServiceBooking::ACTIVE_STATUSES)
            ->get(['booked_for_time']);

        $bookedCounts = [];
        foreach ($activeBookings as $b) {
            $key = substr((string) $b->booked_for_time, 0, 5);   // "HH:MM"
            $bookedCounts[$key] = ($bookedCounts[$key] ?? 0) + 1;
        }

        $max = $availability->max_bookings_per_slot;
        $result = [];
        foreach ($slots as $slot) {
            $key = $slot->format('H:i');
            $remaining = $max - ($bookedCounts[$key] ?? 0);
            if ($remaining > 0) {
                $result[] = ['time' => $key, 'remaining' => $remaining];
            }
        }
        return $result;
    }

    /**
     * Slots across a date range — used to render the customer-facing
     * calendar. Returns ['YYYY-MM-DD' => [...slots...]].
     *
     * @return array<string, array<int, array{time: string, remaining: int}>>
     */
    public function slotsForRange(
        ServiceProvider $provider, Product $service,
        CarbonImmutable $from, CarbonImmutable $to
    ): array {
        $result = [];
        foreach (CarbonPeriod::create($from, $to) as $day) {
            $immutable = CarbonImmutable::parse($day);
            $slots = $this->slotsFor($provider, $service, $immutable);
            if (! empty($slots)) {
                $result[$immutable->toDateString()] = $slots;
            }
        }
        return $result;
    }

    /**
     * Generate raw slot start-times respecting the optional break window.
     *
     * @return array<int, CarbonImmutable>
     */
    private function generateSlots(ServiceAvailability $av, int $slotMinutes): array
    {
        $base = CarbonImmutable::today();
        $start = $base->setTimeFromTimeString((string) $av->start_time);
        $end   = $base->setTimeFromTimeString((string) $av->end_time);

        $breakStart = $av->break_start_time
            ? $base->setTimeFromTimeString((string) $av->break_start_time)
            : null;
        $breakEnd = $av->break_end_time
            ? $base->setTimeFromTimeString((string) $av->break_end_time)
            : null;

        $slots = [];
        $cursor = $start;
        while ($cursor->lt($end)) {
            $slotEnd = $cursor->addMinutes($slotMinutes);
            $inBreak = $breakStart && $breakEnd
                && $cursor->lt($breakEnd) && $slotEnd->gt($breakStart);
            if (! $inBreak && $slotEnd->lte($end)) {
                $slots[] = $cursor;
            }
            $cursor = $cursor->addMinutes($slotMinutes);
        }
        return $slots;
    }
}
