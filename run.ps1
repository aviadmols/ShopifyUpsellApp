# Shopify Upsell App - Full run script (Windows PowerShell)
# Creates DB if needed, runs migrations + seeders, then starts the server.

$ErrorActionPreference = "Stop"
$ProjectRoot = $PSScriptRoot
Set-Location $ProjectRoot

Write-Host "=== Shopify Upsell App - Setup & Run ===" -ForegroundColor Cyan

# 1. .env
if (-not (Test-Path ".env")) {
    Write-Host "Creating .env from .env.example..." -ForegroundColor Yellow
    Copy-Item ".env.example" ".env"
} else {
    Write-Host ".env exists." -ForegroundColor Green
}

# 2. APP_KEY
$envContent = Get-Content ".env" -Raw
if ($envContent -notmatch "APP_KEY=base64:[A-Za-z0-9+/=]+") {
    Write-Host "Generating APP_KEY..." -ForegroundColor Yellow
    php artisan key:generate --force
} else {
    Write-Host "APP_KEY already set." -ForegroundColor Green
}

# 3. Database
$dbConnection = "sqlite"
if (Test-Path ".env") {
    $envLines = Get-Content ".env"
    foreach ($line in $envLines) {
        if ($line -match "^\s*DB_CONNECTION\s*=\s*(\w+)") {
            $dbConnection = $Matches[1].ToLower()
            break
        }
    }
}

if ($dbConnection -eq "sqlite") {
    $dbPath = Join-Path $ProjectRoot "database\database.sqlite"
    if (-not (Test-Path $dbPath)) {
        Write-Host "Creating SQLite database file..." -ForegroundColor Yellow
        New-Item -ItemType File -Path $dbPath -Force | Out-Null
    }
    Write-Host "SQLite database ready: database\database.sqlite" -ForegroundColor Green
} elseif ($dbConnection -eq "mysql" -or $dbConnection -eq "mariadb") {
    $dbName = "laravel"
    $envLines = Get-Content ".env"
    foreach ($line in $envLines) {
        if ($line -match "^\s*DB_DATABASE\s*=\s*(.+)") { $dbName = $Matches[1].Trim(); break }
    }
    Write-Host "Using $dbConnection. Ensure database '$dbName' exists (create it in MySQL if needed)." -ForegroundColor Yellow
}

# 4. Migrate
Write-Host "Running migrations..." -ForegroundColor Yellow
php artisan migrate --force
if ($LASTEXITCODE -ne 0) {
    Write-Host "Migration failed. If using MySQL, create the database first (see DATABASE.md)." -ForegroundColor Red
    exit 1
}
Write-Host "Migrations done." -ForegroundColor Green

# 5. Seed
Write-Host "Running seeders..." -ForegroundColor Yellow
php artisan db:seed --force
Write-Host "Seeders done." -ForegroundColor Green

# 6. Build frontend (optional, can be slow)
$build = $args -contains "--build"
if ($build) {
    Write-Host "Building frontend (npm run build)..." -ForegroundColor Yellow
    npm run build
    Write-Host "Frontend built." -ForegroundColor Green
}

# 7. Serve
Write-Host "Starting Laravel server (Ctrl+C to stop)..." -ForegroundColor Cyan
Write-Host "Admin: http://127.0.0.1:8000/admin?shop=demo-store.myshopify.com" -ForegroundColor Gray
php artisan serve
