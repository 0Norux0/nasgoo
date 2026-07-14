<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceAvailability extends Model
{
    public const DAYS = [
        0 => 'Sunday', 1 => 'Monday', 2 => 'Tuesday', 3 => 'Wednesday',
        4 => 'Thursday', 5 => 'Friday', 6 => 'Saturday',
    ];

    protected $fillable = [
        'service_provider_id', 'day_of_week',
        'start_time', 'end_time', 'slot_duration_minutes',
        'max_bookings_per_slot', 'break_start_time', 'break_end_time',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'day_of_week'           => 'integer',
            'slot_duration_minutes' => 'integer',
            'max_bookings_per_slot' => 'integer',
            'is_active'             => 'boolean',
        ];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'service_provider_id');
    }

    public function dayName(): string
    {
        return self::DAYS[$this->day_of_week] ?? 'Unknown';
    }
}
