<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Product;
use App\Models\ProductTranslation;
use App\Services\Localization\Providers\TranslationProviderInterface;
use App\Services\Localization\TranslationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Phase 11B.1 v11B.1.2 §8 — asynchronous translation request.
 *
 * Per dev §8:
 *   - "Do not perform translation synchronously during a customer page request."
 *   - "Do not make the customer wait for translation generation."
 *
 * Dispatched by:
 *   - VendorProductController on product save (when Arabic fields untouched)
 *   - Filament admin "Queue translation" bulk action
 *   - Artisan `translations:queue` command (future)
 *
 * Behavior:
 *   - If a translation row already exists with status approved/human_reviewed,
 *     do nothing (don't displace human-approved content).
 *   - Otherwise upsert a row with status='pending'.
 *   - If the bound provider's autoGenerates() is true, call translate()
 *     and on success store with status='machine_draft' (NEVER published
 *     by default per dev §13).
 *   - Failures are logged but never thrown — translation generation must
 *     never break the storefront.
 */
class QueueProductTranslation implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(
        public int $productId,
        public string $locale,
        public string $field,
    ) {}

    public function handle(
        TranslationProviderInterface $provider,
        TranslationService $svc,
    ): void {
        $product = Product::find($this->productId);
        if (! $product) {
            return;  // product deleted; nothing to translate
        }

        // Don't overwrite published or reviewer-approved content.
        $existing = ProductTranslation::query()
            ->where('product_id', $product->id)
            ->where('locale', $this->locale)
            ->where('field', $this->field)
            ->first();
        if ($existing && in_array($existing->status, [
            ProductTranslation::STATUS_APPROVED,
            ProductTranslation::STATUS_HUMAN_REVIEWED,
        ], true)) {
            return;
        }

        // English source — what we'd ask the provider to translate.
        $source = match ($this->field) {
            'name'              => $product->name,
            'short_description' => $product->short_description,
            'description'       => $product->description,
            default             => null,
        };
        if ($source === null || $source === '') {
            // No English source → nothing to translate. Skip silently.
            return;
        }

        // Default: write a pending row so admins see it in the workspace.
        $status = ProductTranslation::STATUS_PENDING;
        $value  = null;
        $prov   = ProductTranslation::SOURCE_MANUAL;

        if ($provider->autoGenerates()) {
            try {
                $translated = $provider->translate($source, 'en', $this->locale);
                if (is_string($translated) && $translated !== '') {
                    $status = ProductTranslation::STATUS_MACHINE_DRAFT;
                    $value  = $translated;
                    $prov   = ProductTranslation::SOURCE_MACHINE;
                }
            } catch (\Throwable $e) {
                \Log::warning('v11B.1.2 translation provider failed', [
                    'provider' => $provider->name(),
                    'product'  => $product->id,
                    'field'    => $this->field,
                    'error'    => $e->getMessage(),
                ]);
                // Fall through — row remains 'pending'
            }
        }

        $svc->setTranslation(
            product:    $product,
            locale:     $this->locale,
            field:      $this->field,
            value:      $value,
            status:     $status,
            provenance: $prov,
        );
    }
}
