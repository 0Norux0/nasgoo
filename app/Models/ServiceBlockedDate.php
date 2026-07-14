<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceBlockedDate extends Model
{
    protected $fillable = ['service_provider_id', 'date', 'reason'];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function provider(): BelongsTo
    {
        return $this->belongsTo(ServiceProvider::class, 'service_provider_id');
    }
}
