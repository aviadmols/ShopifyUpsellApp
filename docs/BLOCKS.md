# Blocks / Widgets – Architecture (Checkout / Thank you / Post-purchase)

## Overview

Display in Checkout, Thank you and Post-purchase is managed via **Widgets** (formerly Blocks) in Admin. Each widget has an **ID**. In Shopify, in each App block instance (in the Checkout or Thank you editor) you can enter **Block ID** – so the same extension can show different blocks by position.

## Admin (Widgets)

- **Widgets** (Widgets menu): Create and edit all widget types.
  - **Surface**: `checkout` | `thank_you` | `post_purchase`
  - **Type**: By surface – e.g. `upsell`, `progress_bar`, `content_icon_features`, `content_banner`, `content_rich_text`, `content_button`, `content_product_card`, `post_purchase_funnel`
  - **Rule** (optional): Widget-level rule – when to show (UTM, cart, products, etc.).
  - **Offers (inline)**: In Upsell and Post-purchase funnel widgets you can manage products/offers directly in the form (section "Offers (manage products & rules)") – including per-offer rules. Order in the list sets display order. Data is stored in `block_offers` (pivot) and `offers`.
  - **Singleton**: A `post_purchase_funnel` widget can be created **only once** per store; creating a second will fail validation.
- **Placements** is marked **Legacy**: Still works as fallback when `block_id` is not set in block settings.

## Extensions

Each extension has a **Block ID (optional)** setting:

| Extension        | File                                             | Use |
|------------------|--------------------------------------------------|-----|
| Checkout Upsell  | `extensions/checkout-upsell/shopify.extension.toml`  | Block ID for surface=checkout block (upsell / progress_bar / content_*). |
| Thank You Blocks | `extensions/thank-you-blocks/shopify.extension.toml` | Block ID for surface=thank_you block. |
| Post-purchase    | `extensions/post-purchase/shopify.extension.toml`   | Block ID for surface=post_purchase, type=post_purchase_funnel. |

- **Checkout**: Sends **POST** to `/api/checkout/offers` with `shop`, `block_id`, `subtotal`, `line_items` (so RuleEngine can run cart-based rules).
- **Thank you**: Sends `block_id` in query to `/api/thankyou/blocks` – if set, returns only that block.
- **Post-purchase**: Sends `block_id` in body to `/api/post-purchase/should-render` – if set, uses the matching funnel block.

## API (summary)

- `POST /api/checkout/offers`: body `{ shop, block_id?, subtotal?, line_items? }`. If `block_id` is valid – returns block config (offers + ui or progress_bar or blocks); otherwise fallback to Placement.
- `GET /api/thankyou/blocks?shop=...&block_id=...`: If `block_id` – returns `blocks: [{ id, type, config }]` for that block; otherwise legacy (Placement + ThankYouBlock).
- `POST /api/post-purchase/should-render`: body includes `block_id`. If set and block exists – returns offers + funnel from block; otherwise fallback to Placement.

## Migration

- **blocks** table (migration `2026_02_11_000002_create_blocks_table`).
- Pivot table **block_offers** (migration `2026_02_12_000001_create_block_offers_table`): links widget ↔ offers with `sort_order`. API uses `Block::getOfferIds()` which returns offer IDs from pivot first (in order), then from `config.offer_ids` if none.
- Migration `2026_02_11_000003_migrate_thank_you_blocks_to_blocks` copies records from `thank_you_blocks` to `blocks` (surface=thank_you). `thank_you_blocks` remains as Legacy.
