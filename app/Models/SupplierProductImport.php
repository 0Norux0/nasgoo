<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $vendor_id
 * @property ?int $supplier_integration_id
 * @property int $supplier_platform_id
 * @property ?string $original_filename
 * @property string $status              processing | completed | failed
 * @property bool $dry_run
 * @property int $total_rows
 * @property int $successful_rows
 * @property int $failed_rows
 * @property ?array $errors
 * @property ?array $summary
 */
class SupplierProductImport extends Model
{
    use HasFactory;

    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_FAILED     = 'failed';

    protected $fillable = [
        'vendor_id', 'supplier_integration_id', 'supplier_platform_id',
        'original_filename', 'status', 'dry_run',
        'total_rows', 'successful_rows', 'failed_rows',
        'errors', 'summary', 'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'dry_run'        => 'boolean',
            'total_rows'     => 'integer',
            'successful_rows'=> 'integer',
            'failed_rows'    => 'integer',
            'errors'         => 'array',
            'summary'        => 'array',
            'processed_at'   => 'datetime',
        ];
    }

    public function vendor(): BelongsTo       { return $this->belongsTo(Vendor::class); }
    public function platform(): BelongsTo     { return $this->belongsTo(SupplierPlatform::class, 'supplier_platform_id'); }
    public function integration(): BelongsTo  { return $this->belongsTo(SupplierIntegration::class, 'supplier_integration_id'); }
}
