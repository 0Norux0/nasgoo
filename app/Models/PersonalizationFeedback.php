<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 11B.3 §23 — feedback records: "Not Interested", "Hide product",
 * "Show fewer like this". Applied per-customer OR per-guest-session.
 */
class PersonalizationFeedback extends Model
{
    public const TYPE_NOT_INTERESTED  = 'not_interested';
    public const TYPE_HIDE_PRODUCT    = 'hide_product';
    public const TYPE_SHOW_FEWER_LIKE = 'show_fewer_like';

    protected $table = 'personalization_feedback';

    protected $fillable = [
        'user_id', 'session_key', 'feedback_type',
        'product_id', 'category_id', 'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }
    public function product(): BelongsTo { return $this->belongsTo(Product::class); }
    public function category(): BelongsTo { return $this->belongsTo(Category::class); }
}
