#!/bin/sh
set -e

# Run migrations if the database is reachable and RUN_MIGRATIONS is true
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "Running database migrations..."
  php artisan migrate --force --no-interaction || echo "Warning: migrations failed, continuing..."
fi

# Cache configuration for faster boot (skip in dev)
if [ "${APP_ENV:-production}" = "production" ]; then
  php artisan config:cache 2>/dev/null || true
  php artisan route:cache 2>/dev/null || true
  php artisan view:cache 2>/dev/null || true
fi

# Start the MCP stdio server
exec php artisan mcp:start knowledge
