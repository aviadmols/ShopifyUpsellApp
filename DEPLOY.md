# Deploy checklist (Railway, etc.)

## 1. MySQL environment variables

Ensure these are set (Railway injects them when you add MySQL):

- `MYSQL_URL` or `MYSQL_PUBLIC_URL` (preferred – one connection string)
- Or: `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE` / `MYSQL_DATABASE`, `MYSQLUSER`, `MYSQLPASSWORD` / `MYSQL_ROOT_PASSWORD`

**Important:** Add this so the app uses MySQL and not SQLite:

```env
DB_CONNECTION=mysql
```

## 2. Do not cache config before first DB connection

If you run `php artisan config:cache` **before** the MySQL variables are available, Laravel may cache `sqlite` as the default connection. So either:

- Run config cache **after** the first deploy when MySQL vars are set, or  
- Run once after deploy: `php artisan config:clear`

## 3. Create tables (migrations)

The **database** (the empty MySQL database) is created by the platform when you add MySQL. The **tables** (shops, offers, etc.) are created by Laravel migrations. You must run:

```bash
php artisan migrate --force
```

Add this to your deploy/build or start script, for example:

**Build command (example):**

```bash
composer install --optimize-autoloader --no-dev --no-interaction
php artisan migrate --force
php artisan config:cache
```

**Or as a separate “release” step** (many hosts support a “run before start” command):

```bash
php artisan config:clear && php artisan migrate --force
```

## 4. Check that MySQL is connected

After deploy, open:

```
https://your-app-url/db-check
```

You should see JSON like:

```json
{ "ok": true, "connection": "mysql", "database": "railway" }
```

If you see `"ok": false` and an error, the DB is not connected (check vars and `DB_CONNECTION=mysql`).

## 5. Optional: seed demo data

Only for staging/demo:

```bash
php artisan db:seed --force
```

This creates a demo shop and placements.
