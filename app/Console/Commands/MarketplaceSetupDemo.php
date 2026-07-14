<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Env;

/**
 * Phase 6 v7.2 — `php artisan marketplace:setup-demo`
 *
 * Foolproof guided setup for a fresh local clone. Walks the developer through:
 *   1. .env exists (copies from .env.example if not)
 *   2. APP_KEY is set in .env (runs key:generate if not)
 *   3. optimize:clear (flushes any stale cached config from a half-broken
 *      previous run — this is what bites devs who tried migrate:fresh --seed
 *      before key:generate)
 *   4. migrate:fresh --seed (the actual work)
 *   5. prints the demo login credentials
 *
 * The command refuses to silently continue past missing APP_KEY — if the
 * developer says no to the auto-fix prompt, the command exits non-zero with
 * the exact commands to run by hand.
 *
 * In CI, run with `--force` to skip every confirmation.
 */
class MarketplaceSetupDemo extends Command
{
    protected $signature = 'marketplace:setup-demo
        {--force : Skip every confirmation prompt (auto-accept defaults — use this in CI/scripts)}
        {--skip-migrate : Run the env + cache-clear steps only; do not call migrate:fresh --seed}';

    protected $description = 'Foolproof Phase 6 demo setup: ensure .env + APP_KEY exist, clear caches, fresh-migrate with seed, then print demo logins.';

    public function handle(): int
    {
        $this->line('');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  Marketplace · guided demo setup (Phase 6 v7.2)');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');

        // ── Step 1: .env exists ──
        if (! $this->ensureEnvFile()) {
            return self::FAILURE;
        }

        // ── Step 2: APP_KEY set ──
        if (! $this->ensureAppKey()) {
            return self::FAILURE;
        }

        // ── Step 3: optimize:clear ──
        $this->newLine();
        $this->info('▶ Clearing caches (optimize:clear)…');
        $this->call('optimize:clear');

        if ($this->option('skip-migrate')) {
            $this->newLine();
            $this->info('✓ Setup checks complete. Skipping migrate:fresh --seed (--skip-migrate set).');
            return self::SUCCESS;
        }

        // ── Step 4: migrate:fresh --seed ──
        $this->newLine();
        $this->info('▶ Running migrate:fresh --seed…');
        $code = $this->call('migrate:fresh', ['--seed' => true, '--force' => true]);
        if ($code !== self::SUCCESS) {
            $this->newLine();
            $this->error('✗ migrate:fresh --seed failed. See output above. (Common cause: database not reachable — check DB_HOST / DB_DATABASE / DB_USERNAME / DB_PASSWORD in .env.)');
            return $code;
        }

        // ── Step 5: print demo logins ──
        $this->printDemoAccounts();
        return self::SUCCESS;
    }

    // ─────────────────────────────────────────────────────────────────

    protected function ensureEnvFile(): bool
    {
        $envPath = base_path('.env');
        $examplePath = base_path('.env.example');

        if (is_file($envPath)) {
            $this->line('  ✓ .env already exists');
            return true;
        }

        if (! is_file($examplePath)) {
            $this->error('  ✗ .env is missing AND .env.example is also missing — cannot bootstrap.');
            $this->line('    Please restore .env.example from the repo before re-running.');
            return false;
        }

        $this->warn('  ! .env is missing. Copying from .env.example…');
        if (! $this->option('force') && ! $this->confirmOrAutoAccept('Copy .env.example to .env now?')) {
            $this->line('');
            $this->error('Cannot continue without .env. Run by hand:');
            $this->line('  cp .env.example .env');
            $this->line('  php artisan key:generate');
            $this->line('  php artisan optimize:clear');
            $this->line('  php artisan migrate:fresh --seed');
            return false;
        }

        if (! @copy($examplePath, $envPath)) {
            $this->error('  ✗ Failed to copy .env.example → .env (check filesystem permissions on the project root).');
            return false;
        }
        $this->line('  ✓ Copied .env.example → .env');

        // Reload the env file so any subsequent check + sub-command sees the new values.
        $this->reloadDotenv();
        return true;
    }

    protected function ensureAppKey(): bool
    {
        // Read directly from disk — config('app.key') reflects the value at
        // process boot, BEFORE we may have just copied a fresh .env in step 1.
        $envContents = @file_get_contents(base_path('.env')) ?: '';
        $hasKey = preg_match('/^APP_KEY=base64:[A-Za-z0-9+\/=]+$/m', $envContents) === 1;

        if ($hasKey) {
            $this->line('  ✓ APP_KEY already set in .env');
            return true;
        }

        $this->warn('  ! APP_KEY is missing or empty in .env.');
        if (! $this->option('force') && ! $this->confirmOrAutoAccept('Generate APP_KEY now via php artisan key:generate?')) {
            $this->printMissingKeyHelp();
            return false;
        }

        // key:generate writes back to .env. We pass --force so it doesn't ask
        // about replacing an existing key (we already determined there isn't one).
        $code = $this->call('key:generate', ['--force' => true]);
        if ($code !== self::SUCCESS) {
            $this->error('  ✗ key:generate failed.');
            $this->printMissingKeyHelp();
            return false;
        }

        // Reload .env so the in-process config + env() see the new key
        // before we hand off to migrate:fresh --seed.
        $this->reloadDotenv();
        $reloaded = env('APP_KEY');
        if (! $reloaded || ! str_starts_with($reloaded, 'base64:')) {
            $this->error('  ✗ APP_KEY still does not look right after key:generate. Aborting.');
            $this->printMissingKeyHelp();
            return false;
        }

        // Push it into the in-memory config bag too, so config('app.key')
        // returns the live value for any subsequent code path
        // (including DemoSeeder's APP_KEY pre-seed guard).
        config(['app.key' => $reloaded]);

        $this->line('  ✓ APP_KEY generated and reloaded');
        return true;
    }

    protected function reloadDotenv(): void
    {
        try {
            // Clear cached values so the next read sees the file
            Env::getRepository()->clear('APP_KEY');
            // Re-parse .env from disk
            $dotenv = \Dotenv\Dotenv::createImmutable(base_path());
            $dotenv->load();
        } catch (\Throwable $e) {
            // Reloading failed — not fatal; the next sub-command will boot a
            // fresh process for itself in many cases. Just warn.
            $this->warn('  ! Could not reload .env in-process: ' . $e->getMessage());
        }
    }

    protected function confirmOrAutoAccept(string $question, bool $default = true): bool
    {
        // In a non-interactive context (CI), confirm() returns the default;
        // we still want to be explicit about it.
        if (! $this->input->isInteractive()) {
            $this->line('  (non-interactive: auto-accepting default = ' . ($default ? 'yes' : 'no') . ')');
            return $default;
        }
        return $this->confirm($question, $default);
    }

    protected function printMissingKeyHelp(): void
    {
        $this->newLine();
        $this->error('APP_KEY is missing. Run these commands exactly, in order:');
        $this->line('');
        $this->line('  cp .env.example .env');
        $this->line('  php artisan key:generate');
        $this->line('  php artisan optimize:clear');
        $this->line('  php artisan migrate:fresh --seed');
        $this->line('');
        $this->line('Or just re-run the guided command:');
        $this->line('  php artisan marketplace:setup-demo');
        $this->line('');
        $this->warn('Note: the command is --seed (no trailing dot). "--seed." with a dot is rejected by Laravel.');
    }

    protected function printDemoAccounts(): void
    {
        $this->newLine();
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->info('  ✅ Demo environment ready. Login accounts (password = "password"):');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->line('  Admin           → admin@marketplace.test');
        $this->line('  Staff           → staff@marketplace.test');
        $this->line('  Vendor          → vendor@marketplace.test');
        $this->line('  Vendor 2        → vendor2@marketplace.test');
        $this->line('  Customer        → customer@marketplace.test');
        $this->line('  Pending vendor  → pending-vendor@marketplace.test');
        $this->line('  Rejected vendor → rejected-vendor@marketplace.test');
        $this->newLine();
        $this->line('  Next steps:');
        $this->line('    composer install && npm install && npm run build');
        $this->line('    php artisan serve   # then open http://localhost:8000');
        $this->line('━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━');
        $this->newLine();
    }
}
