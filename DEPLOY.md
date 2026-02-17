# Deploy checklist (Railway, etc.)

## בודקים ש-Git וההגדרות נכונים ב-Railway

אם האתר לא עולה (502) או שנראה שהקוד לא מעודכן:

| מה לבדוק | איפה ב-Railway | מה אמור להיות |
|----------|----------------|----------------|
| **Repository** | Settings → Source (או Connect Repo) | הריפו הנכון, למשל `aviadmols/ShopifyUpsellApp` |
| **Branch** | Settings → Branch | `main` (או הענף שאתה דוחף אליו) |
| **Root Directory** | Settings → Root Directory / Monorepo | **ריק** או `.` (שורש הפרויקט; ה-Dockerfile חייב להיות בשורש) |
| **Start Command** | Settings → Deploy → Start Command | **ריק** – כדי שה-ENTRYPOINT מה-Dockerfile ירוץ |
| **Build** | בונה מ-Dockerfile | אמור לראות "Using Detected Dockerfile" בלוג ה-Build |

**אם שינית משהו:** שמור → **Redeploy** → בטאב Deploy בחר **"Clear build cache"** ואז Deploy מחדש, כדי שלא ישתמש ב-cache ישן.

**קובץ `railway.toml`:** בפרויקט יש `railway.toml` שמגדיר **startCommand = "/docker-start.sh"**. ההגדרה בקובץ **דורסת** את מה שמוגדר בדשבורד, כך שאפילו אם בדשבורד יש פקודת הפעלה אחרת – Railway יריץ את הסקריפט שלנו.

**לאמת שהקוד המעודכן עלה:** כשהאתר עובד, גלוש ל־`https://your-domain/version` – אמור להחזיר JSON עם `deploy_check`. אם אתה רואה את הערך המעודכן, הדיפלוי מהענף הנכון.

---

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

**⚠️ NEVER run in production:** `php artisan migrate:fresh`, `php artisan db:wipe`, or `php artisan migrate:refresh`. These commands **destroy all data**. The start script (`docker-start.sh`) runs only `migrate --force` (applies new migrations incrementally). Do not add any Variable in Railway that would run `migrate:fresh` or the DB will be reset on every deploy.

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

## "עובד אצלי (local) אבל לא בשרת"

1. **לוגים ב-Railway**  
   Deployments → הדיפלוי האחרון → **View logs**. חפש:
   - `ERROR: APP_KEY is not set` → הוסף משתנה `APP_KEY` (ערך: `php artisan key:generate --show`).
   - שגיאות של MySQL/PDO → וודא שהוספת שירות MySQL ב-Railway ומשתנים כמו `MYSQL_URL` או `DB_CONNECTION=mysql` מוגדרים.
   - שגיאות של migrations → וודא חיבור DB תקין; הסקריפט `docker-start.sh` מריץ אוטומטית `php artisan migrate --force` בהפעלה.

2. **משתני סביבה חובה בשרת**  
   ב-Railway → Variables וודא: `APP_KEY`, ואם יש MySQL: `DB_CONNECTION=mysql` (ואין צורך להגדיר את כל ה-DB_* אם מוגדר `MYSQL_URL` / `MYSQL_PUBLIC_URL`).

3. **בדיקת חיבור DB אחרי דיפלוי**  
   גלוש ל־`https://your-app-url/db-check` – אם מקבל JSON עם `"ok": true` החיבור עובד.

---

## Troubleshooting: "Application failed to respond"

אם בדומיין מופיע רק "Application failed to respond":

1. **הוסף APP_KEY (חובה)**  
   ב-Railway: **Variables** → **Add Variable** → `APP_KEY` = הערך מ-`php artisan key:generate --show` (מתחיל ב-`base64:...`).  
   **שמור** ולחץ **Redeploy**. בלי זה Laravel לא עולה.

2. **בדוק לוגים אחרי הדיפלוי**  
   **Deployments** → הדיפלוי האחרון → **View logs**.  
   אחרי "Deployment successful" צפה ב-**runtime logs** (השורות שמופיעות כשהאפליקציה רצה). אם יש שם שגיאה (למשל `No application encryption key` או exception אחר) – זה הסיבה.  
   הסקריפט `docker-start.sh` מדפיס `Starting Laravel on 0.0.0.0:XXXX` – אם אתה רואה את זה ואז קריסה, השגיאה תופיע מיד אחרי.

3. **וודא משתני MySQL**  
   אם הוספת שירות MySQL ב-Railway, וודא ש-**DB_CONNECTION=mysql** מופיע ב-Variables (ולא נשמר config cache עם sqlite).

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
