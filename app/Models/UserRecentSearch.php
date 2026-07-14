<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 11B.1 §11 — per-user recent search history.
 *
 * Strictly user-scoped. The (user_id, query, locale) unique index plus the
 * `scopeForUser` accessor mean cross-user exposure is structurally impossible.
 */
class UserRecentSearch extends Model
{
    use HasFactory;

    protected $table = 'user_recent_searches';

    protected $fillable = [
        'user_id', 'query', 'locale', 'searched_at',
    ];

    protected function casts(): array
    {
        return [
            'searched_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope to a specific user — every query through the model layer should
     * go through this to make cross-user mistakes harder.
     */
    public function scopeForUser(Builder $q, int $userId): Builder
    {
        return $q->where('user_id', $userId);
    }
}
