<?php

declare(strict_types=1);

namespace App\Services\Licensing;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Phase 12.3 — server-side fingerprint for license binding.
 *
 * The fingerprint is a SHA-256 hash of three stable-ish identifiers:
 *
 *   1. Installation UUID (generated ONCE, stored in storage/app/license/installation_id)
 *   2. APP_URL host (from .env)
 *   3. DB_DATABASE name (from .env)
 *
 * Hosting the app on a new server with the same code but different DB
 * OR different APP_URL produces a different fingerprint — that's
 * intentional, it's how the license binding works.
 *
 * Deleting `storage/app/license/installation_id` is a supported
 * "re-install" gesture that requires the owner to issue a new token.
 *
 * We NEVER include raw sensitive data in the fingerprint output. The
 * fingerprint is a hex-encoded SHA-256 that reveals no information
 * about the underlying identifiers.
 */
class ServerFingerprintService
{
    /**
     * Return the 64-character hex fingerprint for this installation.
     * Idempotent — safe to call from every request; internally memoized
     * for the request lifetime.
     */
    public function fingerprint(): string
    {
        static $memo = null;
        if ($memo !== null) return $memo;

        $components = [
            'iid'  => $this->installationId(),
            'url'  => $this->normalizedAppHost(),
            'db'   => (string) config('database.connections.'.config('database.default').'.database'),
        ];

        $memo = hash('sha256', json_encode($components, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        return $memo;
    }

    /**
     * Return the raw installation UUID (readable to owner via artisan).
     * NOT sensitive on its own — safe to display in admin UI.
     */
    public function installationId(): string
    {
        $relativePath = (string) config('license.installation_id_path', 'license/installation_id');

        // We use Laravel's `local` disk which maps to storage/app/
        $disk = Storage::disk('local');
        if ($disk->exists($relativePath)) {
            $existing = trim((string) $disk->get($relativePath));
            if ($existing !== '' && Str::isUuid($existing)) {
                return $existing;
            }
        }

        // First-run generation
        $new = (string) Str::uuid();
        $disk->put($relativePath, $new . "\n");
        return $new;
    }

    /**
     * Return the normalized APP_URL host (lowercase, no port, no path).
     */
    public function normalizedAppHost(): string
    {
        $url = (string) config('app.url', 'http://localhost');
        $parts = parse_url($url);
        $host = strtolower($parts['host'] ?? 'localhost');
        return $host;
    }

    /**
     * Convenience: report all three components for the artisan command.
     * The installation UUID is safe to show; APP_URL is public; DB name
     * is quasi-public (visible to anyone with server access already).
     */
    public function report(): array
    {
        return [
            'installation_id' => $this->installationId(),
            'app_host'        => $this->normalizedAppHost(),
            'fingerprint'     => $this->fingerprint(),
        ];
    }
}
