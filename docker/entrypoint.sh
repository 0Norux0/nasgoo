#!/usr/bin/env bash
set -e

cd /var/www/html

echo "▶ Marketplace container starting…"

# Wait for Postgres
if [ -n "${DB_HOST:-}" ]; then
    echo "▶ Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT:-5432}…"
    until php -r "exit(@fsockopen('${DB_HOST}', ${DB_PORT:-5432}) ? 0 : 1);" 2>/dev/null; do
        sleep 1
    done
    echo "✓ PostgreSQL is reachable."
fi

# Ensure .env exists
if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
    echo "✓ Created .env from .env.example"
fi

# Generate app key if missing
if [ -f artisan ] && grep -q "^APP_KEY=$" .env 2>/dev/null; then
    php artisan key:generate --force
    echo "✓ APP_KEY generated"
fi

# Storage symlink (idempotent thanks to --force; won't error if it exists)
if [ -f artisan ]; then
    php artisan storage:link --force
fi

# Permissions
# Note on chown: in production (no bind mount) this always succeeds. With
# dev bind mounts the host UID owns the files; chown to www-data may still
# succeed since the container runs as root, but on some Docker filesystems
# (rootless, certain SELinux setups) it cannot. We let the command run
# and let any failure surface — bind-mount permission issues are real
# problems worth seeing.
if [ -d storage ]; then
    chown -R www-data:www-data storage bootstrap/cache
    chmod -R 775 storage bootstrap/cache
fi

# ──────────────────────────────────────────────────────────────
# v3.3 — Filament assets are republished unconditionally.
#
# v3.1 only republished if public/css/filament was *missing*. But
# upgrading the Filament package without rebuilding the image leaves
# stale assets in place — the directory exists, so the check passed,
# but the CSS/JS belonged to the previous version → unstyled admin.
# filament:upgrade is idempotent and ≈1s, so we just always run it.
# ──────────────────────────────────────────────────────────────
if [ -f artisan ]; then
    echo "▶ Publishing Filament assets (always, idempotent)…"
    if ! php artisan filament:upgrade --ansi; then
        echo "⚠ filament:upgrade FAILED. The admin UI at /admin/login will be unstyled."
        echo "  Manual recovery: docker compose exec app php artisan filament:upgrade"
    fi

    if [ ! -f /var/www/html/public/build/manifest.json ]; then
        echo "⚠ public/build/manifest.json is missing — Inertia pages will have no CSS/JS."
        echo "  Run \`npm run build\` (one-off) or \`npm run dev\` (hot-reload) inside the container."
    fi
fi

echo "✓ Container ready. Handing off to: $*"
exec "$@"
