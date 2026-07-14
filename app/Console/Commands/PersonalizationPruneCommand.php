<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\CustomerAffinity;
use App\Models\CustomerProductView;
use App\Models\PersonalizationFeedback;
use Illuminate\Console\Command;

/**
 * Phase 11B.3 §36 — prune expired personalization data.
 *
 * Deletes:
 *   - customer_product_views older than retention.customer_views_days (auth)
 *     or retention.guest_views_days (guest)
 *   - personalization_feedback with expires_at < NOW
 *   - customer_affinities with last_signal_at older than retention.affinity_stale_days
 *
 * All chunked to avoid table locks. Reports counts.
 */
class PersonalizationPruneCommand extends Command
{
    protected $signature = 'personalization:prune
                            {--dry-run : Show counts without deleting}
                            {--chunk=1000 : Chunk size}';

    protected $description = 'Prune expired personalization data (Phase 11B.3)';

    public function handle(): int
    {
        $dry   = (bool) $this->option('dry-run');
        $chunk = max(100, (int) $this->option('chunk'));

        $custDays  = (int) config('marketplace_personalization.retention.customer_views_days', 90);
        $guestDays = (int) config('marketplace_personalization.retention.guest_views_days', 30);
        $affDays   = (int) config('marketplace_personalization.retention.affinity_stale_days', 90);

        $custCutoff  = now()->subDays(max(1, $custDays));
        $guestCutoff = now()->subDays(max(1, $guestDays));
        $affCutoff   = now()->subDays(max(1, $affDays));

        // Customer views (older than custDays)
        $custQ = CustomerProductView::query()
            ->whereNotNull('user_id')
            ->where('viewed_at', '<', $custCutoff);
        $custCount = $custQ->count();

        // Guest views (older than guestDays)
        $guestQ = CustomerProductView::query()
            ->whereNull('user_id')
            ->where('viewed_at', '<', $guestCutoff);
        $guestCount = $guestQ->count();

        // Feedback (expired)
        $fbQ = PersonalizationFeedback::query()
            ->whereNotNull('expires_at')
            ->where('expires_at', '<', now());
        $fbCount = $fbQ->count();

        // Affinities (no recent signal)
        $affQ = CustomerAffinity::query()
            ->where(function ($q) use ($affCutoff) {
                $q->whereNull('last_signal_at')->orWhere('last_signal_at', '<', $affCutoff);
            });
        $affCount = $affQ->count();

        $this->info("Prune scope:");
        $this->info("  Customer views:  {$custCount}");
        $this->info("  Guest views:     {$guestCount}");
        $this->info("  Feedback:        {$fbCount}");
        $this->info("  Stale affinities: {$affCount}");

        if ($dry) {
            $this->info('DRY RUN — no deletions performed');
            return self::SUCCESS;
        }

        $deleted = 0;
        do {
            $n = $custQ->limit($chunk)->delete();
            $deleted += $n;
        } while ($n > 0);
        $this->info("Deleted {$deleted} customer view rows");

        $deleted = 0;
        do {
            $n = $guestQ->limit($chunk)->delete();
            $deleted += $n;
        } while ($n > 0);
        $this->info("Deleted {$deleted} guest view rows");

        $deleted = 0;
        do {
            $n = $fbQ->limit($chunk)->delete();
            $deleted += $n;
        } while ($n > 0);
        $this->info("Deleted {$deleted} feedback rows");

        $deleted = 0;
        do {
            $n = $affQ->limit($chunk)->delete();
            $deleted += $n;
        } while ($n > 0);
        $this->info("Deleted {$deleted} stale affinity rows");

        return self::SUCCESS;
    }
}
