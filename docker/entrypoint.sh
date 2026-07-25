#!/bin/sh
set -e

# Wait for database
until PGPASSWORD="${DB_PASSWORD}" psql -h "${DB_HOST:-pgsql}" -U "${DB_USERNAME:-agentoom}" -d "${DB_DATABASE:-agentoom}" -c '\q' 2>/dev/null; do
  echo "Waiting for PostgreSQL..."
  sleep 2
done

# Run migrations
php artisan migrate --force --no-interaction

# Seed demo data and index (idempotent)
if [ "${SEED_DEMO:-true}" = "true" ]; then
  php artisan db:seed --class=KnowledgeDemoSeeder --no-interaction
  php artisan knowledge:chunks:index --no-interaction
fi

# Build registry
php artisan knowledge:registry:refresh --no-interaction

# Cache for production
php artisan config:cache
php artisan route:cache
php artisan view:cache

exec "$@"
