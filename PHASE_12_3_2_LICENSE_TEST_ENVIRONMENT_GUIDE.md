# Phase 12.3 v12.3.2 — License Test Environment Guide

Concrete instructions for the two PHP extensions the license test suite (and the license system generally) needs, and how to configure `.env.testing` so tests boot regardless of which drivers you have.

## TL;DR

Run this to see exactly what your environment is missing:

```bash
php artisan license:doctor
```

It reports OK / WARN / FAIL on each of: PHP version, ext-sodium, DB driver, LICENSE_PUBLIC_KEY, config flags, license tables, installation-ID storage, fingerprint, cache, and license manager status. If sodium or the DB driver is missing, it tells you the exact install command for your OS.

Then set up test env:

```bash
cp .env.testing.example .env.testing
# Default (Option C) uses SQLite in-memory — works on any environment
# with pdo_sqlite. If you prefer MySQL, uncomment Option B.
php artisan test --filter=License
```

## Why the developer's v12.3.1 test failed

The failure text:

```
could not find driver (Connection: pgsql, Database: marketplace_testing)
```

`phpunit.xml` (the CI target file) declares:

```xml
<env name="DB_CONNECTION" value="pgsql"/>
<env name="DB_DATABASE"   value="marketplace_testing"/>
```

The developer's PHP has `pdo_mysql` and `pdo_sqlite` but **not** `pdo_pgsql`. Result: PHP's PDO factory throws "could not find driver" before any assertion runs.

This is an environment mismatch, NOT a license code defect. The license tests are compatible with **all three** drivers (pgsql, mysql, sqlite) — the CI target just happens to be pgsql.

### Fix option A — install pdo_pgsql (matches CI)

**Ubuntu / Debian:**

```bash
php --version                               # note the version, e.g. 8.3.x
sudo apt-get install php8.3-pgsql           # adjust version to match
sudo service php8.3-fpm restart             # or apache2 restart for mod_php
php -m | grep -E "pgsql|pdo_pgsql"
```

**Windows / XAMPP / Laragon:**

1. Open `php.ini` (path shown by `php --ini`)
2. Uncomment `extension=pdo_pgsql`
3. Uncomment `extension=pgsql`
4. Restart Apache / PHP-FPM (or reboot the service in the Laragon panel)
5. Verify: `php -m | findstr pgsql`

**macOS (Homebrew):**

```bash
brew install php@8.3
# php@8.3 bundles pdo_pgsql by default; verify:
php -m | grep pdo_pgsql
```

**Docker:**

```dockerfile
RUN docker-php-ext-install pdo pdo_pgsql pgsql
```

### Fix option B — use pdo_mysql (developer's environment supports this)

The developer's `php -m` shows `pdo_mysql` is loaded. To use MySQL for testing:

```bash
cp .env.testing.example .env.testing
```

Edit `.env.testing`, comment out Option C, and uncomment Option B:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=marketplace_testing
DB_USERNAME=root
DB_PASSWORD=
```

Then create the database:

```sql
CREATE DATABASE marketplace_testing;
```

And run:

```bash
php artisan test --filter=License
```

Laravel's `.env.testing` values override the `<env>` entries in `phpunit.xml`, so no other configuration change is needed.

### Fix option C — use SQLite in-memory (fastest, zero external services)

This is the **DEFAULT** in `.env.testing.example`. It works on any environment with `pdo_sqlite` — including the developer's — with zero external services.

```bash
cp .env.testing.example .env.testing
# Option C (SQLite) is already uncommented as the default
php artisan test --filter=License
```

If `pdo_sqlite` is somehow not loaded:

**Ubuntu / Debian:**

```bash
sudo apt-get install php8.3-sqlite3
sudo service php8.3-fpm restart
```

**Windows:** in `php.ini`, uncomment `extension=pdo_sqlite`.

### Why don't we just change `phpunit.xml`?

Because `phpunit.xml` is CI config — the pipeline expects to run against real PostgreSQL. Modifying it in this repo could regress CI. The correct pattern is a per-developer `.env.testing` file (already gitignored) that overrides the CI defaults locally. `phpunit.xml` intentionally NOT modified in v12.3.2 (or v12.3.1).

---

## The sodium extension — REQUIRED for license cryptography

`php -m` output on the developer's machine shows sodium is **missing**. Sodium is required for:

- Ed25519 signature verification in `LicenseVerifier::verify()`
- Ed25519 keypair generation in `tools/license-generator/generate.php`
- Ed25519 token signing in `tools/license-generator/generate.php`
- `sodium_memzero` cleanup of key material in the generator

**Without sodium, EVERY license verification will fatal with an "undefined function" error the moment a token is loaded.**

### v12.3.2 mitigation: `license:doctor` catches this BEFORE it fatals

The new `php artisan license:doctor` command specifically checks for `extension_loaded('sodium')` AND the individual Ed25519 helper functions we call. If sodium is missing OR a build is incomplete, the FAIL row prints the exact install command for the operator's OS. No cryptic runtime error.

### Install commands

**Ubuntu / Debian:**

```bash
php -m | grep sodium
sudo apt-get install php8.3-sodium          # adjust version to match
sudo service php8.3-fpm restart
php -m | grep sodium
```

**Windows / XAMPP / Laragon:**

1. Open `php.ini`
2. Uncomment `extension=sodium`
3. Confirm `libsodium.dll` (Windows) is in your PHP `ext/` directory — bundled with modern PHP-for-Windows distributions
4. Restart PHP / Apache
5. Verify: `php -m | findstr sodium`

**macOS (Homebrew):**

```bash
# Sodium is bundled with php@8.3 on Homebrew; verify:
php -m | grep sodium
# If missing (unusual):
brew reinstall php@8.3
```

**Docker:**

```dockerfile
RUN apt-get update && apt-get install -y libsodium-dev \
    && docker-php-ext-install sodium
```

### Verifying the install worked

```bash
php artisan license:doctor
```

Look for the `[  OK  ] ext-sodium` row. If it says FAIL, follow the hint printed on the next line.

You can also test manually:

```bash
php -r 'var_dump(extension_loaded("sodium"), function_exists("sodium_crypto_sign_verify_detached"));'
```

Both should print `bool(true)`.

---

## Complete preflight for a fresh machine

If you're bringing a new dev machine online:

```bash
# 1. Confirm PHP version
php --version   # need 8.2 or newer

# 2. Confirm required extensions
php -m | grep -E "sodium|pdo_(pgsql|mysql|sqlite)"
# Need sodium PLUS at least one of the pdo drivers

# 3. Install anything missing (see above)

# 4. Set up .env.testing
cp .env.testing.example .env.testing
# Edit if you want a different DB driver

# 5. Run the doctor
php artisan license:doctor
# Every row should be OK (or WARN — WARN is fine to proceed with; FAIL blocks)

# 6. Migrate
php artisan migrate --force

# 7. Run the license test suite
php artisan test --filter=License
# Expected: 34 pass (Phase 12.3 v12.3.1: 20 baseline + 14 new)
```

## Trusted-proxy configuration (production only — reminder from v12.3.1)

v12.3.1 changed domain-matching to use `request()->getHost()` instead of APP_URL. If your production sits behind a load balancer:

1. Verify `app/Http/Middleware/TrustProxies.php` OR `config/trustedproxy.php` lists your LB's IPs
2. Verify `X-Forwarded-Host` is forwarded by the LB
3. Test the resolved host: `curl -H "Host: real-domain.com" ...` should reach Laravel with `request()->getHost() === 'real-domain.com'`

Without this, the domain check may reject valid tokens because Laravel sees the LB's IP as the host instead of the real client-facing domain.

## When something still doesn't work

Order of operations:

1. `php artisan license:doctor` — will name the specific extension or config issue
2. `php -m | grep -E "sodium|pdo_"` — confirms extension load state
3. `php artisan config:cache && php artisan config:clear` — force config re-read after `.env.testing` changes
4. `php artisan cache:clear && php artisan license:clear-cache`
5. `php artisan migrate:fresh --seed` (destructive — dev DB only)
6. Delete `storage/app/license/installation_id` — regenerates fingerprint on next request (invalidates existing activation!)

If the doctor command says everything is OK but tests still fail, that's a code issue — capture the failing test names + assertions and file it back.
