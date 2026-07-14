<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Licensing\LicenseManager;
use App\Services\Licensing\ServerFingerprintService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Phase 12.3 v12.3.2 — license:doctor preflight diagnostic.
 *
 * Runs a series of environment / configuration checks and reports each
 * result with an OK / WARN / FAIL indicator. Designed to catch the exact
 * class of problems that produced the developer's v12.3.1 test failures:
 *
 *   - ext-sodium missing → cryptic fatal on token verification
 *   - pdo_pgsql / pdo_mysql / pdo_sqlite missing → tests fail before assertions
 *   - LICENSE_PUBLIC_KEY unset while enforcement enabled → nothing works
 *   - license tables absent → migration hasn't run
 *   - installation_id storage unwritable → fingerprint keeps regenerating
 *   - cache unreachable → license status caching thrashes
 *
 * Non-zero exit code if any FAIL check hits, so this can be wired into
 * deploy scripts or CI pre-flight gates.
 *
 * Usage:
 *   php artisan license:doctor
 *   php artisan license:doctor --json      # machine-readable
 */
class LicenseDoctorCommand extends Command
{
    protected $signature = 'license:doctor {--json : Emit JSON instead of a human-readable table}';

    protected $description = 'Preflight checks for the license system (extensions, config, tables, cache).';

    /** @var array<int, array{name:string, level:string, message:string, hint?:string}> */
    private array $results = [];

    public function handle(): int
    {
        $this->checkPhpVersion();
        $this->checkSodiumExtension();
        $this->checkDatabaseDriver();
        $this->checkPublicKey();
        $this->checkConfigFlags();
        $this->checkLicenseTables();
        $this->checkInstallationIdStorage();
        $this->checkFingerprintService();
        $this->checkCache();
        $this->checkLicenseManager();

        $failCount = count(array_filter($this->results, fn ($r) => $r['level'] === 'FAIL'));
        $warnCount = count(array_filter($this->results, fn ($r) => $r['level'] === 'WARN'));

        if ((bool) $this->option('json')) {
            $this->line(json_encode([
                'summary' => ['ok' => count($this->results) - $failCount - $warnCount,
                              'warn' => $warnCount, 'fail' => $failCount],
                'checks'  => $this->results,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return $failCount > 0 ? self::FAILURE : self::SUCCESS;
        }

        $this->line('');
        $this->line('  <fg=cyan;options=bold>License system preflight (php artisan license:doctor)</>');
        $this->line('');

        foreach ($this->results as $r) {
            $badge = match ($r['level']) {
                'OK'   => '<fg=green;options=bold>  OK  </>',
                'WARN' => '<fg=yellow;options=bold> WARN </>',
                'FAIL' => '<fg=red;options=bold> FAIL </>',
                default => '  ??  ',
            };
            $this->line("  [{$badge}] {$r['name']}");
            $this->line("           {$r['message']}");
            if (isset($r['hint']) && $r['hint'] !== '') {
                $this->line("           <fg=gray>hint: {$r['hint']}</>");
            }
        }
        $this->line('');
        $ok = count($this->results) - $failCount - $warnCount;
        $this->line("  Summary: <fg=green>{$ok} OK</>, <fg=yellow>{$warnCount} WARN</>, <fg=red>{$failCount} FAIL</>");
        $this->line('');

        return $failCount > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function record(string $name, string $level, string $message, string $hint = ''): void
    {
        $this->results[] = array_filter([
            'name' => $name, 'level' => $level, 'message' => $message,
            'hint' => $hint !== '' ? $hint : null,
        ], fn ($v) => $v !== null);
    }

    private function checkPhpVersion(): void
    {
        $v = PHP_VERSION;
        if (PHP_VERSION_ID < 80200) {
            $this->record('PHP version', 'FAIL',
                "PHP {$v} — this project requires 8.2+.",
                'Upgrade PHP. On Ubuntu: sudo apt-get install php8.3');
            return;
        }
        $this->record('PHP version', 'OK', "PHP {$v}");
    }

    /**
     * Check ext-sodium — required for Ed25519 signature verification.
     */
    private function checkSodiumExtension(): void
    {
        if (! extension_loaded('sodium')) {
            $this->record('ext-sodium', 'FAIL',
                'The sodium extension is NOT loaded. License token verification will fatal.',
                'Ubuntu:  sudo apt-get install php8.3-sodium && sudo service php8.3-fpm restart' . PHP_EOL .
                '           Windows: enable extension=sodium in php.ini and restart the SAPI' . PHP_EOL .
                '           Verify:  php -m | grep sodium');
            return;
        }
        // Sodium loaded but check for the specific Ed25519 helpers we use.
        $missing = [];
        foreach (['sodium_crypto_sign_verify_detached', 'sodium_crypto_sign_keypair',
                  'sodium_crypto_sign_publickey',       'sodium_crypto_sign_secretkey',
                  'sodium_crypto_sign_detached'] as $fn) {
            if (! function_exists($fn)) $missing[] = $fn;
        }
        if ($missing !== []) {
            $this->record('ext-sodium', 'FAIL',
                'ext-sodium loaded but Ed25519 helpers missing: ' . implode(', ', $missing),
                'Your libsodium build is incomplete. Reinstall a full libsodium/php-sodium package.');
            return;
        }
        $this->record('ext-sodium', 'OK', 'ext-sodium loaded with all required Ed25519 helpers');
    }

    /**
     * Check that the configured DB driver is actually loaded in PHP.
     * Distinguishes production runtime (default connection) from testing.
     */
    private function checkDatabaseDriver(): void
    {
        $connection = (string) config('database.default');
        $driver = (string) config("database.connections.{$connection}.driver", $connection);

        $pdoModule = "pdo_{$driver}";
        if (! extension_loaded($pdoModule)) {
            $installed = array_filter([
                'pdo_pgsql'  => extension_loaded('pdo_pgsql'),
                'pdo_mysql'  => extension_loaded('pdo_mysql'),
                'pdo_sqlite' => extension_loaded('pdo_sqlite'),
            ]);
            $installedList = $installed === [] ? '(none)' : implode(', ', array_keys($installed));
            $this->record('Database driver', 'FAIL',
                "Configured DB connection '{$connection}' needs '{$pdoModule}' which is NOT loaded.",
                "Loaded PDO drivers: {$installedList}." . PHP_EOL .
                "           Ubuntu:  sudo apt-get install php8.3-{$driver} && restart your SAPI" . PHP_EOL .
                "           Or set DB_CONNECTION to a driver you already have (in .env or .env.testing).");
            return;
        }
        $this->record('Database driver', 'OK',
            "Connection '{$connection}' uses '{$driver}' — {$pdoModule} loaded");
    }

    private function checkPublicKey(): void
    {
        $enforcing = (bool) config('license.enforcement_enabled', false);
        $keyB64    = trim((string) config('license.public_key_base64', ''));

        if (! $enforcing && $keyB64 === '') {
            $this->record('Public key', 'OK',
                'Enforcement disabled; empty LICENSE_PUBLIC_KEY is fine for dev.');
            return;
        }
        if ($enforcing && $keyB64 === '') {
            $this->record('Public key', 'FAIL',
                'LICENSE_ENFORCEMENT_ENABLED=true but LICENSE_PUBLIC_KEY is empty.',
                'Ask the owner for their public key (base64) and set LICENSE_PUBLIC_KEY in .env, then: php artisan config:cache');
            return;
        }
        if ($keyB64 === 'PLACEHOLDER_PUBLIC_KEY_MUST_BE_REPLACED') {
            $this->record('Public key', 'FAIL',
                'LICENSE_PUBLIC_KEY is still the placeholder value.',
                'Replace with the real base64 key from the owner.');
            return;
        }
        $decoded = base64_decode($keyB64, true);
        if ($decoded === false || strlen($decoded) !== 32) {
            $this->record('Public key', 'FAIL',
                'LICENSE_PUBLIC_KEY is not a valid 32-byte Ed25519 public key (base64).',
                'Ed25519 public keys are exactly 32 raw bytes → 44 base64 chars (with =).');
            return;
        }
        $this->record('Public key', 'OK', 'LICENSE_PUBLIC_KEY is a valid 32-byte Ed25519 public key');
    }

    private function checkConfigFlags(): void
    {
        $lines = [
            'enforcement_enabled'       => (bool) config('license.enforcement_enabled', false),
            'fail_closed_when_unconfigured' => (bool) config('license.fail_closed_when_unconfigured', false),
            'require_domain_match'      => (bool) config('license.require_domain_match', true),
            'require_fingerprint_match' => (bool) config('license.require_fingerprint_match', false),
            'allow_www_alias'           => (bool) config('license.allow_www_alias', true),
            'grace_days'                => (int)  config('license.grace_days', 0),
        ];
        $summary = collect($lines)
            ->map(fn ($v, $k) => $k . '=' . (is_bool($v) ? ($v ? 'true' : 'false') : $v))
            ->join(', ');
        $this->record('Config flags', 'OK', $summary);
    }

    private function checkLicenseTables(): void
    {
        try {
            $missing = array_filter(['license_activations', 'license_audit_logs'],
                fn ($t) => ! Schema::hasTable($t));
            if ($missing !== []) {
                $this->record('License tables', 'FAIL',
                    'Missing tables: ' . implode(', ', $missing),
                    'Run: php artisan migrate --force');
                return;
            }
            $active = DB::table('license_activations')->where('status', 'active')->count();
            $this->record('License tables', 'OK',
                "Both tables present ({$active} active license row(s))");
        } catch (Throwable $e) {
            $this->record('License tables', 'FAIL',
                'Could not query license tables: ' . $e->getMessage(),
                'This usually means the DB driver check above also failed.');
        }
    }

    private function checkInstallationIdStorage(): void
    {
        $dir = storage_path('app/license');
        if (! is_dir($dir)) {
            if (! @mkdir($dir, 0700, true) && ! is_dir($dir)) {
                $this->record('Installation ID storage', 'FAIL',
                    "Directory '{$dir}' does not exist and cannot be created.",
                    'chown/chmod storage/app so PHP can write to it.');
                return;
            }
        }
        if (! is_writable($dir)) {
            $this->record('Installation ID storage', 'FAIL',
                "Directory '{$dir}' is not writable by PHP.",
                'chmod 700 the directory and chown to the PHP user.');
            return;
        }
        $this->record('Installation ID storage', 'OK', "Directory '{$dir}' exists and is writable");
    }

    private function checkFingerprintService(): void
    {
        try {
            $svc = app(ServerFingerprintService::class);
            $fp = $svc->fingerprint();
            if (strlen($fp) !== 64 || ! ctype_xdigit($fp)) {
                $this->record('Fingerprint', 'FAIL',
                    "Fingerprint has unexpected shape (len=" . strlen($fp) . ").",
                    'Check ServerFingerprintService and installation_id file contents.');
                return;
            }
            $this->record('Fingerprint', 'OK', "64-char hex fingerprint: " . substr($fp, 0, 12) . '…');
        } catch (Throwable $e) {
            $this->record('Fingerprint', 'FAIL',
                'Fingerprint service threw: ' . $e->getMessage(),
                'Likely a storage-permissions or ext-sodium issue (see checks above).');
        }
    }

    private function checkCache(): void
    {
        try {
            Cache::put('license.doctor.ping', 'pong', 5);
            $v = Cache::get('license.doctor.ping');
            Cache::forget('license.doctor.ping');
            if ($v !== 'pong') {
                $this->record('Cache', 'WARN',
                    'Cache put/get did not round-trip (got: ' . var_export($v, true) . ')',
                    'Verify CACHE_STORE / cache driver config.');
                return;
            }
            $this->record('Cache', 'OK', 'Cache put/get round-trip works');
        } catch (Throwable $e) {
            $this->record('Cache', 'WARN',
                'Cache probe threw: ' . $e->getMessage(),
                'License status caching may thrash. Verify CACHE_STORE config.');
        }
    }

    private function checkLicenseManager(): void
    {
        try {
            $status = app(LicenseManager::class)->status();
            $this->record('License manager', 'OK',
                "status={$status['status']}, warning_level=" . ($status['warning_level'] ?? 'n/a'));
        } catch (Throwable $e) {
            $this->record('License manager', 'FAIL',
                'LicenseManager::status() threw: ' . $e->getMessage(),
                'Fix the failures above first — this is usually a downstream symptom.');
        }
    }
}
