<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'user_id',
        'action',
        'model_type',
        'model_id',
        'before',
        'after',
        'ip_address',
        'user_agent',
        'notes',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'before'     => 'array',
            'after'      => 'array',
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, AuditLog> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphTo<\Illuminate\Database\Eloquent\Model, AuditLog> */
    public function model(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Audit logs are immutable.
     */
    protected static function booted(): void
    {
        static::updating(function () {
            throw new \LogicException('Audit logs are immutable.');
        });

        static::deleting(function () {
            throw new \LogicException('Audit logs cannot be deleted.');
        });

        static::creating(function (AuditLog $log) {
            if (! $log->created_at) {
                $log->created_at = now();
            }
        });
    }
}
