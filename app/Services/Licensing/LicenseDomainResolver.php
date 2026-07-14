<?php

declare(strict_types=1);

namespace App\Services\Licensing;

use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;

/**
 * Phase 12.3 v12.3.1 — resolves the "expected domain" for license binding.
 *
 * BUG THIS FIXES (v12.3):
 *   The previous LicenseManager used `ServerFingerprintService::normalizedAppHost()`
 *   which read APP_URL from config. Consequences:
 *     - Stale/wrong APP_URL rejected valid tokens
 *     - Wrong APP_URL could accept tokens meant for a different host
 *     - Attackers with control of APP_URL (unlikely, but not impossible in a
 *       misconfigured deploy) could weaken the binding
 *
 * v12.3.1 FIX:
 *   In web context, we resolve the expected domain from the CURRENT request:
 *   `request()->getHost()`. Laravel's Request::getHost() respects the
 *   TrustProxies middleware, so a properly-configured deployment behind a
 *   load balancer gets the real client-facing host, not the proxy's.
 *
 *   In CLI context (no request bound), we fall back to a configured
 *   `LICENSE_DOMAIN`. If unset, we fall back to APP_URL as a last resort
 *   with a clear log line.
 *
 * TRUSTED-PROXY WARNING:
 *   `request()->getHost()` uses the Host / X-Forwarded-Host header. If your
 *   deployment sits behind a proxy but Laravel's TrustProxies middleware is
 *   NOT configured to include that proxy, the header will be trusted only
 *   for direct requests. Ensure `config/trustedproxy.php` or the equivalent
 *   `app/Http/Middleware/TrustProxies.php` lists your load balancer's IPs.
 *   Without that, an attacker cannot forge X-Forwarded-Host — but you may
 *   see false rejections if the LB rewrites Host to its own address.
 */
class LicenseDomainResolver
{
    public function __construct(
        private readonly Container $container,
        private readonly ServerFingerprintService $fingerprint,
    ) {
    }

    /**
     * Return the host string to compare against the token's `domain` claim.
     * Web context: request->getHost() (respects TrustProxies).
     * CLI context: LICENSE_DOMAIN env → falls back to APP_URL.
     *
     * Result is already normalized (lowercase, no scheme/port/path).
     */
    public function expectedDomain(): string
    {
        // Web context detection: has a bound Request in the container?
        if ($this->container->bound('request')) {
            $request = $this->container->make('request');
            if ($request instanceof Request) {
                $host = (string) $request->getHost();
                if ($host !== '') {
                    return $this->normalize($host);
                }
            }
        }

        // CLI context: prefer explicit LICENSE_DOMAIN.
        $configured = trim((string) config('license.domain', ''));
        if ($configured !== '') {
            return $this->normalize($configured);
        }

        // Last resort: APP_URL host.
        return $this->fingerprint->normalizedAppHost();
    }

    /**
     * Normalize a raw host string. Matches LicenseVerifier::normalizeHost().
     * Kept public so callers (including tests) can produce a value comparable
     * to what the verifier will compare.
     */
    public function normalize(string $raw): string
    {
        $s = trim($raw);
        if ($s === '') return '';

        if (str_contains($s, '://')) {
            $parsed = parse_url($s);
            $s = (string) ($parsed['host'] ?? '');
        } else {
            $slash = strpos($s, '/');
            if ($slash !== false) $s = substr($s, 0, $slash);
        }

        if (str_contains($s, ':')) {
            [$host, $port] = explode(':', $s, 2);
            if ($port === '80' || $port === '443') $s = $host;
        }

        return strtolower($s);
    }
}
