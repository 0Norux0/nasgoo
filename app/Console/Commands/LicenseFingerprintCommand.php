<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Licensing\ServerFingerprintService;
use Illuminate\Console\Command;

class LicenseFingerprintCommand extends Command
{
    protected $signature   = 'license:fingerprint {--json : Emit JSON only}';
    protected $description = 'Print the safe server fingerprint the owner can use when generating a license token.';

    public function handle(ServerFingerprintService $fp): int
    {
        $report = $fp->report();

        if ($this->option('json')) {
            $this->line(json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return self::SUCCESS;
        }

        $this->info('Marketplace installation fingerprint');
        $this->line('');
        $this->line('  installation ID     ' . $report['installation_id']);
        $this->line('  app host            ' . $report['app_host']);
        $this->line('  server fingerprint  ' . $report['fingerprint']);
        $this->line('');
        $this->comment('Send the fingerprint above to the license owner. The owner');
        $this->comment('will use it to generate a bound license token. The installation');
        $this->comment('ID is stored at ' . config('license.installation_id_path'));
        $this->comment('and MUST be backed up — deleting it changes the fingerprint.');

        return self::SUCCESS;
    }
}
