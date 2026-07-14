<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierOrderEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_order_id', 'actor_id', 'actor_role',
        'event_type', 'message', 'payload',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function supplierOrder(): BelongsTo { return $this->belongsTo(SupplierOrder::class); }
    public function actor(): BelongsTo         { return $this->belongsTo(User::class, 'actor_id'); }
}
