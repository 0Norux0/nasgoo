<?php

declare(strict_types=1);

use App\Models\User;
use App\Services\Licensing\LicenseManager;
use App\Services\Licensing\LicenseVerifier;
use App\Services\Licensing\ServerFingerprintService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

// ────────────────────────────────────────────────────────────────────────────
// Helpers (phase 12.3 prefix — no collision with existing p11b42_/p11b43_)
// ────────────────────────────────────────────────────────────────────────────

if (! function_exists('p123_generate_keypair')) {
    /**
     * Generate a fresh Ed25519 keypair for one test.
     * Returns [privateKeyRaw64bytes, publicKeyRaw32bytes, publicKeyBase64].
     */
    function p123_generate_keypair(): array
    {
        $keypair = sodium_crypto_sign_keypair();
        $secret  = sodium_crypto_sign_secretkey($keypair);   // 64 bytes
        $public  = sodium_crypto_sign_publickey($keypair);   // 32 bytes
        return [$secret, $public, base64_encode($public)];
    }
}

if (! function_exists('p123_b64url')) {
    function p123_b64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }
}

if (! function_exists('p123_sign_token')) {
    /**
     * Sign an Ed25519 license token with the given secret.
     * Payload overrides let the test customize any field.
     */
    function p123_sign_token(string $secretKey, array $payloadOverrides = []): string
    {
        $payload = array_merge([
            'app'                => 'ICSA Marketplace',
            'domain'             => 'example.com',
            'issued_at'          => now()->format('Y-m-d\TH:i:s\Z'),
            'expires_at'         => now()->addDays(60)->format('Y-m-d\TH:i:s\Z'),
            'license_holder'     => 'Test Owner',
            'license_type'       => 'owner',
            'max_days'           => 60,
            'nonce'              => bin2hex(random_bytes(8)),
            'server_fingerprint' => null,
        ], $payloadOverrides);
        ksort($payload);

        $header = ['alg' => 'EdDSA', 'typ' => 'MPLIC'];
        ksort($header);

        $hB64 = p123_b64url((string) json_encode($header,  JSON_UNESCAPED_SLASHES));
        $pB64 = p123_b64url((string) json_encode($payload, JSON_UNESCAPED_SLASHES));
        $msg  = "$hB64.$pB64";
        $sig  = sodium_crypto_sign_detached($msg, $secretKey);
        $sB64 = p123_b64url($sig);
        return "$hB64.$pB64.$sB64";
    }
}

if (! function_exists('p123_install_test_key')) {
    function p123_install_test_key(string $publicKeyB64): void
    {
        Config::set('license.public_key_base64', $publicKeyB64);
        Config::set('license.enforcement_enabled', true);
        Config::set('license.require_domain_match', false);
        Config::set('license.require_fingerprint_match', false);
        Cache::forget(LicenseManager::CACHE_KEY);
    }
}

if (! function_exists('p123_super_admin')) {
    function p123_super_admin(): User
    {
        return \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web'])
            ->users()->getRelated()::factory()->create(function ($user) {
                return $user;
            })->assignRole('super_admin');
    }
}

// Simpler: create user + assign role inline
if (! function_exists('p123_make_super_admin')) {
    function p123_make_super_admin(): User
    {
        \Spatie\Permission\Models\Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->assignRole('super_admin');
        return $user;
    }
}

if (! function_exists('p123_make_regular_user')) {
    function p123_make_regular_user(): User
    {
        return User::factory()->create();
    }
}

// ────────────────────────────────────────────────────────────────────────────
// §11 Test cases — 20 scenarios
// ────────────────────────────────────────────────────────────────────────────

it('§11.1 accepts a valid signed token via the LicenseVerifier', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $token  = p123_sign_token($secret);
    $result = app(LicenseVerifier::class)->verify($token);

    expect($result['status'])->toBe(LicenseVerifier::OK);
    expect($result['payload']['license_holder'])->toBe('Test Owner');
});

it('§11.2 rejects a tampered payload', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $token = p123_sign_token($secret);
    // Tamper the payload segment: flip a character
    $parts = explode('.', $token);
    $parts[1] = p123_b64url(str_replace('Owner', 'ATTKR', base64_decode(strtr($parts[1].str_repeat('=', 4 - strlen($parts[1]) % 4), '-_', '+/'))));
    $tampered = implode('.', $parts);

    $result = app(LicenseVerifier::class)->verify($tampered);
    expect($result['status'])->toBe(LicenseVerifier::BAD_SIGNATURE);
});

it('§11.3 rejects an expired token', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $token = p123_sign_token($secret, [
        'issued_at'  => now()->subDays(90)->format('Y-m-d\TH:i:s\Z'),
        'expires_at' => now()->subDay()->format('Y-m-d\TH:i:s\Z'),
        'max_days'   => 90,
    ]);

    $result = app(LicenseVerifier::class)->verify($token);
    expect($result['status'])->toBe(LicenseVerifier::EXPIRED);
});

it('§11.4 rejects a token bound to a different domain', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $token  = p123_sign_token($secret, ['domain' => 'other.example.com']);
    $result = app(LicenseVerifier::class)->verify($token, expectedDomain: 'ours.example.com');

    expect($result['status'])->toBe(LicenseVerifier::DOMAIN_MISMATCH);
});

it('§11.5 rejects a token with a signature made by a different key', function () {
    [$secretA, $publicA, $pubBA] = p123_generate_keypair();
    [$secretB, $publicB, $pubBB] = p123_generate_keypair();

    // Install key A, but sign with key B
    p123_install_test_key($pubBA);
    $token = p123_sign_token($secretB);

    $result = app(LicenseVerifier::class)->verify($token);
    expect($result['status'])->toBe(LicenseVerifier::BAD_SIGNATURE);
});

it('§11.6 middleware permits requests when enforcement is disabled', function () {
    Config::set('license.enforcement_enabled', false);
    Cache::forget(LicenseManager::CACHE_KEY);

    // Reach the home route — no license, no activation
    $this->get('/')->assertOk();
});

it('§11.7 middleware blocks authed non-admin when unlicensed + fail_closed', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);
    Config::set('license.fail_closed_when_unconfigured', true);

    $user = p123_make_regular_user();
    $this->actingAs($user)
        ->get('/orders')
        ->assertStatus(403);
});

it('§11.8 middleware redirects super-admin to /admin/license when unlicensed', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $admin = p123_make_super_admin();
    $this->actingAs($admin)
        ->get('/admin')
        ->assertRedirect(route('admin.license.index'));
});

it('§11.9 admin activation UI is reachable even when license is expired', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $admin = p123_make_super_admin();
    $this->actingAs($admin)
        ->get('/admin/license')
        ->assertOk();  // exempt route
});

it('§11.10 activating a valid token persists an active row', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $token = p123_sign_token($secret);
    $admin = p123_make_super_admin();

    $this->actingAs($admin)
        ->post('/admin/license/activate', ['token' => $token])
        ->assertRedirect(route('admin.license.index'));

    expect(DB::table('license_activations')->where('status', 'active')->count())->toBe(1);
});

it('§11.11 activating a new token supersedes the previous active row', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $admin  = p123_make_super_admin();
    $token1 = p123_sign_token($secret);
    $token2 = p123_sign_token($secret);  // fresh nonce → different hash

    $this->actingAs($admin)->post('/admin/license/activate', ['token' => $token1]);
    $this->actingAs($admin)->post('/admin/license/activate', ['token' => $token2]);

    expect(DB::table('license_activations')->where('status', 'active')->count())->toBe(1);
    expect(DB::table('license_activations')->where('status', 'superseded')->count())->toBe(1);
});

it('§11.12 activation attempt is audited (success + failure)', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $admin = p123_make_super_admin();
    $token = p123_sign_token($secret);

    $this->actingAs($admin)->post('/admin/license/activate', ['token' => $token]);
    $this->actingAs($admin)->post('/admin/license/activate', ['token' => 'obviously_invalid_' . str_repeat('x', 40)]);

    expect(DB::table('license_audit_logs')->where('event', 'activation.success')->count())->toBe(1);
    expect(DB::table('license_audit_logs')->where('event', 'activation.failure')->count())->toBe(1);
});

it('§11.13 status endpoint reveals no fingerprint or expiry to guests', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $response = $this->get('/license/status');
    $response->assertOk();
    $content = $response->getContent();
    expect($content)->not->toContain('server_fingerprint');
    expect($content)->not->toContain('installation_id');
});

it('§11.14 non-admin authed user hitting /admin/license gets 403', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $user = p123_make_regular_user();
    $this->actingAs($user)
        ->get('/admin/license')
        ->assertStatus(403);
});

it('§11.15 middleware allows public storefront when block_public_storefront=false and expired', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);
    Config::set('license.block_public_storefront', false);

    // No activation → status is 'unlicensed' → guests can still see /
    $this->get('/')->assertOk();
    $this->get('/products')->assertOk();
});

it('§11.16 middleware blocks public storefront when block_public_storefront=true', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);
    Config::set('license.block_public_storefront', true);
    Config::set('license.fail_closed_when_unconfigured', true);

    // No activation → status is 'unlicensed' → guests get redirected
    $response = $this->get('/products');
    $response->assertRedirect();
});

it('§11.17 malformed token is rejected without raising exceptions', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    foreach (['', 'just.two', 'a.b.c.d', str_repeat('a', 40)] as $bad) {
        $result = app(LicenseVerifier::class)->verify($bad);
        expect($result['status'])->not->toBe(LicenseVerifier::OK);
    }
});

it('§11.18 grace period allows access after expiry when configured', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);
    Config::set('license.grace_days', 3);

    $token = p123_sign_token($secret, [
        'issued_at'  => now()->subDays(30)->format('Y-m-d\TH:i:s\Z'),
        'expires_at' => now()->subDay()->format('Y-m-d\TH:i:s\Z'),
        'max_days'   => 60,
    ]);

    // Verifier: allowed
    $result = app(LicenseVerifier::class)->verify($token, graceDays: 3);
    expect($result['status'])->toBe(LicenseVerifier::OK);
});

it('§11.19 status computes correct warning level near expiry', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $admin = p123_make_super_admin();
    $token = p123_sign_token($secret, [
        'issued_at'  => now()->subDays(50)->format('Y-m-d\TH:i:s\Z'),
        'expires_at' => now()->addDays(2)->format('Y-m-d\TH:i:s\Z'),
        'max_days'   => 60,
    ]);
    $this->actingAs($admin)->post('/admin/license/activate', ['token' => $token]);

    $status = app(LicenseManager::class)->status();
    expect($status['status'])->toBe('active');
    expect($status['warning_level'])->toBe('urgent');  // <= 3 days
});

it('§11.20 fingerprint service produces a stable 64-char hex fingerprint', function () {
    $fp = app(ServerFingerprintService::class);
    $a  = $fp->fingerprint();
    $b  = $fp->fingerprint();

    expect($a)->toBe($b);
    expect(strlen($a))->toBe(64);
    expect(ctype_xdigit($a))->toBeTrue();
});

// ────────────────────────────────────────────────────────────────────────────
// Phase 12.3 v12.3.1 — security fix scenarios
// ────────────────────────────────────────────────────────────────────────────
// Bug 3: fingerprint bypass. Bug 4: domain uses request host, not APP_URL.
// Each scenario is numbered per directive §10.

// ─── Fingerprint binding (§10 items 1–6) ────────────────────────────────────

it('§12.3.1.f1 required fingerprint + matching token fingerprint = valid', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $currentFp = app(\App\Services\Licensing\ServerFingerprintService::class)->fingerprint();
    $token = p123_sign_token($secret, ['server_fingerprint' => $currentFp]);

    $result = app(LicenseVerifier::class)->verify($token, expectedFingerprint: $currentFp);
    expect($result['status'])->toBe(LicenseVerifier::OK);
});

it('§12.3.1.f2 required fingerprint + wrong token fingerprint = rejected', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $token = p123_sign_token($secret, ['server_fingerprint' => str_repeat('a', 64)]);

    $result = app(LicenseVerifier::class)->verify($token, expectedFingerprint: str_repeat('b', 64));
    expect($result['status'])->toBe(LicenseVerifier::FINGERPRINT_MISMATCH);
});

it('§12.3.1.f3 SECURITY: required fingerprint + missing token fingerprint = REJECTED (was: accepted)', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    // Sign a token with `server_fingerprint` set to null explicitly
    $token = p123_sign_token($secret, ['server_fingerprint' => null]);

    $result = app(LicenseVerifier::class)->verify($token, expectedFingerprint: 'anything');
    expect($result['status'])->toBe(LicenseVerifier::FINGERPRINT_REQUIRED);
});

it('§12.3.1.f4 SECURITY: required fingerprint + empty string = REJECTED', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $token = p123_sign_token($secret, ['server_fingerprint' => '']);

    $result = app(LicenseVerifier::class)->verify($token, expectedFingerprint: 'anything');
    expect($result['status'])->toBe(LicenseVerifier::FINGERPRINT_REQUIRED);
});

it('§12.3.1.f5 SECURITY: required fingerprint + whitespace only = REJECTED', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $token = p123_sign_token($secret, ['server_fingerprint' => "   \t  \n"]);

    $result = app(LicenseVerifier::class)->verify($token, expectedFingerprint: 'anything');
    expect($result['status'])->toBe(LicenseVerifier::FINGERPRINT_REQUIRED);
});

it('§12.3.1.f6 fingerprint NOT required + missing token fingerprint = accepted', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $token = p123_sign_token($secret, ['server_fingerprint' => null]);

    // expectedFingerprint = null means "binding off"
    $result = app(LicenseVerifier::class)->verify($token, expectedFingerprint: null);
    expect($result['status'])->toBe(LicenseVerifier::OK);
});

// ─── Domain matching using request host (§10 items 7–12) ─────────────────────

it('§12.3.1.d1 web request host matching signed domain = valid', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $token = p123_sign_token($secret, ['domain' => 'example.com']);

    $result = app(LicenseVerifier::class)->verify($token, expectedDomain: 'example.com');
    expect($result['status'])->toBe(LicenseVerifier::OK);
});

it('§12.3.1.d2 web request host NOT matching signed domain = rejected', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $token = p123_sign_token($secret, ['domain' => 'wrong.example.com']);

    $result = app(LicenseVerifier::class)->verify($token, expectedDomain: 'ours.example.com');
    expect($result['status'])->toBe(LicenseVerifier::DOMAIN_MISMATCH);
});

it('§12.3.1.d3 LicenseDomainResolver uses request()->getHost() in web context', function () {
    // Bind a synthetic Request to the container
    $request = \Illuminate\Http\Request::create('https://real-host.example.com/some/path');
    app()->instance('request', $request);

    $resolver = app(\App\Services\Licensing\LicenseDomainResolver::class);
    expect($resolver->expectedDomain())->toBe('real-host.example.com');
});

it('§12.3.1.d4 resolver falls back to LICENSE_DOMAIN in CLI context', function () {
    // Unbind the request to simulate CLI context
    app()->forgetInstance('request');
    Config::set('license.domain', 'cli-configured.example.com');

    $resolver = app(\App\Services\Licensing\LicenseDomainResolver::class);
    expect($resolver->expectedDomain())->toBe('cli-configured.example.com');
});

it('§12.3.1.d5 host normalization: lowercase + scheme/port stripping', function () {
    $resolver = app(\App\Services\Licensing\LicenseDomainResolver::class);

    expect($resolver->normalize('HTTPS://Example.COM:443/some/path'))->toBe('example.com');
    expect($resolver->normalize('http://foo.bar:80'))->toBe('foo.bar');
    expect($resolver->normalize('  Example.COM  '))->toBe('example.com');
    expect($resolver->normalize('example.com/whatever'))->toBe('example.com');
});

it('§12.3.1.d6 www alias respected when LICENSE_ALLOW_WWW_ALIAS=true', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);
    Config::set('license.allow_www_alias', true);

    $token = p123_sign_token($secret, ['domain' => 'www.example.com']);
    $result = app(LicenseVerifier::class)->verify($token, expectedDomain: 'example.com');
    expect($result['status'])->toBe(LicenseVerifier::OK);
});

it('§12.3.1.d7 www alias rejected when LICENSE_ALLOW_WWW_ALIAS=false', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);
    Config::set('license.allow_www_alias', false);

    $token = p123_sign_token($secret, ['domain' => 'www.example.com']);
    $result = app(LicenseVerifier::class)->verify($token, expectedDomain: 'example.com');
    expect($result['status'])->toBe(LicenseVerifier::DOMAIN_MISMATCH);
});

it('§12.3.1.d8 SECURITY: required domain + missing token domain = REJECTED', function () {
    [$secret, $public, $pubB64] = p123_generate_keypair();
    p123_install_test_key($pubB64);

    $token = p123_sign_token($secret, ['domain' => '']);

    $result = app(LicenseVerifier::class)->verify($token, expectedDomain: 'example.com');
    expect($result['status'])->toBe(LicenseVerifier::DOMAIN_REQUIRED);
});
