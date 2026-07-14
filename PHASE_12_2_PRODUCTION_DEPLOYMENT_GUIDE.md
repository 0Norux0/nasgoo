# Phase 12.2 — Production Deployment Guide

Step-by-step deployment procedure for the marketplace. Cross-references the automated `scripts/deploy-production-phase12.sh` where applicable — this document is the human procedure.

## Prerequisites (before ever touching the production server)

- [ ] Domain registered
- [ ] DNS control access (to point to server IP)
- [ ] SSL certificate obtainable (Let's Encrypt via certbot or purchased)
- [ ] Production server provisioned per `PHASE_12_2_SERVER_REQUIREMENTS.md`
- [ ] SSH access as a non-root user with sudo
- [ ] Database credentials created per `PHASE_12_DATABASE_READINESS_REPORT.md` §2
- [ ] SMTP provider account created (SES/Postmark/Mailgun)
- [ ] Object storage bucket created (if using S3) with backup destination configured
- [ ] Off-site backup destination configured
- [ ] Two engineers available for the deploy

## Step 1 — Prepare the server

```bash
# Install PHP 8.3 and required extensions (Debian/Ubuntu)
sudo apt update
sudo apt install -y php8.3 php8.3-fpm php8.3-cli \
    php8.3-mysql php8.3-mbstring php8.3-xml php8.3-curl \
    php8.3-zip php8.3-intl php8.3-bcmath php8.3-gd \
    php8.3-redis php8.3-opcache

# Composer
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer

# Node.js (via NodeSource)
curl -fsSL https://deb.nodesource.com/setup_20.x | sudo -E bash -
sudo apt install -y nodejs

# MySQL 8+ (or your preferred version)
sudo apt install -y mysql-server

# Redis
sudo apt install -y redis-server

# Nginx (or Apache)
sudo apt install -y nginx

# Supervisor for queue worker
sudo apt install -y supervisor

# Verify versions
php -v          # >= 8.3
composer -V     # >= 2.7
node -v         # >= 18
mysql --version # >= 8.0
redis-cli --version  # >= 6.x
```

## Step 2 — Upload code

Option A: Git clone (recommended for updates)

```bash
sudo mkdir -p /var/www/marketplace
sudo chown -R www-data:www-data /var/www/marketplace
cd /var/www/marketplace
sudo -u www-data git clone git@github.com:your-org/marketplace.git .
```

Option B: Extract tarball (from this delivery)

```bash
sudo mkdir -p /var/www/marketplace
sudo tar -xzf marketplace-phase-12-2-production-launch-readiness.tar.gz \
    -C /var/www/ --strip-components=1
sudo chown -R www-data:www-data /var/www/marketplace
```

## Step 3 — Configure `.env`

```bash
cd /var/www/marketplace
sudo -u www-data cp .env.example.production .env
sudo -u www-data nano .env
# Replace every CHANGE_ME_ placeholder
# Set APP_URL to your real HTTPS domain
# Configure DB_* to point at the production database

sudo -u www-data php artisan key:generate
# Sets APP_KEY in .env

sudo chmod 640 .env
```

## Step 4 — Install Composer dependencies

```bash
cd /var/www/marketplace
sudo -u www-data composer install --no-dev --optimize-autoloader --no-interaction
```

## Step 5 — Install + build frontend

```bash
sudo -u www-data npm ci
sudo -u www-data npm run build
# Verify: ls public/build/manifest.json
```

## Step 6 — Configure database

Create the production database + user per `PHASE_12_DATABASE_READINESS_REPORT.md` §2. Verify from the app:

```bash
sudo -u www-data php artisan db:show
# Expected: connection details (safe subset)
```

## Step 7 — Take a pre-migration backup

If this is a FIRST deploy (empty DB), skip. If updating an EXISTING DB:

```bash
mkdir -p /var/backups/marketplace
mysqldump \
    --host=localhost --user=DB_USERNAME --password \
    --single-transaction --routines --triggers --events \
    --default-character-set=utf8mb4 --hex-blob \
    DB_DATABASE \
    | gzip -c > /var/backups/marketplace/db_pre_deploy_$(date +%F_%H-%M).sql.gz

# Verify size
ls -la /var/backups/marketplace/db_pre_deploy_*.sql.gz
```

## Step 8 — Migration

```bash
sudo -u www-data php artisan migrate:status
# For first deploy: 77 rows all Pending
# For update: existing rows Ran, new rows Pending

# Preview
sudo -u www-data php artisan migrate --pretend
# Review SQL

# Execute
sudo -u www-data php artisan migrate --force
```

**DO NOT** run `php artisan migrate:fresh` on production. Ever.

## Step 9 — Seed system data (first deploy only)

```bash
for S in RolesAndPermissionsSeeder CurrenciesSeeder SettingsSeeder \
         NotificationTemplatesSeeder VendorPackagesSeeder \
         CategoriesSeeder AttributesSeeder PaymentMethodsSeeder \
         EnsureAdminReportsAccessSeeder; do
    sudo -u www-data php artisan db:seed --class=$S --force
done
```

Do NOT run `php artisan db:seed` without `--class=` on production (would run `DatabaseSeeder` which creates the demo admin@marketplace.test / password).

## Step 10 — Storage link

```bash
sudo -u www-data php artisan storage:link
# Creates public/storage → ../storage/app/public
```

## Step 11 — Create the first super-admin

```bash
sudo -u www-data php artisan marketplace:create-super-admin --confirm
# Interactive: email, name, password (with 12-char + mixed classes)
```

## Step 12 — Cache config / routes / views / events

```bash
sudo -u www-data php artisan optimize:clear
sudo -u www-data php artisan config:cache
sudo -u www-data php artisan route:cache
sudo -u www-data php artisan view:cache
sudo -u www-data php artisan event:cache
```

## Step 13 — Start queue worker

Install Supervisor config per `PHASE_12_2_QUEUE_WORKER_GUIDE.md`:

```bash
sudo tee /etc/supervisor/conf.d/marketplace-queue.conf << 'EOF'
[program:marketplace-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/marketplace/artisan queue:work --tries=3 --backoff=30 --timeout=120 --max-jobs=1000 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/marketplace-queue.log
stopwaitsecs=130
EOF

sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start marketplace-queue:*
sudo supervisorctl status marketplace-queue:*
```

## Step 14 — Add scheduler cron

```bash
sudo -u www-data crontab -e
# Add:
* * * * * cd /var/www/marketplace && php artisan schedule:run >> /dev/null 2>&1
```

Verify:

```bash
sudo -u www-data crontab -l | grep schedule:run
sudo -u www-data php artisan schedule:list
```

## Step 15 — Configure nginx

Sample `/etc/nginx/sites-available/marketplace`:

```nginx
server {
    listen 80;
    server_name YOUR_DOMAIN;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name YOUR_DOMAIN;

    root /var/www/marketplace/public;
    index index.php;

    ssl_certificate     /etc/letsencrypt/live/YOUR_DOMAIN/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/YOUR_DOMAIN/privkey.pem;

    # Security headers
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    # Block sensitive paths
    location ~ /\.(env|git) { deny all; return 404; }
    location ~ /storage/logs { deny all; return 404; }
    location ~ ^/(vendor|node_modules|tests|database)/ { deny all; return 404; }
    location ~ ^/backups/ { deny all; return 404; }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }
}
```

Enable + reload:

```bash
sudo ln -s /etc/nginx/sites-available/marketplace /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

## Step 16 — Test email

Ensure SMTP is reachable + credentials work:

```bash
sudo -u www-data php artisan tinker
>>> use Illuminate\Support\Facades\Mail;
>>> Mail::raw('Production launch test', function($msg) {
...     $msg->to('you@your-domain.com')->subject('Marketplace launch test');
... });
```

Check the receiving inbox within 30 seconds. If not received, check `queue:failed` and `storage/logs/laravel.log`.

## Step 17 — Test checkout

Log in with a test customer account. Add product to cart. Complete checkout with COD. Verify:

- Order created in DB
- Stock decremented
- Confirmation email received
- Vendor sees the order
- Admin sees the order

## Step 18 — Test admin / vendor / customer flows

Work through `PHASE_12_2_FINAL_QA_CHECKLIST.md`. Do NOT skip.

## Step 19 — Maintenance mode during deployment (optional)

For updates to a running production site, wrap the deploy in maintenance mode:

```bash
sudo -u www-data php artisan down --refresh=15 --secret="deploy-$(date +%s)"
# Now: run steps 7-12
sudo -u www-data php artisan up
```

The `--secret` provides a bypass URL for the operator to test during maintenance: `https://YOUR_DOMAIN/deploy-1234567890`.

## Step 20 — Automated deployment

The manual steps 4-12 above are automated in `scripts/deploy-production-phase12.sh`. Prefer the script for regular deploys:

```bash
cd /var/www/marketplace
sudo -u www-data ./scripts/deploy-production-phase12.sh
```

Script safety features covered in `PHASE_12_MIGRATION_SAFETY.md`.

## Do NOT do

- `php artisan migrate:fresh` on production
- `php artisan db:wipe` on production
- `db:seed` without `--class=` on production (creates demo credentials)
- `rm -rf storage/logs/` (destroys error history)
- Deploy without a fresh backup
- Deploy without the queue worker restart
- Leave `APP_DEBUG=true` on production

## Post-launch verification

After the app is up:

- [ ] `curl -I https://YOUR_DOMAIN/` → 200
- [ ] `curl -I https://YOUR_DOMAIN/.env` → 404
- [ ] `curl -I https://YOUR_DOMAIN/up` → 200
- [ ] `sudo supervisorctl status marketplace-queue:*` → all RUNNING
- [ ] `sudo -u www-data crontab -l` → schedule:run present
- [ ] `sudo -u www-data php artisan schedule:list | grep vendor-intelligence` → 2 entries
- [ ] `mysql ... < scripts/db-integrity-check.sql` → all 20 counts 0
- [ ] Full QA checklist per `PHASE_12_2_FINAL_QA_CHECKLIST.md` passed
