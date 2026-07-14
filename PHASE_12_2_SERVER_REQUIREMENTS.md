# Phase 12.2 — Server Requirements

Requirements derived from `composer.json`, `package.json`, and the shipped codebase. Every version is either quoted from the real dependency file or noted as a recommendation.

## Runtime versions

| Component | Required (from source) | Recommended | Verified via |
| --- | --- | --- | --- |
| PHP | `^8.3` | 8.3.6+ | `composer.json` require.php |
| Composer | 2.7+ | Current stable | Standard Laravel 11 requirement |
| Node.js | ≥ 18.x LTS | 20.x LTS | Vite 5.x requires 18+ |
| npm | ≥ 9.x | Bundled with Node | Standard |
| MySQL | 8.0+ | 8.0.36+ | Migration syntax uses `->jsonb()`, `Schema::getIndexes()` |
| MariaDB | 10.6+ (if used instead of MySQL) | 10.11+ | Compatible with Laravel 11 |
| Redis | 6.x+ | 7.x | `predis` / `phpredis` compatible |
| Nginx | 1.20+ | 1.24+ | Standard PHP-FPM |
| Apache | 2.4+ | 2.4.58+ | With mod_rewrite + PHP-FPM |
| SSL certificate | valid, non-self-signed | Let's Encrypt with cert-manager | Required for `SESSION_SECURE_COOKIE=true` |

## Required PHP extensions

Confirmed from source code + `composer.json` + Laravel 11 baseline:

- `pdo` and `pdo_mysql` — database driver
- `mbstring` — Laravel core, UTF-8 handling for Arabic content
- `openssl` — encryption, `SupplierIntegration.credentials` uses `encrypted:array`
- `tokenizer` — Laravel core
- `xml` — Laravel core (some vendor packages)
- `ctype` — Laravel core
- `curl` — HTTP clients (Guzzle, Meilisearch client)
- `fileinfo` — file upload validation
- `bcmath` — money math (KWD is 3-decimal minor)
- `intl` — locale-aware formatting (en/ar)
- `zip` — Composer package extraction
- `redis` (phpredis) — recommended if using Redis for cache/queue/session
- `gd` or `imagick` — image processing for product images

Verify on the server with:

```bash
php -m | grep -Ei 'pdo|mbstring|openssl|tokenizer|xml|ctype|curl|fileinfo|bcmath|intl|zip|redis|gd'
```

## PHP configuration (php.ini)

| Directive | Recommended | Why |
| --- | --- | --- |
| `memory_limit` | 512M | Composer install, artisan optimize, large Filament pages |
| `upload_max_filesize` | 20M (min) | Product images, vendor documents |
| `post_max_size` | 25M (must exceed upload_max_filesize) | HTTP body limit |
| `max_execution_time` | 60 (web) / 0 (CLI) | Long-running artisan commands |
| `max_input_vars` | 3000 | Complex admin forms with many fields |
| `opcache.enable` | 1 | Production performance |
| `opcache.validate_timestamps` | 0 (with cache-invalidating deploys) or 1 (with fs deploys) | Match your deployment strategy |
| `opcache.memory_consumption` | 256 | Enough for Laravel + Filament |
| `session.cookie_httponly` | 1 | Complements Laravel's `SESSION_HTTP_ONLY=true` |
| `session.cookie_secure` | 1 | Complements Laravel's `SESSION_SECURE_COOKIE=true` |
| `expose_php` | Off | Don't advertise PHP version in headers |

## Server resource sizing

Recommendations only — actual sizing depends on traffic profile, product catalog size, and vendor count. Adjust after 30 days of production data.

| Marketplace size | CPU | RAM | Disk | Notes |
| --- | --- | --- | --- | --- |
| Small (< 100 vendors, < 1k products, < 100 orders/day) | 2 vCPU | 4 GB | 40 GB SSD | Single VPS for app + DB + Redis |
| Medium (< 500 vendors, < 20k products, < 1k orders/day) | 4 vCPU | 8 GB | 100 GB SSD | Split: app node (2vCPU/4GB) + DB node (2vCPU/4GB) |
| Growing (> 500 vendors, > 20k products, > 1k orders/day) | 8+ vCPU | 16+ GB | 200+ GB SSD | Managed MySQL (RDS/Aurora), managed Redis, load-balanced app nodes, S3 for storage |

Disk-space math for the "growing" tier:
- Database growth: ~10-50 MB / 1000 orders (depends on order_items count)
- Application code: ~500 MB
- Composer + node_modules: ~800 MB during install (only during deploy)
- Logs: 30d × 10-100 MB = 300 MB - 3 GB (config-dependent)
- Uploaded assets: unbounded — plan for object storage (S3/Wasabi/R2) not local disk

## Required system services

- **cron** — for `php artisan schedule:run` (see PHASE_12_2_SCHEDULER_GUIDE.md)
- **Supervisor or systemd** — for `php artisan queue:work` process supervision (see PHASE_12_2_QUEUE_WORKER_GUIDE.md)
- **logrotate** — for `storage/logs/laravel.log` retention (Laravel's `daily` channel handles most of this internally, but system-level rotation is a safety net)
- **fail2ban or equivalent** — recommended for SSH/nginx access protection (not a Laravel requirement, but a marketplace stores payment methods and customer data)

## Backup storage

- Same-host: `/var/backups/marketplace/` with `chmod 700` and dedicated user
- Off-host: cloud object storage (S3, Wasabi, B2, R2) with versioning + object lock
- Encryption: GPG symmetric AES256 (see PHASE_12_DATABASE_BACKUP_PLAN.md §7)

Minimum retention: 30 days on-server hot storage, 90 days off-site cold storage. Regulatory requirements in Kuwait may require longer for financial data — check with your legal counsel.

## Network / firewall rules

Ingress:
- 80 (HTTP → 301 redirect to 443)
- 443 (HTTPS, public)
- 22 (SSH, restricted to operator IPs)
- No other ports open to `0.0.0.0/0`

Between-service (private subnet only):
- 3306 (app → DB)
- 6379 (app → Redis)
- 7700 (app → Meilisearch, if using scout meilisearch driver)

## SMTP / email

- **Do not use** the DB queue driver for high-volume email
- **Do use** a transactional provider (SES, Postmark, Mailgun, SendGrid) — not port 25 relay
- Configure SPF, DKIM, and DMARC DNS records — see `PHASE_12_2_EMAIL_READINESS_REPORT.md`

## Evidence status

| Claim | Verified statically | Evidence |
| --- | :---: | --- |
| PHP `^8.3` requirement | ✅ | `grep '"php"' composer.json` returns `"php": "^8.3"` |
| Laravel `^11.0` | ✅ | `grep '"laravel/framework"' composer.json` |
| Filament `^3.2` | ✅ | `grep '"filament/filament"' composer.json` |
| Vite `^5.3.3` | ✅ | `grep '"vite"' package.json` |
| React `^18.3.1` | ✅ | `grep '"react"' package.json` |
| TypeScript `^5.5.3` | ✅ | `grep '"typescript"' package.json` |
| Spatie permission `^6.0` | ✅ | `grep '"spatie/laravel-permission"' composer.json` |
| Sanctum `^4.0` | ✅ | `grep '"laravel/sanctum"' composer.json` |
| ext-pdo required | ✅ | `composer.json` require.ext-pdo |
| Actual server provisioning | ⏳ | Developer must apply this on the target server |
| PHP extensions installed on server | ⏳ | `php -m` on server (see command above) |
| Firewall rules applied | ⏳ | Developer / cloud provider console |
