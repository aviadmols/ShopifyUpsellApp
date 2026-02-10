#!/bin/sh
set -e
# Railway injects PORT; default 8000 for local
PORT="${PORT:-8000}"
echo "Starting Laravel on 0.0.0.0:${PORT}"
php artisan config:clear
exec php artisan serve --host=0.0.0.0 --port="$PORT"
