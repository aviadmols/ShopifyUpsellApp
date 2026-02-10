# Deploy checklist (Railway, etc.)

## 0. APP_KEY (חובה – בלי זה האפליקציה לא עולה)

Laravel דורש `APP_KEY` להצפנה ו-sessions. **אם חסר – האפליקציה תכשל בהפעלה** ותקבל "Application failed to respond".

**ב-Railway:**  
1. Variables → Add Variable  
2. Name: `APP_KEY`  
3. Value: מפתח base64 (32 תווים). ליצירה מקומית:
   ```bash
   php artisan key:generate --show
   ```
   להעתיק את הערך (מתחיל ב-`base64:`) ולהדביק ב-Railway.

אחרי הוספת `APP_KEY` – **Redeploy**.

---

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

## 5. Optional: seed demo data + default admin user

Only for staging/demo:

```bash
php artisan db:seed --force
```

This creates a demo shop, placements, and a **default admin user**:

- **Email:** `Aviadmols@gmail.com`
- **Password:** `ChangeMe123!` (change after first login)

---

## Troubleshooting: "Application failed to respond"

אם Railway מציג רק "Application failed to respond" בלי פרטי שגיאה:

1. **בדוק Deploy Logs** ב-Railway (לא רק Request ID): לוגים של ה-build וה-runtime יראו אם השרת קרס (למשל חוסר APP_KEY או חיבור DB).
2. **וודא APP_KEY** – ראה סעיף 0 למעלה. בלי מפתח, Laravel נכשל בטעינה.
3. **וודא PORT** – ב-Dockerfile האפליקציה מאזינה ל-`$PORT`. אל תגדיר PORT ידנית ב-Variables אלא רק אם Railway לא מזריק אותו אוטומטית.

## Troubleshooting: Console/Kernel.php error

If you see an error pointing to `vendor/laravel/framework/.../Console/Kernel.php` or `Application->run()`, that line is only the **framework** – the **real error** is usually a few lines **above** it in the same log (the exception message and first stack lines).

**What to do:**

1. **Get the full error**  
   Run on the server (or in the deploy log):
   ```bash
   php artisan config:clear
   php artisan migrate --force 2>&1
   ```
   The line before `Kernel.php` / `Application->run()` usually says e.g.:
   - `PDOException: could not find driver` → enable PHP extension `pdo_mysql`
   - `SQLSTATE[HY000] [2002]` → MySQL not reachable (host/port/firewall)
   - `No application encryption key has been specified` → set `APP_KEY` in env (e.g. `php artisan key:generate --show` and add to env)
   - `Access denied for user` → wrong `MYSQLUSER` / `MYSQLPASSWORD` (or `DB_USERNAME` / `DB_PASSWORD`)

2. **Before migrate, clear config**  
   If the server cached config when MySQL vars were missing, Laravel may still use SQLite and then fail. Run:
   ```bash
   php artisan config:clear
   ```
   then set `DB_CONNECTION=mysql` and your MySQL vars, then run `migrate --force` again.

3. **Check PHP extensions**  
   The server must have `pdo_mysql`. This repo includes a **Dockerfile** that installs it with `docker-php-ext-install pdo_mysql`. If Railway detects the Dockerfile, it will use it instead of Nixpacks and the "could not find driver" error should disappear. `composer.json` also declares `ext-pdo_mysql` for Nixpacks-only builds.
