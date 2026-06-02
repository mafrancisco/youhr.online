#!/bin/bash
set -e

# ── Ensure storage directories exist ────────────────────────────
mkdir -p /var/www/storage/framework/{sessions,views,cache/data} \
         /var/www/storage/logs \
         /var/www/storage/app/public \
         /var/www/storage/app/mpdf-tmp
chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache
chmod -R 775 /var/www/storage /var/www/bootstrap/cache

# ── Generate .env if missing ─────────────────────────────────────
if [ ! -f /var/www/.env ]; then
    cp /var/www/.env.example /var/www/.env
    # Override DB settings for Docker
    sed -i "s/DB_HOST=.*/DB_HOST=${DB_HOST:-db}/" /var/www/.env
    sed -i "s/DB_PORT=.*/DB_PORT=${DB_PORT:-3306}/" /var/www/.env
    sed -i "s/DB_DATABASE=.*/DB_DATABASE=${DB_DATABASE:-ironone}/" /var/www/.env
    sed -i "s/DB_USERNAME=.*/DB_USERNAME=${DB_USERNAME:-root}/" /var/www/.env
    sed -i "s/DB_PASSWORD=.*/DB_PASSWORD=${DB_PASSWORD:-secret}/" /var/www/.env
    php artisan key:generate --force
fi

# ── Ensure DB_HOST points to Docker container ────────────────────
# Override .env DB_HOST in case it says 127.0.0.1
sed -i "s/DB_HOST=127.0.0.1/DB_HOST=${DB_HOST:-db}/" /var/www/.env
sed -i "s/DB_HOST=localhost/DB_HOST=${DB_HOST:-db}/" /var/www/.env

# ── Wait for MySQL ───────────────────────────────────────────────
echo "Waiting for MySQL at ${DB_HOST:-db}:${DB_PORT:-3306}..."
until php -r "
    new PDO(
        'mysql:host=${DB_HOST:-db};port=${DB_PORT:-3306}',
        '${DB_USERNAME:-root}',
        '${DB_PASSWORD:-secret}'
    );
" 2>/dev/null; do
    sleep 2
done
echo "MySQL is ready."

# ── Run migrations ───────────────────────────────────────────────
php artisan migrate --force 2>/dev/null || true

# ── Storage symlink ──────────────────────────────────────────────
php artisan storage:link 2>/dev/null || true

# ── Clear and cache config ───────────────────────────────────────
php artisan config:clear
php artisan route:clear
php artisan view:clear

exec php-fpm
