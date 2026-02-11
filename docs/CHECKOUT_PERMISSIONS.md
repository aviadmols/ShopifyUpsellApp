# Checkout Upsell – Permissions and app

## Summary (date: 2026-02)

### App permissions (shopify.app.zyg-upsell.toml)

- **scopes (required):** Empty `""` – no required scopes.
- **optional_scopes:** `read_orders`, `write_orders`, `read_products`, `write_products` – merchant can approve on install.

### Extension permissions (checkout-upsell)

- **target:** `purchase.checkout.block.render` – block in Checkout.
- **capabilities:**
  - `network_access = true` – allows fetch to API (e.g. `/api/checkout/offers`).
  - `api_access = true` – access to Checkout API (e.g. add to cart).

### Is that enough?

**Yes.**  
Running the Checkout UI extension does not depend on the app’s OAuth scopes. The block is loaded by Shopify when the block is placed in Checkout, and the extension capabilities (network_access, api_access) are enough to:

1. Show the block.
2. Send requests to the backend (`/api/checkout/offers`).
3. Use `applyCartLinesChange` to add items to the cart.

The backend only verifies `X-Extension-Secret` and the `shop` parameter – no extra scopes are needed for the Checkout block to work.  
If the backend uses the Admin API (e.g. to read products/orders), then optional_scopes apply to those server-side operations, not to rendering the block in Checkout.

### Blocks and block_id

- The Checkout extension sends **POST** to `/api/checkout/offers` with body: `shop`, `block_id` (optional, from block settings), `subtotal`, `line_items`. That allows rules by cart amount/products to run.
- **Block ID** (in block settings in the Checkout editor) = block ID from Admin (Blocks). If set – the server returns that block’s config (Upsell / Progress bar / content); otherwise – fallback to Placement (Legacy).
