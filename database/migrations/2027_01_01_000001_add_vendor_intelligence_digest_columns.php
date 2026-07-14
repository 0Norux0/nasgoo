<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 11B.4 v11B.4.3 Fix 2 — email digest support columns.
 *
 * The dev's third remaining item was that no vendor intelligence email
 * dispatch existed. Adding the mailable + job + trigger + template is
 * this release's Fix 2. Two nullable/default-safe columns support that
 * work:
 *
 *   1. `last_digest_sent_at` — timestamp of the vendor's last digest.
 *      The SendVendorIntelligenceDigest job checks this against
 *      `vendor_intelligence.digest_throttle_hours` (default 24) to
 *      prevent duplicate emails on repeated `generate --send-emails`
 *      calls. Also cheap to add an index on it for future "who hasn't
 *      received one recently" admin queries.
 *
 *   2. `email_opted_out` — per-vendor opt-out. Default FALSE (opt-in
 *      remains the master switch via
 *      site.vendor_intelligence.digest_emails_enabled). Vendors
 *      individually can turn OFF the digest via a future preferences
 *      UI. For v11B.4.3 the column is writable via the model but
 *      not exposed in the vendor settings page — no misleading UI.
 *
 * Both columns are additive and reversible.
 */
return new class extends Migration {

    public function up(): void
    {
        Schema::table('vendor_intelligence_summaries', function (Blueprint $t) {
            if (! Schema::hasColumn('vendor_intelligence_summaries', 'last_digest_sent_at')) {
                $t->timestamp('last_digest_sent_at')->nullable()->after('last_generated_at');
            }
            if (! Schema::hasColumn('vendor_intelligence_summaries', 'email_opted_out')) {
                $t->boolean('email_opted_out')->default(false)->after('last_digest_sent_at');
            }
        });

        $existing = collect(Schema::getIndexes('vendor_intelligence_summaries'))
            ->pluck('name');
        if (! $existing->contains('vis_last_digest_idx')) {
            Schema::table('vendor_intelligence_summaries', function (Blueprint $t) {
                $t->index('last_digest_sent_at', 'vis_last_digest_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::table('vendor_intelligence_summaries', function (Blueprint $t) {
            $t->dropIndex('vis_last_digest_idx');
            $t->dropColumn(['last_digest_sent_at', 'email_opted_out']);
        });
    }
};
