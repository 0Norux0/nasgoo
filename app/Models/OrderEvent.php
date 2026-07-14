<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderEvent extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id', 'event_type', 'message', 'payload', 'actor_id', 'actor_role',
    ];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    /** @return BelongsTo<Order, OrderEvent> */
    public function order(): BelongsTo { return $this->belongsTo(Order::class); }

    /** @return BelongsTo<User, OrderEvent> */
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_id'); }
}
