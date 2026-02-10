# Checkout Extensions

This folder contains three Shopify app extensions that work with the Laravel backend.

## 1. Checkout upsell (`checkout-upsell`)

- **Target**: `purchase.checkout.block.render` (block on checkout page).
- **Flow**: Extension loads, calls `GET /api/checkout/offers?shop=...` with header `X-Extension-Secret`. Laravel returns eligible offers. Buyer clicks "Add to order" and the extension adds the variant to the cart via `applyCartLinesChange({ type: 'addCartLine', merchandiseId, quantity: 1 })`.
- **Settings** (in Shopify checkout editor): `api_url`, `extension_secret`, `shop_domain`.

## 2. Post-purchase (`post-purchase`)

- **Type**: `checkout_post_purchase`. Created with `shopify app generate extension` → Post-purchase.
- **Flow**: Shopify runs your extension with a **ShouldRender** step; your code calls `POST /api/post-purchase/should-render` with shop, order, line_items, customer, shipping. If Laravel returns `render: true`, Shopify shows your UI. On "Add to order" the extension calls `POST /api/post-purchase/accept` and applies the returned changeset (cart lines + optional discount). Use idempotency so the same order cannot accept twice.
- **Settings**: Configure `api_url`, `extension_secret`, `shop_domain` in the app extension settings.

## 3. Thank you blocks (`thank-you-blocks`)

- **Target**: `purchase.thank-you.block.render` (thank you / order status page).
- **Flow**: Extension calls `GET /api/thankyou/blocks?shop=...` with `X-Extension-Secret`. Laravel returns blocks (banner, text, button, product_card). Extension renders each; "Buy now" on product cards goes to product or checkout URL (no one-click same order).
- **Settings**: `api_url`, `extension_secret`, `shop_domain`.

## Creating extensions with Shopify CLI

From the project root (Laravel app root):

```bash
# Install Shopify CLI if needed: npm install -g @shopify/cli @shopify/app
shopify app init
# or link existing app: shopify app config link

# Generate extensions (then replace generated files with the ones in this folder)
shopify app generate extension
# Choose: Checkout UI Extension -> name: checkout-upsell
shopify app generate extension
# Choose: Post-purchase
shopify app generate extension
# Choose: Checkout UI Extension -> thank-you target if available, or use purchase.thank-you.block.render
```

Copy the contents of `extensions/checkout-upsell`, `extensions/post-purchase`, and `extensions/thank-you-blocks` over the generated extension directories, or create the extension directories manually and add the toml + src files.

## Local development

1. Run Laravel with a public URL (ngrok or Cloudflare tunnel): `php artisan serve` and expose with `ngrok http 8000`.
2. Set `APP_URL` and extension settings to the tunnel URL.
3. Run `shopify app dev` to preview extensions in a development store.

## Deploy

```bash
shopify app deploy
```

Ensure the Laravel backend is deployed and `APP_URL` is set. Configure each extension’s settings in the Partner Dashboard (or checkout editor) with the production API URL and `CHECKOUT_EXTENSION_SECRET`.
