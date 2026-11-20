#!/bin/sh
# Starts any of the backend containers (api, queue, reverb) from the same image.
set -e

cd /var/www/html

case "$*" in
  *"artisan test"*|*"composer test"*|*phpunit*)
    # Tests use their own in-memory SQLite database (see phpunit.xml). Clear Docker's
    # environment so nothing can point them at the real PostgreSQL database.
    exec env -i PATH="$PATH" HOME=/tmp "$@"
    ;;
esac

if [ -z "$APP_KEY" ]; then
  echo "APP_KEY is not set. Run ./setup.sh in the repo root to create your .env file." >&2
  exit 1
fi

mkdir -p storage/app/public/posts storage/app/public/avatars storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
chown -R www-data:www-data storage bootstrap/cache

echo "Waiting for PostgreSQL at ${DB_HOST}:${DB_PORT:-5432}..."
until php -r 'try { new PDO(sprintf("pgsql:host=%s;port=%s;dbname=%s", getenv("DB_HOST"), getenv("DB_PORT") ?: 5432, getenv("DB_DATABASE")), getenv("DB_USERNAME"), getenv("DB_PASSWORD")); exit(0); } catch (Throwable $e) { exit(1); }'; do
  sleep 2
done

su-exec www-data php artisan config:cache
su-exec www-data php artisan route:cache

if [ "$RUN_MIGRATIONS" = "true" ]; then
  su-exec www-data php artisan migrate --force
  if [ "$SEED_DEMO_DATA" = "true" ]; then
    su-exec www-data php artisan db:seed --force
  fi
fi

# php-fpm starts as root and drops its workers to www-data itself; everything else runs as www-data.
if [ "$1" = "php-fpm" ]; then
  exec "$@"
fi
exec su-exec www-data "$@"
