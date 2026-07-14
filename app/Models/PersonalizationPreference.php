<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Phase 11B.3 §21 — per-user privacy preferences.
 */
class PersonalizationPreference extends Model
{
    protected $fillable = [
        'user_id',
        'behavioral_personalization_enabled',
        'guest_merge_enabled',
        'behavior_tracking_enabled',
        'last_reset_at',
    ];

    protected function casts(): array
    {
        return [
            'behavioral_personalization_enabled' => 'boolean',
            'guest_merge_enabled'                => 'boolean',
            'behavior_tracking_enabled'          => 'boolean',
            'last_reset_at'                      => 'datetime',
        ];
    }

    public function user(): BelongsTo { return $this->belongsTo(User::class); }

    /**
     * Load a user's prefs, returning config defaults when the row doesn't
     * exist. Absence != opt-out; missing row = defaults.
     */
    public static function forUser(?User $user): array
    {
        if (! $user) {
            return [
                'behavioral_personalization_enabled' => true,
                'guest_merge_enabled'                => true,
                'behavior_tracking_enabled'          => true,
            ];
        }
        $row = static::where('user_id', $user->id)->first();
        return [
            'behavioral_personalization_enabled' =>
                $row?->behavioral_personalization_enabled
                ?? (bool) config('marketplace_personalization.defaults.behavioral_personalization_enabled', true),
            'guest_merge_enabled' =>
                $row?->guest_merge_enabled
                ?? (bool) config('marketplace_personalization.defaults.guest_merge_enabled', true),
            'behavior_tracking_enabled' =>
                $row?->behavior_tracking_enabled
                ?? (bool) config('marketplace_personalization.defaults.behavior_tracking_enabled', true),
        ];
    }
}
