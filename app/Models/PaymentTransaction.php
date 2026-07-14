<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    use HasFactory;

    public const TYPE_AUTHORIZE = 'authorize';
    public const TYPE_CAPTURE   = 'capture';
    public const TYPE_REFUND    = 'refund';
    public const TYPE_VOID      = 'void';
    public const TYPE_WEBHOOK   = 'webhook';

    protected $fillable = [
        'payment_id', 'type', 'status', 'amount_minor', 'currency',
        'external_id', 'payload', 'error',
    ];

    protected function casts(): array
    {
        return [
            'amount_minor' => 'integer',
            'payload'      => 'array',
        ];
    }

    /** @return BelongsTo<Payment, PaymentTransaction> */
    public function payment(): BelongsTo { return $this->belongsTo(Payment::class); }
}
