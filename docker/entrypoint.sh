#!/bin/sh
set -e

echo "=========================================="
echo "  BAUST Campus - Starting Up..."
echo "=========================================="

# Set system timezone if TZ is provided
if [ -n "$TZ" ] && [ -f "/usr/share/zoneinfo/$TZ" ]; then
    ln -snf "/usr/share/zoneinfo/$TZ" /etc/localtime
    echo "$TZ" > /etc/timezone
fi

# Require the secrets that must never fall back to a weak default.
: "${APP_KEY:?APP_KEY must be set (see .env.docker)}"
: "${DB_DATABASE:?DB_DATABASE must be set}"
: "${DB_USERNAME:?DB_USERNAME must be set}"
: "${DB_PASSWORD:?DB_PASSWORD must be set}"

# Laravel reads configuration straight from the container environment (set by
# compose from .env.docker), so no .env file is written inside the image.

# Wait for MySQL to be ready
echo "[entrypoint] Waiting for MySQL to be ready..."
MAX_RETRIES=30
RETRY_COUNT=0
until php -r "
    try {
        new PDO(
            'mysql:host=' . getenv('DB_HOST') . ';port=' . getenv('DB_PORT'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD')
        );
        exit(0);
    } catch (Exception \$e) {
        exit(1);
    }
" 2>/dev/null || [ $RETRY_COUNT -eq $MAX_RETRIES ]; do
    echo "[entrypoint] MySQL not ready yet... retrying ($((RETRY_COUNT+1))/$MAX_RETRIES)"
    RETRY_COUNT=$((RETRY_COUNT+1))
    sleep 2
done

if [ $RETRY_COUNT -eq $MAX_RETRIES ]; then
    echo "[entrypoint] WARNING: MySQL may not be fully ready, proceeding anyway..."
fi

# Run database migrations (no seeding: the default seeder creates a
# test@example.com/password account).
echo "[entrypoint] Running database migrations..."
php artisan migrate --force || echo "[entrypoint] WARNING: migrate failed, continuing..."

# Cache configuration for performance. Re-run on every start, so changing
# APP_URL etc. in .env.docker only needs `docker compose up -d`.
echo "[entrypoint] Caching configuration..."
php artisan config:cache || echo "[entrypoint] WARNING: config:cache failed, continuing..."
php artisan route:cache || echo "[entrypoint] WARNING: route:cache failed, continuing..."
php artisan view:cache || echo "[entrypoint] WARNING: view:cache failed, continuing..."

# Ensure public/storage is a symlink to storage/app/public
if [ -d /var/www/html/public/storage ] && [ ! -L /var/www/html/public/storage ]; then
    rm -rf /var/www/html/public/storage
fi
php artisan storage:link || true

# Fix ownership of files created by root during entrypoint
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

echo "=========================================="
echo "  Application is ready!"
echo "  URL: ${APP_URL:-http://localhost}"
echo "=========================================="

exec "$@"
