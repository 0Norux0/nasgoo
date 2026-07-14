<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A vendor's specific configuration to interact with a supplier platform.
 *
 * `credentials` is encrypted at rest via Eloquent's 'encrypted:array' cast.
 * Once stored, the raw values never leave the database in plaintext via
 * normal Eloquent queries.
 *
 * @property int $id
 * @property int $vendor_id
 * @property int $supplier_platform_id
 * @property string $name
 * @property string $integration_type
 * @property ?array $credentials
 * @property ?string $feed_url
 * @property ?array $sync_options
 * @property bool $is_active
 * @property ?\Carbon\Carbon $last_synced_at
 * @property ?string $last_sync_status
 * @property ?string $last_sync_message
 */
class SupplierIntegration extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_id', 'supplier_platform_id', 'name', 'integration_type',
        'credentials', 'feed_url', 'sync_options',
        'is_active', 'last_synced_at', 'last_sync_status', 'last_sync_message',
    ];

    protected function casts(): array
    {
        return [
            'credentials'      => 'encrypted:array',
            'sync_options'     => 'array',
            'is_active'        => 'boolean',
            'last_synced_at'   => 'datetime',
        ];
    }

    protected $hidden = ['credentials'];

    public function vendor(): BelongsTo           { return $this->belongsTo(Vendor::class); }
    public function platform(): BelongsTo         { return $this->belongsTo(SupplierPlatform::class, 'supplier_platform_id'); }
    public function supplierProducts(): HasMany   { return $this->hasMany(SupplierProduct::class); }
    public function imports(): HasMany            { return $this->hasMany(SupplierProductImport::class); }

    /** Returns last-4 of api key etc. for safe display in UI. */
    public function maskedCredentials(): array
    {
        $masked = [];
        foreach ($this->credentials ?? [] as $key => $val) {
            if (! is_string($val) || $val === '') {
                $masked[$key] = '—';
                continue;
            }
            $masked[$key] = strlen($val) <= 4 ? '••••' : '••••' . substr($val, -4);
        }
        return $masked;
    }
}
