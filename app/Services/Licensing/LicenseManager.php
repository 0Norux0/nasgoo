<?php

declare(strict_types=1);

namespace App\Services\Licensing;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Phase 12.3 — orchestrator over LicenseVerifier + DB + cache.
 *
 * Responsibilities:
 *   - resolve the "current" active license (most recent row with status=active)
 *   - cache the resolved status for O(request) middleware calls
 *   - activate a fresh token (verify → persist → audit)
 *   - clear cache on activation or explicit reset
 *   - produce status data for the admin UI + banner
 *   - audit every activation attempt (success + failure)
 *
 * Deliberately does NOT:
 *   - delete data on expiry (only READ operations after expiry)
 *   - block routes itself (that's the middleware's job)
 *   - generate license tokens (that's the offline generator's job)
 */
class LicenseManager
{
    public const CACHE_KEY = 'license.state.v1';

    public function __construct(
        private readonly LicenseVerifier $verifier,
        private readonly ServerFingerprintService $fingerprint,
        private readonly LicenseDomainResolver $domainResolver,
    ) {
    }

    // ────────────────────────────────────────────────────────────────
    // Public API
    // ────────────────────────────────────────────────────────────────

    /**
     * Return a normalized status array for the current license.
     * Cached; call clearCache() after activation.
     *
     * @return array{
     *   enforcement_enabled: bool,
     *   configured: bool,
     *   status: string,        // 'active' | 'expired' | 'grace' | 'unlicensed' | 'unconfigured'
     *   expires_at: ?string,
     *   days_remaining: ?int,
     *   license_holder: ?string,
     *   license_type: ?string,
     *   domain: ?string,
     *   server_fingerprint: string,
     *   installation_id: string,
     *   warning_level: ?string,  // 'ok'|'notice'|'warning'|'urgent'|'expired'|'grace'
     * }
     */
    public function status(): array
    {
        return Cache::remember(self::CACHE_KEY, (int) config('license.cache_ttl_seconds', 300),
            fn() => $this->computeStatus());
    }

    /**
     * Verify + activate a token. Returns [ok, status, reason, activation?].
     */
    public function activate(string $token, Request $request, ?int $activatedBy = null): array
    {
        // v12.3.1: domain resolution now goes through LicenseDomainResolver,
        // which uses request()->getHost() in web context (respecting
        // TrustProxies middleware) and falls back to LICENSE_DOMAIN or
        // APP_URL in CLI context. Previously we used the fingerprint
        // service's normalizedAppHost() which read only APP_URL — that
        // could reject valid tokens when APP_URL was stale, or accept
        // wrong-host tokens if APP_URL was misconfigured.
        $expectedDomain = (bool) config('license.require_domain_match', true)
            ? $this->domainResolver->expectedDomain()
            : null;

        $expectedFp = (bool) config('license.require_fingerprint_match', false)
            ? $this->fingerprint->fingerprint()
            : null;

        $graceDays = (int) config('license.grace_days', 0);

        $result = $this->verifier->verify($token, $expectedDomain, $expectedFp, $graceDays);

        if ($result['status'] !== LicenseVerifier::OK) {
            $this->audit('activation.failure', $activatedBy, $this->verifier->tokenHash($token), $result['reason'] ?? 'unknown', $request);
            return [
                'ok'     => false,
                'status' => $result['status'],
                'reason' => $result['reason'] ?? null,
            ];
        }

        // Persist. Use INSERT ... ON DUPLICATE KEY UPDATE semantics via updateOrCreate.
        if (! Schema::hasTable('license_activations')) {
            // Migration not run yet — fail closed
            $this->audit('activation.failure', $activatedBy, $this->verifier->tokenHash($token), 'license_activations table missing', $request);
            return ['ok' => false, 'status' => 'schema_missing', 'reason' => 'license tables not migrated'];
        }

        $payload = $result['payload'];
        $hash    = $this->verifier->tokenHash($token);
        $now     = Carbon::now();

        // Supersede any previously-active row
        DB::table('license_activations')->where('status', 'active')->update(['status' => 'superseded']);

        DB::table('license_activations')->updateOrInsert(
            ['token_hash' => $hash],
            [
                'token_hash'         => $hash,
                'payload'            => json_encode($payload),
                'license_holder'     => $payload['license_holder'] ?? null,
                'license_type'       => $payload['license_type'] ?? 'standard',
                'domain'             => $payload['domain'] ?? null,
                'app_url'            => (string) config('app.url'),
                'server_fingerprint' => $payload['server_fingerprint'] ?? $this->fingerprint->fingerprint(),
                'issued_at'          => isset($payload['issued_at']) ? Carbon::parse($payload['issued_at']) : null,
                'expires_at'         => isset($payload['expires_at']) ? Carbon::parse($payload['expires_at']) : null,
                'activated_at'       => $now,
                'activated_by'       => $activatedBy,
                'status'             => 'active',
                'last_checked_at'    => $now,
                'metadata'           => json_encode([
                    'ip'         => $request->ip(),
                    'user_agent' => substr((string) $request->userAgent(), 0, 500),
                ]),
                'updated_at'         => $now,
                'created_at'         => $now,
            ]
        );

        $this->audit('activation.success', $activatedBy, $hash, null, $request);
        $this->clearCache();

        return ['ok' => true, 'status' => 'active', 'payload' => $payload];
    }

    /**
     * Invalidate cached license state. Call after activation OR from
     * `license:clear-cache` artisan command.
     */
    public function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        // Fingerprint memo is per-request only, no invalidation needed.
    }

    /**
     * Should the middleware block the given request?
     *
     * Returns [shouldBlock: bool, reason: ?string].
     */
    public function shouldBlockRequest(Request $request): array
    {
        if (! (bool) config('license.enforcement_enabled', false)) {
            return [false, null];
        }

        // Exempt routes/paths — always allowed so the owner can reach activation.
        $routeName = optional($request->route())->getName();
        if (in_array($routeName, (array) config('license.exempt_route_names', []), true)) {
            return [false, null];
        }
        $path = '/' . ltrim($request->path(), '/');
        foreach ((array) config('license.exempt_uri_prefixes', []) as $prefix) {
            if ($prefix !== '' && str_starts_with($path, $prefix)) {
                return [false, null];
            }
        }

        $status = $this->status();

        // Configured + active: passthrough
        if ($status['status'] === 'active' || $status['status'] === 'grace') {
            return [false, null];
        }

        // Unconfigured or expired or unlicensed: check the settings
        if ($status['status'] === 'unconfigured') {
            $failClosed = (bool) config('license.fail_closed_when_unconfigured', true);
            if (! $failClosed) return [false, null];
        }

        // Public storefront check
        if ($this->isPublicStorefrontRequest($request) && ! (bool) config('license.block_public_storefront', false)) {
            return [false, null];
        }

        return [true, "license status: {$status['status']}"];
    }

    // ────────────────────────────────────────────────────────────────
    // Internal
    // ────────────────────────────────────────────────────────────────

    private function computeStatus(): array
    {
        $installationId = $this->fingerprint->installationId();
        $fingerprint    = $this->fingerprint->fingerprint();
        $publicKey      = trim((string) config('license.public_key_base64', ''));
        $enforcement    = (bool) config('license.enforcement_enabled', false);

        $base = [
            'enforcement_enabled' => $enforcement,
            'configured'          => $publicKey !== '' && $publicKey !== 'PLACEHOLDER_PUBLIC_KEY_MUST_BE_REPLACED',
            'installation_id'     => $installationId,
            'server_fingerprint'  => $fingerprint,
            'app_url'             => (string) config('app.url'),
            'expires_at'          => null,
            'days_remaining'      => null,
            'license_holder'      => null,
            'license_type'        => null,
            'domain'              => null,
            'warning_level'       => 'ok',
        ];

        if (! $base['configured']) {
            return array_merge($base, ['status' => 'unconfigured']);
        }

        if (! Schema::hasTable('license_activations')) {
            return array_merge($base, ['status' => 'unconfigured']);
        }

        $row = DB::table('license_activations')
            ->where('status', 'active')
            ->orderByDesc('activated_at')
            ->first();

        if (! $row) {
            return array_merge($base, ['status' => 'unlicensed']);
        }

        $expiresAt = $row->expires_at ? Carbon::parse($row->expires_at)->utc() : null;
        $graceDays = (int) config('license.grace_days', 0);

        $status = 'active';
        if ($expiresAt) {
            $now       = Carbon::now();
            $graceEnd  = $expiresAt->copy()->addDays($graceDays);
            if ($now->gt($graceEnd)) {
                $status = 'expired';
            } elseif ($now->gt($expiresAt) && $now->lte($graceEnd)) {
                $status = 'grace';
            }
        }

        $daysRemaining = $expiresAt ? max(0, (int) Carbon::now()->diffInDays($expiresAt, false)) : null;
        $warningLevel  = $this->warningLevelFor($status, $daysRemaining);

        return array_merge($base, [
            'status'          => $status,
            'expires_at'      => $expiresAt?->toIso8601String(),
            'days_remaining'  => $daysRemaining,
            'license_holder'  => $row->license_holder,
            'license_type'    => $row->license_type,
            'domain'          => $row->domain,
            'warning_level'   => $warningLevel,
        ]);
    }

    private function warningLevelFor(string $status, ?int $daysRemaining): string
    {
        if ($status === 'expired') return 'expired';
        if ($status === 'grace')   return 'grace';
        if ($daysRemaining === null) return 'ok';

        $thresholds = (array) config('license.warning_thresholds', [14, 7, 3]);
        sort($thresholds);
        if (in_array(3, $thresholds, true) && $daysRemaining <= 3)  return 'urgent';
        if (in_array(7, $thresholds, true) && $daysRemaining <= 7)  return 'warning';
        if (in_array(14, $thresholds, true) && $daysRemaining <= 14) return 'notice';
        return 'ok';
    }

    private function isPublicStorefrontRequest(Request $request): bool
    {
        $path = '/' . ltrim($request->path(), '/');
        $publicPrefixes = [
            '/', '/products', '/vendors', '/services', '/search',
            '/robots.txt', '/sitemap.xml', '/favicon.ico',
        ];
        foreach ($publicPrefixes as $prefix) {
            if ($prefix === '/' && $path === '/') return true;
            if ($prefix !== '/' && str_starts_with($path, $prefix)) return true;
        }
        return false;
    }

    private function audit(string $event, ?int $userId, ?string $tokenHash, ?string $reason, Request $request): void
    {
        if (! Schema::hasTable('license_audit_logs')) return;
        try {
            DB::table('license_audit_logs')->insert([
                'event'      => $event,
                'user_id'    => $userId,
                'token_hash' => $tokenHash,
                'reason'     => $reason,
                'ip_address' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 500),
                'context'    => json_encode([
                    'path'   => '/' . ltrim($request->path(), '/'),
                    'method' => $request->method(),
                ]),
                'created_at' => Carbon::now(),
            ]);
        } catch (\Throwable) {
            // Never let audit failure crash the request.
        }
    }
}
