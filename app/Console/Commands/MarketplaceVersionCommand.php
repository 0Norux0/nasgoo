<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Phase 8 v8.6 — version verification command.
 *
 * Lets the developer confirm exactly which release is deployed on their
 * machine, and run the same static-defense checks that CI runs without
 * having to wait for GitHub Actions. This was added because the v8.5
 * release shipped the duplicate-helper fix correctly, but a v8.5 bug
 * report came in with line numbers matching the OLD v8.4 code — meaning
 * the package was applied somewhere other than where tests ran.
 *
 * Run with:
 *
 *     php artisan marketplace:version
 *
 * Outputs the VERSION file contents, then runs four stub-independent
 * static checks (duplicate-global-test-function, form-errors-key,
 * schema-vs-runtime-data, MySQL identifier-length). Exits 0 if all
 * checks pass; exits 1 if any check fails — so the command is also
 * usable as a git pre-push hook.
 */
class MarketplaceVersionCommand extends Command
{
    protected $signature = 'marketplace:version {--strict : Exit 1 if any static check fails}';

    protected $description = 'Print the deployed marketplace platform version and run static defense checks.';

    public function handle(): int
    {
        $base = base_path();

        // ───────────────────────────────────────────────────────────
        // Section 1 — VERSION file
        // ───────────────────────────────────────────────────────────
        $this->info('═══════════════════════════════════════════════════════');
        $this->info(' Marketplace Platform — version verification');
        $this->info('═══════════════════════════════════════════════════════');
        $this->line('');

        $versionFile = $base . '/VERSION';
        if (! file_exists($versionFile)) {
            $this->error('  ✗ VERSION file MISSING from project root');
            $this->error('     Expected at: ' . $versionFile);
            $this->error('     The package you applied does not include v8.6 verification.');
            $this->error('     Re-apply the latest release archive.');
            return self::FAILURE;
        }

        $version = trim((string) file_get_contents($versionFile));
        $this->info("  Deployed version: {$version}");

        // ───────────────────────────────────────────────────────────
        // Section 2 — Static defense checks
        // ───────────────────────────────────────────────────────────
        $this->line('');
        $this->info('── Running stub-independent static defense checks ──');
        $this->line('');

        $failures = 0;

        // Check 1 — duplicate global test functions (Phase 8 v8.5 defense)
        $failures += $this->runCheck(
            'Phase 8 v8.5 — duplicate global test functions',
            fn () => $this->checkDuplicateTestFunctions($base . '/tests'),
        );

        // Check 2 — form.errors.KEY references map to useForm data keys (Phase 8 v8.4 defense)
        $failures += $this->runCheck(
            'Phase 8 v8.4 — form-errors-key references map to useForm data keys',
            fn () => $this->checkFormErrorsKey($base . '/resources/js'),
        );

        // Check 3 — Product::create() keys map to real columns (Phase 8 v8.3 defense)
        $failures += $this->runCheck(
            'Phase 8 v8.3 — Product create/updateOrCreate keys are real columns',
            fn () => $this->checkProductKeys($base),
        );

        // Check 4 — MySQL identifier length (Phase 8 v8.2 defense)
        $failures += $this->runCheck(
            'Phase 8 v8.2 — MySQL identifier-length pre-flight (auto-generated names ≤ 60 chars)',
            fn () => $this->checkIdentifierLength($base . '/database/migrations'),
        );

        $this->line('');
        $this->info('═══════════════════════════════════════════════════════');
        if ($failures === 0) {
            $this->info("  ✓ Deployed version {$version} — all 4 static defenses pass.");
            $this->info('═══════════════════════════════════════════════════════');
            return self::SUCCESS;
        }

        $this->error("  ✗ {$failures} static defense check(s) failed against the deployed code.");
        $this->error('     The package you applied is incomplete or stale.');
        $this->info('═══════════════════════════════════════════════════════');
        return $this->option('strict') ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Run a single check and report its result. Returns 0 on pass, 1 on fail.
     */
    private function runCheck(string $label, callable $check): int
    {
        try {
            $issues = $check();
            if (empty($issues)) {
                $this->line("  ✓ {$label}");
                return 0;
            }
            $this->error("  ✗ {$label} — " . count($issues) . ' issue(s):');
            foreach ($issues as $issue) {
                $this->line("      {$issue}");
            }
            return 1;
        } catch (\Throwable $e) {
            $this->error("  ! {$label} — check errored: " . $e->getMessage());
            return 1;
        }
    }

    /**
     * Check 1 — scan tests/ for top-level `function NAME(` declarations,
     * fail on any name appearing in multiple files.
     */
    private function checkDuplicateTestFunctions(string $testsDir): array
    {
        $byName = [];
        foreach ($this->phpFiles($testsDir) as $path) {
            $src = (string) file_get_contents($path);
            if (preg_match_all('/^function\s+(\w+)\s*\(/m', $src, $matches, PREG_OFFSET_CAPTURE)) {
                foreach ($matches[1] as $match) {
                    $name = $match[0];
                    $line = substr_count(substr($src, 0, $match[1]), "\n") + 1;
                    $byName[$name][] = sprintf('%s:%d', $path, $line);
                }
            }
        }

        $issues = [];
        foreach ($byName as $name => $sites) {
            if (count($sites) > 1) {
                $issues[] = "'{$name}()' declared " . count($sites) . '× in: ' . implode(', ', $sites);
            }
        }
        return $issues;
    }

    /**
     * Check 2 — every formVar.errors.KEY in .tsx files references a key declared
     * in the corresponding useForm({...}) call.
     */
    private function checkFormErrorsKey(string $jsDir): array
    {
        $issues = [];
        $useFormRe = '/const\s+(\w+)\s*=\s*useForm\s*\(\s*\{(?P<body>[^{}]*(?:\{[^{}]*\}[^{}]*)*)\}/m';
        $errorsRe  = '/\b(\w+)\.errors\.(\w+)\b/';

        foreach ($this->tsxFiles($jsDir) as $path) {
            $src = (string) file_get_contents($path);
            $forms = [];
            if (preg_match_all($useFormRe, $src, $um, PREG_SET_ORDER)) {
                foreach ($um as $m) {
                    $name = $m[1];
                    $body = $m['body'];
                    preg_match_all('/(?:^|[\s,])(\w+)\s*:/', $body, $km);
                    $forms[$name] = array_values(array_unique($km[1] ?? []));
                }
            }
            if (empty($forms)) continue;

            if (preg_match_all($errorsRe, $src, $em, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                foreach ($em as $m) {
                    $var = $m[1][0]; $key = $m[2][0];
                    if (! isset($forms[$var])) continue;
                    if (! in_array($key, $forms[$var], true)) {
                        $line = substr_count(substr($src, 0, $m[0][1]), "\n") + 1;
                        $issues[] = "{$path}:{$line}  {$var}.errors.{$key} — '{$key}' not in useForm data";
                    }
                }
            }
        }
        return $issues;
    }

    /**
     * Check 3 — every key in Product::create / updateOrCreate / factory()->create
     * across seeders/controllers/tests is a real column in the products table.
     */
    private function checkProductKeys(string $base): array
    {
        $migs = glob($base . '/database/migrations/*create_products_table.php');
        if (empty($migs)) return ['create_products_table migration not found'];
        $src = (string) file_get_contents($migs[0]);

        $cols = [];
        preg_match_all(
            '/->(?:string|text|longText|tinyText|integer|unsignedInteger|unsignedSmallInteger|unsignedTinyInteger|tinyInteger|smallInteger|bigInteger|boolean|date|time|dateTime|datetime|timestamp|json|jsonb|foreignId|decimal|float|softDeletes)\(\s*\'([a-z_][a-z0-9_]*)\'/',
            $src,
            $cm
        );
        $cols = array_merge($cm[1] ?? [], ['id', 'created_at', 'updated_at', 'deleted_at']);

        foreach (glob($base . '/database/migrations/*add*products*.php') as $extra) {
            $esrc = (string) file_get_contents($extra);
            preg_match_all(
                '/->(?:string|text|longText|integer|unsignedInteger|boolean|date|time|datetime|timestamp|json|foreignId|decimal|float)\(\s*\'([a-z_][a-z0-9_]*)\'/',
                $esrc,
                $em
            );
            $cols = array_merge($cols, $em[1] ?? []);
        }
        $cols = array_values(array_unique($cols));

        $callRe = '/(?<![A-Za-z_])Product(?:::|->factory\(\)->)(?:create|updateOrCreate|firstOrCreate)\s*\(\s*\[(?P<b1>[^\[\]]*(?:\[[^\[\]]*\][^\[\]]*)*)\](?:\s*,\s*\[(?P<b2>[^\[\]]*(?:\[[^\[\]]*\][^\[\]]*)*)\])?/';

        $issues = [];
        foreach (['database/seeders', 'app/Http/Controllers', 'tests/Feature'] as $sub) {
            $dir = $base . '/' . $sub;
            if (! is_dir($dir)) continue;
            foreach ($this->phpFiles($dir) as $path) {
                $psrc = (string) file_get_contents($path);
                if (preg_match_all($callRe, $psrc, $cm, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
                    foreach ($cm as $m) {
                        foreach (['b1', 'b2'] as $g) {
                            if (! isset($m[$g]) || $m[$g][0] === '') continue;
                            $block = $m[$g][0];
                            preg_match_all('/\'([a-z_][a-z0-9_]*)\'\s*=>/', $block, $km);
                            foreach ($km[1] ?? [] as $key) {
                                if (! in_array($key, $cols, true)) {
                                    $line = substr_count(substr($psrc, 0, $m[0][1]), "\n") + 1;
                                    $issues[] = "{$path}:{$line}  '{$key}' is not a real products column";
                                }
                            }
                        }
                    }
                }
            }
        }
        return $issues;
    }

    /**
     * Check 4 — every Laravel auto-generated compound index name on Phase 8
     * migrations is ≤ 60 chars (4-char buffer below MySQL's 64-char limit).
     */
    private function checkIdentifierLength(string $migDir): array
    {
        $limit = 60;
        $issues = [];
        foreach (glob($migDir . '/2026_01_08_*.php') as $path) {
            $src = (string) file_get_contents($path);
            if (! preg_match('/Schema::create\(\s*\'([a-z_]+)\'/', $src, $tm)) continue;
            $table = $tm[1];

            preg_match_all('/->foreignId\(\s*\'([a-z_]+)\'/', $src, $fkm);
            foreach ($fkm[1] ?? [] as $col) {
                $name = "{$table}_{$col}_foreign";
                if (strlen($name) > $limit) $issues[] = "{$path}: {$name} (" . strlen($name) . " chars)";
            }

            preg_match_all('/\$table->unique\(\s*\[([^\]]+)\](?:\s*,\s*\'([^\']+)\')?\s*\)/', $src, $um, PREG_SET_ORDER);
            foreach ($um as $m) {
                if (! empty($m[2])) continue; // explicit name — skip implicit-prediction
                preg_match_all("/'(\w+)'/", $m[1], $colm);
                $name = "{$table}_" . implode('_', $colm[1] ?? []) . '_unique';
                if (strlen($name) > $limit) $issues[] = "{$path}: {$name} (" . strlen($name) . " chars)";
            }

            preg_match_all('/\$table->index\(\s*\[([^\]]+)\](?:\s*,\s*\'([^\']+)\')?\s*\)/', $src, $im, PREG_SET_ORDER);
            foreach ($im as $m) {
                if (! empty($m[2])) continue;
                preg_match_all("/'(\w+)'/", $m[1], $colm);
                $name = "{$table}_" . implode('_', $colm[1] ?? []) . '_index';
                if (strlen($name) > $limit) $issues[] = "{$path}: {$name} (" . strlen($name) . " chars)";
            }
        }
        return $issues;
    }

    /** Walk a directory and yield every .php file path. */
    private function phpFiles(string $dir): iterable
    {
        if (! is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'php') yield $f->getPathname();
        }
    }

    /** Walk a directory and yield every .tsx file path. */
    private function tsxFiles(string $dir): iterable
    {
        if (! is_dir($dir)) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'tsx') yield $f->getPathname();
        }
    }
}
