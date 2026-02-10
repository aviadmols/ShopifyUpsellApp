#!/bin/sh
set -e
echo "[docker-start] Container started, PORT=${PORT:-8000}"
# Railway injects PORT; default 8000 for local
PORT="${PORT:-8000}"
php artisan config:clear
echo "[docker-start] Starting Laravel on 0.0.0.0:${PORT}"
exec php artisan serve --host=0.0.0.0 --port="$PORT"
