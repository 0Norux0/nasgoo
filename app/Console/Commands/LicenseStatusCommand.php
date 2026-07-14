<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Licensing\LicenseManager;
use Illuminate\Console\Command;

class LicenseStatusCommand extends Command
{
    protected $signature   = 'license:status {--json : Emit JSON instead of a table}';
    protected $description = 'Show current license activation status.';

    public function handle(LicenseManager $license): int
    {
        $status = $license->status();

        if ($this->option('json')) {
            $this->line(json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->info('Marketplace license status');
        $this->line('');
        $rows = [
            ['enforcement_enabled', $status['enforcement_enabled'] ? 'true' : 'false'],
            ['configured (public key installed)', $status['configured'] ? 'yes' : 'no'],
            ['status', $status['status']],
            ['warning level', $status['warning_level'] ?? 'ok'],
            ['expires at', $status['expires_at'] ?? '-'],
            ['days remaining', $status['days_remaining'] ?? '-'],
            ['license holder', $status['license_holder'] ?? '-'],
            ['license type', $status['license_type'] ?? '-'],
            ['domain', $status['domain'] ?? '-'],
            ['app URL', $status['app_url'] ?? '-'],
            ['installation ID', $status['installation_id']],
            ['server fingerprint', $status['server_fingerprint']],
        ];
        foreach ($rows as [$k, $v]) {
            $this->line(sprintf('  %-40s %s', $k, $v));
        }

        return self::SUCCESS;
    }
}
