<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_item_id
 * @property ?int $vendor_id
 * @property string $file_path
 * @property string $file_original_name
 * @property string $file_mime
 * @property int $file_size_bytes
 * @property string $status
 * @property ?string $vendor_note
 * @property ?string $customer_response
 * @property ?\Carbon\Carbon $sent_at
 * @property ?\Carbon\Carbon $responded_at
 */
class CustomizationProof extends Model
{
    use HasFactory;

    public const STATUS_DRAFT    = 'draft';
    public const STATUS_SENT     = 'sent';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';

    public const ALL_STATUSES = [
        self::STATUS_DRAFT, self::STATUS_SENT,
        self::STATUS_APPROVED, self::STATUS_REJECTED,
    ];

    protected $fillable = [
        'order_item_id', 'vendor_id',
        'file_path', 'file_original_name', 'file_mime', 'file_size_bytes',
        'status', 'vendor_note', 'customer_response',
        'sent_at', 'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'file_size_bytes' => 'integer',
            'sent_at'         => 'datetime',
            'responded_at'    => 'datetime',
        ];
    }

    public function orderItem(): BelongsTo { return $this->belongsTo(OrderItem::class); }
    public function vendor(): BelongsTo    { return $this->belongsTo(Vendor::class); }

    public function isAwaitingCustomer(): bool { return $this->status === self::STATUS_SENT; }
    public function isApproved(): bool         { return $this->status === self::STATUS_APPROVED; }
    public function isRejected(): bool         { return $this->status === self::STATUS_REJECTED; }

    /**
     * Phase 7 v7.4 — bulletproof defense against the v7.0-v7.3 bug class.
     *
     * The customization_proofs.file_path column is NOT NULL in the migration.
     * Up to v7.3 we relied on the SQL constraint to reject null file_path,
     * which surfaced as the cryptic "Column 'file_path' cannot be null"
     * SQLSTATE[23000] error. That error happens AFTER an entire round trip
     * to the DB, and the message doesn't point at the code path that fed
     * the bad value.
     *
     * This `creating` event fires BEFORE the INSERT and throws a
     * LogicException with a clear, actionable message. It catches:
     *   - any seeder that forgets to upload the file first
     *   - any service that fails to call CustomizationFileStorage and
     *     proceeds anyway
     *   - any test that creates a proof without a path
     *
     * The exception is intentionally LogicException (not ValidationException)
     * because this is a developer error: legitimate user-facing code paths
     * always upload the file first via CustomizationFileStorage. If you see
     * this in production, the calling code is broken.
     */
    protected static function booted(): void
    {
        static::creating(function (self $proof): void {
            if (empty($proof->file_path)) {
                throw new \LogicException(
                    'CustomizationProof::file_path cannot be null or empty. '
                    . 'The calling code must first upload the proof file to '
                    . 'the private disk (via CustomizationFileStorage::storeVendorProof) '
                    . 'and pass the returned path. See Phase 7 v7.3/v7.4 patch notes '
                    . 'for the demo seeder pattern.'
                );
            }
        });
    }
}
