<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Domain\Supplier\SupplierProductImporter;
use App\Domain\Supplier\SupplierProductMapper;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\SupplierIntegration;
use App\Models\SupplierPlatform;
use App\Models\SupplierProduct;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Exception\BadRequestException;

/**
 * Phase 6 — vendor-side controller for supplier products.
 *
 * All scoping by vendor_id at the query layer. The vendor cannot see or
 * modify other vendors' supplier products by any code path here.
 */
class VendorSupplierProductController extends Controller
{
    public function index(Request $request): Response
    {
        $vendor = $this->vendor($request);

        $products = SupplierProduct::query()
            ->forVendor($vendor->id)
            ->with(['platform:id,name,slug', 'product:id,name,status'])
            ->latest('imported_at')
            ->paginate(20)
            ->through(fn (SupplierProduct $sp) => [
                'id'                  => $sp->id,
                'title'               => $sp->title,
                'platform'            => $sp->platform?->name,
                'platform_slug'       => $sp->platform?->slug,
                'cost'                => number_format($sp->supplier_cost_minor / 100, 2) . ' ' . $sp->supplier_currency,
                'cost_minor'          => $sp->supplier_cost_minor,
                'currency'            => $sp->supplier_currency,
                'stock_status'        => $sp->supplier_stock_status,
                'stock_qty'           => $sp->supplier_stock_qty,
                'import_status'       => $sp->import_status,
                'imported_at'         => $sp->imported_at?->toDateTimeString(),
                'product'             => $sp->product ? [
                    'id'     => $sp->product->id,
                    'name'   => $sp->product->name,
                    'status' => $sp->product->status,
                ] : null,
                'images_count'        => is_array($sp->images) ? count($sp->images) : 0,
            ]);

        return Inertia::render('Vendor/Supplier/Products/Index', [
            'products' => $products,
            'platforms' => SupplierPlatform::query()
                ->where('is_active', true)
                ->orderBy('display_order')->orderBy('name')
                ->get(['id', 'name', 'slug', 'integration_type', 'default_currency', 'default_delivery_days']),
        ]);
    }

    public function manualForm(Request $request): Response
    {
        return Inertia::render('Vendor/Supplier/Products/Manual', [
            'platforms' => SupplierPlatform::where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'slug', 'default_currency', 'default_delivery_days']),
        ]);
    }

    public function storeManual(Request $request): RedirectResponse
    {
        $vendor = $this->vendor($request);

        $data = $request->validate([
            'supplier_platform_id'    => 'required|exists:supplier_platforms,id',
            'title'                   => 'required|string|max:255',
            'description'             => 'nullable|string|max:5000',
            'supplier_sku'            => 'nullable|string|max:128',
            'source_url'              => 'nullable|url|max:1024',
            'supplier_cost_major'     => 'required|numeric|min:0',
            'supplier_currency'       => 'nullable|string|size:3',
            'supplier_stock_status'   => 'nullable|in:in_stock,out_of_stock,unknown',
            'supplier_stock_qty'      => 'nullable|integer|min:0',
            'estimated_delivery_days' => 'nullable|integer|min:0|max:365',
            'images'                  => 'nullable|array|max:10',
            'images.*'                => 'url',
        ]);

        $platform = SupplierPlatform::findOrFail($data['supplier_platform_id']);

        $sp = app(SupplierProductImporter::class)->importManual($vendor, $platform, [
            'title'                   => $data['title'],
            'description'             => $data['description'] ?? null,
            'supplier_sku'            => $data['supplier_sku'] ?? null,
            'source_url'              => $data['source_url'] ?? null,
            'supplier_cost_minor'     => (int) round(((float) $data['supplier_cost_major']) * 100),
            'supplier_currency'       => $data['supplier_currency'] ?? $platform->default_currency,
            'supplier_stock_status'   => $data['supplier_stock_status'] ?? 'unknown',
            'supplier_stock_qty'      => $data['supplier_stock_qty'] ?? null,
            'estimated_delivery_days' => $data['estimated_delivery_days'] ?? null,
            'images'                  => $data['images'] ?? [],
        ]);

        return redirect()->route('vendor.supplier-products.index')
            ->with('success', "Imported supplier product #{$sp->id}: \"{$sp->title}\".");
    }

    public function mapForm(Request $request, int $id): Response
    {
        $vendor = $this->vendor($request);
        $sp = SupplierProduct::forVendor($vendor->id)->with('platform')->findOrFail($id);

        return Inertia::render('Vendor/Supplier/Products/Map', [
            'supplier_product' => [
                'id'                  => $sp->id,
                'title'               => $sp->title,
                'description'         => $sp->description,
                'platform'            => $sp->platform?->name,
                'cost_minor'          => $sp->supplier_cost_minor,
                'cost'                => number_format($sp->supplier_cost_minor / 100, 2) . ' ' . $sp->supplier_currency,
                'currency'            => $sp->supplier_currency,
                'stock_qty'           => $sp->supplier_stock_qty,
                'eta_days'            => $sp->estimated_delivery_days,
                'images'              => $sp->images ?? [],
                'source_url'          => $sp->source_url,
                'import_status'       => $sp->import_status,
                'mapped_product_id'   => $sp->product_id,
            ],
            'categories' => Category::orderBy('name')->get(['id', 'name'])->map(fn ($c) => [
                'id' => $c->id, 'name' => $c->name,
            ]),
        ]);
    }

    public function storeMapping(Request $request, int $id): RedirectResponse
    {
        $vendor = $this->vendor($request);
        $sp = SupplierProduct::forVendor($vendor->id)->findOrFail($id);

        if ($sp->import_status !== SupplierProduct::STATUS_PENDING) {
            return back()->with('error', 'This supplier product is no longer pending and cannot be re-mapped from here.');
        }

        $data = $request->validate([
            'name'                    => 'required|string|max:255',
            'description'             => 'nullable|string|max:5000',
            'category_id'             => 'nullable|exists:categories,id',
            'price_major'             => 'required|numeric|min:0',
            'currency'                => 'nullable|string|size:3',
            'stock'                   => 'required|integer|min:0',
            'estimated_delivery_days' => 'nullable|integer|min:0|max:365',
            'fulfillment_mode'        => 'nullable|in:dropship_manual,dropship_admin',
        ]);

        try {
            $product = app(SupplierProductMapper::class)->map($sp, [
                'name'                    => $data['name'],
                'description'             => $data['description'] ?? null,
                'category_id'             => $data['category_id'] ?? null,
                'price_minor'             => (int) round(((float) $data['price_major']) * 100),
                'currency'                => $data['currency'] ?? 'KWD',
                'stock'                   => (int) $data['stock'],
                'estimated_delivery_days' => $data['estimated_delivery_days'] ?? null,
                'fulfillment_mode'        => $data['fulfillment_mode'] ?? \App\Models\Product::FULFILLMENT_DROPSHIP_MANUAL,
            ], $request->user());
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('vendor.supplier-products.index')
            ->with('success', "Mapped \"{$sp->title}\" → product #{$product->id}. Awaiting admin approval.");
    }

    // ------------------------------------------------------------------
    // CSV import
    // ------------------------------------------------------------------

    public function csvForm(Request $request): Response
    {
        return Inertia::render('Vendor/Supplier/Products/CsvImport', [
            'platforms' => SupplierPlatform::where('is_active', true)
                ->orderBy('name')->get(['id', 'name', 'slug', 'default_currency']),
            'csv_columns' => SupplierProductImporter::CSV_COLUMNS,
        ]);
    }

    public function csvImport(Request $request): RedirectResponse
    {
        $vendor = $this->vendor($request);
        $data = $request->validate([
            'supplier_platform_id' => 'required|exists:supplier_platforms,id',
            'csv'                  => 'required|file|mimetypes:text/csv,text/plain,application/csv|max:2048',
            'dry_run'              => 'nullable|boolean',
        ]);

        $platform = SupplierPlatform::findOrFail($data['supplier_platform_id']);

        $rows = $this->parseCsvFile($data['csv']->getRealPath());

        if (empty($rows)) {
            return back()->with('error', 'CSV file is empty or has no data rows.');
        }

        $batch = app(SupplierProductImporter::class)->importCsv(
            vendor: $vendor,
            platform: $platform,
            rows: $rows,
            dryRun: (bool) ($data['dry_run'] ?? false),
            originalFilename: $data['csv']->getClientOriginalName(),
        );

        $msg = "{$batch->successful_rows} rows succeeded, {$batch->failed_rows} failed";
        if ($batch->dry_run) {
            $msg = "[DRY RUN] {$msg}. No supplier products were saved.";
        }

        return redirect()->route('vendor.supplier-imports.show', $batch->id)
            ->with($batch->failed_rows > 0 ? 'error' : 'success', $msg);
    }

    public function importReport(Request $request, int $id): Response
    {
        $vendor = $this->vendor($request);
        $batch = \App\Models\SupplierProductImport::where('vendor_id', $vendor->id)
            ->with('platform:id,name')
            ->findOrFail($id);

        return Inertia::render('Vendor/Supplier/Products/ImportReport', [
            'batch' => [
                'id'                => $batch->id,
                'original_filename' => $batch->original_filename,
                'platform'          => $batch->platform?->name,
                'status'            => $batch->status,
                'dry_run'           => $batch->dry_run,
                'total_rows'        => $batch->total_rows,
                'successful_rows'   => $batch->successful_rows,
                'failed_rows'       => $batch->failed_rows,
                'errors'            => $batch->errors ?? [],
                'processed_at'      => $batch->processed_at?->toDateTimeString(),
            ],
        ]);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function vendor(Request $request): Vendor
    {
        /** @var Vendor $v */
        $v = $request->attributes->get('vendor');
        if (! $v) {
            throw new BadRequestException('Vendor context not resolved on this request.');
        }
        return $v;
    }

    /**
     * Parse a CSV file into an array of assoc rows keyed by lowercased header.
     * Uses PHP's built-in fgetcsv so we don't add another composer dependency.
     */
    private function parseCsvFile(string $path): array
    {
        $rows = [];
        $handle = fopen($path, 'r');
        if (! $handle) {
            return [];
        }
        try {
            $header = fgetcsv($handle);
            if (! $header) {
                return [];
            }
            $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) === 1 && trim((string) $row[0]) === '') {
                    continue; // skip empty lines
                }
                $assoc = [];
                foreach ($header as $i => $col) {
                    $assoc[$col] = $row[$i] ?? null;
                }
                $rows[] = $assoc;
            }
        } finally {
            fclose($handle);
        }
        return $rows;
    }
}
