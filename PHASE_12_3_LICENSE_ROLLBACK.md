# Phase 12.3 — License Activation Rollback

If the license layer misbehaves, here's how to unwind it safely without touching any marketplace business data. Every step below is non-destructive.

## Tier 0 — Disable enforcement (fastest, safest)

**Downtime: seconds.**

Set in production `.env`:

```env
LICENSE_ENFORCEMENT_ENABLED=false
```

Then:

```bash
php artisan config:cache
php artisan license:clear-cache
```

The middleware becomes a no-op passthrough. Users see the marketplace exactly as they did before Phase 12.3. Existing `license_activations` and `license_audit_logs` rows remain intact for audit.

Use this if:
- The license gate is blocking users incorrectly
- An activation token is somehow invalid but you can't issue a fresh one
- You need to buy time to debug

## Tier 1 — Revert to v12.2.2 baseline

**Downtime: 1-3 minutes.**

If the license code has a real bug in it and Tier 0 isn't enough:

```bash
# On production
cd /var/www/marketplace
sudo -u www-data git stash  # if using Git
# OR extract v12.2.2 tarball:
sudo tar -xzf /var/backups/marketplace-phase-12-2-2-final-lint-format-repair.tar.gz \
    -C /var/www/ --strip-components=1
sudo chown -R www-data:www-data /var/www/marketplace

# Rebuild caches
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci && sudo -u www-data npm run build
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan queue:restart
```

The v12.2.2 archive contains no license code. The `license_activations` and `license_audit_logs` tables remain in the DB (they're additive), doing nothing.

## Tier 2 — Rolling back the migration

**Downtime: 30-60 seconds.**

If you specifically want the `license_*` tables gone (for a truly clean revert):

```bash
sudo -u www-data php artisan migrate:rollback --step=1
```

This drops `license_audit_logs` and `license_activations`. Nothing else is affected because the migration is additive.

**Do NOT** roll back more than one step — earlier migrations touch business tables (vendor intelligence, orders, etc.).

## Tier 3 — Disabling only for a subset of routes

If enforcement is desired for MOST routes but not one specific one, add the route's name or URI prefix to `config/license.php`:

```php
'exempt_route_names' => [
    ...existing...,
    'your.new.route.name',
],

'exempt_uri_prefixes' => [
    ...existing...,
    '/your/new/uri/prefix',
],
```

Then:

```bash
php artisan config:cache
php artisan license:clear-cache
```

This is a code change (not a rollback), but it lets you keep the license gate while unblocking a specific path that legitimately shouldn't be gated.

## What NEVER happens during rollback

- No `orders` row is deleted
- No `products` row is deleted
- No `users` row is deleted
- No `vendors` row is deleted
- No `.env` file is written to (rollback edits it, but never overwrites unrelated values)
- No production data is destroyed by any tier above

The license layer was designed to be strictly additive. Rolling it back is safe.

## Recovery testing

Before your first production deploy of Phase 12.3, drill this on staging:

1. Deploy v12.3 to staging
2. Enable enforcement + install key + activate a token
3. Simulate a bad state (e.g. delete the activation row manually)
4. Execute Tier 0 rollback
5. Verify staging is functional
6. Re-enable enforcement + re-activate

Time to complete the drill should be under 5 minutes. If not, something in the rollback path needs to be simpler.

## Do NOT do during rollback

- Do NOT `TRUNCATE license_activations` — the row is your audit record. Update `status='revoked'` instead if needed.
- Do NOT `TRUNCATE license_audit_logs` — it's your incident timeline.
- Do NOT delete `storage/app/license/installation_id` — this changes the fingerprint. If regenerated, previously-issued fingerprint-bound tokens stop verifying.
- Do NOT change `LICENSE_PUBLIC_KEY` in production during a rollback — that invalidates all previously-issued tokens.
- Do NOT run `php artisan migrate:fresh` at any point.
