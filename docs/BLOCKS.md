# Blocks – ארכיטקטורת בלוקים (Checkout / Thank you / Post-purchase)

## סקירה

ניהול תצוגה ב-Checkout, Thank you ו-Post-purchase מתבצע דרך **Blocks** באדמין. לכל בלוק יש **מזהה (ID)**. ב-Shopify, בכל מופע של App block (בעורך Checkout או Thank you) ניתן להזין **Block ID** – כך אותו extension יכול להציג בלוקים שונים לפי מיקום.

## אדמין

- **Blocks** (תפריט Blocks): יצירה ועריכה של בלוקים.
  - **Surface**: `checkout` | `thank_you` | `post_purchase`
  - **Type**: לפי surface – למשל `upsell`, `progress_bar`, `content_icon_features`, `content_banner`, `content_rich_text`, `content_button`, `content_product_card`, `post_purchase_funnel`
  - **Rule** (אופציונלי): חוק ברמת בלוק – מתי להציג את הבלוק (UTM, סל, מוצרים וכו').
- **Placements** מסומן כ-**Legacy**: ממשיך לעבוד כ-fallback כשלא מוגדר `block_id` בהגדרות הבלוק.

## הרחבות (Extensions)

בכל extension יש בהגדרות שדה **Block ID (optional)**:

| Extension        | קובץ                             | שימוש |
|------------------|-----------------------------------|--------|
| Checkout Upsell  | `extensions/checkout-upsell/shopify.extension.toml`  | Block ID של בלוק surface=checkout (upsell / progress_bar / content_*). |
| Thank You Blocks | `extensions/thank-you-blocks/shopify.extension.toml` | Block ID של בלוק surface=thank_you. |
| Post-purchase    | `extensions/post-purchase/shopify.extension.toml`    | Block ID של בלוק surface=post_purchase, type=post_purchase_funnel. |

- **Checkout**: שולח **POST** ל-`/api/checkout/offers` עם `shop`, `block_id`, `subtotal`, `line_items` (כדי ש-RuleEngine יוכל להפעיל חוקים לפי סל).
- **Thank you**: שולח `block_id` ב-query ל-`/api/thankyou/blocks` – אם מוגדר, מחזירים רק את הבלוק הזה.
- **Post-purchase**: שולח `block_id` ב-body ל-`/api/post-purchase/should-render` – אם מוגדר, משתמשים בבלוק ה-funnel המתאים.

## API (תמצית)

- `POST /api/checkout/offers`: body `{ shop, block_id?, subtotal?, line_items? }`. אם `block_id` תקין – מחזיר תצורת הבלוק (offers + ui או progress_bar או blocks); אחרת fallback ל-Placement.
- `GET /api/thankyou/blocks?shop=...&block_id=...`: אם `block_id` – מחזיר `blocks: [{ id, type, config }]` עבור הבלוק הבודד; אחרת legacy (Placement + ThankYouBlock).
- `POST /api/post-purchase/should-render`: body כולל `block_id`. אם מוגדר ובלוק קיים – מחזיר offers + funnel מהבלוק; אחרת fallback ל-Placement.

## מיגרציה

- טבלה **blocks** (מיגרציה `2026_02_11_000002_create_blocks_table`).
- מיגרציה `2026_02_11_000003_migrate_thank_you_blocks_to_blocks` מעתיקה רשומות מ-`thank_you_blocks` ל-`blocks` (surface=thank_you). טבלת `thank_you_blocks` נשארת כ-Legacy.
