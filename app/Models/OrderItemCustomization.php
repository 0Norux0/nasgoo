<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_item_id
 * @property string $field_key
 * @property string $field_label
 * @property string $field_type
 * @property ?string $value
 * @property ?string $file_path
 * @property ?string $file_original_name
 * @property ?string $file_mime
 * @property ?int $file_size_bytes
 * @property int $extra_fee_minor
 */
class OrderItemCustomization extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_item_id',
        'field_key', 'field_label', 'field_type',
        'value',
        'file_path', 'file_original_name', 'file_mime', 'file_size_bytes',
        'extra_fee_minor',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'extra_fee_minor' => 'integer',
        ];
    }

    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
}
