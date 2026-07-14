<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceDetail extends Model
{
    public const TYPE_APPOINTMENT  = 'appointment';
    public const TYPE_HOME_VISIT   = 'home_visit';
    public const TYPE_ONLINE       = 'online';
    public const TYPE_CONSULTATION = 'consultation';
    public const TYPE_FIXED_PRICE  = 'fixed_price';

    public const LOCATION_CUSTOMER  = 'customer_location';
    public const LOCATION_PROVIDER  = 'provider_location';
    public const LOCATION_ONLINE    = 'online';
    public const LOCATION_FLEXIBLE  = 'flexible';

    public const TYPES = [
        self::TYPE_APPOINTMENT, self::TYPE_HOME_VISIT, self::TYPE_ONLINE,
        self::TYPE_CONSULTATION, self::TYPE_FIXED_PRICE,
    ];

    public const LOCATION_MODES = [
        self::LOCATION_CUSTOMER, self::LOCATION_PROVIDER,
        self::LOCATION_ONLINE, self::LOCATION_FLEXIBLE,
    ];

    protected $fillable = [
        'product_id', 'service_type', 'location_mode',
        'duration_minutes', 'service_area_text',
        'min_lead_time_minutes', 'max_advance_days',
        'allow_customer_provider_pick', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'duration_minutes'              => 'integer',
            'min_lead_time_minutes'         => 'integer',
            'max_advance_days'              => 'integer',
            'allow_customer_provider_pick'  => 'boolean',
            'is_active'                     => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function isHomeVisit(): bool      { return $this->service_type === self::TYPE_HOME_VISIT; }
    public function isOnline(): bool         { return $this->location_mode === self::LOCATION_ONLINE; }
    public function requiresAddress(): bool  { return $this->location_mode === self::LOCATION_CUSTOMER; }
}
