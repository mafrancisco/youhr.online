# ── Stage 1: Build frontend assets ─────────────────────────────
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources/ resources/
COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY public/ public/
RUN npm run build

# ── Stage 2: PHP application ────────────────────────────────────
FROM php:8.2-fpm-alpine AS app

RUN apk add --no-cache \
    bash curl \
    libpng-dev libjpeg-turbo-dev freetype-dev \
    libzip-dev zip oniguruma-dev \
    icu-dev icu-libs

RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install -j"$(nproc)" \
    pdo pdo_mysql mbstring exif pcntl bcmath gd zip opcache intl

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Install PHP deps (cached layer — only invalidated when lock file changes)
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# Copy application source
COPY . .

# Overlay compiled frontend assets from the node stage
COPY --from=frontend /app/public/build public/build

# Optimise autoloader for production
RUN composer dump-autoload --optimize --no-dev

# Keep a copy of public/ so the entrypoint can populate the shared volume
RUN cp -r public public_init

# Writable directories
RUN chown -R www-data:www-data storage bootstrap/cache \
 && chmod -R 775 storage bootstrap/cache

COPY docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 9000
ENTRYPOINT ["/entrypoint.sh"]
