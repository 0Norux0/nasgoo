<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Phase 10 v10.4 — fingerprint command.
 *
 * After 4 rounds of "fixes don't work" reports, the developer needs a
 * way to PROVE which code state is running in their environment. This
 * command outputs a SHA-256 of every critical fix file's content. The
 * dev compares this fingerprint against the canonical fingerprint
 * shipped in PHASE_10_v10.4_PACKAGE_INTEGRITY.md.
 *
 * Match → the deployed source is provably v10.4.
 * Mismatch → the deployed source is NOT v10.4 (re-extract the archive).
 *
 * Unlike `marketplace:verify-fixes` which checks fix MARKERS (substring
 * presence), this command computes a cryptographic hash of the file's
 * entire contents. It cannot be spoofed by adding the right substring
 * to the wrong file.
 *
 * Usage:
 *   php artisan marketplace:fingerprint
 *   php artisan marketplace:fingerprint --json  (machine-readable)
 */
class FingerprintCommand extends Command
{
    protected $signature = 'marketplace:fingerprint {--json : Output as JSON}';

    protected $description = 'Output SHA-256 fingerprints of critical fix files for deployment verification';

    /**
     * Critical files whose contents define Phase 10 v10.4.
     * Order matters for the aggregate hash.
     */
    private const FILES = [
        'VERSION',
        'app/Models/Product.php',
        'app/Filament/Resources/VendorResource.php',
        'app/Http/Controllers/Vendor/VendorProductController.php',
        'app/Http/Controllers/Vendor/VendorOrderController.php',
        'app/Http/Controllers/Admin/VendorFileController.php',
        'app/Http/Controllers/Admin/ReportsController.php',
        'app/Http/Controllers/Vendor/VendorReportsController.php',
        'app/Http/Controllers/Public/SitemapController.php',
        'app/Domain/Vendor/VendorFileLinks.php',
        'app/Providers/Filament/AdminPanelProvider.php',
        'app/Http/Middleware/HandleInertiaRequests.php',
        'app/Console/Commands/VerifyFixesCommand.php',
        'app/Console/Commands/FingerprintCommand.php',
        'resources/js/Layouts/AdminLayout.tsx',
        'resources/js/Layouts/VendorLayout.tsx',
        'resources/js/Layouts/StorefrontLayout.tsx',
        'resources/js/Pages/Vendor/Orders/Show.tsx',
        'resources/js/Pages/Vendor/Orders/Index.tsx',
        'resources/css/app.css',
        'routes/web.php',
        'scripts/deploy.sh',
        '.github/workflows/ci.yml',
    ];

    public function handle(): int
    {
        $rows  = [];
        $found = 0;
        foreach (self::FILES as $rel) {
            $full = base_path($rel);
            if (! is_file($full)) {
                $rows[] = ['file' => $rel, 'sha256' => 'MISSING'];
                continue;
            }
            $sha = hash_file('sha256', $full);
            $rows[] = ['file' => $rel, 'sha256' => $sha];
            $found++;
        }

        // Aggregate fingerprint = sha256 of the concatenation of file:sha pairs.
        // This single value should be referenced by the dev to confirm full
        // deployment match.
        $agg = hash('sha256', implode("\n", array_map(
            fn ($r) => $r['file'] . ':' . $r['sha256'],
            $rows
        )));

        if ($this->option('json')) {
            $version = trim((string) @file_get_contents(base_path('VERSION'))) ?: 'unknown';
            $this->line(json_encode([
                'version' => $version,
                'aggregate_sha256' => $agg,
                'files_found' => $found,
                'files_total' => count(self::FILES),
                'files' => $rows,
            ], JSON_PRETTY_PRINT));
            return self::SUCCESS;
        }

        $this->info('Phase 10 v10.4 Deployment Fingerprint');
        $this->line('Version: ' . (trim((string) @file_get_contents(base_path('VERSION'))) ?: 'unknown'));
        $this->newLine();
        $this->table(['File', 'SHA-256'], array_map(fn ($r) => [$r['file'], substr($r['sha256'], 0, 16) . '…'], $rows));
        $this->newLine();
        $this->info("Aggregate fingerprint: {$agg}");
        $this->newLine();
        $this->line('Compare this aggregate against the canonical fingerprint in');
        $this->line('PHASE_10_v10.4_PACKAGE_INTEGRITY.md. Match = deployed source is v10.4.');
        $this->line('Mismatch = re-extract the v10.4 archive over the running app directory.');

        if ($found < count(self::FILES)) {
            $missing = count(self::FILES) - $found;
            $this->newLine();
            $this->error("{$missing} expected file(s) MISSING from this codebase.");
            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
