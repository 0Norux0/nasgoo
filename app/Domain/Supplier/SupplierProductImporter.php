<?php

declare(strict_types=1);

namespace App\Domain\Supplier;

use App\Models\SupplierIntegration;
use App\Models\SupplierPlatform;
use App\Models\SupplierProduct;
use App\Models\SupplierProductImport;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Phase 6 — SupplierProductImporter.
 *
 * Two entry points:
 *   - importManual()  : single supplier product row from form input
 *   - importCsv()     : batch import from a parsed CSV (array of rows). Dry-run
 *                      mode validates and reports without persisting.
 *
 * Importer never reaches outside the application; it does not scrape or fetch
 * from third-party platforms. CSV upload is the only batch path in Phase 6.
 * API-based pulling is foundation only and not exercised here.
 */
class SupplierProductImporter
{
    /**
     * Expected CSV columns (all optional except title + supplier_cost):
     *   title, description, supplier_sku, source_url,
     *   supplier_cost, currency, stock_quantity, stock_status,
     *   image_url, estimated_delivery_days, external_product_id
     */
    public const CSV_COLUMNS = [
        'title', 'description', 'supplier_sku', 'source_url',
        'supplier_cost', 'currency', 'stock_quantity', 'stock_status',
        'image_url', 'estimated_delivery_days', 'external_product_id',
    ];

    public function importManual(Vendor $vendor, SupplierPlatform $platform, array $payload, ?SupplierIntegration $integration = null): SupplierProduct
    {
        $validated = Validator::make($payload, [
            'title'                   => 'required|string|max:255',
            'description'             => 'nullable|string|max:5000',
            'supplier_sku'            => 'nullable|string|max:128',
            'source_url'              => 'nullable|url|max:1024',
            'external_product_id'     => 'nullable|string|max:128',
            'supplier_cost_minor'     => 'required|integer|min:0',
            'supplier_currency'       => 'nullable|string|size:3',
            'supplier_stock_status'   => 'nullable|in:in_stock,out_of_stock,unknown',
            'supplier_stock_qty'      => 'nullable|integer|min:0',
            'supplier_shipping_minor' => 'nullable|integer|min:0',
            'estimated_delivery_days' => 'nullable|integer|min:0|max:365',
            'images'                  => 'nullable|array|max:10',
            'images.*'                => 'url',
        ])->validate();

        return SupplierProduct::create(array_merge([
            'vendor_id'               => $vendor->id,
            'supplier_platform_id'    => $platform->id,
            'supplier_integration_id' => $integration?->id,
            'supplier_currency'       => $payload['supplier_currency'] ?? $platform->default_currency,
            'supplier_stock_status'   => $payload['supplier_stock_status'] ?? SupplierProduct::STOCK_UNKNOWN,
            'supplier_shipping_minor' => 0,
            'estimated_delivery_days' => $platform->default_delivery_days,
            'import_status'           => SupplierProduct::STATUS_PENDING,
            'imported_at'             => now(),
            'raw_payload'             => ['source' => 'manual', 'payload' => $payload],
        ], $validated));
    }

    /**
     * @param array<int, array<string, string|int|null>> $rows
     */
    public function importCsv(
        Vendor $vendor,
        SupplierPlatform $platform,
        array $rows,
        ?SupplierIntegration $integration = null,
        bool $dryRun = false,
        ?string $originalFilename = null,
    ): SupplierProductImport {
        $batch = SupplierProductImport::create([
            'vendor_id'               => $vendor->id,
            'supplier_integration_id' => $integration?->id,
            'supplier_platform_id'    => $platform->id,
            'original_filename'       => $originalFilename,
            'status'                  => SupplierProductImport::STATUS_PROCESSING,
            'dry_run'                 => $dryRun,
            'total_rows'              => count($rows),
        ]);

        $errors = [];
        $successful = 0;
        $failed = 0;

        DB::transaction(function () use ($rows, $vendor, $platform, $integration, $dryRun, &$errors, &$successful, &$failed) {
            foreach ($rows as $index => $row) {
                $rowNum = $index + 2; // header is row 1
                try {
                    $payload = $this->normalizeCsvRow($row, $platform);

                    $validator = Validator::make($payload, [
                        'title'                 => 'required|string|max:255',
                        'supplier_cost_minor'   => 'required|integer|min:0',
                        'supplier_currency'     => 'nullable|string|size:3',
                        'source_url'            => 'nullable|url',
                        'estimated_delivery_days' => 'nullable|integer|min:0|max:365',
                    ]);
                    if ($validator->fails()) {
                        $failed++;
                        $errors[] = ['row' => $rowNum, 'errors' => $validator->errors()->all()];
                        continue;
                    }

                    if (! $dryRun) {
                        SupplierProduct::create(array_merge([
                            'vendor_id'               => $vendor->id,
                            'supplier_platform_id'    => $platform->id,
                            'supplier_integration_id' => $integration?->id,
                            'import_status'           => SupplierProduct::STATUS_PENDING,
                            'imported_at'             => now(),
                            'raw_payload'             => ['source' => 'csv', 'row' => $row],
                        ], $payload));
                    }
                    $successful++;
                } catch (\Throwable $e) {
                    $failed++;
                    $errors[] = ['row' => $rowNum, 'errors' => [$e->getMessage()]];
                }
            }
        });

        $batch->update([
            'status'           => SupplierProductImport::STATUS_COMPLETED,
            'successful_rows'  => $successful,
            'failed_rows'      => $failed,
            'errors'           => $errors,
            'summary'          => [
                'dry_run'   => $dryRun,
                'platform'  => $platform->slug,
                'persisted' => ! $dryRun,
            ],
            'processed_at'     => now(),
        ]);

        return $batch->fresh();
    }

    /** Convert a raw CSV row (string-keyed) to a SupplierProduct payload. */
    private function normalizeCsvRow(array $row, SupplierPlatform $platform): array
    {
        $images = [];
        if (! empty($row['image_url'])) {
            // image_url cell may be comma-or-pipe separated for multiple images
            $images = preg_split('/[|,]/', (string) $row['image_url']);
            $images = array_values(array_filter(array_map('trim', $images)));
        }

        $costMajor = isset($row['supplier_cost']) ? (float) $row['supplier_cost'] : 0.0;

        return [
            'title'                   => (string) ($row['title'] ?? ''),
            'description'             => $row['description'] ?? null,
            'external_sku'            => $row['supplier_sku'] ?? null,
            'external_product_id'     => $row['external_product_id'] ?? null,
            'source_url'              => $row['source_url'] ?? null,
            'supplier_cost_minor'     => (int) round($costMajor * 100),
            'supplier_currency'       => $row['currency'] ?? $platform->default_currency,
            'supplier_stock_qty'      => isset($row['stock_quantity']) && $row['stock_quantity'] !== '' ? (int) $row['stock_quantity'] : null,
            'supplier_stock_status'   => $row['stock_status'] ?? SupplierProduct::STOCK_UNKNOWN,
            'estimated_delivery_days' => isset($row['estimated_delivery_days']) && $row['estimated_delivery_days'] !== '' ? (int) $row['estimated_delivery_days'] : $platform->default_delivery_days,
            'images'                  => $images,
        ];
    }
}
