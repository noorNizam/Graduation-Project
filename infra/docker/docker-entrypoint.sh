#!/bin/sh
set -e

if [ -z "${APP_KEY}" ]; then
    if [ ! -f .env ] || ! grep -q "^APP_KEY=" .env 2>/dev/null || [ -z "$(grep "^APP_KEY=" .env 2>/dev/null | cut -d= -f2)" ]; then
        php artisan key:generate --force --quiet
        echo "Generated APP_KEY (none was set in env or .env)."
    else
        echo "Using APP_KEY from .env file."
    fi
else
    echo "Using APP_KEY from environment variable."
fi

if [ -n "${DB_HOST}" ]; then
    echo "Waiting for database connection at ${DB_HOST}:${DB_PORT:-3306}..."
    delay=1
    max_attempts=30
    attempt=0
    while [ $attempt -lt $max_attempts ]; do
        php -r "
            try {
                \$pdo = new PDO('mysql:host=${DB_HOST};port=${DB_PORT:-3306};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}', [PDO::ATTR_TIMEOUT => 2]);
                echo 'ok';
            } catch (PDOException \$e) {
                echo 'waiting';
            }
        " 2>/dev/null | grep -q ok && break
        attempt=$((attempt + 1))
        sleep $delay
    done
    if [ $attempt -ge $max_attempts ]; then
        echo "ERROR: database did not become available after ${max_attempts} attempts."
        exit 1
    fi
    echo "Database ready."
fi

php artisan migrate --force --quiet || true

# Seed only if the database is empty (no users yet)
user_count=$(php -r "try { \$pdo = new PDO('mysql:host=${DB_HOST};port=${DB_PORT:-3306};dbname=${DB_DATABASE}', '${DB_USERNAME}', '${DB_PASSWORD}', [PDO::ATTR_TIMEOUT => 2]); echo \$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(); } catch(\Exception \$e) { echo '0'; }" 2>/dev/null)
if [ "${user_count}" = "0" ]; then
    php artisan db:seed --force --quiet
fi

php artisan storage:link --force --quiet 2>/dev/null || true

if [ "${APP_ENV}" = "production" ]; then
    touch database/database.sqlite
    php artisan package:discover --quiet || true
    php artisan config:cache --quiet || true
    php artisan route:cache --quiet || true
    php artisan view:cache --quiet || true
fi

php artisan storage:link --force --quiet 2>/dev/null || true

if [ "${APP_ENV}" = "production" ]; then
    touch database/database.sqlite
    php artisan package:discover --quiet || true
    php artisan config:cache --quiet || true
    php artisan route:cache --quiet || true
    php artisan view:cache --quiet || true
fi

exec "$@"
