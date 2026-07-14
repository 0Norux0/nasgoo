<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Personalization\CustomerAffinityService;
use Illuminate\Console\Command;

/**
 * Phase 11B.3 §25 — rebuild customer affinity profiles.
 *
 * Idempotent: replaces rows in customer_affinities per-user. Chunked to
 * avoid full-user-table memory loads. Runs on schedule OR can be
 * targeted with --user=ID.
 *
 * Usage:
 *   php artisan personalization:rebuild                  # all active customers
 *   php artisan personalization:rebuild --user=123       # one customer
 *   php artisan personalization:rebuild --stale-days=7   # only users whose profile is stale
 */
class PersonalizationRebuildCommand extends Command
{
    protected $signature = 'personalization:rebuild
                            {--user= : Rebuild only for this user id}
                            {--stale-days= : Only rebuild users whose profile is older than N days}
                            {--chunk=100 : Chunk size}';

    protected $description = 'Rebuild customer affinity profiles (Phase 11B.3)';

    public function handle(CustomerAffinityService $service): int
    {
        $userId = $this->option('user');
        if ($userId) {
            $user = User::find($userId);
            if (! $user) {
                $this->error("User {$userId} not found");
                return self::FAILURE;
            }
            $result = $service->rebuildForUser($user);
            $this->info("Rebuilt user {$user->id}: " . json_encode($result));
            return self::SUCCESS;
        }

        $chunkSize = max(10, (int) $this->option('chunk'));
        $q = User::query()
            ->where('status', 'active')
            ->whereNotNull('email_verified_at');

        // Only customers (skip staff/admin/vendor to keep rebuild bounded)
        $q->whereHas('roles', fn ($r) => $r->where('name', 'customer'));

        $count = 0;
        $failed = 0;
        $q->chunkById($chunkSize, function ($users) use ($service, &$count, &$failed) {
            foreach ($users as $user) {
                try {
                    $service->rebuildForUser($user);
                    $count++;
                } catch (\Throwable $e) {
                    $failed++;
                    $this->warn("Failed user {$user->id}: {$e->getMessage()}");
                }
            }
        });

        $this->info("Rebuild complete. Users: {$count}, Failed: {$failed}");
        return self::SUCCESS;
    }
}
