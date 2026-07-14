<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════════════
 *  Marketplace License Generator (Phase 12.3)
 * ═══════════════════════════════════════════════════════════════════════════
 *
 *  OFFLINE tool. Run ONLY on the license owner's private machine.
 *
 *  This script is deliberately SEPARATE from the production runtime — it is
 *  not autoloaded, not registered as an artisan command, and does not depend
 *  on Laravel. The only shared code path is the Ed25519 primitives from
 *  ext-sodium (built into PHP >= 7.2).
 *
 *  It NEVER writes the private key anywhere. It reads the private key from
 *  a file path the operator supplies, signs the requested payload, prints
 *  the token, and exits.
 *
 *  Usage:
 *
 *      # First time: generate a keypair
 *      php generate.php --generate-keypair --out ./license-keys/
 *
 *      # Subsequent: sign a license
 *      php generate.php \
 *          --private-key ./license-keys/private.b64 \
 *          --holder "Aamir Farooq / ICSA" \
 *          --domain example.com \
 *          --days 60 \
 *          --type owner \
 *          --fingerprint <optional-fingerprint>
 *
 *  Output is a single-line license token you paste into /admin/license or
 *  pass to `php artisan license:activate`.
 * ═══════════════════════════════════════════════════════════════════════════
 */

// ─── Requirements check ─────────────────────────────────────────────────────
if (PHP_VERSION_ID < 80300) {
    fwrite(STDERR, "Error: PHP >= 8.3 required (found " . PHP_VERSION . ")\n");
    exit(2);
}
if (! extension_loaded('sodium')) {
    fwrite(STDERR, "Error: ext-sodium not loaded. Install php-sodium (built into PHP >= 7.2).\n");
    exit(2);
}

// ─── Arg parsing (deliberately minimal — no getopt() ambiguity) ─────────────
$args = [];
for ($i = 1; $i < $argc; $i++) {
    $a = $argv[$i];
    if (str_starts_with($a, '--')) {
        $k = substr($a, 2);
        if (in_array($k, ['generate-keypair', 'help', 'h'], true)) {
            $args[$k] = true;
        } elseif (isset($argv[$i + 1]) && ! str_starts_with($argv[$i + 1], '--')) {
            $args[$k] = $argv[++$i];
        } else {
            $args[$k] = true;
        }
    }
}

if (isset($args['help']) || isset($args['h'])) {
    printHelp(); exit(0);
}

// ─── Mode 1: keypair generation ─────────────────────────────────────────────
if (isset($args['generate-keypair'])) {
    $outDir = rtrim((string) ($args['out'] ?? './license-keys'), '/');
    if (! is_dir($outDir)) {
        if (! mkdir($outDir, 0700, true) && ! is_dir($outDir)) {
            fwrite(STDERR, "Cannot create output directory: $outDir\n"); exit(2);
        }
    }
    $keypair = sodium_crypto_sign_keypair();
    $secret  = sodium_crypto_sign_secretkey($keypair);
    $public  = sodium_crypto_sign_publickey($keypair);

    // v12.3.1 removed: previous code invoked
    //     $seed = sodium_crypto_sign_ed25519_sk_to_curve25519($secret);
    // whose result was immediately overwritten by the correct substr()
    // call below. That helper also (a) does X25519 conversion, not seed
    // extraction, and (b) may throw on libsodium builds without the
    // Ed25519↔Curve25519 conversion helpers. The seed we want is simply
    // the first 32 bytes of the 64-byte secret key.
    $rawSeed = substr($secret, 0, SODIUM_CRYPTO_SIGN_SEEDBYTES);

    $privPath = "$outDir/private.b64";
    $pubPath  = "$outDir/public.b64";

    if (file_exists($privPath) || file_exists($pubPath)) {
        fwrite(STDERR, "Refusing to overwrite existing keys in $outDir. Delete them first if you really mean to.\n");
        exit(2);
    }
    file_put_contents($privPath, base64_encode($rawSeed) . "\n");
    file_put_contents($pubPath,  base64_encode($public)  . "\n");
    chmod($privPath, 0600);
    chmod($pubPath,  0644);

    echo "Keypair generated.\n";
    echo "  private (KEEP SECRET):  $privPath\n";
    echo "  public  (install in app): $pubPath\n\n";
    echo "Public key (paste into .env as LICENSE_PUBLIC_KEY):\n";
    echo "  " . trim(base64_encode($public)) . "\n\n";
    echo "The private key MUST NEVER leave this machine or your password manager.\n";
    echo "It has been written with mode 0600 (owner read/write only).\n";

    // Zero the sensitive material from memory
    sodium_memzero($secret);
    sodium_memzero($rawSeed);
    exit(0);
}

// ─── Mode 2: sign a token ───────────────────────────────────────────────────
foreach (['private-key', 'holder', 'domain', 'days'] as $required) {
    if (! isset($args[$required])) {
        fwrite(STDERR, "Missing --$required (see --help)\n");
        exit(2);
    }
}

$privPath = (string) $args['private-key'];
if (! is_readable($privPath)) {
    fwrite(STDERR, "Private key not readable: $privPath\n"); exit(2);
}
$rawSeed = base64_decode(trim((string) file_get_contents($privPath)), true);
if ($rawSeed === false || strlen($rawSeed) !== SODIUM_CRYPTO_SIGN_SEEDBYTES) {
    fwrite(STDERR, "Private key file is corrupt or wrong length (want 32 raw bytes base64-encoded)\n");
    exit(2);
}

// Regenerate the full 64-byte secret key from the 32-byte seed
$keypair    = sodium_crypto_sign_seed_keypair($rawSeed);
$secretKey  = sodium_crypto_sign_secretkey($keypair);

// Build the payload
$days   = max(1, (int) $args['days']);
$now    = new DateTimeImmutable('now', new DateTimeZone('UTC'));
$exp    = $now->modify("+{$days} days");

$payload = [
    'app'                => (string) ($args['app'] ?? 'ICSA Marketplace'),
    'domain'             => (string) $args['domain'],
    'expires_at'         => $exp->format('Y-m-d\TH:i:s\Z'),
    'issued_at'          => $now->format('Y-m-d\TH:i:s\Z'),
    'license_holder'     => (string) $args['holder'],
    'license_type'       => (string) ($args['type'] ?? 'standard'),
    'max_days'           => $days,
    'nonce'              => bin2hex(random_bytes(8)),
    'server_fingerprint' => isset($args['fingerprint']) ? (string) $args['fingerprint'] : null,
];

$header = ['alg' => 'EdDSA', 'typ' => 'MPLIC'];

// Sort keys for deterministic JSON (matches the verifier)
ksort($payload);
ksort($header);

$hB64  = b64urlEncode((string) json_encode($header,  JSON_UNESCAPED_SLASHES));
$pB64  = b64urlEncode((string) json_encode($payload, JSON_UNESCAPED_SLASHES));
$msg   = "$hB64.$pB64";
$sig   = sodium_crypto_sign_detached($msg, $secretKey);
$sB64  = b64urlEncode($sig);

$token = "$hB64.$pB64.$sB64";

// Zero secret from memory
sodium_memzero($secretKey);
sodium_memzero($rawSeed);

echo $token . "\n";
exit(0);

// ─── Helpers ────────────────────────────────────────────────────────────────
function b64urlEncode(string $bytes): string
{
    return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
}

function printHelp(): void
{
    echo <<<HELP
Marketplace License Generator (offline)

Modes:
  --generate-keypair --out ./license-keys/
      Generate a fresh Ed25519 keypair. Prints the public key for
      installation into .env. NEVER shares the private key.

  --private-key ./private.b64 --holder "..." --domain ... --days 60
      Sign a license token. Prints the token to stdout.

Options:
  --generate-keypair   Generate a keypair instead of signing.
  --out                Output directory for keypair (default ./license-keys).
  --private-key        Path to the base64-encoded 32-byte raw private seed.
  --holder             License holder name (goes into the payload).
  --domain             The domain this token is bound to (required).
  --days               Duration in days (default 60).
  --type               License type: 'owner' | 'standard' | 'trial' (default 'standard').
  --app                App name in the payload (default 'ICSA Marketplace').
  --fingerprint        Optional server fingerprint the token binds to.
  --help, -h           This help text.

Examples:
  php generate.php --generate-keypair --out /secure/license-keys

  php generate.php \\
      --private-key /secure/license-keys/private.b64 \\
      --holder "Aamir Farooq / ICSA" \\
      --domain marketplace.example.com \\
      --days 60 \\
      --type owner

HELP;
}
