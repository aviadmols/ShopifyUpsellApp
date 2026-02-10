# Database Setup

The app supports two databases. You only need to connect **one** of them.

---

## Option 1: SQLite (recommended for local development)

- **No installation**: PHP uses SQLite by default.
- **No server**: everything is stored in a single file.

### Setup

1. In `.env` set:
   ```env
   DB_CONNECTION=sqlite
   ```
   (Comment out or remove `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` if they are set.)

2. Run the app (the run script creates the file if missing):
   ```bash
   .\run.ps1
   ```
   or
   ```bash
   .\run.bat
   ```
   The file `database/database.sqlite` will be created automatically on first migrate.

**When to use:** Local development, testing, single-merchant setups.

---

## Option 2: MySQL / MariaDB (recommended for production)

- **Better for production**: concurrent writes, backups, scaling.
- **You must have** MySQL 8+ or MariaDB 10.3+ installed and running.

### Setup

1. Create a database and user (in MySQL client or phpMyAdmin):
   ```sql
   CREATE DATABASE shopify_upsell CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'shopify_upsell'@'localhost' IDENTIFIED BY 'your_password';
   GRANT ALL PRIVILEGES ON shopify_upsell.* TO 'shopify_upsell'@'localhost';
   FLUSH PRIVILEGES;
   ```

2. In `.env` set:
   ```env
   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=shopify_upsell
   DB_USERNAME=shopify_upsell
   DB_PASSWORD=your_password
   ```
   Remove or comment `DB_CONNECTION=sqlite` if it is set.

3. Run migrations (run script or manually):
   ```bash
   php artisan migrate --force
   php artisan db:seed --force
   ```

**When to use:** Production, multiple stores, or when you need MySQL features.

---

## Summary: which DB to connect?

| Case                         | Use        | What to do                                      |
|-----------------------------|------------|--------------------------------------------------|
| Local dev / quick start     | **SQLite** | Set `DB_CONNECTION=sqlite` in `.env`, run script |
| Production / shared hosting | **MySQL**  | Create DB + user, set MySQL vars in `.env`       |

The run script (`run.ps1` or `run.bat`) creates the SQLite file when using SQLite; for MySQL it only runs migrations (you create the database and user yourself as above).

---

## Platform MySQL variables (Railway, etc.)

If your host injects **MySQL** variables with names like `MYSQLHOST`, `MYSQLPORT`, `MYSQLDATABASE`, `MYSQLUSER`, `MYSQLPASSWORD` (or `MYSQL_URL` / `MYSQL_PUBLIC_URL`), the app will use them automatically. No need to set `DB_HOST`, `DB_DATABASE`, etc. separately.

**Required (at least one of these):**

- Connection string: **`MYSQL_URL`** or **`MYSQL_PUBLIC_URL`** — Laravel can use this as `DB_URL` and connect with one value.

**Or** individual values:

- **`MYSQLHOST`** (or `DB_HOST`)
- **`MYSQLPORT`** (or `DB_PORT`) — default 3306
- **`MYSQLDATABASE`** or **`MYSQL_DATABASE`** (or `DB_DATABASE`)
- **`MYSQLUSER`** (or `DB_USERNAME`)
- **`MYSQLPASSWORD`** or **`MYSQL_ROOT_PASSWORD`** (or `DB_PASSWORD`)

**Recommended:** set **`DB_CONNECTION=mysql`** in your platform’s environment. Otherwise the app may use SQLite if the MySQL vars are not visible when config is loaded (e.g. after config cache). See [DEPLOY.md](DEPLOY.md).
