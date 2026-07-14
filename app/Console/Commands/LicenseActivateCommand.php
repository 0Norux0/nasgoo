<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Licensing\LicenseManager;
use Illuminate\Console\Command;
use Illuminate\Http\Request;

/**
 * Phase 12.3 — CLI license activation.
 *
 * Recovery path when the admin activation UI is unreachable (e.g. license
 * already expired and public storefront blocked). The token is passed
 * as an argument; the command feeds it through the same LicenseManager
 * used by the web UI, so all audit + persistence still happens.
 */
class LicenseActivateCommand extends Command
{
    protected $signature   = 'license:activate
                              {token : The full license token (base64url.base64url.base64url)}
                              {--no-cache-clear : Skip cache invalidation after activation}';
    protected $description = 'Activate a license token from the command line (recovery / bootstrap).';

    public function handle(LicenseManager $license): int
    {
        $token = (string) $this->argument('token');
        if (strlen($token) < 32) {
            $this->error('Token looks too short — refusing to attempt activation.');
            return self::FAILURE;
        }

        // Synthesize a Request so the manager's audit code has something to work with.
        $request = Request::create('/artisan/license:activate', 'POST');
        $request->headers->set('User-Agent', 'artisan license:activate');

        $result = $license->activate($token, $request, null);

        if (! $result['ok']) {
            $this->error('Activation failed: ' . ($result['status'] ?? 'unknown'));
            if (! empty($result['reason'])) $this->line('  reason: ' . $result['reason']);
            return self::FAILURE;
        }

        $this->info('License activated successfully.');
        $payload = $result['payload'] ?? [];
        foreach (['license_holder', 'license_type', 'domain', 'expires_at'] as $k) {
            if (isset($payload[$k])) $this->line(sprintf('  %-16s %s', $k, $payload[$k]));
        }

        if (! $this->option('no-cache-clear')) {
            $license->clearCache();
            $this->line('  cache cleared.');
        }

        return self::SUCCESS;
    }
}
