<?php

declare(strict_types=1);

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductCustomizationField;
use App\Models\Vendor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Phase 7 — vendor manages customization fields on their own customizable
 * products. Scoping: every action looks up the Product via
 * Product::where('vendor_id', $vendor->id)->findOrFail($productId) so
 * cross-vendor access returns 404.
 */
class VendorCustomizationFieldController extends Controller
{
    public function index(Request $request, int $productId): Response
    {
        $vendor = $this->vendor($request);
        $product = Product::where('vendor_id', $vendor->id)->findOrFail($productId);

        return Inertia::render('Vendor/Customization/Fields/Index', [
            'product' => [
                'id'             => $product->id,
                'name'           => $product->name,
                'type'           => $product->type,
                'is_customizable'=> $product->isCustomizable(),
            ],
            'fields' => $product->customizationFields()
                ->orderBy('sort_order')
                ->get()
                ->map(fn (ProductCustomizationField $f) => $this->toArray($f)),
        ]);
    }

    public function store(Request $request, int $productId): RedirectResponse
    {
        $vendor = $this->vendor($request);
        $product = Product::where('vendor_id', $vendor->id)->findOrFail($productId);

        $data = $request->validate([
            'label'                 => 'required|string|max:120',
            'type'                  => 'required|in:' . implode(',', ProductCustomizationField::ALL_TYPES),
            'key'                   => 'nullable|string|max:64|regex:/^[a-z0-9_]+$/',
            'required'              => 'nullable|boolean',
            'sort_order'            => 'nullable|integer|min:0|max:999',
            'allowed_file_types'    => 'nullable|array|max:10',
            'allowed_file_types.*'  => 'string|max:10',
            'max_file_size_kb'      => 'nullable|integer|min:1|max:51200',
            'max_text_length'       => 'nullable|integer|min:1|max:5000',
            'extra_fee_minor'       => 'nullable|integer|min:0|max:1000000',
            'placeholder'           => 'nullable|string|max:255',
            'helper_text'           => 'nullable|string|max:1000',
            'options'               => 'nullable|array|max:50',
            'options.*.value'       => 'required_with:options|string|max:120',
            'options.*.label'       => 'required_with:options|string|max:120',
            'options.*.extra_fee'   => 'nullable|integer|min:0|max:1000000',
            'is_active'             => 'nullable|boolean',
        ]);

        $key = $data['key'] ?? Str::slug($data['label'], '_');
        // Guard against collision
        $baseKey = $key;
        $i = 1;
        while ($product->customizationFields()->where('key', $key)->exists()) {
            $key = $baseKey . '_' . (++$i);
        }

        $product->customizationFields()->create(array_merge($data, [
            'key'       => $key,
            'required'  => (bool) ($data['required'] ?? false),
            'is_active' => (bool) ($data['is_active'] ?? true),
        ]));

        return redirect()->route('vendor.products.customization-fields.index', $product->id)
            ->with('success', "Field \"{$data['label']}\" added.");
    }

    public function update(Request $request, int $productId, int $fieldId): RedirectResponse
    {
        $vendor = $this->vendor($request);
        $product = Product::where('vendor_id', $vendor->id)->findOrFail($productId);
        $field = $product->customizationFields()->findOrFail($fieldId);

        $data = $request->validate([
            'label'                 => 'sometimes|required|string|max:120',
            'required'              => 'nullable|boolean',
            'sort_order'            => 'nullable|integer|min:0|max:999',
            'allowed_file_types'    => 'nullable|array|max:10',
            'allowed_file_types.*'  => 'string|max:10',
            'max_file_size_kb'      => 'nullable|integer|min:1|max:51200',
            'max_text_length'       => 'nullable|integer|min:1|max:5000',
            'extra_fee_minor'       => 'nullable|integer|min:0|max:1000000',
            'placeholder'           => 'nullable|string|max:255',
            'helper_text'           => 'nullable|string|max:1000',
            'options'               => 'nullable|array|max:50',
            'is_active'             => 'nullable|boolean',
        ]);

        $field->update($data);

        return back()->with('success', 'Field updated.');
    }

    public function destroy(Request $request, int $productId, int $fieldId): RedirectResponse
    {
        $vendor = $this->vendor($request);
        $product = Product::where('vendor_id', $vendor->id)->findOrFail($productId);
        $field = $product->customizationFields()->findOrFail($fieldId);
        $field->delete();

        return back()->with('success', 'Field removed.');
    }

    // ---- helpers ----

    private function vendor(Request $request): Vendor
    {
        /** @var Vendor $v */
        $v = $request->attributes->get('vendor');
        if (! $v) {
            throw new \Symfony\Component\HttpFoundation\Exception\BadRequestException('Vendor context not resolved.');
        }
        return $v;
    }

    private function toArray(ProductCustomizationField $f): array
    {
        return [
            'id'                 => $f->id,
            'key'                => $f->key,
            'label'              => $f->label,
            'type'               => $f->type,
            'required'           => $f->required,
            'sort_order'         => $f->sort_order,
            'allowed_file_types' => $f->allowed_file_types,
            'max_file_size_kb'   => $f->max_file_size_kb,
            'max_text_length'    => $f->max_text_length,
            'extra_fee_minor'    => $f->extra_fee_minor,
            'placeholder'        => $f->placeholder,
            'helper_text'        => $f->helper_text,
            'options'            => $f->options,
            'is_active'          => $f->is_active,
        ];
    }
}
