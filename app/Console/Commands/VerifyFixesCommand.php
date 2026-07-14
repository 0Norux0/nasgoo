<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Phase 10 v10.2 — verify-fixes command.
 *
 * The developer's report that v10.1 fixes weren't present in their
 * environment means we need a way to PROVE which fixes are live in the
 * deployed codebase. This command checks each defect's fix marker and
 * reports green/red so the developer can immediately see whether the
 * deployment actually includes the corrections.
 *
 * Usage:
 *   php artisan marketplace:verify-fixes
 *
 * Exit code: 0 if all green, 1 if any check fails.
 */
class VerifyFixesCommand extends Command
{
    protected $signature = 'marketplace:verify-fixes';

    protected $description = 'Verify that all Phase 10 v10.1+v10.2 fixes are present in the live codebase';

    public function handle(): int
    {
        $version = trim((string) @file_get_contents(base_path('VERSION'))) ?: 'unknown';
        $this->info("Marketplace version: {$version}");
        $this->newLine();

        $checks = [
            [
                'defect' => '#4 Product images MassAssignmentException',
                'check'  => fn () => $this->fileContainsCount(
                    'app/Http/Controllers/Vendor/VendorProductController.php',
                    "unset(\$data['images'])",
                    2  // must appear in BOTH store() and update()
                ),
                'detail' => 'expect 2 occurrences of unset($data[\'images\']) in VendorProductController',
            ],
            [
                'defect' => '#6 AdminLayout.tsx exists',
                'check'  => fn () => file_exists(base_path('resources/js/Layouts/AdminLayout.tsx')),
                'detail' => 'resources/js/Layouts/AdminLayout.tsx must exist; without it /admin/reports cannot render',
            ],
            [
                'defect' => '#6 Filament Reports nav registered',
                'check'  => fn () => $this->fileContains(
                    'app/Providers/Filament/AdminPanelProvider.php',
                    "'Reports Dashboard'"
                ),
                'detail' => 'AdminPanelProvider must register the Reports Dashboard navigation item',
            ],
            [
                'defect' => '#7 Vendor Reports nav link',
                'check'  => fn () => $this->fileContains(
                    'resources/js/Layouts/VendorLayout.tsx',
                    'vendor-nav-reports'
                ),
                'detail' => 'VendorLayout must include the Reports link with data-testid=vendor-nav-reports',
            ],
            [
                'defect' => '#5 Vendor order list inline actions',
                'check'  => fn () => $this->fileContains(
                    'resources/js/Pages/Vendor/Orders/Index.tsx',
                    'row-confirm-'
                ) && $this->fileContains(
                    'resources/js/Pages/Vendor/Orders/Index.tsx',
                    'row-ship-'
                ) && $this->fileContains(
                    'resources/js/Pages/Vendor/Orders/Index.tsx',
                    'row-deliver-'
                ),
                'detail' => 'Vendor Orders Index.tsx must expose row-confirm-, row-ship-, row-deliver- testids',
            ],
            [
                'defect' => '#2/#3/#9 VendorFileLinks helper used in VendorResource',
                'check'  => fn () => $this->fileContains(
                    'app/Filament/Resources/VendorResource.php',
                    'VendorFileLinks::previewHtml'
                ),
                'detail' => 'VendorResource must use VendorFileLinks::previewHtml to render uploaded files',
            ],
            [
                'defect' => '#3 Vendor-selected package displayed',
                'check'  => fn () => $this->fileContains(
                    'app/Filament/Resources/VendorResource.php',
                    'requested_package'
                ),
                'detail' => 'VendorResource must show the vendor-selected package via requested_package field',
            ],
            [
                'defect' => '#8 /sitemap.xml route + controller',
                'check'  => fn () => $this->fileContains('routes/web.php', "'/sitemap.xml'")
                    && file_exists(base_path('app/Http/Controllers/Public/SitemapController.php')),
                'detail' => 'sitemap route must be registered AND SitemapController must exist',
            ],
            [
                'defect' => '#10 Storefront mobile menu',
                'check'  => fn () => $this->fileContains(
                    'resources/js/Layouts/StorefrontLayout.tsx',
                    'storefront-mobile-menu'
                ),
                'detail' => 'StorefrontLayout must have storefront-mobile-menu testid',
            ],
            [
                'defect' => '#10 Vendor mobile menu',
                'check'  => fn () => $this->fileContains(
                    'resources/js/Layouts/VendorLayout.tsx',
                    'vendor-mobile-menu'
                ),
                'detail' => 'VendorLayout must have vendor-mobile-menu testid',
            ],
            [
                'defect' => '#1 Translations cached',
                'check'  => fn () => $this->fileContains(
                    'app/Http/Middleware/HandleInertiaRequests.php',
                    'inertia:translations:v1'
                ),
                'detail' => 'HandleInertiaRequests must cache translations',
            ],
            [
                'defect' => '#1 Performance indexes migration',
                'check'  => fn () => count(glob(base_path('database/migrations/*phase10_v101_performance_indexes*'))) > 0,
                'detail' => 'Performance indexes migration file must exist',
            ],
            [
                'defect' => 'v10.2 — Reports in baseItems (always visible)',
                'check'  => fn () => $this->fileContains(
                    'resources/js/Layouts/VendorLayout.tsx',
                    'Reports moved into baseItems'
                ),
                'detail' => 'VendorLayout must have Reports in baseItems (visible to all vendors)',
            ],
            [
                'defect' => 'v10.2 — Filament nav uses hasAnyRole (not Spatie can())',
                'check'  => fn () => $this->fileContains(
                    'app/Providers/Filament/AdminPanelProvider.php',
                    "hasAnyRole(['super_admin', 'admin_staff'])"
                ),
                'detail' => 'AdminPanelProvider Reports nav visibility must use hasAnyRole directly (not ->can())',
            ],
            [
                'defect' => 'v10.2 — Version exposed via shared Inertia prop',
                'check'  => fn () => $this->fileContains(
                    'app/Http/Middleware/HandleInertiaRequests.php',
                    "'version'"
                ) && $this->fileContains(
                    'resources/js/Layouts/StorefrontLayout.tsx',
                    'app-version-banner'
                ),
                'detail' => 'Marketplace version must be exposed and rendered in the storefront footer',
            ],
            [
                'defect' => 'v10.3 — Filament VendorResource uses NO deprecated disableLabel()',
                'check'  => fn () => ! $this->fileContains(
                    'app/Filament/Resources/VendorResource.php',
                    '->disableLabel('
                ),
                'detail' => 'VendorResource must NOT call ->disableLabel() — deprecated in Filament 3.x, throws BadMethodCallException, crashes the form (root cause of "admin can\'t view vendor documents")',
            ],
            [
                'defect' => 'v10.3 — Product::fill() bulletproof guard against images mass-assignment',
                'check'  => fn () => $this->fileContains(
                    'app/Models/Product.php',
                    "unset(\$attributes['images'])"
                ) && $this->fileContains(
                    'app/Models/Product.php',
                    'public function fill(array $attributes): static'
                ),
                'detail' => 'Product model must override fill() and strip images key — defense in depth against ALL mass-assignment paths',
            ],
            [
                'defect' => 'v10.3 — Vendor order status dropdown (dev §4 demand)',
                'check'  => fn () => $this->fileContains(
                    'resources/js/Pages/Vendor/Orders/Show.tsx',
                    'vendor-order-status-dropdown'
                ),
                'detail' => 'Vendor order Show page must expose status dropdown with data-testid=vendor-order-status-dropdown',
            ],
            [
                'defect' => 'v10.3 — Global mobile overflow guards in app.css',
                'check'  => fn () => $this->fileContains(
                    'resources/css/app.css',
                    'overflow-x-hidden'
                ) && $this->fileContains(
                    'resources/css/app.css',
                    'max-width: 100vw'
                ),
                'detail' => 'app.css must have html/body overflow-x-hidden + max-width 100vw as the defensive mobile net',
            ],
        ];

        $failed = 0;
        foreach ($checks as $c) {
            try {
                $ok = (bool) $c['check']();
            } catch (\Throwable $e) {
                $ok = false;
            }
            if ($ok) {
                $this->line(sprintf('  <fg=green>✓</> %s', $c['defect']));
            } else {
                $failed++;
                $this->line(sprintf('  <fg=red>✗</> %s', $c['defect']));
                $this->line(sprintf('     %s', $c['detail']));
            }
        }

        $this->newLine();
        if ($failed === 0) {
            $this->info('✅ All v10.1 + v10.2 fixes verified present in the live codebase.');
            $this->line('');
            $this->warn('If the developer still observes the original defects, the issue is at the');
            $this->warn('deployment/cache layer (Vite build, OPcache, Spatie permission cache,');
            $this->warn('route cache, view cache). Run scripts/deploy.sh to invalidate ALL caches.');
            return self::SUCCESS;
        }

        $this->error("✗ {$failed} fix(es) not detected in the live codebase.");
        $this->line('');
        $this->warn('This means the deployed source does NOT include the v10.1/v10.2 corrections.');
        $this->warn('Possible causes:');
        $this->warn('  1. Archive was extracted into a different directory than the running app');
        $this->warn('  2. Deployment ran `composer install` but not `tar -xzf` on the source');
        $this->warn('  3. A git checkout overwrote the extracted files');
        $this->warn('Re-extract the v10.2 archive over the running app directory, then run:');
        $this->warn('  scripts/deploy.sh');
        return self::FAILURE;
    }

    private function fileContains(string $path, string $needle): bool
    {
        $full = base_path($path);
        if (! is_file($full)) {
            return false;
        }
        return str_contains((string) file_get_contents($full), $needle);
    }

    private function fileContainsCount(string $path, string $needle, int $expected): bool
    {
        $full = base_path($path);
        if (! is_file($full)) {
            return false;
        }
        return substr_count((string) file_get_contents($full), $needle) === $expected;
    }
}
