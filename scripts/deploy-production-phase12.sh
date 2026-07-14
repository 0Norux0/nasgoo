#!/usr/bin/env bash
#
# ═══════════════════════════════════════════════════════════════════════════
#  Phase 12 — Production Deployment Script
#  Marketplace (Laravel 11 + Inertia + React) — Kuwait
# ═══════════════════════════════════════════════════════════════════════════
#
#  Replaces scripts/deploy.sh (labelled Phase 10, no backup, no confirmation).
#  This script is SAFE FOR PRODUCTION USE. Every destructive step is
#  gated on a check or explicit typed confirmation.
#
#  Usage:
#     cd /var/www/marketplace
#     ./scripts/deploy-production-phase12.sh
#
#  Requirements on the host:
#     - php >= 8.3 in PATH
#     - composer in PATH
#     - node + npm in PATH (only required if BUILD_FRONTEND=1)
#     - mysqldump + mysql in PATH
#     - .env file present with production values
#     - writable backups/ directory (auto-created below)
#
#  Exit codes:
#     0   success
#     1   sanity failure (wrong directory, missing tool, env mismatch)
#     2   safety guard triggered (local env, debug=true, or user cancelled)
#     3   backup failed (dump empty or errored)
#     4   migration failed — app left in maintenance mode; see recovery
#     5   post-migration step failed — app left in maintenance mode
#
# ═══════════════════════════════════════════════════════════════════════════

set -eEuo pipefail
IFS=$'\n\t'

# ─── Configuration ──────────────────────────────────────────────────────────
DEPLOY_ROOT="$(pwd)"
LOG_DIR="${DEPLOY_ROOT}/storage/logs"
BACKUP_DIR="${DEPLOY_ROOT}/backups"
DEPLOY_STAMP="$(date -u +%Y-%m-%dT%H-%M-%SZ)"
LOG_FILE="${LOG_DIR}/deploy_${DEPLOY_STAMP}.log"
BUILD_FRONTEND="${BUILD_FRONTEND:-1}"      # export BUILD_FRONTEND=0 to skip npm build
RESTART_QUEUE="${RESTART_QUEUE:-1}"        # export RESTART_QUEUE=0 to skip queue:restart
MIN_FREE_MB=1024                            # abort if less than 1 GB free
DEPLOY_START=$(date +%s)

mkdir -p "$LOG_DIR" "$BACKUP_DIR"

# ─── Logging helpers ────────────────────────────────────────────────────────
log()   { printf '%s  %s\n' "[$(date -u +%FT%TZ)]" "$*" | tee -a "$LOG_FILE"; }
info()  { log "INFO  $*"; }
warn()  { log "WARN  $*"; }
err()   { log "ERROR $*" >&2; }

# ─── Failure trap: log line + exit code, leave app in maintenance if we're past that step ───
FAILED_STEP=""
on_error() {
    local exit_code=$?
    local line=$1
    err "Deployment failed at line $line (exit $exit_code) during step: ${FAILED_STEP:-unknown}"
    err "Log: $LOG_FILE"
    err ""
    err "Recovery:"
    err "  1. Check the log above for the specific error"
    err "  2. Restore DB from ${BACKUP_FILE:-<backup file>} if migrations ran partially"
    err "     Run:  gunzip -c ${BACKUP_FILE:-<backup>} | mysql -u \$DB_USERNAME -p \$DB_DATABASE"
    err "  3. Fix the underlying issue"
    err "  4. Re-run this script OR bring the app back manually with 'php artisan up'"
    err ""
    err "The app is currently in MAINTENANCE MODE (if we reached that step)."
    err "Do NOT force 'php artisan up' until you understand what failed."
    exit $exit_code
}
trap 'on_error $LINENO' ERR

# ═══════════════════════════════════════════════════════════════════════════
# STEP 0 — Banner + sanity
# ═══════════════════════════════════════════════════════════════════════════
FAILED_STEP="banner + sanity"
info "═══════════════════════════════════════════════════════════════════"
info "  Phase 12 Production Deployment"
info "  Time (UTC): $(date -u '+%F %T')"
info "  Working dir: $DEPLOY_ROOT"
info "  Log:         $LOG_FILE"
info "═══════════════════════════════════════════════════════════════════"

# Directory sanity
if [ ! -f VERSION ] || [ ! -f artisan ]; then
    err "VERSION or artisan file not found. Run from the project root."
    exit 1
fi
info "Current VERSION: $(cat VERSION)"

# ═══════════════════════════════════════════════════════════════════════════
# STEP 1 — Environment safety gates
# ═══════════════════════════════════════════════════════════════════════════
FAILED_STEP="environment safety gates"
info ""
info "[1/12] Environment safety gates"

if [ ! -f .env ]; then
    err ".env not found. Copy .env.example.production to .env and configure it."
    exit 1
fi

# Read APP_ENV + APP_DEBUG without sourcing .env (avoid injection)
APP_ENV_VAL=$(grep -E '^APP_ENV=' .env | head -1 | cut -d= -f2- | tr -d '"'"'"' | tr -d '[:space:]' || true)
APP_DEBUG_VAL=$(grep -E '^APP_DEBUG=' .env | head -1 | cut -d= -f2- | tr -d '"'"'"' | tr -d '[:space:]' || true)
APP_URL_VAL=$(grep -E '^APP_URL=' .env | head -1 | cut -d= -f2- | tr -d '"'"'"' | tr -d '[:space:]' || true)

info "  APP_ENV:   ${APP_ENV_VAL}"
info "  APP_DEBUG: ${APP_DEBUG_VAL}"
info "  APP_URL:   ${APP_URL_VAL}"

# HARD refuse: APP_ENV=local
if [ "$APP_ENV_VAL" = "local" ]; then
    err "APP_ENV=local — refusing to run production deploy against a local environment."
    err "If this IS your production server, set APP_ENV=production in .env first."
    exit 2
fi

# HARD warn (not refuse): APP_DEBUG=true
if [ "$APP_DEBUG_VAL" = "true" ]; then
    warn "APP_DEBUG=true — this leaks stack traces to end users."
    warn "Strongly recommended to set APP_DEBUG=false before production traffic."
    printf "Type 'CONTINUE-WITH-DEBUG' to proceed anyway: "
    read -r _debug_confirm
    if [ "$_debug_confirm" != "CONTINUE-WITH-DEBUG" ]; then
        err "Cancelled by user."
        exit 2
    fi
fi

# ═══════════════════════════════════════════════════════════════════════════
# STEP 2 — Tool availability
# ═══════════════════════════════════════════════════════════════════════════
FAILED_STEP="tool availability"
info ""
info "[2/12] Verifying required tools"

require_tool() {
    if ! command -v "$1" >/dev/null 2>&1; then
        err "Required tool not found: $1"
        exit 1
    fi
    info "  ✓ $1: $($1 --version 2>&1 | head -1 || echo present)"
}
require_tool php
require_tool composer
require_tool mysqldump
require_tool mysql
if [ "$BUILD_FRONTEND" = "1" ]; then
    require_tool node
    require_tool npm
fi

# PHP version >= 8.3
PHP_MAJOR=$(php -r 'echo PHP_MAJOR_VERSION;')
PHP_MINOR=$(php -r 'echo PHP_MINOR_VERSION;')
if [ "$PHP_MAJOR" -lt 8 ] || { [ "$PHP_MAJOR" -eq 8 ] && [ "$PHP_MINOR" -lt 3 ]; }; then
    err "PHP >= 8.3 required (found ${PHP_MAJOR}.${PHP_MINOR})"
    exit 1
fi

# ═══════════════════════════════════════════════════════════════════════════
# STEP 3 — DB connection + disk space
# ═══════════════════════════════════════════════════════════════════════════
FAILED_STEP="db connection + disk space"
info ""
info "[3/12] Database connection + disk space"

# Verify DB reachable (php artisan handles credential parsing)
if ! php artisan db:show >/dev/null 2>&1; then
    err "Cannot connect to database. Check DB_* values in .env."
    err "Try:  php artisan db:show"
    exit 1
fi
info "  ✓ database reachable"

# Free space check (backups/ directory volume)
FREE_MB=$(df -Pm "$BACKUP_DIR" | awk 'NR==2 {print $4}')
info "  free disk on backup volume: ${FREE_MB} MB"
if [ "$FREE_MB" -lt "$MIN_FREE_MB" ]; then
    err "Only ${FREE_MB} MB free on backup volume — need at least ${MIN_FREE_MB} MB."
    err "Free some space, then re-run."
    exit 1
fi

# ═══════════════════════════════════════════════════════════════════════════
# STEP 4 — Source-code sanity + composer + npm install
# ═══════════════════════════════════════════════════════════════════════════
FAILED_STEP="composer + npm install"
info ""
info "[4/12] Installing dependencies (composer install --no-dev)"
composer install --no-dev --optimize-autoloader --no-interaction 2>&1 | tee -a "$LOG_FILE"

if [ "$BUILD_FRONTEND" = "1" ]; then
    info ""
    info "  [4b] Installing frontend deps + building assets"
    npm ci 2>&1 | tee -a "$LOG_FILE"
    npm run build 2>&1 | tee -a "$LOG_FILE"
else
    info "  BUILD_FRONTEND=0 — skipping npm ci + npm run build"
fi

# ═══════════════════════════════════════════════════════════════════════════
# STEP 5 — Database backup (REQUIRED before any schema change)
# ═══════════════════════════════════════════════════════════════════════════
FAILED_STEP="database backup"
info ""
info "[5/12] Database backup (REQUIRED before migrations)"

# Extract DB_* values safely from .env
DB_DATABASE=$(grep -E '^DB_DATABASE=' .env | head -1 | cut -d= -f2- | tr -d '"'"'"' | tr -d '[:space:]' || true)
DB_USERNAME=$(grep -E '^DB_USERNAME=' .env | head -1 | cut -d= -f2- | tr -d '"'"'"' | tr -d '[:space:]' || true)
DB_PASSWORD=$(grep -E '^DB_PASSWORD=' .env | head -1 | cut -d= -f2- || true)
DB_HOST=$(grep -E '^DB_HOST=' .env | head -1 | cut -d= -f2- | tr -d '"'"'"' | tr -d '[:space:]' || true)
DB_PORT=$(grep -E '^DB_PORT=' .env | head -1 | cut -d= -f2- | tr -d '"'"'"' | tr -d '[:space:]' || true)
: "${DB_HOST:=127.0.0.1}"
: "${DB_PORT:=3306}"

if [ -z "$DB_DATABASE" ] || [ -z "$DB_USERNAME" ]; then
    err "Could not read DB_DATABASE/DB_USERNAME from .env for backup."
    err "Take a manual backup, then re-run with SKIP_BACKUP=1 (NOT recommended)."
    exit 3
fi

BACKUP_FILE="${BACKUP_DIR}/db_before_deploy_${DEPLOY_STAMP}.sql.gz"
info "  Dumping ${DB_DATABASE} to ${BACKUP_FILE}"

# --single-transaction avoids locking prod tables during the dump
MYSQL_PWD="$DB_PASSWORD" mysqldump \
    --host="$DB_HOST" --port="$DB_PORT" \
    --user="$DB_USERNAME" \
    --single-transaction --routines --triggers --events \
    --default-character-set=utf8mb4 --hex-blob \
    --no-tablespaces \
    "$DB_DATABASE" \
    | gzip -c > "$BACKUP_FILE"

if [ ! -s "$BACKUP_FILE" ]; then
    err "Backup file $BACKUP_FILE is empty or missing — refusing to proceed."
    exit 3
fi
BACKUP_SIZE=$(stat -c%s "$BACKUP_FILE" 2>/dev/null || wc -c < "$BACKUP_FILE")
info "  ✓ backup complete (${BACKUP_SIZE} bytes)"
info "  Restore command if needed:"
info "    gunzip -c ${BACKUP_FILE} | mysql -h $DB_HOST -P $DB_PORT -u $DB_USERNAME -p $DB_DATABASE"

# ═══════════════════════════════════════════════════════════════════════════
# STEP 6 — Typed confirmation before touching the database
# ═══════════════════════════════════════════════════════════════════════════
FAILED_STEP="typed confirmation"
info ""
info "[6/12] Typed confirmation required"
info ""
info "  About to run:"
info "    - php artisan down --refresh=15    (maintenance mode)"
info "    - php artisan migrate --force      (schema changes may apply)"
info "    - php artisan optimize:clear + config:cache + route:cache"
info "    - php artisan queue:restart"
info ""
info "  Backup taken:    $BACKUP_FILE"
info "  Target DB:       $DB_DATABASE @ $DB_HOST"
info ""
printf "  Type 'DEPLOY' to continue, anything else to abort: "
read -r _confirm
if [ "$_confirm" != "DEPLOY" ]; then
    info "Cancelled by user. Backup kept at $BACKUP_FILE."
    exit 2
fi

# ═══════════════════════════════════════════════════════════════════════════
# STEP 7 — Maintenance mode
# ═══════════════════════════════════════════════════════════════════════════
FAILED_STEP="maintenance mode"
info ""
info "[7/12] Enabling maintenance mode"

MAINT_SECRET="deploy-${DEPLOY_STAMP}"
php artisan down --refresh=15 --secret="$MAINT_SECRET" 2>&1 | tee -a "$LOG_FILE"
info "  Maintenance bypass URL: ${APP_URL_VAL}/${MAINT_SECRET}"

# ═══════════════════════════════════════════════════════════════════════════
# STEP 8 — Migrations (--force, NEVER --fresh)
# ═══════════════════════════════════════════════════════════════════════════
FAILED_STEP="migrations"
info ""
info "[8/12] Running migrations (php artisan migrate --force)"
info "  This script uses --force so no interactive prompt is required."
info "  This script will NEVER call migrate:fresh or db:wipe on production."

php artisan migrate --force 2>&1 | tee -a "$LOG_FILE"

# ═══════════════════════════════════════════════════════════════════════════
# STEP 9 — Cache / config / route caches
# ═══════════════════════════════════════════════════════════════════════════
FAILED_STEP="cache rebuild"
info ""
info "[9/12] Rebuilding caches"
php artisan optimize:clear 2>&1 | tee -a "$LOG_FILE"
php artisan config:cache   2>&1 | tee -a "$LOG_FILE"
php artisan route:cache    2>&1 | tee -a "$LOG_FILE"
php artisan view:cache     2>&1 | tee -a "$LOG_FILE"
php artisan event:cache    2>&1 | tee -a "$LOG_FILE"

# ═══════════════════════════════════════════════════════════════════════════
# STEP 10 — Queue workers restart (graceful)
# ═══════════════════════════════════════════════════════════════════════════
FAILED_STEP="queue restart"
info ""
info "[10/12] Restarting queue workers"
if [ "$RESTART_QUEUE" = "1" ]; then
    php artisan queue:restart 2>&1 | tee -a "$LOG_FILE"
    info "  queue:restart signal sent — workers will finish current job then exit"
    info "  supervisor/systemd should relaunch them automatically"
else
    info "  RESTART_QUEUE=0 — skipping queue:restart"
fi

# ═══════════════════════════════════════════════════════════════════════════
# STEP 11 — Storage link (idempotent)
# ═══════════════════════════════════════════════════════════════════════════
FAILED_STEP="storage link"
info ""
info "[11/12] Ensuring storage symlink"
php artisan storage:link 2>&1 | tee -a "$LOG_FILE" || info "  (already linked — non-fatal)"

# ═══════════════════════════════════════════════════════════════════════════
# STEP 12 — Bring the site back up
# ═══════════════════════════════════════════════════════════════════════════
FAILED_STEP="bring up"
info ""
info "[12/12] Bringing the site out of maintenance mode"
php artisan up 2>&1 | tee -a "$LOG_FILE"

# ═══════════════════════════════════════════════════════════════════════════
# Summary
# ═══════════════════════════════════════════════════════════════════════════
DEPLOY_END=$(date +%s)
DEPLOY_SECS=$((DEPLOY_END - DEPLOY_START))

info ""
info "═══════════════════════════════════════════════════════════════════"
info "  ✅ Deployment complete in ${DEPLOY_SECS}s"
info "  VERSION:    $(cat VERSION)"
info "  Backup:     $BACKUP_FILE"
info "  Log:        $LOG_FILE"
info "═══════════════════════════════════════════════════════════════════"
info ""
info "  Next steps (do not skip):"
info "    1. Smoke-test: curl -I ${APP_URL_VAL}/  (expect 200)"
info "    2. Watch: tail -f storage/logs/laravel.log"
info "    3. Verify queue: php artisan queue:work --once"
info "    4. Copy backup off-site: rclone copy $BACKUP_FILE remote:backups/"
info ""

exit 0
