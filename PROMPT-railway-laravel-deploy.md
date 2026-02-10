# פרומפט: דיפלוי Laravel (עם Filament) ל-Railway – בלי להסתבך

**העתק את הבלוק הזה וכתוב ל-AI בתחילת פרויקט חדש (או כשאתה מוסיף Railway):**

---

אני רוצה לדפלס אפליקציית Laravel (עם Filament) ל-Railway עם MySQL. תגדיר הכל כך שהאפליקציה תעלה ותרוץ בלי להסתבך.

**דרישות:**

1. **Dockerfile (PHP 8.2+)**
   - התקן במפורש אתטנשנים: `pdo_mysql`, `mbstring`, `exif`, `pcntl`, `bcmath`, `zip`, **`intl`** (חובה ל-Filament). השתמש ב-`libicu-dev` ו-`docker-php-ext-configure intl` לפני `docker-php-ext-install intl`.
   - אחרי `COPY . .` צור תיקיות ריקות וכתיבה: `storage/framework/sessions`, `storage/framework/views`, `storage/framework/cache`, `storage/logs`, `bootstrap/cache` עם `chmod -R 775 storage bootstrap/cache` (כי `.dockerignore` לפעמים לא מעתיק את התוכן שלהן).

2. **סקריפט הפעלה (למשל `docker-start.sh`)**
   - לא להשתמש ב-`set -e` כדי שאם `php artisan migrate --force` נכשל (למשל DB לא מוכן), השרת בכל זאת יעלה.
   - בתחילה: בדיקה ש-`APP_KEY` לא ריק; אם ריק – להדפיס הודעת שגיאה ברורה ו-`exit 1`.
   - להריץ: `php artisan config:clear`, אחר כך `php artisan migrate --force` (אם נכשל – להדפיס אזהרה ולהמשיך), ואז `exec php artisan serve --host=0.0.0.0 --port="$PORT"`.
   - להשתמש ב-`PORT` מהסביבה (Railway מזריק), ברירת מחדל 8000.
   - להדפיס שורות לוג שמתחילות ב-`[docker-start]` כדי שיהיה ברור בלוגים מה רץ.

3. **Railway – Config as Code**
   - להוסיף קובץ `railway.toml` בשורש הפרויקט עם:
     - `[build]` ו-`builder = "DOCKERFILE"`.
     - `[deploy]` עם **`startCommand = "/docker-start.sh"`** (נתיב מלא לסקריפט), כדי ששום הגדרה בדשבורד לא תדרוס את פקודת ההפעלה.
     - אופציונלי: `healthcheckPath = "/up"` ו-`healthcheckTimeout = 30`.

4. **Laravel – חיבור MySQL ב-Railway**
   - ב-`config/database.php`: ברירת מחדל ל-connection לפי נוכחות `MYSQL_URL` / `MYSQL_PUBLIC_URL` / `MYSQLHOST` (אם קיימים → `mysql`, אחרת `sqlite`). בחיבור `mysql` להשתמש ב-`env('DB_URL', env('MYSQL_URL', env('MYSQL_PUBLIC_URL')))` ל-`url` ולהוסיף fallbacks ל-`host`, `port`, `database`, `username`, `password` מ-`MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`/`MYSQL_DATABASE`, `MYSQLUSER`, `MYSQLPASSWORD`/`MYSQL_ROOT_PASSWORD`.

5. **משתני סביבה חובה ב-Railway (Variables)**
   - `APP_KEY` – חובה (למשל `php artisan key:generate --show`).
   - `DB_CONNECTION=mysql` אם יש MySQL.
   - `APP_URL` – ה-URL המלא עם **https** (למשל `https://my-app.up.railway.app`). להשתמש ב-`MYSQL_URL` (חיבור פרטי) ולא ב-`MYSQL_PUBLIC_URL` כדי להימנע מ-egress.

6. **HTTPS ו-Mixed Content**
   - ב-`AppServiceProvider::boot()`: אם `app()->environment('production')`, להריץ `URL::forceScheme('https')` כדי שכל ה-URLs (assets, redirects) יהיו עם https ולא יגרמו ל-Mixed Content.

7. **Route לבדיקת דיפלוי**
   - Route פשוט (למשל `GET /version` או `GET /up`) שמחזיר JSON עם סטטוס או גרסה, כדי לאמת שהאפליקציה רצה והקוד המעודכן עלה.

8. **תיעוד קצר (למשל DEPLOY.md)**
   - צ’קליסט: APP_KEY, DB_CONNECTION, APP_URL, איפה לראות לוגים, איך לבדוק חיבור DB (למשל `/db-check`).
   - להזכיר שאם יש "Start Command" בדשבורד של Railway – להשאיר ריק או שיתאים ל-`railway.toml`; אחרת הסקריפט מהדשבורד דורס את הסקריפט שלנו.
   - להזכיר שאחרי שינוי הגדרות – Redeploy עם "Clear build cache" אם צריך.

תוודא שכל הקבצים האלה קיימים ועקביים עם ההנחיות (Dockerfile, סקריפט הפעלה, railway.toml, config database, AppServiceProvider, routes, DEPLOY.md), ושאני לא אסתבך בדיפלוי או ב-Mixed Content.

---

**סוף הפרומפט.**
