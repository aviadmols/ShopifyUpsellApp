# איך להציג מוצר כ-Upsell (Capability Matrix מלא)

כדי שמוצר יופיע כ-upsell צריך: (1) להגדיר **Offer Builder** באדמין, (2) להפעיל את ה-extensions ב-Shopify ולוודא שיש להם settings תקינים.

## מטריצת יכולות לפי מיקום הצגה

| מיקום | Target של Shopify | מה ניתן להגדיר באפליקציה | מה API מחזיר |
|---|---|---|---|
| **Checkout** | `purchase.checkout.block.render` | **Placements → Checkout:** `offer_ids`, `max_offers`, `priority`, `display_mode` (stacked/single), `require_expanded`. **Checkout UI:** `section_heading`, `title_size`, `title_appearance`, `show_price`, `show_description`, `image_aspect_ratio`, `image_fit`, `image_corner_radius`, `button_kind`, `button_appearance`, `card_spacing`, `divider_between_cards`. (Display mode נקבע רק ב-Placement, לא בעריכת Offer.) | רשימת offers עם `variant_id`, `title`, `description`, `discount`, `image_url`, `price`; אובייקט `ui` עם כל ההגדרות; `display_mode`. |
| **Post-purchase** | `checkout_post_purchase` / `purchase.post_purchase.render` | `offer_ids`, `max_offers`, `cooldown_hours`, `allow_reoffer`, `show_timer`, `timer_seconds` | should-render + accept changeset (add line + optional discount) |
| **Thank-you** | `purchase.thank-you.block.render` | `block_ids`, auto product-card per offer (`title/body/button_url/sort_order`) | רשימת blocks מסוג `banner/text/button/product_card` |

> הערה: השדות נפתחים דינמית ב-Offer Builder לפי המיקום שנבחר.

---

## שלב 1: באדמין Laravel (Filament)

### 1.1 יצירת Offer (המוצר ל-upsell)

1. היכנס לאדמין: `https://shopifyupsellapp-production.up.railway.app/admin`
2. תפריט **Upsell** → **Offers** → **New Offer**
3. מלא:
   - **Shop:** בחר את החנות (למשל `millsdailypacks.myshopify.com`)
   - **Title:** כותרת להצגה (למשל "הוסף לקנייה")
   - **Description:** תיאור קצר (אופציונלי)
   - **Product variant ID:** **מזהה ה-Variant** של המוצר ב-Shopify (מספר, למשל `41234567890123`).  
     איפה מוצאים? ב-Shopify Admin → Products → בוחרים מוצר → לוחצים על Variant → ה-ID מופיע ב-URL או ב-API.
   - **Discount type:** none / percentage / fixed
   - **Discount value:** אם בחרת percentage או fixed
   - **Image URL:** קישור לתמונה (אופציונלי)
   - **Rule:** אופציונלי – אם יש Rule, ה-offer יוצג רק כשהתנאים מתקיימים
4. **Save** – שים לב ל-**ID** של ה-Offer (למשל 1).

### 1.2 הגדרת Placement ל-Checkout

1. **Upsell** → **Placements** → **New Placement** (או ערוך Placement קיים של אותה חנות)
2. מלא:
   - **Shop:** אותה חנות (למשל `millsdailypacks.myshopify.com`)
   - **Placement type:** **checkout**
   - **Checkout config:**
     - **Offer IDs (comma separated):** `1` או `1,2,3`
     - **Max offers:** `3`
     - **Priority:** `100`
     - **Display mode:** **Stacked cards** (או Single card – קובע איך הבלוק מציג את האופטימיזציות)
     - **Require expanded:** כבוי/פתוח
   - **Checkout UI** (איך הבלוק נראה ב-checkout):
     - **Section heading:** כותרת האזור (ברירת מחדל: "Add to your order")
     - **Title text size:** small / medium / large / extraLarge
     - **Title appearance:** default, accent, subdued, info, success, warning, critical
     - **Show price:** הצגת מחיר מתחת לכותרת
     - **Show description:** הצגת תיאור
     - **Image aspect ratio:** Auto, 1:1, 5:4, 3:2, 4:3
     - **Image fit:** cover / contain / fill
     - **Image corner radius:** none / small / base / large
     - **Button kind:** primary / secondary / plain
     - **Button appearance:** default / monochrome / critical
     - **Card spacing:** tight / loose / extra loose
     - **Divider between cards:** מפריד בין כרטיסים
3. **Save**

**חשוב:** Display mode (Stacked vs Single) נקבע **רק** כאן ב-Placement. שמירת אופר לא משנה אותו.

עכשיו ה-API יחזיר את ה-offers עם אובייקט `ui` והבלוק יציג לפי ההגדרות.

---

## שלב 2: מה לעשות ב-Shopify (Checkout Extension)

ה-upsell ב-checkout מוצג על ידי **Checkout UI Extension**. יש שני חלקים: (א) להעלות את ה-Extension ולמלא את ההגדרות שלו, (ב) להוסיף את הבלוק בדף ה-Checkout.

---

### 2.1 להעלות את ה-Extension (פעם אחת, מהמחשב)

1. **התקנת Shopify CLI** (אם עדיין אין):
   ```bash
   npm install -g @shopify/cli @shopify/app
   ```
2. **מחוברים לאפליקציה שיצרת ב-Partners:**
   ```bash
   cd "c:\Users\user\Desktop\Projects\ShopifyUpsellApp"
   shopify auth login --store millsdailypacks.myshopify.com
   shopify app config link
   ```
   ב-`config link` בוחרים את האפליקציה שיצרת ב-Dev Dashboard (Zyg Upsell או השם שנתת).
3. **העלאת ה-Extension:**
   ```bash
   shopify app deploy
   ```
   אחרי ההעלאה ה-Extension "Checkout Upsell" יהיה מחובר לאפליקציה ב-Partners.

---

### 2.2 איפה ב-Shopify למלא את ההגדרות של ה-Extension

1. היכנס ל־**Shopify Partners**: [partners.shopify.com](https://partners.shopify.com)
2. **Apps** → בוחרים את **האפליקציה** (זו שיצרת ב-Dev Dashboard).
3. בתפריט הצד: **Configuration** או **App setup** → גלול ל-**Extensions** (או **Checkout UI extensions**).
4. לוחצים על **Checkout Upsell** (או על שם ה-Extension שהעלית).
5. נפתחות **ההגדרות (Settings)** של ה-Extension – שם יופיעו שלושה שדות. מלא:

| בשדה ב-Shopify (Name) | מה להזין (Value) |
|------------------------|------------------|
| **API URL**     | `https://shopifyupsellapp-production.up.railway.app` |
| **Extension secret**   | **בדיוק** אותו ערך שהגדרת ב-Railway ב-**CHECKOUT_EXTENSION_SECRET** |
| **Shop domain**        | `millsdailypacks.myshopify.com` |

6. **Save** / **Update**.

**חשוב:** ב-Railway (Variables) חייב להיות משתנה **CHECKOUT_EXTENSION_SECRET** עם ערך סודי כלשהו (למשל מחרוזת אקראית). **אותו ערך** חייב להיות בשדה **Extension secret** ב-Shopify.

---

### 2.3 איפה ב-Shopify להוסיף את הבלוק בדף ה-Checkout

1. היכנס ל-**ניהול החנות** (לא ל-Partners): `millsdailypacks.myshopify.com/admin`
2. **Settings** (הגדרות) → **Checkout**.
3. ליד "Checkout customization" או "Customize checkout" → **Customize** (להתאמה אישית).
4. נפתח **Checkout editor** (עורך דף ה-Checkout).
5. בצד שמאל או בתפריט הבלוקים: **Add block** / **הוסף בלוק** → תחת **Apps** (אפליקציות) בוחרים את **האפליקציה שלך** (Zyg Upsell / השם של האפליקציה).
6. מוסיפים את הבלוק של **Checkout Upsell** (או "Add to your order") לגף וממקמים אותו איפה שרוצים (לפני סיכום, אחרי פריטים וכו').
7. **Save** (שמירה).

מעכשיו, ב-checkout אמיתי (או ב-test checkout) יופיעו ה-offers שהגדרת ב-Placement מסוג checkout.

---

## סיכום זרימה

1. **Offer** = מוצר אחד (variant + כותרת + הנחה וכו') – קשור לחנות.
2. **Placement** עם `placement_type = checkout` + **config**: `offer_ids` (1 או 1,2,3) ו-`max_offers` – קובע **אילו** offers יוצגו ב-checkout.
3. **Checkout Extension** קורא ל-`/api/checkout/offers?shop=...` ומציג את ה-offers; הלקוח לוחץ "Add to order" והמוצר מתווסף לעגלה.
4. **Post-purchase Extension** קורא ל-`/api/post-purchase/should-render` ואז `accept`.
5. **Thank-you Extension** קורא ל-`/api/thankyou/blocks?shop=...`.

אם ה-offers לא מופיעים – בדוק: (א) Placement עם placement_type checkout ו-config offer_ids נכון, (ב) CHECKOUT_EXTENSION_SECRET זהה ב-Railway וב-Shopify, (ג) הבלוק של האפליקציה נוסף ב-Checkout Editor.
