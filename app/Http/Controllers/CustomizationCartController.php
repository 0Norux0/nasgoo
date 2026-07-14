<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Customization\CustomizationCartService;
use App\Domain\Customization\CustomizationFieldValidator;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Phase 7 — customer add-to-cart with customization payload.
 *
 * POST /cart/items/customized
 *   product_id: int
 *   variant_id: ?int
 *   quantity: int
 *   customizations[<field_key>]: string|bool   (text/dropdown/checkbox values)
 *   customizations[<file_field_key>]: UploadedFile  (sent via $request->file)
 *
 * The validator throws ValidationException on missing required fields or
 * unsafe file uploads — the customer is bounced back with field-level
 * errors so the React form can highlight them.
 */
class CustomizationCartController extends Controller
{
    public function __construct(
        protected CustomizationFieldValidator $validator,
        protected CustomizationCartService $cart,
    ) {}

    public function add(Request $request): RedirectResponse
    {
        $base = $request->validate([
            'product_id' => 'required|exists:products,id',
            'variant_id' => 'nullable|integer|exists:product_variants,id',
            'quantity'   => 'required|integer|min:1|max:100',
        ]);

        /** @var Product $product */
        $product = Product::findOrFail($base['product_id']);

        if (! $product->isCustomizable()) {
            return back()->with('error', 'This product does not accept customizations.');
        }

        // Split text + file inputs by their `customizations.{key}` shape
        $textInputs = (array) $request->input('customizations', []);
        $fileInputs = [];
        $rawFiles = $request->file('customizations', []);
        if (is_array($rawFiles)) {
            $fileInputs = $rawFiles;
        }

        // Validator throws ValidationException → Laravel sends user back with errors
        $normalized = $this->validator->validate($product, $textInputs, $fileInputs);

        $this->cart->addCustomized(
            user: $request->user(),
            product: $product,
            quantity: (int) $base['quantity'],
            variantId: $base['variant_id'] ?? null,
            normalized: $normalized,
        );

        return redirect()->route('cart.show')
            ->with('success', "Added \"{$product->name}\" with your customizations to the cart.");
    }
}
