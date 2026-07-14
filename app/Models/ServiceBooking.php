<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceBooking extends Model
{
    use HasFactory;

    // State machine constants — kept in sync with migration comment
    public const STATUS_PENDING         = 'pending';
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_CONFIRMED       = 'confirmed';
    public const STATUS_ACCEPTED        = 'accepted';
    public const STATUS_REJECTED        = 'rejected';
    public const STATUS_RESCHEDULED     = 'rescheduled';
    public const STATUS_CANCELLED       = 'cancelled';
    public const STATUS_COMPLETED       = 'completed';
    public const STATUS_NO_SHOW         = 'no_show';
    public const STATUS_REFUNDED        = 'refunded';

    /**
     * "Active" statuses block the slot for other bookings.
     */
    public const ACTIVE_STATUSES = [
        self::STATUS_PENDING, self::STATUS_PENDING_PAYMENT,
        self::STATUS_CONFIRMED, self::STATUS_ACCEPTED,
    ];

    /**
     * Terminal statuses — booking is closed.
     */
    public const TERMINAL_STATUSES = [
        self::STATUS_REJECTED, self::STATUS_CANCELLED,
        self::STATUS_COMPLETED, self::STATUS_NO_SHOW, self::STATUS_REFUNDED,
    ];

    protected $fillable = [
        'number', 'user_id', 'vendor_id', 'product_id', 'service_provider_id',
        'order_id',
        'booked_for_date', 'booked_for_time', 'duration_minutes',
        'location_mode', 'price_minor', 'currency',
        'service_address', 'status',
        'customer_notes', 'vendor_notes', 'rejection_reason',
        'confirmed_at', 'accepted_at', 'completed_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'booked_for_date'  => 'date',
            'duration_minutes' => 'integer',
            'price_minor'      => 'integer',
            'service_address'  => 'array',
            'confirmed_at'     => 'datetime',
            'accepted_at'      => 'datetime',
            'completed_at'     => 'datetime',
            'cancelled_at'     => 'datetime',
        ];
    }

    /**
     * Phase 8 — model-level invariant guard (v7.4-style defense).
     *
     * Mirroring the v7.4 CustomizationProof safeguard: throw a
     * LogicException with a clear, actionable message BEFORE the SQL
     * round trip if a caller tries to create a booking with state that
     * the migration's NOT NULL constraints would reject anyway. Catches
     * seeder / service / test code paths that skip the proper service
     * layer.
     */
    protected static function booted(): void
    {
        static::creating(function (self $booking): void {
            $required = ['user_id', 'vendor_id', 'product_id',
                         'booked_for_date', 'booked_for_time', 'duration_minutes',
                         'location_mode', 'price_minor', 'currency', 'status', 'number'];
            foreach ($required as $field) {
                if ($booking->{$field} === null || $booking->{$field} === '') {
                    throw new \LogicException(
                        "ServiceBooking::{$field} cannot be null or empty. "
                        . 'Use ServiceBookingService::createBooking() to create bookings — '
                        . 'it sets all required fields including the booking number, '
                        . 'snapshots price/currency/duration from the service, and '
                        . 'verifies slot availability under a row-level lock. '
                        . 'See Phase 8 PATCH_NOTES for the booking lifecycle.'
                    );
                }
            }
            if (! in_array($booking->status, array_merge(self::ACTIVE_STATUSES, self::TERMINAL_STATUSES), true)) {
                throw new \LogicException(
                    "ServiceBooking::status '{$booking->status}' is not a known status. "
                    . 'Valid: ' . implode(', ', array_merge(self::ACTIVE_STATUSES, self::TERMINAL_STATUSES))
                );
            }
        });
    }

    public function customer(): BelongsTo  { return $this->belongsTo(User::class, 'user_id'); }
    public function vendor(): BelongsTo    { return $this->belongsTo(Vendor::class); }
    public function product(): BelongsTo   { return $this->belongsTo(Product::class); }
    public function provider(): BelongsTo  { return $this->belongsTo(ServiceProvider::class, 'service_provider_id'); }
    public function order(): BelongsTo     { return $this->belongsTo(Order::class); }

    public function isActive(): bool   { return in_array($this->status, self::ACTIVE_STATUSES, true); }
    public function isTerminal(): bool { return in_array($this->status, self::TERMINAL_STATUSES, true); }

    public function canBeCancelledBy(User $user): bool
    {
        if ($this->isTerminal()) return false;
        return $this->user_id === $user->id || $this->vendor->user_id === $user->id;
    }
}
