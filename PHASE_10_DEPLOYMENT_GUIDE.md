# Phase 10 — Deployment Guide

Step-by-step deployment instructions for a typical Linux server (Ubuntu 22.04 / 24.04 reference; adapt for your distro). Two paths are documented: **bare metal (host machine)** and **Docker compose**. Hostnames differ between the two; the brief specifically called this out — don't use `postgres`/`redis`/`mailpit` hostnames outside Docker.

---

## 1. System Requirements

### PHP
- **PHP 8.3+** (8.4 recommended)
- Extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `gd` (or `imagick`), `intl`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `zip`. (`pdo_pgsql` only if you genuinely use PostgreSQL — the developer environment is MySQL.)
- Install on Ubuntu:
  ```bash
  sudo apt update
  sudo apt install -y software-properties-common ca-certificates lsb-release
  sudo add-apt-repository -y ppa:ondrej/php
  sudo apt update
  sudo apt install -y php8.3-cli php8.3-fpm php8.3-mysql php8.3-redis \
      php8.3-mbstring php8.3-xml php8.3-bcmath php8.3-curl php8.3-gd \
      php8.3-intl php8.3-zip php8.3-tokenizer php8.3-fileinfo
  ```

### Composer
- **Composer 2.7+**
  ```bash
  curl -sS https://getcomposer.org/installer | php
  sudo mv composer.phar /usr/local/bin/composer
  composer --version
  ```

### Node / npm
- **Node 20 LTS or 22 LTS**
- **npm 10+**
  ```bash
  curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
  sudo apt install -y nodejs
  node --version    # v20.x
  npm --version     # 10.x
  ```

### MySQL
- **MySQL 8.0+** or **MariaDB 10.6+**
- Create DB + user:
  ```sql
  CREATE DATABASE marketplace CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
  CREATE USER 'marketplace'@'localhost' IDENTIFIED BY '<strong password>';
  GRANT ALL PRIVILEGES ON marketplace.* TO 'marketplace'@'localhost';
  FLUSH PRIVILEGES;
  ```

### Redis
- **Redis 7.0+** for queue + cache + session
- Bare metal: install `redis-server`, bind to `127.0.0.1`, require AUTH
- Docker: provided by the `redis` service

### Web server
- **Nginx 1.24+** (recommended) or Apache 2.4 with `mod_php` or PHP-FPM
- Nginx is documented below; Apache works with `.htaccess` + `mod_rewrite`

---

## 2. Application Deployment (bare metal)

### Step 2.1 — Clone / upload

```bash
sudo mkdir -p /var/www
sudo chown -R www-data:www-data /var/www
sudo -u www-data git clone <repo-url> /var/www/marketplace
cd /var/www/marketplace
```

Or, if uploading the Phase 10 archive:

```bash
sudo mkdir -p /var/www/marketplace
sudo chown -R www-data:www-data /var/www/marketplace
cd /var/www/marketplace
sudo -u www-data tar -xzf /tmp/marketplace-phase-10.tar.gz --strip-components=1
```

### Step 2.2 — Install PHP dependencies

```bash
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction
```

`--no-dev` skips dev-only packages. `--optimize-autoloader` builds a classmap for faster autoload.

### Step 2.3 — Install + build frontend

```bash
sudo -u www-data npm ci
sudo -u www-data npm run build
```

This produces `public/build/` (Vite manifest + bundled JS/CSS).

### Step 2.4 — Environment configuration

```bash
sudo -u www-data cp .env.example .env
sudo -u www-data php artisan key:generate
sudo -u www-data nano .env
```

Required `.env` values for production:

```
APP_NAME=Marketplace
APP_ENV=production
APP_KEY=base64:...        # generated above
APP_DEBUG=false
APP_URL=https://your-domain.example

LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=warning

# Bare metal: use localhost (or DB host on private network)
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=marketplace
DB_USERNAME=marketplace
DB_PASSWORD=<your strong password>

# Bare metal: use localhost
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=<your redis password>
REDIS_PORT=6379

CACHE_DRIVER=redis
QUEUE_CONNECTION=redis
SESSION_DRIVER=redis
SESSION_LIFETIME=120
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax       # 'strict' if no cross-site embedding is intended

BROADCAST_DRIVER=log
FILESYSTEM_DISK=public

MAIL_MAILER=smtp
MAIL_HOST=<your smtp host>
MAIL_PORT=587
MAIL_USERNAME=<smtp user>
MAIL_PASSWORD=<smtp pass>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="no-reply@your-domain.example"
MAIL_FROM_NAME="${APP_NAME}"

# Trusted proxies — if behind a load balancer (Cloudflare, AWS ALB, etc.)
TRUSTED_PROXIES=*
```

**Do NOT** set:
- `APP_DEBUG=true` in production — leaks stack traces
- `MAIL_MAILER=log` in production — silently drops outgoing email
- Use the dev `mailpit` hostname outside Docker

### Step 2.5 — Migrate + seed

```bash
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan db:seed --class=RolesAndPermissionsSeeder --force
sudo -u www-data php artisan db:seed --class=VendorPackagesSeeder --force
sudo -u www-data php artisan db:seed --class=CategoriesSeeder --force
sudo -u www-data php artisan db:seed --class=AttributesSeeder --force
sudo -u www-data php artisan db:seed --class=PaymentMethodsSeeder --force
sudo -u www-data php artisan db:seed --class=SettingsSeeder --force
# Do NOT run DemoSeeder in production. It creates demo accounts.
```

For a fresh install with all foundation seeders:

```bash
sudo -u www-data php artisan migrate:fresh --seed --force --seeder=ProductionSeeder
```

(If `ProductionSeeder` doesn't exist in your repo, run each foundation seeder individually as above.)

### Step 2.6 — Create the first admin

```bash
sudo -u www-data php artisan tinker
```

```php
$admin = \App\Models\User::create([
    'name'              => 'Site Admin',
    'email'             => 'admin@your-domain.example',
    'password'          => bcrypt('<strong password>'),
    'email_verified_at' => now(),
    'role'              => 'admin',
]);
$admin->assignRole('super_admin');
```

### Step 2.7 — Storage permissions + symlink

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
sudo -u www-data php artisan storage:link
```

### Step 2.8 — Production caches

```bash
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache
```

### Step 2.9 — Queue worker (systemd)

Create `/etc/systemd/system/marketplace-queue.service`:

```ini
[Unit]
Description=Marketplace queue worker
After=network.target redis.service mysql.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/marketplace
ExecStart=/usr/bin/php artisan queue:work redis --tries=3 --backoff=10 --max-time=3600

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable --now marketplace-queue
sudo systemctl status marketplace-queue
```

### Step 2.10 — Scheduler (cron)

Add to `www-data`'s crontab:

```bash
sudo -u www-data crontab -e
```

```cron
* * * * * cd /var/www/marketplace && /usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

### Step 2.11 — Nginx

`/etc/nginx/sites-available/marketplace`:

```nginx
server {
    listen 80;
    server_name your-domain.example www.your-domain.example;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.example www.your-domain.example;

    root /var/www/marketplace/public;
    index index.php;
    charset utf-8;

    ssl_certificate     /etc/letsencrypt/live/your-domain.example/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/your-domain.example/privkey.pem;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header Referrer-Policy "strict-origin-when-cross-origin";
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Phase 10 — sitemap + robots are routes, not files. Don't try_files them
    # to a missing static file; let Laravel handle the route.

    client_max_body_size 20M;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/marketplace /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

### Step 2.12 — HTTPS via Let's Encrypt

```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d your-domain.example -d www.your-domain.example
# certbot renew runs via systemd timer by default; verify with:
sudo systemctl status certbot.timer
```

### Step 2.13 — Smoke test

```bash
curl -sI https://your-domain.example/                # 200
curl -sI https://your-domain.example/sitemap.xml    # 200, Content-Type: application/xml
curl -sI https://your-domain.example/robots.txt     # 200, Content-Type: text/plain
curl -s  https://your-domain.example/robots.txt | head
```

---

## 3. Docker Compose path

If you use docker-compose, the hostnames are different:

| Service | Docker hostname | Bare metal hostname |
|---|---|---|
| MySQL | `mysql` (or `db`) | `127.0.0.1` |
| Redis | `redis` | `127.0.0.1` |
| Mailpit (dev) | `mailpit` | NOT VALID in prod — use real SMTP |

In `.env` inside the Laravel container:

```
DB_HOST=mysql           # NOT 127.0.0.1 — the host is the docker service name
REDIS_HOST=redis
MAIL_HOST=mailpit       # ONLY in dev. Production must use real SMTP.
```

**Common mistake**: copying the Docker `.env` to a bare-metal server. The `redis` and `mailpit` hostnames don't resolve outside Docker. Always reset `DB_HOST`, `REDIS_HOST`, `MAIL_HOST` for the target environment.

A minimal `docker-compose.prod.yml` if you operate Docker in production:

```yaml
services:
  app:
    image: marketplace:latest
    build: .
    env_file: .env
    depends_on: [mysql, redis]
    volumes:
      - storage_data:/var/www/marketplace/storage
    ports: ["8000:8000"]

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_DATABASE: ${DB_DATABASE}
      MYSQL_USER: ${DB_USERNAME}
      MYSQL_PASSWORD: ${DB_PASSWORD}
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
    volumes:
      - mysql_data:/var/lib/mysql

  redis:
    image: redis:7-alpine
    command: redis-server --requirepass ${REDIS_PASSWORD}
    volumes:
      - redis_data:/data

volumes:
  storage_data:
  mysql_data:
  redis_data:
```

The application image should run `php artisan migrate --force` + queue worker + scheduler. Typically: an `entrypoint.sh` that runs migrations + execs PHP-FPM, plus a separate worker container running `php artisan queue:work`.

---

## 4. Rollback procedure

Pre-deployment backup:

```bash
mysqldump -u marketplace -p marketplace > backup-pre-$(date +%Y%m%d-%H%M%S).sql
tar -czf storage-pre-$(date +%Y%m%d-%H%M%S).tar.gz /var/www/marketplace/storage/app
```

If a deploy fails, rollback to a previous tag:

```bash
cd /var/www/marketplace
sudo -u www-data git fetch --tags
sudo -u www-data git checkout <previous-tag>
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci && npm run build
sudo -u www-data php artisan migrate:rollback --step=N --force   # ONLY if you must — see backup/recovery guide
sudo systemctl restart marketplace-queue
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo systemctl reload nginx
```

For destructive rollbacks (recently-shipped migration broke production), prefer restoring the DB backup over `migrate:rollback`:

```bash
mysql -u marketplace -p marketplace < backup-pre-<timestamp>.sql
```

This is documented in `PHASE_10_BACKUP_RECOVERY_GUIDE.md`.

---

## 5. Update / re-deploy

```bash
cd /var/www/marketplace
sudo -u www-data php artisan down --refresh=60 --secret=<your secret>
sudo -u www-data git pull origin main
sudo -u www-data composer install --no-dev --optimize-autoloader
sudo -u www-data npm ci && npm run build
sudo -u www-data php artisan migrate --force
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo systemctl restart marketplace-queue
sudo -u www-data php artisan up
```

The `--secret` lets admins access the site during maintenance via `?secret=<your secret>`.

---

## 6. Production-only environment checklist (Phase 10 §16)

| Item | Value | Reason |
|---|---|---|
| `APP_ENV` | `production` | Disables debug helpers |
| `APP_DEBUG` | `false` | Don't leak stack traces |
| `APP_KEY` | Set, ~32 bytes base64 | Encrypts cookies + sessions + signed URLs |
| `APP_URL` | Your HTTPS URL | Used in canonical URLs + sitemap |
| Database | MySQL 8.0+ on localhost or private network | The codebase is MySQL-tested. PostgreSQL also works (Phase 9 v9.4 confirmed portability). |
| Redis | bound `127.0.0.1`, password-protected | Queue + session + cache |
| `CACHE_DRIVER` | `redis` | Faster than file |
| `SESSION_DRIVER` | `redis` | Survives PHP-FPM restarts |
| `QUEUE_CONNECTION` | `redis` | Default queue (for emails, exports, notifications) |
| Mail | Real SMTP — NOT `mailpit` outside Docker | Otherwise email silently fails or goes nowhere |
| Storage | `storage:link` run; `storage/app/public` writable | Product images need to be accessible via `/storage/...` |
| Queue worker | systemd service running | Without this, queued jobs sit forever |
| Scheduler | cron line installed | Hourly cleanup, expired-token sweeps, etc. |
| HTTPS | Let's Encrypt or other | `AppServiceProvider::forceScheme('https')` already enforces in production |
| Trusted proxies | `TRUSTED_PROXIES=*` if behind LB | So Laravel sees the real client IP and HTTPS scheme |
| Secure cookies | `SESSION_SECURE_COOKIE=true` | Only ship over HTTPS |
| Backups | nightly DB dump + storage tarball | See `PHASE_10_BACKUP_RECOVERY_GUIDE.md` |
| Logs | `LOG_LEVEL=warning` minimum; daily rotation | Avoid disk fill |
| Error monitoring | Optional: Sentry / Bugsnag / Flare | Adds observability beyond `storage/logs/*.log` |
| Sitemap reachable | `GET /sitemap.xml` returns 200 + XML | Phase 10 controller |
| Robots reachable | `GET /robots.txt` returns 200 + text | Phase 10 controller |

---

## Common deploy mistakes to avoid

1. **Forgetting `php artisan storage:link`** — product images won't load.
2. **Copying Docker `.env` to bare metal** — `redis`/`mysql`/`mailpit` hostnames don't resolve.
3. **`APP_DEBUG=true` in production** — leaks stack traces with potentially sensitive paths and DB schema.
4. **No queue worker** — jobs stack up in Redis forever.
5. **No scheduler cron** — cleanup tasks never run.
6. **Storage owned by root** — `chown -R www-data:www-data storage bootstrap/cache` is required.
7. **`MAIL_MAILER=log` in production** — outgoing email silently drops to `storage/logs/laravel.log`.
8. **Running `DemoSeeder` in production** — creates the demo accounts with publicly-known passwords.

If any of these are unclear, check `PHASE_10_BACKUP_RECOVERY_GUIDE.md` and `PHASE_10_DEVELOPER_TESTING_CHECKLIST.md`.
