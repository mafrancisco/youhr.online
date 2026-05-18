#!/bin/bash
set -e

# ── Populate shared public volume (nginx needs these) ───────────
echo "Syncing public assets..."
cp -r /var/www/public_init/. /var/www/public/

# ── Ensure storage directories exist (volume starts empty) ───────
mkdir -p /var/www/storage/framework/{sessions,views,cache/data} \
         /var/www/storage/logs \
         /var/www/storage/app/public
chown -R www-data:www-data /var/www/storage
chmod -R 775 /var/www/storage

# ── Wait for MySQL ───────────────────────────────────────────────
echo "Waiting for MySQL at ${DB_HOST}:${DB_PORT:-3306}..."
until php -r "
    new PDO(
        'mysql:host=${DB_HOST};port=${DB_PORT:-3306};dbname=${DB_DATABASE}',
        '${DB_USERNAME}',
        '${DB_PASSWORD}'
    );
" 2>/dev/null; do
    sleep 2
done
echo "MySQL is ready."

# ── Bootstrap caches ────────────────────────────────────────────
php artisan config:cache
php artisan route:cache
php artisan view:cache || true

# ── Run migrations ───────────────────────────────────────────────
php artisan migrate --force

# ── Storage symlink ──────────────────────────────────────────────
php artisan storage:link 2>/dev/null || true

exec php-fpm
