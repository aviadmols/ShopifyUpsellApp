#!/bin/sh
# Don't exit on first error so we always try to start the server (migrate can fail if DB not ready)
# IMPORTANT: We only run "migrate --force" (applies NEW migrations). NEVER run migrate:fresh or db:wipe in production – that would DESTROY all data.
echo "[docker-start] Container started, PORT=${PORT:-8000}" >&2
echo "[docker-start] Container started, PORT=${PORT:-8000}"

PORT="${PORT:-8000}"

if [ -z "$APP_KEY" ]; then
  echo "[docker-start] ERROR: APP_KEY is not set. Add it in Railway Variables."
  exit 1
fi

# Safety: refuse to run if someone mistakenly set a variable that would wipe the DB
if [ -n "$MIGRATE_FRESH" ] || [ -n "$RUN_MIGRATE_FRESH" ]; then
  echo "[docker-start] ERROR: MIGRATE_FRESH or RUN_MIGRATE_FRESH is set. Refusing to run (would reset DB). Remove it from Variables."
  exit 1
fi

echo "[docker-start] Clearing config cache..."
php artisan config:clear

echo "[docker-start] Running migrations (incremental only; DB is NOT reset)..."
if php artisan migrate --force; then
  echo "[docker-start] Migrations OK."
else
  echo "[docker-start] WARNING: Migrations failed (check DB). Starting server anyway."
fi

echo "[docker-start] Starting Laravel on 0.0.0.0:${PORT}"
exec php artisan serve --host=0.0.0.0 --port="$PORT"
