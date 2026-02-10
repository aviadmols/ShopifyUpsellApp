#!/bin/sh
# Don't exit on first error so we always try to start the server (migrate can fail if DB not ready)
echo "[docker-start] Container started, PORT=${PORT:-8000}" >&2
echo "[docker-start] Container started, PORT=${PORT:-8000}"

PORT="${PORT:-8000}"

if [ -z "$APP_KEY" ]; then
  echo "[docker-start] ERROR: APP_KEY is not set. Add it in Railway Variables."
  exit 1
fi

echo "[docker-start] Clearing config cache..."
php artisan config:clear

echo "[docker-start] Running migrations..."
if php artisan migrate --force; then
  echo "[docker-start] Migrations OK."
else
  echo "[docker-start] WARNING: Migrations failed (check DB). Starting server anyway."
fi

echo "[docker-start] Starting Laravel on 0.0.0.0:${PORT}"
exec php artisan serve --host=0.0.0.0 --port="$PORT"
