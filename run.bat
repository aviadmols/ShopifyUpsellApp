@echo off
REM Shopify Upsell App - Quick run (uses existing .env and DB)
cd /d "%~dp0"

if not exist .env (
    echo Creating .env from .env.example...
    copy .env.example .env
    php artisan key:generate --force
)

if not exist database\database.sqlite (
    if "%DB_CONNECTION%"=="" set DB_CONNECTION=sqlite
    if "%DB_CONNECTION%"=="sqlite" (
        echo Creating SQLite database...
        type nul > database\database.sqlite
    )
)

php artisan migrate --force
php artisan db:seed --force
echo.
echo Starting server at http://127.0.0.1:8000
echo Admin: http://127.0.0.1:8000/admin?shop=demo-store.myshopify.com
echo.
php artisan serve
