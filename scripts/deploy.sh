#!/usr/bin/env bash
#
# ═══════════════════════════════════════════════════════════════════════════
#  LEGACY — DO NOT USE FOR PRODUCTION
# ═══════════════════════════════════════════════════════════════════════════
#
#  This script was written in Phase 10 v10.2. It runs `php artisan migrate --force`
#  without a database backup and without operator confirmation. That behavior is
#  UNSAFE for a live marketplace.
#
#  For production deployments, use:
#
#      scripts/deploy-production-phase12.sh
#
#  This file is retained for local development / disposable-environment reference
#  only. It refuses to run when APP_ENV=production is set in .env.
# ═══════════════════════════════════════════════════════════════════════════

# Refuse to run against production
if [ -f .env ]; then
    if grep -qE '^APP_ENV=production' .env; then
        echo "REFUSING to run: this legacy script is unsafe against APP_ENV=production." >&2
        echo "Use scripts/deploy-production-phase12.sh instead." >&2
        exit 2
    fi
fi

set -euo pipefail

#
# Phase 10 v10.2 — deployment script
#
# Purpose: ensure every cache layer is invalidated when applying a new
# release, since stale caches are the most likely cause of "I deployed the
# new version but I see the old behaviour."
#
# Runs from the project root:
#   cd /var/www/marketplace && ./scripts/deploy.sh
#
# Requires: composer, npm, php (8.3+), redis-cli optional, mysql client optional.
#

echo "═══════════════════════════════════════════════════════════════════"
echo "  Marketplace deploy.sh — Phase 10 v10.2"
echo "═══════════════════════════════════════════════════════════════════"

# Sanity check
if [ ! -f VERSION ] || [ ! -f artisan ]; then
    echo "ERROR: VERSION or artisan file not found. Are you in the project root?" >&2
    exit 1
fi

echo ""
echo "  Current VERSION: $(cat VERSION)"
echo ""

# Step 1 — verify the source actually contains v10.2
echo "[1/9] Verifying source contains v10.1+v10.2 fixes..."
if ! grep -q "unset(\$data\['images'\])" app/Http/Controllers/Vendor/VendorProductController.php 2>/dev/null; then
    echo "✗ source does not contain the v10.1 product images fix — re-extract the archive" >&2
    exit 1
fi
if [ ! -f resources/js/Layouts/AdminLayout.tsx ]; then
    echo "✗ AdminLayout.tsx missing — re-extract the archive" >&2
    exit 1
fi
echo "    ✓ source contains v10.1+v10.2 fixes"
echo ""

# Step 2 — composer
echo "[2/9] Installing PHP dependencies (composer install --no-dev)..."
composer install --no-dev --optimize-autoloader --no-interaction
echo ""

# Step 3 — npm
echo "[3/9] Installing JS dependencies (npm ci)..."
npm ci
echo ""

# Step 4 — Vite build (CRITICAL — without this, the browser serves the OLD compiled JS)
echo "[4/9] Building frontend assets (npm run build)..."
echo "      *** This step is essential — if you skip it, the browser will"
echo "      *** load the OLD compiled JS and the v10.1+v10.2 React fixes"
echo "      *** will appear absent."
npm run build
echo ""

# Step 5 — database migrations
echo "[5/9] Running migrations (php artisan migrate --force)..."
php artisan migrate --force
echo ""

# Step 6 — flush ALL caches (Laravel + Filament + Spatie permission)
echo "[6/9] Flushing every cache layer..."
php artisan optimize:clear         # config + route + view + compiled
php artisan route:clear
php artisan config:clear
php artisan view:clear
php artisan cache:clear
# Filament caches its navigation registry; the package provides a cache-reset command
if php artisan list 2>/dev/null | grep -q "filament:cache-components"; then
    php artisan filament:cache-components || true
fi
# Spatie permission cache — if stale, role checks return false even when permissions ARE granted
if php artisan list 2>/dev/null | grep -q "permission:cache-reset"; then
    php artisan permission:cache-reset || true
fi
echo "    ✓ all caches flushed"
echo ""

# Step 7 — rebuild production caches (route + view + config)
echo "[7/9] Rebuilding production caches..."
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache 2>/dev/null || true
echo ""

# Step 8 — verify the fixes are detectable in the live source
echo "[8/9] Running fix verification..."
if ! php artisan marketplace:verify-fixes; then
    echo ""
    echo "✗ Fix verification failed. The deployed source is missing one or" >&2
    echo "  more v10.1/v10.2 corrections. Re-extract the archive." >&2
    exit 1
fi
echo ""

# Step 9 — restart queue worker (so the new code is loaded)
echo "[9/9] Restarting queue worker..."
if systemctl is-active --quiet marketplace-queue 2>/dev/null; then
    sudo systemctl restart marketplace-queue && echo "    ✓ marketplace-queue restarted"
else
    php artisan queue:restart && echo "    ✓ queue:restart signal sent"
fi

# Optional: reload PHP-FPM to flush OPcache. OPcache validation depends on
# opcache.validate_timestamps — if 0 (recommended in production), code
# changes only become live after an FPM reload.
echo ""
echo "[optional] Reloading PHP-FPM (flushes OPcache)..."
if command -v systemctl >/dev/null 2>&1; then
    if systemctl is-active --quiet php8.3-fpm 2>/dev/null; then
        sudo systemctl reload php8.3-fpm 2>/dev/null && echo "    ✓ php8.3-fpm reloaded" || echo "    ⚠ couldn't reload php8.3-fpm; do it manually if OPcache is enabled"
    elif systemctl is-active --quiet php-fpm 2>/dev/null; then
        sudo systemctl reload php-fpm 2>/dev/null && echo "    ✓ php-fpm reloaded"
    else
        echo "    ⚠ PHP-FPM service not detected; if OPcache is enabled with validate_timestamps=0, you MUST reload your PHP-FPM service manually"
    fi
fi

echo ""
echo "═══════════════════════════════════════════════════════════════════"
echo "  Deploy complete. VERSION: $(cat VERSION)"
echo "═══════════════════════════════════════════════════════════════════"
echo ""
echo "  Verify v10.2 is live by visiting any page and confirming the"
echo "  storefront footer shows '· v$(cat VERSION)'."
echo ""
echo "  If you still see issues, run:"
echo "    php artisan marketplace:verify-fixes"
