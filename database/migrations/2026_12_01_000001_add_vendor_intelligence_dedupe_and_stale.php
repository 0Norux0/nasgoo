<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.4 v11B.4.2 — Defect 5 (DB-level dedupe UNIQUE) + Defect 11
 * (stale marking columns) additive migration.
 *
 * ─── Defect 5: real UNIQUE dedupe ───────────────────────────────────
 *
 * Pre-v11B.4.2 the dev's directive said:
 *   "Migration comments claim duplicate active alert protection, but
 *    only a normal index exists, not a database unique constraint."
 *
 * Correct. The v11B.4 migration created `via_uniqness_idx` — a regular
 * index. Service-level dedup logic (Manager::materializeAlerts) is
 * susceptible to race conditions where two concurrent
 * regenerateForVendor calls could both check-then-insert the same alert.
 *
 * Fix: add a nullable `active_dedupe_key` column. For alerts in
 * unresolved states (active/snoozed/dismissed), the key is the
 * deterministic string `vendor:{id}|type:{type}|entity:{et}:{eid}`.
 * For resolved/expired historical alerts, the key is NULL. MySQL/PgSQL
 * treat multiple NULLs as distinct, so historical rows don't conflict.
 *
 * Migration ORDER MATTERS:
 *   1. Add the column (nullable, unindexed)
 *   2. Backfill for all existing unresolved alerts
 *   3. RESOLVE duplicates FIRST (keep newest per key, mark rest resolved)
 *   4. Only then add the UNIQUE index
 *
 * ─── Defect 11: stale marking columns ──────────────────────────────
 *
 * Pre-v11B.4.2 the summary table had no way to signal "this vendor's
 * intelligence is out of date". Observers (created by this release) now
 * write to these fields when product/order/translation/profile changes
 * occur. The `vendor-intelligence:generate --stale-only` mode uses
 * `stale_at IS NOT NULL` to skip fresh vendors.
 *
 * The dashboard reads `last_generated_at` so vendors can see when the
 * data was last refreshed — no more implicit "is this real-time?" question.
 */
return new class extends Migration {

    public function up(): void
    {
        // ─── (1) Add active_dedupe_key column ────────────────────────
        if (! Schema::hasColumn('vendor_intelligence_alerts', 'active_dedupe_key')) {
            Schema::table('vendor_intelligence_alerts', function (Blueprint $t) {
                $t->string('active_dedupe_key', 255)->nullable()->after('status');
            });
        }

        // ─── (2) Backfill for unresolved rows ────────────────────────
        // Only alerts in active/snoozed/dismissed states get a key.
        // Resolved/expired remain NULL (no conflict, historical).
        DB::table('vendor_intelligence_alerts')
            ->whereIn('status', ['active', 'snoozed', 'dismissed'])
            ->orderBy('id')
            ->chunkById(500, function ($rows) {
                foreach ($rows as $r) {
                    $key = sprintf(
                        'vendor:%d|type:%s|entity:%s:%s',
                        $r->vendor_id,
                        $r->alert_type,
                        (string) ($r->entity_type ?? '-'),
                        (string) ($r->entity_id ?? '-'),
                    );
                    DB::table('vendor_intelligence_alerts')
                        ->where('id', $r->id)
                        ->update(['active_dedupe_key' => $key]);
                }
            });

        // ─── (3) Resolve pre-existing duplicates ─────────────────────
        // If duplicate active rows exist, keep the newest and flip the
        // rest to `resolved` with a NULL dedupe_key. This must happen
        // BEFORE the UNIQUE index is added, or the CREATE INDEX fails.
        $duplicates = DB::table('vendor_intelligence_alerts')
            ->whereNotNull('active_dedupe_key')
            ->select('active_dedupe_key')
            ->groupBy('active_dedupe_key')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('active_dedupe_key');

        foreach ($duplicates as $key) {
            $ids = DB::table('vendor_intelligence_alerts')
                ->where('active_dedupe_key', $key)
                ->orderByDesc('id')
                ->pluck('id')
                ->all();
            // Keep the first (newest); mark the rest resolved with NULL key
            array_shift($ids);
            if (! empty($ids)) {
                DB::table('vendor_intelligence_alerts')
                    ->whereIn('id', $ids)
                    ->update([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                        'active_dedupe_key' => null,
                    ]);
            }
        }

        // ─── (4) Add UNIQUE index ────────────────────────────────────
        // Now that no duplicates exist among non-NULL keys, this succeeds.
        // NULLs are still distinct so historical resolved rows are safe.
        $indexName = 'via_active_dedupe_uniq';
        $existing = collect(Schema::getIndexes('vendor_intelligence_alerts'))
            ->pluck('name');
        if (! $existing->contains($indexName)) {
            Schema::table('vendor_intelligence_alerts', function (Blueprint $t) use ($indexName) {
                $t->unique('active_dedupe_key', $indexName);
            });
        }

        // ─── Defect 11: stale marking columns on summaries ───────────
        Schema::table('vendor_intelligence_summaries', function (Blueprint $t) {
            if (! Schema::hasColumn('vendor_intelligence_summaries', 'stale_at')) {
                $t->timestamp('stale_at')->nullable()->after('computed_at');
            }
            if (! Schema::hasColumn('vendor_intelligence_summaries', 'stale_reason')) {
                $t->string('stale_reason', 64)->nullable()->after('stale_at');
            }
            if (! Schema::hasColumn('vendor_intelligence_summaries', 'last_generated_at')) {
                $t->timestamp('last_generated_at')->nullable()->after('stale_reason');
            }
        });

        // Also add an index for the stale-only generate mode
        $existingSum = collect(Schema::getIndexes('vendor_intelligence_summaries'))
            ->pluck('name');
        if (! $existingSum->contains('vis_stale_at_idx')) {
            Schema::table('vendor_intelligence_summaries', function (Blueprint $t) {
                $t->index('stale_at', 'vis_stale_at_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('vendor_intelligence_alerts', function (Blueprint $t) {
            $t->dropUnique('via_active_dedupe_uniq');
            $t->dropColumn('active_dedupe_key');
        });
        Schema::table('vendor_intelligence_summaries', function (Blueprint $t) {
            $t->dropIndex('vis_stale_at_idx');
            $t->dropColumn(['stale_at', 'stale_reason', 'last_generated_at']);
        });
    }
};
