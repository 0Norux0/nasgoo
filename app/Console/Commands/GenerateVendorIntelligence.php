<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Vendor;
use App\Services\VendorIntelligence\VendorIntelligenceManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Phase 11B.4 §25 §44 — vendor-intelligence:generate command.
 *
 * Usage:
 *   php artisan vendor-intelligence:generate           (all approved vendors)
 *   php artisan vendor-intelligence:generate --vendor=1
 *
 * §25 requirements met:
 *   - idempotent (regenerateForVendor is transactional, dedupes alerts)
 *   - chunked (100 vendors per batch)
 *   - retry-safe (each vendor's regeneration is independent; one failure
 *     doesn't abort the rest)
 *   - memory-safe (uses chunkById, not ->get())
 *   - logged (Log::info on batch progress, Log::error on per-vendor failure)
 */
class GenerateVendorIntelligence extends Command
{
    protected $signature = 'vendor-intelligence:generate
                            {--vendor= : Only regenerate for one vendor ID}
                            {--chunk=100 : Chunk size for batch runs}
                            {--stale-only : Only regenerate vendors whose stale_at is set}
                            {--send-emails : After regeneration, dispatch digest emails to vendors with alerts}
                            {--force : Bypass the vendor_intelligence.enabled feature flag}';

    protected $description = 'Regenerate vendor intelligence summaries, alerts, and product quality scores';

    public function handle(VendorIntelligenceManager $manager): int
    {
        // Phase 11B.4 v11B.4.2 Defect 4 fix — feature-flag enforcement.
        // Pre-v11B.4.2 the command ran regardless. Now it exits cleanly
        // when the feature is disabled. Use --force to override (e.g. to
        // regenerate one vendor for debugging while the feature is off).
        if (! $manager->isEnabled() && ! $this->option('force')) {
            $this->warn('Vendor intelligence is disabled (site.defaults.vendor_intelligence.enabled = false).');
            $this->warn('Use --force to override.');
            return self::SUCCESS;   // exit 0 — this is intentional, not an error
        }

        $vendorId = $this->option('vendor');
        $chunkSize = (int) $this->option('chunk');
        $staleOnly = (bool) $this->option('stale-only');
        $sendEmails = (bool) $this->option('send-emails');

        if ($vendorId) {
            $vendor = Vendor::find($vendorId);
            if (! $vendor) {
                $this->error("Vendor #{$vendorId} not found.");
                return self::FAILURE;
            }
            $this->info("Regenerating intelligence for vendor #{$vendor->id} ({$vendor->business_name})...");
            $manager->regenerateForVendor($vendor);
            if ($sendEmails) {
                // v11B.4.3 Fix 2 — job enforces all send-side gates
                \App\Jobs\SendVendorIntelligenceDigest::dispatch($vendor->id);
                $this->info("Digest job dispatched for vendor #{$vendor->id}.");
            }
            $this->info("Done.");
            return self::SUCCESS;
        }

        $totalProcessed = 0;
        $totalFailed = 0;
        $totalDispatched = 0;

        $query = Vendor::where('status', Vendor::STATUS_APPROVED);

        // Phase 11B.4 v11B.4.2 Defect 11 fix — --stale-only mode.
        // Skips vendors whose summary is fresh; only touches vendors marked
        // stale by observers (see ProductObserver / OrderObserver).
        if ($staleOnly) {
            $staleVendorIds = \App\Models\VendorIntelligenceSummary::whereNotNull('stale_at')
                ->pluck('vendor_id')->all();
            if (empty($staleVendorIds)) {
                $this->info('No stale vendors — nothing to do.');
                return self::SUCCESS;
            }
            $query->whereIn('id', $staleVendorIds);
            $this->info('Running stale-only regeneration for ' . count($staleVendorIds) . ' vendors.');
        }

        $query->chunkById($chunkSize, function ($vendors) use ($manager, $sendEmails, &$totalProcessed, &$totalFailed, &$totalDispatched) {
                foreach ($vendors as $vendor) {
                    try {
                        $manager->regenerateForVendor($vendor);
                        $totalProcessed++;

                        // Phase 11B.4 v11B.4.3 Fix 2 — dispatch digest job.
                        // The JOB is authoritative on whether to actually
                        // send (checks feature flags, throttle, opt-out,
                        // alert count, vendor status). Command just fires.
                        if ($sendEmails) {
                            \App\Jobs\SendVendorIntelligenceDigest::dispatch($vendor->id);
                            $totalDispatched++;
                        }
                    } catch (\Throwable $e) {
                        $totalFailed++;
                        Log::error('v11B.4 vendor-intelligence:generate failed for vendor', [
                            'vendor_id' => $vendor->id,
                            'err' => $e->getMessage(),
                        ]);
                        $this->warn("Vendor #{$vendor->id} failed: {$e->getMessage()}");
                    }
                }
                $this->info("Batch processed: {$totalProcessed} successful, {$totalFailed} failed");
            });

        Log::info('v11B.4 vendor-intelligence:generate complete', [
            'processed' => $totalProcessed,
            'failed' => $totalFailed,
            'digests_dispatched' => $totalDispatched,
        ]);
        $this->info("Complete. {$totalProcessed} vendors processed, {$totalFailed} failed.");
        if ($sendEmails) {
            $this->info("Digest jobs dispatched: {$totalDispatched} (individual job decides whether to actually send).");
        }

        return $totalFailed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
