# Shopify Upsell Custom App

Laravel backend + React admin for a **post-purchase one-click upsell** and **checkout upsell** plus **thank you / order status page blocks**. Rule-based; controlled from an admin dashboard.

## Features

- **Checkout upsell**: Show offers during checkout; buyer adds product to cart with one click (same session).
- **Post-purchase offer**: After payment, interstitial offer; one-click add to the same order (order edit).
- **Thank you blocks**: Banner, text, button, product card on thank you / order status page (no one-click charge; "Buy now" links to product/checkout).
- **Admin**: CRUD for Offers, Rules, Thank You Blocks, Placements; JSON rule editor; preview simulator.

## Tech stack

- **Backend**: Laravel 12 (PHP 8.2+), MySQL or SQLite, encrypted store tokens.
- **Admin**: React 18, Vite, Tailwind; embedded in Shopify Admin or standalone.
- **Extensions**: Shopify Checkout UI Extensions (checkout block, post-purchase, thank-you block).

## Step-by-step run instructions

### 1. Laravel setup (quick: use the run script)

**Option A – Full run script (creates DB, migrates, seeds, starts server):**

```powershell
cd ShopifyUpsellApp
.\run.ps1
```

Or from CMD:

```cmd
run.bat
```

The script creates `.env` from `.env.example` if missing, generates `APP_KEY`, creates the SQLite database file if you use SQLite, runs migrations and seeders, then starts `php artisan serve`.

**Option B – Manual:**

```bash
cd ShopifyUpsellApp
cp .env.example .env
php artisan key:generate
php artisan migrate --force
php artisan db:seed --force
```

**Which database to use:** See **[DATABASE.md](DATABASE.md)**. Default is **SQLite** (no install). For production you can use **MySQL** or **MariaDB** (create the database and user first, then set `DB_CONNECTION=mysql` and DB_* in `.env`).

Edit `.env` for Shopify: `SHOPIFY_API_KEY`, `SHOPIFY_API_SECRET`, `SHOPIFY_SCOPES`, `CHECKOUT_EXTENSION_SECRET`, `APP_URL`.

### 2. Run Laravel locally

```bash
php artisan serve
```

Use a tunnel (ngrok or Cloudflare) so Shopify can reach your app:

```bash
ngrok http 8000
```

Set `APP_URL` in `.env` to the tunnel URL (e.g. `https://abc123.ngrok.io`).

### 3. Shopify app and extensions

- Create an app in [Shopify Partners](https://partners.shopify.com) (Custom app or “Create app”).
- Set **App URL** to `{APP_URL}/admin` and **Allowed redirection URL(s)** to `{APP_URL}/auth/callback`.
- In the app, add **Webhooks**: `app/uninstalled` → `{APP_URL}/api/webhooks/app-uninstalled`; optionally `orders/create` → `{APP_URL}/api/webhooks/orders-create`.
- Install the app on a development store: open `{APP_URL}/auth/install?shop=YOUR-STORE.myshopify.com` to complete OAuth.

Using **Shopify CLI** (optional, for extensions):

```bash
npm install -g @shopify/cli @shopify/app
shopify app config link
shopify app generate extension
# Create: Checkout UI (checkout-upsell), Post-purchase, Checkout UI (thank-you target).
# Copy the extension code from extensions/checkout-upsell, extensions/post-purchase, extensions/thank-you-blocks into the generated folders.
shopify app dev
```

Configure each extension in the checkout editor with:

- **Laravel API URL**: your `APP_URL`.
- **Extension secret**: same as `CHECKOUT_EXTENSION_SECRET`.
- **Shop domain**: e.g. `your-store.myshopify.com`.

### 4. Admin dashboard

- Open `{APP_URL}/admin?shop=YOUR-STORE.myshopify.com` (after OAuth, or with a seeded shop).
- Build frontend: `npm install && npm run build`.
- Use the admin to create Offers, Rules, Blocks, and Placements (checkout, post_purchase, thank_you).

### 5. API endpoints (extension calls)

All extension endpoints require header `X-Extension-Secret` equal to `CHECKOUT_EXTENSION_SECRET`.

| Endpoint | Method | Purpose |
|----------|--------|--------|
| `/api/checkout/offers` | GET / POST | List offers for checkout (cart context). |
| `/api/post-purchase/should-render` | POST | Decide if post-purchase offer should show; return offer payload. |
| `/api/post-purchase/accept` | POST | Validate and return changeset; idempotent. |
| `/api/thankyou/blocks` | GET | List blocks for thank you page. |

Admin CRUD: `/api/offers`, `/api/rules`, `/api/blocks`, `/api/placements`, `/api/preview/offer` (use `?shop=...` or session).

## Deploy (outline)

1. **Backend**: Deploy Laravel to a PHP 8.2+ host (e.g. Laravel Forge, Railway, shared hosting). Set `APP_URL` to production URL, configure DB and `.env` (Shopify keys, `CHECKOUT_EXTENSION_SECRET`).
2. **Shopify app**: In Partners, set App URL and webhooks to production. Ensure CORS allows `*` for extension requests (Laravel can set `Access-Control-Allow-Origin: *` for extension routes).
3. **Extensions**: Run `shopify app deploy` from the project root (with linked app). Configure extension settings in the Partner Dashboard / checkout editor with production API URL and secret.
4. **Admin**: Run `npm run build` and serve `public/build` via Laravel; no separate deploy.

## MVP and roadmap

- **MVP**: Single offer per placement (checkout, post_purchase); simple rule (e.g. subtotal >= X); one thank you block type.
- **Roadmap**: Multiple offers per placement; advanced rules (line items, customer tags, country); visual rule builder; A/B tests; product recommendations on thank you.

## Security

- Store access tokens encrypted (Laravel `encrypted` cast).
- Validate webhooks with `X-Shopify-Hmac-Sha256`.
- Validate extension calls with `X-Extension-Secret` (or signed token).
- Use `.env` for all secrets; never commit them.

## Extensions folder

See [extensions/README.md](extensions/README.md) for extension targets, settings, and Shopify CLI commands.

## License

MIT.
