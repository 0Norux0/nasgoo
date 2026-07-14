<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Scheduled tasks (placeholders — wired up in later phases)
|--------------------------------------------------------------------------
*/

// Supplier sync, deal-of-day activation, subscription expiry, payout cron
// all live here from Phase 5 onwards.
Schedule::command('queue:prune-failed --hours=72')->daily();

// Phase 11B.2 §24 — nightly recommendation aggregation. Idempotent; safe
// to run multiple times. The `--since=2` flag keeps the work bounded to
// the last 2 days of orders for an incremental refresh; periodically
// the dev can run the unbounded version (`recommendations:generate`)
// or the truncate-and-rebuild (`--truncate`) for a full recompute.
Schedule::command('recommendations:generate --since=2')->dailyAt('03:30');

// Phase 11B.3 §25 §36 — personalization maintenance.
//   03:00  daily prune of expired views/feedback/stale affinities
//   03:15  daily incremental rebuild of active customers' affinity profiles
Schedule::command('personalization:prune')->dailyAt('03:00');
Schedule::command('personalization:rebuild --stale-days=1')->dailyAt('03:15');

// Phase 11B.4 v11B.4.2 Defect 2 fix — vendor intelligence scheduling.
// Was previously not wired at all. schedule:list showed nothing for the
// vendor-intelligence commands.
//
// Two entries:
//   1. hourly stale-only regeneration — cheap (only touches vendors with
//      pending changes from ProductObserver / OrderObserver — see Defect 11)
//   2. daily prune to clean up expired snoozes + very old resolved rows
//
// Both wrapped with withoutOverlapping() so a slow run doesn't stack.
// onOneServer() intentionally omitted — requires a shared cache driver
// with lock support; safe to omit for single-server deployments.
// Add ->onOneServer() when running in a multi-app-server cluster.
Schedule::command('vendor-intelligence:generate --stale-only')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('vendor-intelligence:prune')
    ->dailyAt('03:00')
    ->withoutOverlapping();
