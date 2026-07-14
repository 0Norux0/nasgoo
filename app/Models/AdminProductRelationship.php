<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminProductRelationship extends Model
{
    use HasFactory;

    public const TYPE_PINNED        = 'pinned';
    public const TYPE_HIDDEN        = 'hidden';
    public const TYPE_COMPLEMENTARY = 'complementary';
    public const TYPE_EXCLUDED      = 'excluded';

    public const TYPES = [
        self::TYPE_PINNED, self::TYPE_HIDDEN,
        self::TYPE_COMPLEMENTARY, self::TYPE_EXCLUDED,
    ];

    protected $fillable = [
        'product_id', 'related_product_id', 'relationship_type',
        'reciprocal', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'reciprocal' => 'boolean',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function relatedProduct(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'related_product_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
