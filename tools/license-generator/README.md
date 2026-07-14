# Marketplace License Generator — Owner-Only Tool

This directory contains the offline license token generator. Only the person holding the private signing key should ever use it. It is intentionally NOT wired into the Laravel application — it's a standalone CLI.

## Prerequisites

- PHP 8.3 or later
- `ext-sodium` (built into PHP since 7.2)

## First-time setup

Generate a signing keypair. Run this ONCE, on the owner's private laptop or a dedicated air-gapped machine:

```bash
mkdir -p /secure/license-keys
chmod 700 /secure/license-keys
php tools/license-generator/generate.php --generate-keypair --out /secure/license-keys
```

The command outputs:

- `/secure/license-keys/private.b64` — the private signing key (mode 0600). **KEEP SECRET.**
- `/secure/license-keys/public.b64` — the public verification key. Copy this into the deployed app's `.env` as `LICENSE_PUBLIC_KEY=<base64>`.

**Never** commit the `private.b64` file to Git. Never put it in the marketplace archive. Never put it in `.env`. Ideally, encrypt it with GPG (`gpg -c private.b64`) and store the encrypted copy in a password manager and one air-gapped backup.

## Signing a license token

Every time you want to issue a fresh 60-day (or other duration) license:

```bash
php tools/license-generator/generate.php \
    --private-key /secure/license-keys/private.b64 \
    --holder "Aamir Farooq / ICSA" \
    --domain marketplace.example.com \
    --days 60 \
    --type owner
```

The command prints a single-line token to stdout. Copy that token and paste it into `/admin/license` on the deployed marketplace, OR run:

```bash
php artisan license:activate <token>
```

## Optional: bind a token to a specific server fingerprint

The deployed app can print its fingerprint:

```bash
php artisan license:fingerprint
# → prints installation ID + app host + SHA-256 fingerprint
```

Include the fingerprint when signing to prevent the token from being used on a different installation:

```bash
php tools/license-generator/generate.php \
    --private-key /secure/license-keys/private.b64 \
    --holder "Aamir Farooq / ICSA" \
    --domain marketplace.example.com \
    --days 60 \
    --fingerprint <64-hex-chars-from-fingerprint-command>
```

To use this feature, also set `LICENSE_REQUIRE_FINGERPRINT_MATCH=true` in the deployment's `.env`.

## Rotating keys

If a private key is compromised:

1. Generate a new keypair on a fresh secure machine
2. Update `LICENSE_PUBLIC_KEY` in the production `.env`
3. Run `php artisan license:clear-cache && php artisan config:cache`
4. Any tokens signed by the old key will fail verification — issue a fresh one
5. Old activation rows in `license_activations` remain (audit trail) but their verifier check will fail; they will be treated as `unlicensed` until a new token is activated

## Never do

- Never store the private key in the marketplace archive
- Never store the private key on the production server
- Never email or Slack the private key
- Never commit the `license-keys/` directory to Git
- Never share the private key with anyone else

## What this tool does NOT do

- It does NOT connect to the deployed marketplace
- It does NOT talk to any license server
- It does NOT phone home
- It does NOT need internet access
- It does NOT read any Laravel config

It is a pure offline cryptographic signer. That is by design.

## Honest disclosure about the private key (v12.3.1 clarification)

Earlier versions of this document implied the tool "never writes the private
key anywhere." That was inaccurate and has been corrected. The precise truth:

**The production application package NEVER contains the private key.** The
Laravel app, the shipped `.zip` / `.tar.gz`, `.env`, `config/`, `database/`,
and every file inside the marketplace deployment carry only the paired
public verification key (`LICENSE_PUBLIC_KEY`).

**The offline generator MAY write a `private.b64` file to disk** — but ONLY
when explicitly invoked in keypair-generation mode:

```bash
php tools/license-generator/generate.php --generate-keypair --out ./license-keys
```

That command writes:
- `./license-keys/private.b64` (mode 0600) — the private signing key
- `./license-keys/public.b64` (mode 0644) — the public verification key

Signing mode (the normal use — `--private-key ... --holder ... --domain ...`)
does NOT write the private key; it READS it from the path you supply, uses
it to sign a token, zeroes it from memory (`sodium_memzero`), and exits.

So the private key file exists on disk ONLY in the location where the OWNER
generated it, on the OWNER'S trusted machine. The tool does not copy it,
upload it, or persist it elsewhere.

### Rules for the written `private.b64` file

The `private.b64` file that keypair mode produces:

- **Must be generated only by the owner** (Aamir) on a trusted private
  machine — not on the production server, not on the developer's machine,
  not in a shared workspace
- **Must NOT be shared with the developer** — the developer never needs
  the private key; they receive signed tokens instead
- **Must NOT be uploaded to any server** — including the production
  marketplace, staging environments, cloud sync (Google Drive/iCloud/
  Dropbox unless encrypted)
- **Must NOT be committed to Git** — not to the marketplace repo, not to
  a "secrets" repo, not anywhere version-controlled
- **Must be backed up securely offline** — see `LICENSE_PRIVATE_KEY_OWNER_INSTRUCTIONS.md`
  for the "at least TWO backup locations" rule (password manager, GPG-encrypted
  cloud file, air-gapped USB drive)
- **Temporary staging/dev keys** used by developers or QA for testing MUST
  be clearly marked (e.g. `private.b64.STAGING-DELETE-AFTER-TEST`) and
  destroyed with `shred -u` immediately after use

Any deviation from these rules eliminates the ownership protection this
system was designed to provide.
