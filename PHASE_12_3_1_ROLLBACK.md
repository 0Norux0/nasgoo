# Phase 12.3 v12.3.1 — Rollback Procedure

Tier 1 revert if any v12.3.1 fix regresses. Reminder: Bug #3 and Bug #4 were **security** fixes — rolling those back reopens the fingerprint bypass and the APP_URL-based domain drift.

## Scope

v12.3.1 touches:

- `app/Services/Licensing/LicenseVerifier.php` (fingerprint + domain security fixes)
- `app/Services/Licensing/LicenseManager.php` (uses domain resolver instead of APP_URL)
- `app/Services/Licensing/LicenseDomainResolver.php` (NEW — request-host resolver)
- `config/license.php` (added `domain` + `allow_www_alias` keys)
- `.env.example.production` (added `LICENSE_DOMAIN` + `LICENSE_ALLOW_WWW_ALIAS`)
- `tools/license-generator/generate.php` (removed dead sk_to_curve25519 call)
- `tools/license-generator/README.md` (doc clarifications)
- `LICENSE_PRIVATE_KEY_OWNER_INSTRUCTIONS.md` (doc clarifications)
- `PHASE_12_3_LICENSE_OWNER_GUIDE.md` (doc clarifications)
- `.env.testing.example` (NEW — DB driver options)
- `tests/Feature/Phase12_3LicenseActivationTest.php` (+14 scenarios, 34 total)
- `VERSION` (bumped)
- 5 new `PHASE_12_3_1_*.md` documents

No migration change. No route change. No dependency change. No behavior change on the auth path outside of `LicenseController` (unchanged in v12.3.1).

## When to roll back

- The v12.3.1 fingerprint-required rejection is blocking a token you know is legitimate (the token was actually issued without a fingerprint claim — regenerate it with `--fingerprint <fp>`)
- The v12.3.1 request-host domain resolver is reading the wrong host (usually a TrustProxies misconfiguration — fix that instead)
- `php artisan test --filter=Phase12_3License` regresses on scenarios that PASSED in Phase 12.3

**Do NOT roll back just because activation rejected a formerly-accepted token — that rejection is likely correct now that the bypass is closed.**

## Tier 1 — Revert the license code files

Fastest option. Restore only the 6 modified files from Phase 12.3.

### Prerequisites

- Prior approved archive: `marketplace-phase-12-3-license-activation-protection.tar.gz`
- OR Git commit at `phase-12-3-license-activation`

### Step 1 — Maintenance mode (recommended)

```bash
sudo -u www-data php artisan down --refresh=15
```

### Step 2 — Restore the 6 code files

Option A — Git:

```bash
cd /var/www/marketplace
sudo -u www-data git checkout phase-12-3-license-activation -- \
    app/Services/Licensing/LicenseVerifier.php \
    app/Services/Licensing/LicenseManager.php \
    config/license.php \
    .env.example.production \
    tools/license-generator/generate.php \
    tools/license-generator/README.md \
    LICENSE_PRIVATE_KEY_OWNER_INSTRUCTIONS.md \
    PHASE_12_3_LICENSE_OWNER_GUIDE.md

# Remove the new resolver + testing config
sudo rm -f app/Services/Licensing/LicenseDomainResolver.php
sudo rm -f .env.testing.example
```

Option B — Archive extract:

```bash
cd /tmp
tar -xzf /path/to/marketplace-phase-12-3-license-activation-protection.tar.gz \
    marketplace/app/Services/Licensing/LicenseVerifier.php \
    marketplace/app/Services/Licensing/LicenseManager.php \
    marketplace/config/license.php \
    marketplace/.env.example.production \
    marketplace/tools/license-generator/generate.php \
    marketplace/tools/license-generator/README.md \
    marketplace/LICENSE_PRIVATE_KEY_OWNER_INSTRUCTIONS.md \
    marketplace/PHASE_12_3_LICENSE_OWNER_GUIDE.md

for f in \
    app/Services/Licensing/LicenseVerifier.php \
    app/Services/Licensing/LicenseManager.php \
    config/license.php \
    .env.example.production \
    tools/license-generator/generate.php \
    tools/license-generator/README.md \
    LICENSE_PRIVATE_KEY_OWNER_INSTRUCTIONS.md \
    PHASE_12_3_LICENSE_OWNER_GUIDE.md
do
    sudo cp "marketplace/$f" "/var/www/marketplace/$f"
    sudo chown www-data:www-data "/var/www/marketplace/$f"
done

# Remove new files
sudo rm -f /var/www/marketplace/app/Services/Licensing/LicenseDomainResolver.php
sudo rm -f /var/www/marketplace/.env.testing.example
```

### Step 3 — Revert VERSION

```bash
echo "Phase 12.3 License Activation" | sudo tee /var/www/marketplace/VERSION > /dev/null
```

### Step 4 — Rebuild caches

```bash
cd /var/www/marketplace
sudo -u www-data composer dump-autoload -o
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan license:clear-cache
```

### Step 5 — Bring back up

```bash
sudo -u www-data php artisan up
```

### Step 6 — Verify

- [ ] `cat VERSION` returns `Phase 12.3 License Activation`
- [ ] `/admin/license` loads
- [ ] `php artisan license:status` reports the correct state
- [ ] `ls app/Services/Licensing/LicenseDomainResolver.php` reports "not found"

## What Tier 1 rollback RE-OPENS (security regression!)

- **Fingerprint bypass**: signed tokens with missing/empty/whitespace `server_fingerprint` will again be accepted even when `LICENSE_REQUIRE_FINGERPRINT_MATCH=true`
- **APP_URL-dependent domain**: stale/misconfigured `APP_URL` will again reject valid tokens or accept wrong-host tokens
- **Dead sodium call**: keypair generation may fatally error on libsodium builds without the Curve25519 conversion helper

You accept these regressions if you roll back. Consider Tier 2 (documented issue + patch) instead if the v12.3.1 fix is misbehaving in your specific environment.

## Tier 2 — Selective disable via config

If a specific v12.3.1 security check is misbehaving, you can turn OFF just that binding without reverting the code:

```env
# In production .env:
LICENSE_REQUIRE_FINGERPRINT_MATCH=false   # if fingerprint check is over-rejecting
LICENSE_REQUIRE_DOMAIN_MATCH=false        # if domain check is over-rejecting
```

Then:

```bash
php artisan config:cache
php artisan license:clear-cache
```

This preserves the v12.3.1 code (and all its other fixes) while temporarily softening one specific binding. Track the reason in your operations log and re-enable after diagnosing.

## Tier 3 — Full Phase 12.3 rollback

If you want to revert the entire Phase 12.3 license layer (uninstall license enforcement):

See `PHASE_12_3_LICENSE_ROLLBACK.md`. That drops back to v12.2.3 — no license gate anywhere.

## What NOT to roll back

- Migration schema — no v12.3.1 migration change; leave existing `license_activations` / `license_audit_logs` tables intact.
- Active license activation rows in the DB — preserved.
- `storage/app/license/installation_id` — DO NOT delete; regenerating changes the fingerprint.
- `bootstrap/app.php` — unchanged in v12.3.1.
- `routes/web.php` — unchanged in v12.3.1.
- Any Phase 12.2, v12.2.1, v12.2.2, v12.2.3 file.

## Sign-off

- Reviewer name: _________________________
- Date: _________________________
- Tier chosen (1, 2, or 3): _________________________
- Reason for rollback: _________________________
- Security-regression acknowledgment (Tier 1 only): [ ]
- Post-rollback verification passed: [ ]
