<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ServiceProvider extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id', 'name', 'slug', 'email', 'phone',
        'bio', 'specialization', 'qualification',
        'profile_image_path', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    /**
     * Services this provider can deliver.
     */
    public function services(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'service_provider_assignments')
            ->withTimestamps();
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(ServiceAvailability::class);
    }

    public function blockedDates(): HasMany
    {
        return $this->hasMany(ServiceBlockedDate::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(ServiceBooking::class);
    }
}
