# Checkout Upsell – הרשאות ואפליקציה

## סיכום בדיקה (תאריך: 2026-02)

### הרשאות האפליקציה (shopify.app.zyg-upsell.toml)

- **scopes (חובה):** ריק `""` – אין הרשאות חובה.
- **optional_scopes:** `read_orders`, `write_orders`, `read_products`, `write_products` – המוכר יכול לאשר בהתקנה.

### הרשאות ה-Extension (checkout-upsell)

- **target:** `purchase.checkout.block.render` – בלוק ב-Checkout.
- **capabilities:**
  - `network_access = true` – מאפשר fetch ל-API (למשל `/api/checkout/offers`).
  - `api_access = true` – גישה ל-API של ה-Checkout (למשל הוספה לעגלה).

### האם זה מספיק?

**כן.**  
הרצת ה-Checkout UI extension אינה תלויה ב-OAuth scopes של האפליקציה. הבלוק נטען על ידי Shopify כשהבלוק ממוקם ב-Checkout, וה-capabilities ב-extension (network_access, api_access) מספיקים כדי:

1. להציג את הבלוק.
2. לשלוח בקשות ל-backend (`/api/checkout/offers`).
3. להשתמש ב-`applyCartLinesChange` כדי להוסיף פריטים לעגלה.

ה-backend מאמת רק את `X-Extension-Secret` ופרמטר `shop` – לא נדרשים scopes נוספים כדי שה-Checkout block יעבוד.  
אם ה-backend משתמש ב-Admin API (למשל לקריאת מוצרים/הזמנות), אז ה-optional_scopes רלוונטיים לאותן פעולות בצד השרת, לא לרינדור הבלוק ב-Checkout.

### Blocks ו-block_id

- ה-Checkout extension שולח **POST** ל-`/api/checkout/offers` עם גוף: `shop`, `block_id` (אופציונלי, מהגדרות הבלוק), `subtotal`, `line_items`. כך חוקים (rules) לפי סכום סל/מוצרים יכולים לרוץ.
- **Block ID** (בהגדרות הבלוק בעורך Checkout) = מזהה בלוק מהאדמין (Blocks). אם מוגדר – השרת מחזיר את תצורת הבלוק הזה (Upsell / Progress bar / תוכן); אחרת – fallback ל-Placement (Legacy).
