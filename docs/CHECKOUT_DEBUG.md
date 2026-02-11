# Checkout Upsell + Thank You Blocks – למה אין חיווי ואיפה לוגים

## 0. הרשאות Checkout extensions

ב-Shopify, ה-scope `write_checkout_ui_extensions` **לא תקף** כ-optional_scope (מחזיר Validation error ב-deploy). Checkout UI extensions נשלטים דרך ה-extensions ב-app, לא דרך scopes – אז אין צורך להוסיף scope זה. הבעיה למה הבלוק לא מופיע היא כנראה **הוספת הבלוק בעורך ה-Checkout** או גרסה לא Released.

---

## 0. שגיאות Console ב-Checkout (channel / prototype)

אם ב-Console מופיעות:

- **`ExtensionUsageError: Cannot read properties of undefined (reading 'channel')`**
- **`ExtensionUsageError: Cannot read properties of undefined (reading 'prototype')`** (לעיתים עם `setupOnce` / `_setupIntegrations` – מזכיר Sentry)

ה-extensions רצים ב-**WebWorker** עם סביבה מוגבלת. שגיאות כאלה נגרמות כשקוד (למשל React scheduler או ספריית ניטור) מניח שיש `MessageChannel` / `window` / אובייקטים שלא קיימים ב-Worker.

**הערה:** Shopify דורש ל-Checkout UI את הגרסאות 2025-04 ומעלה (2024-01 לא תקף). השתמשנו ב-**2025-04**. אם השגיאות channel/prototype ממשיכות אחרי deploy, ייתכן שסקריפט בדף ה-Checkout (Sentry/אנליטיקס) משפיע – לנסות כיבוי זמני של אפליקציות שמזריקות סקריפטים ל-Checkout.

**401 על `private_access_tokens`:** זה בקשת Shopify פנימית (לא מהאפליקציה שלך). 401 שם יכול להיות מהגדרות החנות/תמאור; לא מונע בהכרח מה-extension לרוץ אחרי תיקון ה-channel/prototype.

---

## 1. למה ב-Webhooks אין כלום?

הלינק שפתחת הוא **לוגים של Webhooks**:
`https://dev.shopify.com/dashboard/.../logs/webhooks`

- **Webhooks** = Shopify שולח אירועים **לאפליקציה** (order created, app uninstalled וכו').
- **Checkout UI extension** = הקוד רץ **בדפדפן של הלקוח** ב-Checkout, ו**ה extension** קורא ל-**ה-API שלך** (`/api/checkout/offers`). הבקשה היא **POST** עם גוף JSON: `shop`, `block_id` (אופציונלי), `subtotal`, `line_items` – כדי שחוקים (rules) לפי סל/סכום יעבדו.

לכן:
- אם ה-extension לא רץ או לא קורא ל-API – **לא יופיע שום דבר** בלוגי Webhooks.
- לוגי Webhooks רלוונטיים רק כש-Shopify שולח בקשת HTTP לאפליקציה (למשל אחרי יצירת הזמנה).

**איפה כן רואים פעילות של Checkout extension:**

| מקום | מה רואים |
|------|----------|
| **Railway (לוגי Laravel)** | כשהדפדפן קורא ל-`/api/checkout/offers` – בקשת HTTP וכל מה שהשרת כותב ללוג. |
| **דפדפן → Network** | בדף Checkout: פתח DevTools → Network, חפש `checkout/offers` או את ה-URL של Railway. אם אין בקשה – ה-extension לא הגיע ל-fetch (לא רץ או קרס לפני). |
| **דפדפן → Console** | אם יש `ExtensionUsageError` או שגיאות אחרות – ה-extension קורס לפני שהוא מציג משהו. |

---

## 2. צ'קליסט – למה הבלוק לא מופיע ב-Checkout

### א. הבלוק לא נוסף ב-Checkout Customizer

ה-extension חייב להיות **נוסף כ-App block** בתוך עורך ה-Checkout:

1. ניהול החנות → **Settings** → **Checkout** (או **Online Store** → **Themes** → **Customize** ואז בחירת Checkout אם יש).
2. בעורך ה-Checkout – לחפש **"App block"** / **"Add block"**.
3. לבחור את האפליקציה (Zyg Upsell / Checkout Upsell) ולהוסיף את הבלוק לאזור הרצוי.
4. **Widget ID**: בכל מופע של הבלוק אפשר להזין **Widget ID** (מהאדמין → Widgets). כל מופע יכול להציג widget אחר: Upsell, Progress bar, או Content (אייקונים, באנר, כפתור וכו'). אם לא מזינים ID – משתמשים ב-Legacy Placement.

אם הבלוק לא נוסף – **ה-extension לא יוצג** ב-Checkout, גם אם ה-deploy תקין.

### ב. חנות לא ב-Plus (או הגבלת תכנית)

בלוקים מסוג **block** (`purchase.checkout.block.render`) עובדים גם בחנויות **לא-Plus**. רק extension targets ספציפיים (לשלבים מסוימים ב-Checkout) דורשים Plus. אם בכל זאת אין לך אפשרות "Add block" / App block – ייתכן מגבלת תכנית או ממשק שונה.

### ג. גרסת האפליקציה לא Released

אחרי `shopify app deploy` צריך ב-Partner Dashboard:

- **Apps** → האפליקציה → **App version** / **Releases**
- לוודא שהגרסה האחרונה במצב **Released** (ולא רק Draft).

אם הגרסה לא Released, החנות עלולה להמשיך להציג גרסה ישנה או כלום.

### ד. Extension secret לא מוגדר (רק לחיווי / לוגיקה)

בלי **Extension secret** ב-Block settings הבלוק אצלנו מציג "Not configured" אבל **עדיין אמור להציג** את BUILD_ID והסטטוס. אם אין **כלום** על המסך – הסיבה היא כנראה אחד הסעיפים למעלה (בלוק לא נוסף / גרסה / תכנית).

### ה. Blocks ו-block_id (הגדרה חדשה)

- **Blocks** = ניהול בלוקים לפי ID באדמין (Blocks → יצירת בלוקים ל-Checkout / Thank you / Post-purchase). כל בלוק מקבל **מספר (ID)**.
- **ב-Checkout**: בהגדרות הבלוק (Block settings) בעורך ה-Checkout אפשר להזין **Block ID** – המספר של הבלוק שיוצג במופע הזה. אם לא מזינים – משתמשים ב-**Placement** הישן (Legacy).
- אם מזינים Block ID שלא קיים או שייך לחנות אחרת – השרת מחזיר fallback ל-Placement או מערך ריק. ב-Network תבדוק שהבקשה ל-`/api/checkout/offers` היא **POST** והגוף כולל `shop` ו-(אופציונלי) `block_id`.

---

## 3. סדר פעולות מומלץ

1. **לוודא שה-App block נוסף** ב-Checkout Customizer (סעיף 2א).
2. **לבדוק בדפדפן** בדף Checkout אמיתי:
   - **Console**: האם יש `ExtensionUsageError` או שגיאות אדומות.
   - **Network**: האם יש בקשה ל-`/api/checkout/offers` (או ל-URL של Railway). אם אין – ה-extension לא מגיע ל-fetch.
3. **ב-Railway**: אם יש בקשה ל-`/api/checkout/offers` – הלוגים יופיעו שם, לא ב-Webhooks של Shopify.
4. אם יש **Plus** וודא שהגרסה **Released** ב-Partner Dashboard.

---

## 4. גרסה מינימלית לבדיקה

אם אחרי כל הצ'קליסט עדיין לא רואים כלום, אפשר לפרוס **בלוק מינימלי** שמציג רק טקסט (ללא fetch, ללא settings):

- אם **כן** רואים את הטקסט – הבעיה בקוד שלנו (hooks/fetch/settings).
- אם **לא** רואים – הבעיה במיקום/תכנית/גרסה (סעיפים 2א–2ג).

**שימוש בגרסה המינימלית:** ב-`extensions/checkout-upsell/shopify.extension.toml` שנה את השורה:
`module = "./src/Checkout.minimal.jsx"`
ואז הרץ `shopify app deploy --config shopify.app.zyg-upsell.toml`. אם אחרי ה-deploy (ו-Release) אתה רואה "Checkout Upsell (minimal)" ו-BUILD_ID ב-Checkout – ה-extension רץ והבעיה בקוד המלא. אחר כך החזר ל-`module = "./src/Checkout.jsx"`.

---

## 5. חסימה מאפליקציה אחרת?

- **בלוקים של אפליקציות** (App blocks) ב-Checkout בדרך כלל **לא חוסמים** אחד את השני – כל אפליקציה מציגה את הבלוק שלה במקום שהוגדר. אם הבלוק של Zyg Upsell **נוסף** בעורך ה-Checkout, אפליקציה אחרת לא אמורה להסתיר אותו.
- **אפליקציות "Checkout rules" / Validation** – יש דיווחים שכשיש **שתי אפליקציות** שמשנות כללי Checkout (validation, shipping rules), לפעמים **רק אחת עובדת**. זה לא מסתיר בלוקים ויזואליים, אבל אם יש אפליקציה כזו אולי כדאי לבדוק.
- **מה לבדוק בפועל:**
  1. **Checkout editor** – Settings → Checkout → Customize (או עורך ה-Checkout). וודא שברשימת הבלוקים **מופיע** "Zyg Upsell" / "Checkout Upsell" והוא **לא כבוי** ולא הוסר.
  2. **אפליקציות Checkout** – ב-Settings → Apps (או Apps and sales channels) ראה אילו אפליקציות משפיעות על Checkout. אם יש ספק, אפשר **לכבות זמנית** אפליקציית Checkout אחרת, לרענן Checkout, ולבדוק אם הבלוק של Zyg Upsell מופיע.
  3. **מיקום הבלוק** – אולי הבלוק נוסף אבל **מתחת לקפל** או באזור שלא גלול אליו. בעורך בדוק באיזה section הוא (למשל Order summary, Contact וכו') ונסה להעביר אותו למעלה.
