<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Vite;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Phase 11B.1 v11B.1.2 §7 — translation provider abstraction.
        // Default: manual (no auto-generation, no network calls). Future
        // adapters (LibreTranslate, ImportProvider, etc.) can override this
        // binding without touching consumers (QueueProductTranslation,
        // TranslationService, Filament actions).
        $this->app->bind(
            \App\Services\Localization\Providers\TranslationProviderInterface::class,
            \App\Services\Localization\Providers\ManualTranslationProvider::class,
        );
    }

    public function boot(): void
    {
        $this->ensureRuntimeDirectoriesExist();

        // Prevent N+1 queries in non-production
        Model::shouldBeStrict(! app()->isProduction());

        // Force HTTPS in production
        if (app()->isProduction()) {
            URL::forceScheme('https');
        }

        // Vite prefetch for production performance
        app(Vite::class)->prefetch(concurrency: 3);

        // Phase 11B.4 v11B.4.2 Defect 11 fix — vendor intelligence observers.
        // Mark vendor_intelligence_summaries.stale_at whenever material
        // product/order/vendor data changes so the hourly stale-only
        // regeneration picks up only affected vendors (not the whole
        // marketplace on every product edit).
        \App\Models\Product::observe(\App\Observers\VendorIntelligence\ProductObserver::class);
        \App\Models\Order::observe(\App\Observers\VendorIntelligence\OrderObserver::class);
        \App\Models\Vendor::observe(\App\Observers\VendorIntelligence\VendorObserver::class);

        // Phase 11B.4 v11B.4.3 Fix 3 — product_translations workflow was
        // untouched by ProductObserver because translator edits go through
        // the ProductTranslation model directly (Filament), not through
        // Product::update(). This observer closes that gap.
        \App\Models\ProductTranslation::observe(
            \App\Observers\VendorIntelligence\ProductTranslationObserver::class
        );

        // Phase 10 v10.9 — super_admin shortcut. A common Laravel pattern:
        // super_admin returning true from Gate::before makes every ability
        // pass for them. Returning null (not false) lets the per-ability
        // closure decide for other roles. Pre-v10.9 this didn't exist, so
        // super_admin had to be explicitly listed in every Gate or hold
        // every permission via Spatie. That created the brittleness behind
        // the v10.9 defect (menu visible, /admin/reports 403): super_admin
        // had the role but didn't always have the reports.view permission
        // row, depending on whether the seeder ran after Phase 10 added it.
        Gate::before(function (User $user, string $ability) {
            if ($user->hasRole('super_admin')) {
                return true;
            }
            return null;   // let other gates / policies decide
        });

        // Phase 10 — admin reports authorization gate.
        // Phase 10 v10.9 — the Gate now uses User::canManageAdminReports(),
        // the SAME method that controls the Filament "Reports Dashboard"
        // navigation item visibility. Menu and route enforce identical rules
        // so they cannot drift. (Pre-v10.9 the menu was role-based and the
        // Gate was permission-based via `hasPermissionTo('reports.view')`;
        // on installations where the permission row was missing or the
        // Spatie cache was stale, the menu showed but the route returned
        // 403 — exactly the dev's reproduction. The role-based check is
        // robust against permission-table drift.)
        Gate::define('viewReports', function (User $user): bool {
            return $user->canManageAdminReports();
        });

        // Phase 11B.1 v11B.1.2 §6 — stale-translation detection.
        // When a product's English source content changes, any approved or
        // human-reviewed Arabic (or other locale) translations are marked
        // 'stale' so admins know they need re-approval. The English column
        // is NEVER altered by this observer — only the status of related
        // ProductTranslation rows.
        \App\Models\Product::saved(function (\App\Models\Product $p) {
            // Phase 11B.2 §23 — invalidate recommendation cache when the
            // product becomes unpublishable, goes out of stock, or has a
            // price change. The recommendation cache layer keys by source
            // product id so we only purge that product's cached recs.
            //
            // Phase 11B.2 v11B.2.1 §4 — for events that may have made a
            // product unsuitable AS A RECOMMENDED RESULT (status flips to
            // draft, stock to 0, vendor change), also bump the cache version
            // so source products that recommend this one re-resolve from
            // scratch instead of leaking the now-ineligible product.
            try {
                $relevantChanged = $p->wasChanged([
                    'status', 'stock', 'track_stock',
                    'price_minor', 'category_id', 'vendor_id',
                ]);
                if ($relevantChanged) {
                    $mgr = app(\App\Services\Recommendations\RecommendationManager::class);
                    $mgr->invalidate($p->id);  // source-of cache invalidation

                    // Reverse-reference safety: if the change could remove
                    // eligibility (status/stock/vendor change), bump the global
                    // version. Pure price changes don't need a bump because the
                    // shaped payload re-renders price on the next miss.
                    $eligibilityAffecting = $p->wasChanged(['status', 'stock', 'track_stock', 'vendor_id']);
                    if ($eligibilityAffecting) {
                        $mgr->bumpVersion();
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('v11B.2 recommendation cache invalidation failed', [
                    'product' => $p->id, 'error' => $e->getMessage(),
                ]);
            }

            // Phase 11B.1 v11B.1.2 §6 — stale-translation detection.
            // Only react when an English content column actually changed.
            if (! $p->wasChanged(['name', 'short_description', 'description'])) {
                return;
            }
            try {
                app(\App\Services\Localization\TranslationService::class)
                    ->markStaleIfSourceChanged($p);
            } catch (\Throwable $e) {
                \Log::warning('v11B.1.2 stale-translation detection failed', [
                    'product' => $p->id,
                    'error'   => $e->getMessage(),
                ]);
            }
        });

        // Phase 11B.2 v11B.2.1 §4 — Vendor cascade. When a vendor is
        // suspended/rejected, ALL their products become ineligible as
        // recommended items everywhere on the storefront. Without this,
        // suspended-vendor products would remain in cached rec lists for
        // up to 24h (the TTL). Bumping the global version forces all
        // cached recs to be re-resolved on next read. (Runtime eligibility
        // recheck in RecommendationManager::reapplyEligibility() is the
        // second line of defense.)
        \App\Models\Vendor::saved(function (\App\Models\Vendor $v) {
            try {
                if ($v->wasChanged('status')) {
                    app(\App\Services\Recommendations\RecommendationManager::class)->bumpVersion();
                }
            } catch (\Throwable $e) {
                \Log::warning('v11B.2.1 vendor cascade invalidation failed', [
                    'vendor' => $v->id, 'error' => $e->getMessage(),
                ]);
            }
        });

        // Phase 11B.2 v11B.2.1 §4 — Translation cascade. When an Arabic
        // translation transitions to/from APPROVED, the localized display_name
        // on cached rec items needs to refresh. Bump the version so the next
        // read re-runs TranslationService::displayFields.
        \App\Models\ProductTranslation::saved(function (\App\Models\ProductTranslation $t) {
            try {
                if ($t->wasChanged('status') || $t->wasRecentlyCreated) {
                    app(\App\Services\Recommendations\RecommendationManager::class)->bumpVersion();
                }
            } catch (\Throwable $e) {
                \Log::warning('v11B.2.1 translation cascade invalidation failed', [
                    'translation' => $t->id, 'error' => $e->getMessage(),
                ]);
            }
        });

        // Phase 11B.2 v11B.2.1 §4 — Admin curation cascade. When an admin
        // creates/edits/deletes a pinned/hidden/excluded/complementary
        // relationship, the affected source product's rec list must change
        // immediately. Bumping the version is the simplest correct fix.
        $bumpForRelationship = function () {
            try {
                app(\App\Services\Recommendations\RecommendationManager::class)->bumpVersion();
            } catch (\Throwable $e) {
                \Log::warning('v11B.2.1 admin-relationship cascade invalidation failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        };
        \App\Models\AdminProductRelationship::saved($bumpForRelationship);
        \App\Models\AdminProductRelationship::deleted($bumpForRelationship);

        // Phase 11B.2 v11B.2.1 §3 — purchase attribution. When an order
        // status changes, dispatch a queued job to scan recent recommendation
        // click/add_to_cart events for the user and insert purchase events
        // for matching products. The job is idempotent (unique constraint
        // on (order_item_id, event_type='purchase', product_id, type)) so
        // it can fire on every status save without double-counting.
        //
        // Refund/cancellation: the same job handles reversal — it sets
        // reversed_at = NOW on existing purchase events for that order.
        \App\Models\Order::saved(function (\App\Models\Order $o) {
            if (! $o->wasChanged('status')) {
                return;
            }
            $qualifying = [
                \App\Models\Order::STATUS_PAID,
                \App\Models\Order::STATUS_CONFIRMED,
                \App\Models\Order::STATUS_SHIPPED,
                \App\Models\Order::STATUS_DELIVERED,
                \App\Models\Order::STATUS_COMPLETED,
                \App\Models\Order::STATUS_REFUNDED,
                \App\Models\Order::STATUS_CANCELLED,
            ];
            if (! in_array($o->status, $qualifying, true)) {
                return;
            }
            try {
                \App\Jobs\RecordPurchaseAttributionJob::dispatch($o->id);
            } catch (\Throwable $e) {
                \Log::warning('v11B.2.1 purchase attribution dispatch failed', [
                    'order' => $o->id, 'error' => $e->getMessage(),
                ]);
            }
        });
    }

    private function ensureRuntimeDirectoriesExist(): void
    {
        foreach ([
            storage_path('app/public'),
            storage_path('framework/cache'),
            storage_path('framework/cache/data'),
            storage_path('framework/sessions'),
            storage_path('framework/testing'),
            storage_path('framework/views'),
            storage_path('logs'),
            base_path('bootstrap/cache'),
        ] as $path) {
            if (! File::isDirectory($path)) {
                File::makeDirectory($path, 0755, true);
            }
        }
    }
}
