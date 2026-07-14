<?php

declare(strict_types=1);

namespace App\Services\Licensing;

use Carbon\Carbon;
use InvalidArgumentException;

/**
 * Phase 12.3 — pure Ed25519 signature verification for license tokens.
 *
 * Token format (JWT-adjacent but with an "MPLIC" type discriminator):
 *
 *     base64url(header) . base64url(payload) . base64url(signature)
 *
 * Header:   {"alg":"EdDSA","typ":"MPLIC"}
 * Payload:  see Phase 12.3 §5 (license_holder, domain, expires_at, ...)
 * Signature: raw 64-byte Ed25519 signature over `header.payload`
 *
 * This service has no dependencies on Eloquent, cache, or HTTP — it can
 * be tested in isolation. The higher-level LicenseManager wraps it with
 * caching, DB persistence, and audit logging.
 *
 * SECURITY NOTES:
 *   - We use sodium_crypto_sign_verify_detached (constant-time, side-channel-safe)
 *   - Public key MUST be 32 raw bytes (base64-decoded from config)
 *   - Signature MUST be 64 raw bytes
 *   - Header alg MUST equal "EdDSA" — we never trust a header-supplied algorithm
 *   - Malformed input throws InvalidArgumentException; caller should NEVER
 *     expose the exception message to end users
 */
class LicenseVerifier
{
    public const HEADER_ALG = 'EdDSA';
    public const HEADER_TYP = 'MPLIC';

    /**
     * Result codes returned by verify(). Callers pattern-match on these.
     */
    public const OK                       = 'ok';
    public const BAD_FORMAT               = 'bad_format';
    public const BAD_HEADER               = 'bad_header';
    public const BAD_SIGNATURE            = 'bad_signature';
    public const EXPIRED                  = 'expired';
    public const DOMAIN_MISMATCH          = 'domain_mismatch';
    public const DOMAIN_REQUIRED          = 'domain_required';         // v12.3.1
    public const FINGERPRINT_MISMATCH     = 'fingerprint_mismatch';
    public const FINGERPRINT_REQUIRED     = 'fingerprint_required';    // v12.3.1
    public const NO_PUBLIC_KEY            = 'no_public_key';
    public const MAX_DAYS_EXCEEDED        = 'max_days_exceeded';

    /**
     * Verify a license token against the currently-configured public key.
     *
     * @return array{status:string, payload?:array, expires_at?:Carbon, reason?:string}
     */
    public function verify(
        string $token,
        ?string $expectedDomain = null,
        ?string $expectedFingerprint = null,
        ?int $graceDays = 0,
    ): array {
        $publicKeyB64 = trim((string) config('license.public_key_base64', ''));
        if ($publicKeyB64 === '' || $publicKeyB64 === 'PLACEHOLDER_PUBLIC_KEY_MUST_BE_REPLACED') {
            return ['status' => self::NO_PUBLIC_KEY,
                    'reason' => 'LICENSE_PUBLIC_KEY not configured'];
        }

        $publicKey = base64_decode($publicKeyB64, true);
        if ($publicKey === false || strlen($publicKey) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return ['status' => self::NO_PUBLIC_KEY,
                    'reason' => 'LICENSE_PUBLIC_KEY has wrong length (need 32 raw bytes, base64-encoded)'];
        }

        // Parse the 3-part token.
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return ['status' => self::BAD_FORMAT, 'reason' => 'token must have 3 dot-separated parts'];
        }
        [$headerB64, $payloadB64, $signatureB64] = $parts;

        $headerBytes    = $this->b64urlDecode($headerB64);
        $payloadBytes   = $this->b64urlDecode($payloadB64);
        $signatureBytes = $this->b64urlDecode($signatureB64);

        if ($headerBytes === null || $payloadBytes === null || $signatureBytes === null) {
            return ['status' => self::BAD_FORMAT, 'reason' => 'base64url decode failed'];
        }
        if (strlen($signatureBytes) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return ['status' => self::BAD_SIGNATURE, 'reason' => 'signature has wrong length'];
        }

        // Header claims: {"alg":"EdDSA","typ":"MPLIC"}
        $header = json_decode($headerBytes, true);
        if (! is_array($header)
            || ($header['alg'] ?? null) !== self::HEADER_ALG
            || ($header['typ'] ?? null) !== self::HEADER_TYP) {
            return ['status' => self::BAD_HEADER, 'reason' => 'header alg/typ not accepted'];
        }

        // Constant-time signature verification
        $message = $headerB64 . '.' . $payloadB64;
        try {
            $sigOk = sodium_crypto_sign_verify_detached($signatureBytes, $message, $publicKey);
        } catch (\SodiumException $e) {
            return ['status' => self::BAD_SIGNATURE, 'reason' => 'sodium reject'];
        }
        if (! $sigOk) {
            return ['status' => self::BAD_SIGNATURE, 'reason' => 'signature does not verify'];
        }

        // Payload claims
        $payload = json_decode($payloadBytes, true);
        if (! is_array($payload)) {
            return ['status' => self::BAD_FORMAT, 'reason' => 'payload is not a JSON object'];
        }

        // Expiry
        $expiresAt = isset($payload['expires_at']) ? $this->parseIso($payload['expires_at']) : null;
        if ($expiresAt === null) {
            return ['status' => self::BAD_FORMAT, 'reason' => 'expires_at missing or unparseable'];
        }
        $graceCutoff = $expiresAt->copy()->addDays(max(0, (int) $graceDays));
        if (Carbon::now()->gt($graceCutoff)) {
            return ['status' => self::EXPIRED, 'payload' => $payload, 'expires_at' => $expiresAt,
                    'reason' => 'token expired at ' . $expiresAt->toIso8601String()];
        }

        // Max-days sanity — reject tokens that claim implausibly long durations
        $issuedAt = isset($payload['issued_at']) ? $this->parseIso($payload['issued_at']) : null;
        $maxDays  = (int) ($payload['max_days'] ?? 0);
        if ($maxDays > 0 && $issuedAt !== null) {
            $windowDays = $issuedAt->diffInDays($expiresAt);
            if ($windowDays > $maxDays) {
                return ['status' => self::MAX_DAYS_EXCEEDED, 'reason' => "token declares {$windowDays}d but max_days={$maxDays}"];
            }
        }

        // Domain binding — v12.3.1: require a non-empty domain claim when
        // binding is on. Previously an empty/missing domain in the token
        // could slip through if the caller wasn't careful about the
        // `expectedDomain === null` check.
        if ($expectedDomain !== null) {
            $tokenDomain = trim((string) ($payload['domain'] ?? ''));
            if ($tokenDomain === '') {
                return ['status' => self::DOMAIN_REQUIRED, 'payload' => $payload,
                        'reason' => 'domain binding is enabled but token has no domain claim'];
            }
            if (! $this->hostsEqual($tokenDomain, $expectedDomain)) {
                return ['status' => self::DOMAIN_MISMATCH, 'payload' => $payload,
                        'reason' => "token bound to {$tokenDomain} but current domain is {$expectedDomain}"];
            }
        }

        // Fingerprint binding — v12.3.1 SECURITY FIX.
        //
        // Previous code:
        //     $tokenFp = $payload['server_fingerprint'] ?? null;
        //     if ($expectedFingerprint !== null && $tokenFp !== null
        //         && $tokenFp !== '' && $tokenFp !== $expectedFingerprint) { REJECT }
        //
        // Bug: the compound && short-circuited on `$tokenFp !== null` and
        // `$tokenFp !== ''`, so a token with missing / null / empty
        // server_fingerprint slipped through UNCHECKED when binding was on.
        //
        // Fix: split into two separate checks. When binding is required,
        // FIRST demand a non-empty token fingerprint, THEN constant-time
        // compare it against the current installation fingerprint.
        if ($expectedFingerprint !== null) {
            $tokenFp = trim((string) ($payload['server_fingerprint'] ?? ''));
            if ($tokenFp === '') {
                return ['status' => self::FINGERPRINT_REQUIRED, 'payload' => $payload,
                        'reason' => 'fingerprint binding is enabled but token has no server_fingerprint claim'];
            }
            if (! hash_equals($expectedFingerprint, $tokenFp)) {
                return ['status' => self::FINGERPRINT_MISMATCH, 'payload' => $payload,
                        'reason' => 'server fingerprint does not match this installation'];
            }
        }

        return ['status' => self::OK, 'payload' => $payload, 'expires_at' => $expiresAt];
    }

    /**
     * Compute the SHA-256 hex digest of the raw token — used as a
     * deduplication key in `license_activations.token_hash`.
     */
    public function tokenHash(string $token): string
    {
        return hash('sha256', $token);
    }

    // ────────────────────────────────────────────────────────────────
    // Private helpers
    // ────────────────────────────────────────────────────────────────

    private function b64urlDecode(string $s): ?string
    {
        // JWT-style base64url: '-' '_' instead of '+' '/', no padding
        $s = strtr($s, '-_', '+/');
        $pad = strlen($s) % 4;
        if ($pad > 0) $s .= str_repeat('=', 4 - $pad);
        $out = base64_decode($s, true);
        return $out === false ? null : $out;
    }

    private function parseIso(string $s): ?Carbon
    {
        try {
            return Carbon::parse($s)->utc();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * v12.3.1 — normalized host comparison for domain binding.
     *
     * Applies the rules from Phase 12.3 v12.3.1 §5:
     *   - lowercase
     *   - strip scheme (http:// / https://) if the caller passed a URL
     *   - strip path / query / fragment
     *   - strip default ports 80, 443
     *   - optionally treat www.example.com and example.com as equal
     *     (controlled by `license.allow_www_alias`, default true)
     *
     * Uses hash_equals for constant-time comparison to avoid timing
     * side-channels on the (unlikely but possible) case where an attacker
     * probes valid-vs-invalid domain values.
     */
    private function hostsEqual(string $a, string $b): bool
    {
        $na = $this->normalizeHost($a);
        $nb = $this->normalizeHost($b);
        if ($na === '' || $nb === '') return false;
        if (hash_equals($na, $nb)) return true;

        if ((bool) config('license.allow_www_alias', true)) {
            $wa = str_starts_with($na, 'www.') ? substr($na, 4) : 'www.' . $na;
            if (hash_equals($wa, $nb)) return true;
        }
        return false;
    }

    private function normalizeHost(string $raw): string
    {
        $s = trim($raw);
        if ($s === '') return '';

        // If the caller passed a URL, strip scheme + path.
        if (str_contains($s, '://')) {
            $parsed = parse_url($s);
            $s = (string) ($parsed['host'] ?? '');
        } else {
            // Strip any accidental path like "example.com/whatever"
            $slash = strpos($s, '/');
            if ($slash !== false) $s = substr($s, 0, $slash);
        }

        // Strip default port
        if (str_contains($s, ':')) {
            [$host, $port] = explode(':', $s, 2);
            if ($port === '80' || $port === '443') $s = $host;
        }

        return strtolower($s);
    }
}
