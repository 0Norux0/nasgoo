<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Licensing\LicenseManager;
use Illuminate\Console\Command;

class LicenseClearCacheCommand extends Command
{
    protected $signature   = 'license:clear-cache';
    protected $description = 'Invalidate the cached license state (forces a fresh read on the next request).';

    public function handle(LicenseManager $license): int
    {
        $license->clearCache();
        $this->info('License cache cleared.');
        return self::SUCCESS;
    }
}
