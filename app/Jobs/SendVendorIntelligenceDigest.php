<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Mail\VendorIntelligenceDigestMail;
use App\Models\Vendor;
use App\Models\VendorIntelligenceAlert as Alert;
use App\Models\VendorIntelligenceSummary;
use App\Services\Settings\SiteSettingsService;
use App\Services\VendorIntelligence\VendorIntelligenceManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

/**
 * Phase 11B.4 v11B.4.3 Fix 2 — vendor intelligence digest dispatcher.
 *
 * Per directive §4 the digest MUST NOT go to:
 *   • pending / rejected / suspended vendors
 *   • vendors without a valid email
 *   • vendors opted out
 *   • vendors where the master feature flag is off
 *   • vendors where digest_emails_enabled=false
 *   • vendors with no active alerts (or fewer than digest_min_critical)
 *   • vendors within the throttle window (default 24h)
 *
 * All eight gates are enforced HERE in the job so the caller (the
 * generate command) can dispatch blindly without duplicating rules.
 *
 * Queue: implements ShouldQueue so the generate command doesn't block
 * on SMTP. Falls back to sync driver when the app has no queue worker
 * configured (`config('queue.default') === 'sync'` — Laravel's normal
 * behavior).
 */
class SendVendorIntelligenceDigest implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $vendorId) {}

    public function handle(
        VendorIntelligenceManager $manager,
        SiteSettingsService $settings,
    ): void {
        // ─── Gate 1: master feature flag ───────────────────────────
        if (! $manager->isEnabled()) return;

        // ─── Gate 2: digest emails opt-in flag ─────────────────────
        $digestEnabled = (bool) $settings->get(
            'vendor_intelligence.digest_emails_enabled',
            (bool) config('site.defaults.vendor_intelligence.digest_emails_enabled', false),
        );
        if (! $digestEnabled) return;

        // ─── Gate 3: vendor exists + approved ───────────────────────
        $vendor = Vendor::find($this->vendorId);
        if ($vendor === null) return;
        if ($vendor->status !== Vendor::STATUS_APPROVED) return;

        // ─── Gate 4: vendor has a valid email ───────────────────────
        // Prefer business_email; fall back to user->email.
        $to = $vendor->business_email
            ?: (optional($vendor->user)->email);
        if (! $to || ! filter_var($to, FILTER_VALIDATE_EMAIL)) return;

        // ─── Gate 5: vendor opted out ───────────────────────────────
        $summary = VendorIntelligenceSummary::where('vendor_id', $vendor->id)->first();
        if ($summary === null) return;   // no data → nothing to summarize
        if ((bool) $summary->email_opted_out) return;

        // ─── Gate 6: has alerts / meets minimum criticality ─────────
        $activeAlerts = Alert::where('vendor_id', $vendor->id)
            ->where('status', Alert::STATUS_ACTIVE)
            ->get();
        if ($activeAlerts->isEmpty()) return;

        $minCritical = (int) $settings->get(
            'vendor_intelligence.digest_min_critical',
            (int) config('site.defaults.vendor_intelligence.digest_min_critical', 1),
        );
        // digest_min_critical=0 means "any active alert is enough";
        // otherwise require at least N critical.
        if ($minCritical > 0) {
            $criticalCount = $activeAlerts->where('priority', Alert::PRIORITY_CRITICAL)->count();
            if ($criticalCount < $minCritical) return;
        }

        // ─── Gate 7: throttle window ────────────────────────────────
        $throttleHours = (int) $settings->get(
            'vendor_intelligence.digest_throttle_hours',
            (int) config('site.defaults.vendor_intelligence.digest_throttle_hours', 24),
        );
        if ($summary->last_digest_sent_at !== null) {
            $hoursSince = $summary->last_digest_sent_at->diffInHours(now());
            if ($hoursSince < $throttleHours) return;
        }

        // ─── Compose payload (PII-free) ─────────────────────────────
        $topAlerts = $activeAlerts
            ->sortBy(fn ($a) => match ($a->priority) {
                Alert::PRIORITY_CRITICAL => 0,
                Alert::PRIORITY_HIGH     => 1,
                Alert::PRIORITY_MEDIUM   => 2,
                Alert::PRIORITY_LOW      => 3,
                default                  => 4,
            })
            ->take(5)
            ->map(fn ($a) => [
                'alert_type' => $a->alert_type,
                'priority'   => $a->priority,
                // Only include marketplace-side aggregates from evidence.
                // Explicit whitelist — no customer_id / customer_email / etc.
                'evidence' => array_intersect_key((array) $a->evidence, array_flip([
                    'product_name', 'variant_label', 'stock', 'threshold',
                    'recent_orders', 'age_days', 'days_since_last_order',
                    'views', 'purchases', 'conversion_rate',
                    'wishlist_adds', 'cart_adds', 'abandonment',
                    'window_days', 'search_term', 'search_count', 'locale',
                ])),
            ])
            ->values()
            ->all();

        $data = [
            'summary' => [
                'active_alerts_count'    => (int) $summary->active_alerts_count,
                'critical_alerts_count'  => $activeAlerts->where('priority', Alert::PRIORITY_CRITICAL)->count(),
                'high_alerts_count'      => $activeAlerts->where('priority', Alert::PRIORITY_HIGH)->count(),
                'out_of_stock_count'     => (int) $summary->out_of_stock_count,
                'low_stock_count'        => (int) $summary->low_stock_count,
                'slow_moving_count'      => (int) $summary->slow_moving_count,
                'missing_arabic_count'   => (int) $summary->missing_arabic_count,
                'missing_images_count'   => (int) $summary->missing_images_count,
                'avg_product_quality'    => (int) $summary->avg_product_quality,
            ],
            'top_alerts'    => $topAlerts,
            'dashboard_url' => url('/vendor'),
        ];

        // ─── Dispatch (locale-aware) ────────────────────────────────
        $preferredLocale = optional($vendor->user)->locale ?? app()->getLocale();
        try {
            Mail::to($to)
                ->locale($preferredLocale)
                ->send(new VendorIntelligenceDigestMail($vendor, $data));

            // Record send time to enforce throttle on the next run
            $summary->update(['last_digest_sent_at' => now()]);
        } catch (\Throwable $e) {
            \Log::warning('v11B.4.3 vendor intelligence digest failed', [
                'vendor_id' => $vendor->id,
                'to' => $to,
                'err' => $e->getMessage(),
            ]);
        }
    }
}
