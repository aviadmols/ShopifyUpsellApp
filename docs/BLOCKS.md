# Blocks / Widgets – ארכיטקטורת בלוקים (Checkout / Thank you / Post-purchase)

## סקירה

ניהול תצוגה ב-Checkout, Thank you ו-Post-purchase מתבצע דרך **Widgets** (לשעבר Blocks) באדמין. לכל widget יש **מזהה (ID)**. ב-Shopify, בכל מופע של App block (בעורך Checkout או Thank you) ניתן להזין **Block ID** – כך אותו extension יכול להציג בלוקים שונים לפי מיקום.

## אדמין (Widgets)

- **Widgets** (תפריט Widgets): יצירה ועריכה של כל סוגי ה-widgets.
  - **Surface**: `checkout` | `thank_you` | `post_purchase`
  - **Type**: לפי surface – למשל `upsell`, `progress_bar`, `content_icon_features`, `content_banner`, `content_rich_text`, `content_button`, `content_product_card`, `post_purchase_funnel`
  - **Rule** (אופציונלי): חוק ברמת widget – מתי להציג (UTM, סל, מוצרים וכו').
  - **Offers (inline)**: ב-widgetים מסוג Upsell ו-Post-purchase funnel ניתן לנהל מוצרים/הצעות ישירות בתוך הטופס (סעיף "Offers (manage products & rules)") – כולל חוק per-offer. הסדר בקופסה קובע את סדר ההצגה. נתונים נשמרים ב-tabel `block_offers` (pivot) ו-`offers`.
  - **Singleton**: widget מסוג `post_purchase_funnel` ניתן ליצירה **פעם אחת בלבד** לכל חנות; ניסיון ליצור שני ייכשל בולידציה.
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
- טבלת pivot **block_offers** (מיגרציה `2026_02_12_000001_create_block_offers_table`): קישור widget ↔ offers עם `sort_order`. ה-API משתמש ב-`Block::getOfferIds()` שמחזיר קודם את ה-offer IDs מ-pivot (לפי סדר), ורק אם אין – מ-`config.offer_ids`.
- מיגרציה `2026_02_11_000003_migrate_thank_you_blocks_to_blocks` מעתיקה רשומות מ-`thank_you_blocks` ל-`blocks` (surface=thank_you). טבלת `thank_you_blocks` נשארת כ-Legacy.
