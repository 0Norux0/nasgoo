<?php

declare(strict_types=1);

namespace App\Services\VendorIntelligence;

use App\Models\Product;
use App\Models\Vendor;
use App\Models\VendorIntelligenceAlert as Alert;
use App\Models\VendorIntelligenceFeedback;
use App\Models\VendorIntelligenceSummary;
use App\Models\VendorProductQualityScore;
use App\Services\Settings\SiteSettingsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.4 §5 §17 §32 §37 — orchestrator for vendor intelligence.
 *
 * Responsibilities:
 *   - Coordinate InventoryAlert / Opportunity / Performance / Quality
 *     services
 *   - Materialize alerts into `vendor_intelligence_alerts` idempotently
 *     (dedupe by vendor+type+entity — dev §32 "duplicate active alert
 *     should not be created")
 *   - Refresh `vendor_intelligence_summaries` rollup
 *   - Refresh `vendor_product_quality_scores`
 *   - Apply the vendor's dismiss/snooze feedback when returning alerts
 *     for display (§17)
 *   - Enforce vendor isolation on every read (§24 §37)
 *
 * Called from:
 *   - `VendorIntelligenceController::index` (fresh dashboard request)
 *   - `GenerateVendorIntelligence` command (scheduled batch)
 */
class VendorIntelligenceManager
{
    public function __construct(
        private readonly InventoryAlertService $inventory,
        private readonly VendorOpportunityService $opportunity,
        private readonly VendorPerformanceService $performance,
        private readonly VendorActionChecklistService $checklist,
        private readonly ProductQualityService $quality,
        private readonly VendorIntelligenceCacheService $cache,
        private readonly SiteSettingsService $settings,
    ) {}

    /**
     * Phase 11B.4 v11B.4.2 Defect 4 fix — canonical feature-flag check.
     *
     * Pre-v11B.4.2 the `enabled` config value existed in config/site.php
     * and could be edited via /admin/site-settings/vendor_intelligence
     * (after Defect 3 fix), but no code path actually READ it. Controller
     * always answered; commands always generated; React panel always
     * fetched. This method is now the ONE canonical source of truth.
     *
     * Callers:
     *   - VendorIntelligenceController::index / dismiss / snooze
     *   - GenerateVendorIntelligence::handle (honored unless --force)
     *   - PruneVendorIntelligence::handle
     *   - Admin\VendorIntelligenceController::index (shows disabled state)
     *   - HandleInertiaRequests (shares to React via
     *     siteSettings.vendor_intelligence.enabled)
     */
    public function isEnabled(): bool
    {
        try {
            $flag = $this->settings->get('vendor_intelligence.enabled', null);
            if ($flag !== null) return (bool) $flag;
        } catch (\Throwable $e) {
            \Log::warning('v11B.4.2 isEnabled read failed (defensive catch)', ['err' => $e->getMessage()]);
        }
        // Config fallback preserves pre-v11B.4.2 default (true)
        return (bool) config('site.defaults.vendor_intelligence.enabled', true);
    }

    /**
     * Full dashboard payload for a vendor, cached per-vendor per-locale.
     *
     * @return array<string,mixed>
     */
    public function dashboardFor(Vendor $vendor, string $locale = 'en'): array
    {
        return $this->cache->rememberDashboard($vendor->id, $locale, function () use ($vendor) {
            return $this->buildDashboardPayload($vendor);
        });
    }

    /**
     * Regenerate ALL intelligence data for a vendor: alerts, quality
     * scores, summary rollup. Called by the generate command.
     */
    public function regenerateForVendor(Vendor $vendor): void
    {
        if ($vendor->status !== Vendor::STATUS_APPROVED) {
            return;   // §7 suspended vendor excluded
        }

        DB::transaction(function () use ($vendor) {
            // 1. Compute + persist per-product quality scores
            $this->refreshProductQualityScores($vendor);

            // 2. Compute new alerts (inventory + opportunity)
            $inventoryAlerts   = $this->inventory->computeForVendor($vendor);
            $opportunityAlerts = $this->opportunity->computeForVendor($vendor);
            $allNewAlerts = array_merge($inventoryAlerts, $opportunityAlerts);

            // 3. Materialize alerts idempotently (dedupe by vendor+type+entity)
            $this->materializeAlerts($vendor, $allNewAlerts);

            // 4. Mark stale active alerts as resolved (§32)
            $this->resolveObsoleteAlerts($vendor, $allNewAlerts);

            // 5. Refresh summary rollup — also clears stale_at + sets
            //    last_generated_at (v11B.4.2 Defect 11)
            $this->refreshSummary($vendor);
        });

        // Flush the read cache after regeneration
        $this->cache->flush($vendor->id);
    }

    /**
     * Phase 11B.4 v11B.4.2 Defect 11 fix — mark a vendor's intelligence
     * as stale so the next scheduled --stale-only run picks it up.
     * Called by observers on product/order/translation/profile change.
     */
    public function markVendorStale(int $vendorId, string $reason): void
    {
        try {
            \App\Models\VendorIntelligenceSummary::updateOrCreate(
                ['vendor_id' => $vendorId],
                [
                    'stale_at' => now(),
                    'stale_reason' => substr($reason, 0, 64),
                ]
            );
            // Also flush the read cache so the vendor sees fresh data on
            // next request (even before the scheduler runs).
            $this->cache->flush($vendorId);
        } catch (\Throwable $e) {
            \Log::warning('v11B.4.2 markVendorStale failed', [
                'vendor_id' => $vendorId,
                'reason' => $reason,
                'err' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dismiss a suggestion (§17). Critical types cannot be permanently
     * dismissed — reappear on next regeneration.
     */
    public function dismissSuggestion(Vendor $vendor, string $suggestionType, string $entityType, ?int $entityId): void
    {
        if (in_array($suggestionType, Alert::NON_DISMISSABLE_TYPES, true)) {
            // Reject dismissal of critical types
            return;
        }

        VendorIntelligenceFeedback::create([
            'vendor_id'       => $vendor->id,
            'suggestion_type' => $suggestionType,
            'entity_type'     => $entityType,
            'entity_id'       => $entityId,
            'action'          => VendorIntelligenceFeedback::ACTION_DISMISSED,
            'dismissed_at'    => now(),
        ]);

        // Update the underlying alert row so future reads exclude it
        Alert::where('vendor_id', $vendor->id)
            ->where('alert_type', $suggestionType)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('status', Alert::STATUS_ACTIVE)
            ->update(['status' => Alert::STATUS_DISMISSED]);

        $this->cache->flush($vendor->id);
    }

    /**
     * Snooze a suggestion for the vendor's configured duration.
     */
    public function snoozeSuggestion(Vendor $vendor, string $suggestionType, string $entityType, ?int $entityId, ?int $days = null): void
    {
        $snoozeDays = $days ?? (int) $this->settings->get('vendor_intelligence.default_snooze_days', 7);
        $snoozeUntil = now()->addDays($snoozeDays);

        VendorIntelligenceFeedback::create([
            'vendor_id'       => $vendor->id,
            'suggestion_type' => $suggestionType,
            'entity_type'     => $entityType,
            'entity_id'       => $entityId,
            'action'          => VendorIntelligenceFeedback::ACTION_SNOOZED,
            'snoozed_until'   => $snoozeUntil,
        ]);

        Alert::where('vendor_id', $vendor->id)
            ->where('alert_type', $suggestionType)
            ->where('entity_type', $entityType)
            ->where('entity_id', $entityId)
            ->where('status', Alert::STATUS_ACTIVE)
            ->update([
                'status' => Alert::STATUS_SNOOZED,
                'expires_at' => $snoozeUntil,
            ]);

        $this->cache->flush($vendor->id);
    }

    // ─── private helpers ──────────────────────────────────────────

    private function buildDashboardPayload(Vendor $vendor): array
    {
        $limit = (int) $this->settings->get('vendor_intelligence.dashboard_alert_limit', 10);

        // §17 skip dismissed + not-yet-due snoozed alerts
        $now = now();
        $alerts = Alert::where('vendor_id', $vendor->id)
            ->where(function ($q) use ($now) {
                $q->where('status', Alert::STATUS_ACTIVE)
                  ->orWhere(function ($q2) use ($now) {
                      $q2->where('status', Alert::STATUS_SNOOZED)
                         ->whereNotNull('expires_at')
                         ->where('expires_at', '<=', $now);
                  });
            })
            ->orderByRaw("
                CASE priority
                    WHEN '" . Alert::PRIORITY_CRITICAL . "' THEN 1
                    WHEN '" . Alert::PRIORITY_HIGH     . "' THEN 2
                    WHEN '" . Alert::PRIORITY_MEDIUM   . "' THEN 3
                    WHEN '" . Alert::PRIORITY_LOW      . "' THEN 4
                    WHEN '" . Alert::PRIORITY_INFO     . "' THEN 5
                    ELSE 6 END
            ")
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();

        $summary = VendorIntelligenceSummary::firstOrNew(['vendor_id' => $vendor->id]);

        return [
            'summary'         => $this->serializeSummary($summary),
            'alerts'          => $alerts->map(fn ($a) => $this->serializeAlert($a))->all(),
            'top_selling'     => $this->performance->topSellingProducts($vendor, 30, 5),
            'most_viewed'     => $this->performance->mostViewedProducts($vendor, 30, 5),
            'most_wishlisted' => $this->performance->mostWishlistedProducts($vendor, 5),
            'checklist'       => $this->checklist->checklistFor(
                $vendor,
                $summary->low_stock_count ?? 0,
                $summary->missing_arabic_count ?? 0,
                $summary->missing_images_count ?? 0,
                $summary->store_completion_score ?? 0,
                $this->activeSupportTicketCount($vendor)
            ),
            'store_completion' => $this->checklist->storeCompletion($vendor),
        ];
    }

    private function refreshProductQualityScores(Vendor $vendor): void
    {
        $products = Product::where('vendor_id', $vendor->id)
            ->whereIn('status', ['published', 'pending_review'])
            ->get();

        foreach ($products as $p) {
            $result = $this->quality->scoreProduct($p);
            VendorProductQualityScore::updateOrCreate(
                ['product_id' => $p->id],
                [
                    'vendor_id' => $vendor->id,
                    'score' => $result['score'],
                    'missing_fields' => $result['missing_fields'],
                    'score_breakdown' => $result['breakdown'],
                    'computed_at' => now(),
                ]
            );
        }
    }

    /**
     * @param list<array{alert_type:string, entity_type:string, entity_id:int, priority:string, evidence:array<string,mixed>}> $newAlerts
     */
    private function materializeAlerts(Vendor $vendor, array $newAlerts): void
    {
        foreach ($newAlerts as $alertData) {
            // Phase 11B.4 v11B.4.2 Defect 5 fix — compute the dedupe key.
            // Migration 2026_12_01 added a UNIQUE index on this column.
            $dedupeKey = Alert::buildDedupeKey(
                $vendor->id,
                $alertData['alert_type'],
                $alertData['entity_type'],
                $alertData['entity_id'],
            );

            // Dedupe by dedupe_key across OPEN_STATUSES (active + snoozed
            // + dismissed all share the key; resolved rows have NULL).
            $existing = Alert::where('vendor_id', $vendor->id)
                ->where('active_dedupe_key', $dedupeKey)
                ->first();

            if ($existing) {
                // Update evidence but preserve status (dismissed/snoozed stays)
                if ($existing->status === Alert::STATUS_ACTIVE) {
                    $existing->update([
                        'evidence' => $alertData['evidence'],
                        'priority' => $alertData['priority'],
                    ]);
                }
                continue;
            }

            // Skip if the vendor previously dismissed this and it's non-critical
            $wasDismissed = VendorIntelligenceFeedback::where('vendor_id', $vendor->id)
                ->where('suggestion_type', $alertData['alert_type'])
                ->where('entity_type', $alertData['entity_type'])
                ->where('entity_id', $alertData['entity_id'])
                ->where('action', VendorIntelligenceFeedback::ACTION_DISMISSED)
                ->exists();

            if ($wasDismissed && ! in_array($alertData['alert_type'], Alert::NON_DISMISSABLE_TYPES, true)) {
                continue;
            }

            Alert::create([
                'vendor_id'   => $vendor->id,
                'alert_type'  => $alertData['alert_type'],
                'entity_type' => $alertData['entity_type'],
                'entity_id'   => $alertData['entity_id'],
                'priority'    => $alertData['priority'],
                'status'      => Alert::STATUS_ACTIVE,
                'evidence'    => $alertData['evidence'],
                'active_dedupe_key' => $dedupeKey,
            ]);
        }
    }

    /**
     * Mark active alerts as resolved when they no longer appear in the
     * newly-computed set (§32 "alert becomes resolved when condition no
     * longer exists").
     *
     * @param list<array{alert_type:string, entity_type:string, entity_id:int, priority:string, evidence:array<string,mixed>}> $newAlerts
     */
    private function resolveObsoleteAlerts(Vendor $vendor, array $newAlerts): void
    {
        $stillPresent = collect($newAlerts)
            ->map(fn ($a) => "{$a['alert_type']}:{$a['entity_type']}:{$a['entity_id']}")
            ->flip()
            ->all();

        Alert::where('vendor_id', $vendor->id)
            ->where('status', Alert::STATUS_ACTIVE)
            ->get()
            ->each(function (Alert $a) use ($stillPresent) {
                $key = "{$a->alert_type}:{$a->entity_type}:{$a->entity_id}";
                if (! isset($stillPresent[$key])) {
                    // v11B.4.2 Defect 5 fix — null the dedupe_key so a
                    // future active alert with the same key can be
                    // inserted without a UNIQUE-index conflict.
                    $a->update([
                        'status' => Alert::STATUS_RESOLVED,
                        'resolved_at' => now(),
                        'active_dedupe_key' => null,
                    ]);
                }
            });
    }

    private function refreshSummary(Vendor $vendor): void
    {
        $productIds = Product::where('vendor_id', $vendor->id)->pluck('id')->all();
        $activeProductIds = Product::where('vendor_id', $vendor->id)
            ->where('status', 'published')->pluck('id')->all();

        $missingArabic = 0;
        $missingImages = 0;
        $qualitySum = 0;
        $qualityCount = 0;

        if (! empty($productIds)) {
            $qualityScores = VendorProductQualityScore::whereIn('product_id', $productIds)->get();
            foreach ($qualityScores as $q) {
                $missing = (array) $q->missing_fields;
                if (in_array('i18n.arabic_title', $missing) || in_array('i18n.arabic_description', $missing)) {
                    $missingArabic++;
                }
                if (in_array('media.no_image', $missing) || in_array('media.additional_images', $missing)) {
                    $missingImages++;
                }
                $qualitySum += $q->score;
                $qualityCount++;
            }
        }

        $activeAlerts = Alert::where('vendor_id', $vendor->id)
            ->where('status', Alert::STATUS_ACTIVE)
            ->get(['alert_type']);

        $outOfStock = $activeAlerts->where('alert_type', Alert::TYPE_OUT_OF_STOCK)->count();
        $lowStock   = $activeAlerts->whereIn('alert_type', [Alert::TYPE_LOW_STOCK, Alert::TYPE_FAST_MOVING_LOW_STOCK])->count();
        $slowMoving = $activeAlerts->where('alert_type', Alert::TYPE_SLOW_MOVING)->count();

        $storeCompletion = $this->checklist->storeCompletion($vendor);

        VendorIntelligenceSummary::updateOrCreate(
            ['vendor_id' => $vendor->id],
            [
                'total_products'         => count($productIds),
                'total_active_products'  => count($activeProductIds),
                'out_of_stock_count'     => $outOfStock,
                'low_stock_count'        => $lowStock,
                'slow_moving_count'      => $slowMoving,
                'missing_arabic_count'   => $missingArabic,
                'missing_images_count'   => $missingImages,
                'active_alerts_count'    => $activeAlerts->count(),
                'store_completion_score' => $storeCompletion['score'],
                'store_missing_fields'   => implode(',', $storeCompletion['missing_fields']),
                'avg_product_quality'    => $qualityCount > 0 ? (int) round($qualitySum / $qualityCount) : 0,
                'computed_at'            => now(),
                // v11B.4.2 Defect 11 fix — track staleness lifecycle.
                // Just finished a fresh generation → clear stale flags.
                'stale_at'               => null,
                'stale_reason'           => null,
                'last_generated_at'      => now(),
            ]
        );
    }

    private function activeSupportTicketCount(Vendor $vendor): int
    {
        // Phase 11B.4 v11B.5 BUG FIX:
        //   Pre-v11B.5 read `Schema::hasTable('tickets')` — that table
        //   name doesn't exist. The actual table is `support_tickets`
        //   (see database/migrations/*create_support_tickets*).
        //   Consequence: the check always returned 0 and the "Respond to
        //   support tickets" checklist item never surfaced even when
        //   the vendor had open tickets.
        if (! Schema::hasTable('support_tickets')) return 0;
        return (int) DB::table('support_tickets')
            ->where('vendor_id', $vendor->id)
            ->whereIn('status', ['open', 'in_progress', 'awaiting_reply'])
            ->count();
    }

    private function serializeSummary(VendorIntelligenceSummary $s): array
    {
        return [
            'total_products'         => (int) $s->total_products,
            'total_active_products'  => (int) $s->total_active_products,
            'out_of_stock_count'     => (int) $s->out_of_stock_count,
            'low_stock_count'        => (int) $s->low_stock_count,
            'slow_moving_count'      => (int) $s->slow_moving_count,
            'missing_arabic_count'   => (int) $s->missing_arabic_count,
            'missing_images_count'   => (int) $s->missing_images_count,
            'active_alerts_count'    => (int) $s->active_alerts_count,
            'store_completion_score' => (int) $s->store_completion_score,
            'avg_product_quality'    => (int) $s->avg_product_quality,
            'computed_at'            => $s->computed_at?->toIso8601String(),
            // Phase 11B.4 v11B.4.2 Defect 11 fix — expose freshness info
            // to the panel. `is_stale` is a derived boolean so React
            // doesn't need to reason about timestamps.
            'last_generated_at'      => $s->last_generated_at?->toIso8601String(),
            'stale_at'               => $s->stale_at?->toIso8601String(),
            'stale_reason'           => $s->stale_reason,
            'is_stale'               => $s->stale_at !== null,
        ];
    }

    private function serializeAlert(Alert $a): array
    {
        return [
            'id'          => $a->id,
            'alert_type'  => $a->alert_type,
            'entity_type' => $a->entity_type,
            'entity_id'   => $a->entity_id,
            'priority'    => $a->priority,
            'status'      => $a->status,
            'evidence'    => $a->evidence,
            'is_dismissable' => ! in_array($a->alert_type, Alert::NON_DISMISSABLE_TYPES, true),
            'action_link' => $this->buildActionLink($a),
        ];
    }

    private function buildActionLink(Alert $a): string
    {
        if ($a->entity_type === 'product' && $a->entity_id) {
            return match ($a->alert_type) {
                Alert::TYPE_OUT_OF_STOCK,
                Alert::TYPE_LOW_STOCK,
                Alert::TYPE_FAST_MOVING_LOW_STOCK,
                Alert::TYPE_NO_STOCK_TRACKING => "/vendor/products/{$a->entity_id}/edit",
                Alert::TYPE_MISSING_ARABIC,
                Alert::TYPE_MISSING_IMAGES,
                Alert::TYPE_HIGH_VIEW_LOW_CONVERSION => "/vendor/products/{$a->entity_id}/edit",
                Alert::TYPE_PROMOTION_OPPORTUNITY => "/vendor/promotions/create?product={$a->entity_id}",
                default => "/vendor/products/{$a->entity_id}/edit",
            };
        }
        return '/vendor';
    }
}
