# חיבור האפליקציה לחנות Shopify (Custom App / OAuth)

כשאתה נכנס ל־`/auth/install?shop=millsdailypacks.myshopify.com` האפליקציה מפנה אותך ל־Shopify לאישור (OAuth). כדי שזה יעבוד מקצה לקצה צריך להגדיר אפליקציה ב־Shopify ו־Variables ב־Railway.

---

## מטריצת מיקומים (לפי Shopify)

| מיקום | Target | דרישות |
|---|---|---|
| Checkout | `purchase.checkout.block.render` | checkout UI extension + network access + API URL/secret/shop_domain |
| Post-purchase | `checkout_post_purchase` / `purchase.post_purchase.render` | אישור post-purchase access + extension deploy |
| Thank you | `purchase.thank-you.block.render` | thank-you block extension + API URL/secret/shop_domain |

---

## 1. ליצור אפליקציה ב־Shopify

יש שתי דרכים (בחר אחת):

### א׳. אפליקציה מחנות אחת (Custom – מתוך האדמין של החנות)

1. היכנס ל־**ניהול החנות** (millsdailypacks.myshopify.com) → **Settings** → **Apps and sales channels**.
2. לחץ **Develop apps** → **Create an app** → **Create app manually**.
3. תן שם לאפליקציה (למשל "Zyg Upsell").
4. בטאב **Configuration** (או **App setup**):
   - **App URL:**  
     `https://shopifyupsellapp-production.up.railway.app`
   - **Allowed redirection URL(s):** (חובה בדיוק)  
     `https://shopifyupsellapp-production.up.railway.app/auth/callback`
5. שמור. תקבל **Client ID** (API key) ו־**Client secret** (API secret).

### ב׳. אפליקציה מ־Shopify Partners (לכמה חנויות)

1. היכנס ל־[partners.shopify.com](https://partners.shopify.com) → **Apps** → **Create app** → **Create app manually**.
2. **App URL:**  
   `https://shopifyupsellapp-production.up.railway.app`
3. **Allowed redirection URL(s):**  
   `https://shopifyupsellapp-production.up.railway.app/auth/callback`
4. **App setup** → הגדר Scopes לפי הצורך (למשל: `read_products`, `write_products`, `read_orders`, `write_orders`).
5. שמור. תקבל **Client ID** ו־**Client secret** מההגדרות.

---

## 2. להגדיר Variables ב־Railway

ב־Railway → הפרויקט → **Variables** הוסף (או עדכן):

| Name               | Value                                      |
|--------------------|--------------------------------------------|
| `SHOPIFY_API_KEY`  | ה־**Client ID** מהאפליקציה ב־Shopify      |
| `SHOPIFY_API_SECRET` | ה־**Client secret** מהאפליקציה ב־Shopify |
| `APP_URL`          | `https://shopifyupsellapp-production.up.railway.app` |

אופציונלי (אם רוצים scopes/גרסה אחרים):

- `SHOPIFY_SCOPES` = `read_products,write_products,read_orders,write_orders` (ברירת מחדל בפרויקט)
- `SHOPIFY_API_VERSION` = `2024-01` (ברירת מחדל)

אחרי עדכון Variables – **Redeploy** (פעם אחת).

---

## 3. לבדוק את החיבור

1. גלוש ל־  
   `https://shopifyupsellapp-production.up.railway.app/auth/install?shop=millsdailypacks.myshopify.com`
2. אמור להופיע מסך אישור של Shopify (להתקין את האפליקציה ולתת הרשאות).
3. אחרי אישור – Shopify מפנה ל־`/auth/callback` והאפליקציה שומרת את ה־token ומפנה ל־`/admin/shops`.

אם מקבלים "Invalid HMAC" או redirect לא עובד – לבדוק ש־**Allowed redirection URL(s)** ב־Shopify **בדיוק**:  
`https://shopifyupsellapp-production.up.railway.app/auth/callback`  
וש־`APP_URL` ב־Railway תואם (עם https).
