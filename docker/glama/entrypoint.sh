#!/bin/sh
set -e

# Run migrations if enabled (skip during Glama introspection)
if [ "${RUN_MIGRATIONS:-false}" = "true" ]; then
  echo "Running database migrations..."
  php artisan migrate --force --no-interaction || echo "Warning: migrations failed, continuing..."
fi

# Cache configuration if we have a real database connection
# Skip caching when using SQLite :memory: (Glama introspection mode)
if [ "${APP_ENV:-production}" = "production" ] && [ "${GLAMA_INTROSPECTION:-false}" != "true" ]; then
  php artisan config:cache 2>/dev/null || true
  php artisan route:cache 2>/dev/null || true
  php artisan view:cache 2>/dev/null || true
fi

# Start the MCP stdio server via CMD arguments or fallback default
if [ $# -eq 0 ]; then
  exec php artisan mcp:start knowledge
else
  exec "$@"
fi
