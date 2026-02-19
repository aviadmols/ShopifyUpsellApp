# איך לראות את ה־Upgrade Card ב-Checkout ב-Shopify

כדי שהמודול (Upgrade Card) יופיע בעמוד ה-Checkout של החנות, צריך **שלושה דברים**: להעלות את ה-extension, ליצור Widget באדמין, ולהוסיף את הבלוק בעורך ה-Checkout.

---

## 1. העלאת ה-Extension ל-Shopify (Deploy)

משורת הפקודה בתיקיית הפרויקט:

```bash
shopify app deploy
```

(דורש Shopify CLI מותקן ו־`shopify app config link` כבר מקושר לאפליקציה.)

אחרי ה-deploy:
- ב-**Shopify Partners** → האפליקציה → **Releases** – לוודא שה־version החדש במצב **Released** (לא רק Draft).

---

## 2. יצירת Widget (Block) באדמין של האפליקציה

1. היכנס ל-**אדמין של האפליקציה** (ה-URL של ה-Laravel, למשל Railway).
2. עבור ל-**Widgets** (או Blocks).
3. **צור Widget חדש**:
   - **Surface:** `Checkout`
   - **Type:** `Upgrade card` (או `checkout_upgrade_card`)
4. הגדר את התוכן (כותרת, mappings, cart-wide וכו') ושמור.
5. **העתק את ה־ID של ה-Widget** (מספר או UUID) – זה ה-**Widget ID** שתזין ב-Checkout.

---

## 3. הוספת הבלוק בעורך ה-Checkout ב-Shopify

1. ב-**ניהול החנות ב-Shopify** (לא ב-Partners):  
   **Settings** → **Checkout** (או **Checkout customization**).
2. לחץ **Customize** / **ערוך** כדי לפתוח את עורך ה-Checkout.
3. בעמוד העריכה:
   - חפש **"Add block"** / **"App block"** / **"בלוק אפליקציה"**.
   - בחר את **האפליקציה שלך** (למשל Zyg Upsell) ואת ה-extension **Zyg Upgrade Card**.
   - הוסף את הבלוק לאזור הרצוי (למשל מתחת לסיכום ההזמנה).
4. **בהגדרות הבלוק** (לחיצה על הבלוק שהוסף):
   - **Widget ID** (או Block ID): הדבק את ה־ID מהשלב 2.
   - **Extension secret**: אותו ערך כמו `CHECKOUT_EXTENSION_SECRET` בשרת.
   - **API URL** (אופציונלי): כתובת ה-backend (למשל `https://....railway.app`). אם ריק – האפליקציה תשתמש בברירת המחדל.
   - **Shop domain** (אופציונלי): אם צריך – דומיין החנות.

שמור את עורך ה-Checkout.

---

## 4. בדיקה

1. הוסף מוצר לעגלה בחנות ועבור ל-Checkout.
2. אם ההגדרות נכונות וה־Widget ID תואם ל-Widget עם upgrade mappings שתואמים לעגלה – תראה את כרטיס השדרוג (Upgrade Card) ב-Checkout.

**אם הבלוק לא מופיע:**
- וודא ש־**App version** ב-Partners במצב **Released**.
- וודא שה-**Widget ID** בהגדרות הבלוק תואם ל-Widget מסוג Upgrade card ב-**Surface = Checkout**.
- ב־Network בדפדפן (בעמוד Checkout) חפש קריאות ל־`/api/checkout/` – אם אין קריאה, ה-extension לא רץ או לא הגיע ל-fetch (למשל שגיאה ב-Console).
