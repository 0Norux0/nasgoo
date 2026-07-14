<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\ServiceAvailability;
use App\Models\ServiceBlockedDate;
use App\Models\ServiceProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class VendorAvailabilityController extends Controller
{
    public function show(Request $request, int $providerId): Response
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);

        $provider = ServiceProvider::where('vendor_id', $vendor->id)
            ->with(['availabilities', 'blockedDates'])
            ->findOrFail($providerId);

        return Inertia::render('Vendor/Providers/Availability', [
            'provider' => [
                'id'   => $provider->id,
                'name' => $provider->name,
            ],
            'availabilities' => $provider->availabilities->map(fn ($a) => [
                'id'                    => $a->id,
                'day_of_week'           => $a->day_of_week,
                'day_name'              => $a->dayName(),
                'start_time'            => substr((string) $a->start_time, 0, 5),
                'end_time'              => substr((string) $a->end_time, 0, 5),
                'slot_duration_minutes' => $a->slot_duration_minutes,
                'max_bookings_per_slot' => $a->max_bookings_per_slot,
                'break_start_time'      => $a->break_start_time ? substr((string) $a->break_start_time, 0, 5) : null,
                'break_end_time'        => $a->break_end_time ? substr((string) $a->break_end_time, 0, 5) : null,
                'is_active'             => (bool) $a->is_active,
            ])->values(),
            'blocked_dates' => $provider->blockedDates->map(fn ($d) => [
                'id'     => $d->id,
                'date'   => $d->date->toDateString(),
                'reason' => $d->reason,
            ])->values(),
            'days_of_week' => ServiceAvailability::DAYS,
        ]);
    }

    public function upsertAvailability(Request $request, int $providerId): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);

        $provider = ServiceProvider::where('vendor_id', $vendor->id)->findOrFail($providerId);

        $data = $request->validate([
            'day_of_week'           => ['required', 'integer', 'min:0', 'max:6'],
            'start_time'            => ['required', 'date_format:H:i'],
            'end_time'              => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_duration_minutes' => ['required', 'integer', 'min:5', 'max:480'],
            'max_bookings_per_slot' => ['required', 'integer', 'min:1', 'max:50'],
            'break_start_time'      => ['nullable', 'date_format:H:i'],
            'break_end_time'        => ['nullable', 'date_format:H:i', 'after:break_start_time'],
            'is_active'             => ['boolean'],
        ]);

        ServiceAvailability::updateOrCreate(
            ['service_provider_id' => $provider->id, 'day_of_week' => $data['day_of_week']],
            [
                'start_time'            => $data['start_time'] . ':00',
                'end_time'              => $data['end_time'] . ':00',
                'slot_duration_minutes' => $data['slot_duration_minutes'],
                'max_bookings_per_slot' => $data['max_bookings_per_slot'],
                'break_start_time'      => isset($data['break_start_time']) ? $data['break_start_time'] . ':00' : null,
                'break_end_time'        => isset($data['break_end_time']) ? $data['break_end_time'] . ':00' : null,
                'is_active'             => (bool) ($data['is_active'] ?? true),
            ]
        );

        return back()->with('success', 'Availability updated for ' . ServiceAvailability::DAYS[$data['day_of_week']] . '.');
    }

    public function blockDate(Request $request, int $providerId): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);

        $provider = ServiceProvider::where('vendor_id', $vendor->id)->findOrFail($providerId);

        $data = $request->validate([
            'date'   => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:200'],
        ]);

        ServiceBlockedDate::updateOrCreate(
            ['service_provider_id' => $provider->id, 'date' => $data['date']],
            ['reason' => $data['reason'] ?? null]
        );

        return back()->with('success', "Date {$data['date']} blocked.");
    }

    public function unblockDate(Request $request, int $providerId, int $blockedId): RedirectResponse
    {
        $vendor = $request->user()->vendor;
        abort_unless($vendor, 403);

        $blocked = ServiceBlockedDate::where('service_provider_id', $providerId)
            ->whereHas('provider', fn ($q) => $q->where('vendor_id', $vendor->id))
            ->findOrFail($blockedId);

        $blocked->delete();

        return back()->with('success', 'Date unblocked.');
    }
}
