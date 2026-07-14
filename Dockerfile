# syntax=docker/dockerfile:1.7

# ─────────────────────────────────────────────────────────────────
# Stage 1: Node builder (compile frontend assets)
# ─────────────────────────────────────────────────────────────────
FROM node:20-alpine AS node_builder

WORKDIR /app

# Copy package files. The glob `package-lock.json*` matches the lock
# if it exists and contributes nothing if it doesn't — so the build
# works both pre- and post-first-install.
COPY package.json ./
COPY package-lock.json* ./

# Use `npm ci` for reproducible builds when the lock is committed,
# fall back to `npm install` on first ever build (which will write
# a lock locally inside this layer — committed lock should follow).
RUN if [ -f package-lock.json ]; then \
        echo "→ Using committed package-lock.json (npm ci)"; \
        npm ci --no-audit --no-fund; \
    else \
        echo "→ No lock file present — running npm install (commit the lock!)"; \
        npm install --no-audit --no-fund; \
    fi

# Copy frontend source
COPY vite.config.ts tsconfig.json tailwind.config.js postcss.config.js ./
COPY resources/ resources/

# Production asset build. Fails the image if it errors — no `|| true`.
RUN npm run build

# ─────────────────────────────────────────────────────────────────
# Stage 2: Composer dependencies
# ─────────────────────────────────────────────────────────────────
FROM composer:2 AS composer_deps

WORKDIR /app

COPY composer.json ./
COPY composer.lock* ./

# `composer install` handles both cases natively: it uses composer.lock
# if present, otherwise resolves from composer.json and writes a lock.
RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --no-scripts \
        --prefer-dist \
        --optimize-autoloader

# ─────────────────────────────────────────────────────────────────
# Stage 3: Application image (PHP-FPM 8.3 with nginx + supervisor)
# ─────────────────────────────────────────────────────────────────
FROM php:8.3-fpm-alpine AS app

# System dependencies
RUN apk add --no-cache \
        nginx \
        supervisor \
        bash \
        curl \
        git \
        unzip \
        icu-dev \
        libzip-dev \
        postgresql-dev \
        oniguruma-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        libwebp-dev \
        autoconf \
        g++ \
        make \
        linux-headers \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
        --with-webp \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        intl \
        opcache \
        zip \
        bcmath \
        gd \
        pcntl \
        exif \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del autoconf g++ make linux-headers \
    && rm -rf /tmp/* /var/cache/apk/*

# Composer binary
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# PHP config
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini
COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Nginx config
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
RUN mkdir -p /run/nginx

# Supervisor config
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

# Copy app source
COPY . /var/www/html

# Bring in built artifacts from earlier stages
COPY --from=composer_deps /app/vendor /var/www/html/vendor
COPY --from=node_builder  /app/public/build /var/www/html/public/build

# ──────────────────────────────────────────────────────────────
# v3.1 — Publish Filament's CSS/JS to public/
# Composer was run with --no-scripts in the composer_deps stage,
# so package:discover and filament:upgrade haven't run yet. Run
# them explicitly now that vendor/ + source code are both present.
# ──────────────────────────────────────────────────────────────
RUN php artisan package:discover --ansi \
    && php artisan filament:upgrade

# Ensure storage/bootstrap dirs exist and are writable
RUN mkdir -p /var/www/html/storage/framework/{cache/data,sessions,views,testing} \
             /var/www/html/storage/logs \
             /var/www/html/bootstrap/cache \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf", "-n"]

# ─────────────────────────────────────────────────────────────────
# Stage 4 (optional): Development image with Xdebug + dev composer deps
# ─────────────────────────────────────────────────────────────────
FROM app AS dev

# Xdebug for debugging
RUN apk add --no-cache --virtual .build-deps autoconf g++ make linux-headers \
    && pecl install xdebug \
    && docker-php-ext-enable xdebug \
    && apk del .build-deps

ENV PHP_IDE_CONFIG="serverName=marketplace"

# Re-install with dev dependencies for the dev image
WORKDIR /var/www/html
RUN composer install \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
    && php artisan package:discover --ansi \
    && php artisan filament:upgrade
