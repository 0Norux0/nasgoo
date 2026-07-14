<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\VendorIntelligenceAlert as Alert;
use App\Services\VendorIntelligence\VendorIntelligenceManager;
use Illuminate\Console\Command;

/**
 * Phase 11B.4 §32 §44 — vendor-intelligence:prune command.
 *
 * Tidies the alerts table:
 *   1. Snoozed alerts whose expires_at has passed → back to active
 *   2. Resolved alerts older than 90 days → deleted (keep table lean)
 */
class PruneVendorIntelligence extends Command
{
    protected $signature = 'vendor-intelligence:prune {--resolved-days=90}';
    protected $description = 'Un-snooze expired alerts and delete very old resolved alerts';

    public function handle(VendorIntelligenceManager $manager): int
    {
        // Phase 11B.4 v11B.4.2 Defect 4 fix — respect feature flag.
        // Prune is safe when disabled (no data corruption) but running it
        // is pointless if the feature is off.
        if (! $manager->isEnabled()) {
            $this->warn('Vendor intelligence is disabled — nothing to prune.');
            return self::SUCCESS;
        }

        $now = now();

        // 1. Un-snooze expired
        $unsnoozed = Alert::where('status', Alert::STATUS_SNOOZED)
            ->whereNotNull('expires_at')
            ->where('expires_at', '<=', $now)
            ->update([
                'status' => Alert::STATUS_ACTIVE,
                'expires_at' => null,
            ]);

        $this->info("Unsnoozed {$unsnoozed} alerts");

        // 2. Delete old resolved
        $resolvedDays = (int) $this->option('resolved-days');
        $deleted = Alert::where('status', Alert::STATUS_RESOLVED)
            ->where('resolved_at', '<', $now->clone()->subDays($resolvedDays))
            ->delete();

        $this->info("Deleted {$deleted} resolved alerts older than {$resolvedDays} days");

        return self::SUCCESS;
    }
}
