<?php

declare(strict_types=1);

namespace App\Domain\Customization;

use App\Domain\Cart\CartService;
use App\Models\CartItem;
use App\Models\CartItemCustomization;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Phase 7 — CustomizationCartService.
 *
 * Adds a customized product to the cart and persists the per-field
 * customization snapshot rows. Customized items NEVER merge with existing
 * cart lines for the same product+variant — each add-to-cart submission
 * creates a fresh CartItem, since two customers' designs are not
 * interchangeable.
 *
 * Composes CartService (which still owns price/quantity logic) and
 * CustomizationFileStorage (which handles secure uploads).
 */
class CustomizationCartService
{
    public function __construct(
        protected CartService $cart,
        protected CustomizationFileStorage $storage,
    ) {}

    /**
     * Add a customized product to the user's cart, persisting all
     * customization snapshots. $normalized comes from
     * CustomizationFieldValidator::validate().
     */
    public function addCustomized(User $user, Product $product, int $quantity, ?int $variantId, Collection $normalized): CartItem
    {
        return DB::transaction(function () use ($user, $product, $quantity, $variantId, $normalized) {
            // 1. Create a FRESH cart line (do not merge with existing identical product+variant)
            $cartItem = $this->cart->addItem($user, $product, $quantity, $variantId, forceNewLine: true);

            // 2. Persist each customization snapshot
            $totalExtra = 0;
            foreach ($normalized as $entry) {
                /** @var \App\Models\ProductCustomizationField $field */
                $field = $entry['field'];
                $value = $entry['value'];
                $file  = $entry['file'];
                $extra = (int) $entry['extra_fee_minor'];

                $filePath = null;
                $fileName = null;
                $fileMime = null;
                $fileSize = null;

                if ($file) {
                    $filePath = $this->storage->storeCustomerUpload($file, $user->id);
                    $fileName = $file->getClientOriginalName();
                    $fileMime = $file->getMimeType();
                    $fileSize = $file->getSize() ?: null;
                }

                CartItemCustomization::create([
                    'cart_item_id'       => $cartItem->id,
                    'field_id'           => $field->id,
                    'field_key'          => $field->key,
                    'field_label'        => $field->label,
                    'field_type'         => $field->type,
                    'value'              => $value,
                    'file_path'          => $filePath,
                    'file_original_name' => $fileName,
                    'file_mime'          => $fileMime,
                    'file_size_bytes'    => $fileSize,
                    'extra_fee_minor'    => $extra,
                ]);

                $totalExtra += $extra;
            }

            // 3. Roll the per-field fees up onto the cart line so totals are deterministic
            $cartItem->update(['customization_fee_minor' => $totalExtra]);
            $this->cart->recomputeTotals($cartItem->cart);

            return $cartItem->fresh('customizations');
        });
    }

    /**
     * Remove a cart item along with its customization rows + uploaded files.
     */
    public function removeWithFiles(CartItem $cartItem): void
    {
        DB::transaction(function () use ($cartItem) {
            foreach ($cartItem->customizations as $c) {
                if ($c->file_path) {
                    $this->storage->delete($c->file_path);
                }
            }
            $cart = $cartItem->cart;
            $cartItem->delete();
            $this->cart->recomputeTotals($cart);
        });
    }
}
