# כפתורי שידרוג / פעולות מתחת ל-Line Item – בדיקה ותכנון

## 1. האם זה אפשרי? – כן

- **יעד:** `purchase.checkout.cart-line-item.render-after` – רנדור תוכן מתחת לפרטי המוצר ב-Order summary.
- **האפליקציה כבר משתמשת** ביעד הזה ב-extension `checkout-cart-line` (ו־CartLineItem ב-checkout-upsell).
- **שינויי עגלה:** `applyCartLinesChange` זמין ביעד הזה ומאפשר:
  - **removeCartLine** – הסרת שורה (או חלק ממנה)
  - **addCartLine** – הוספת וריאנט (עם quantity, sellingPlanId, attributes)
  - **updateCartLine** – עדכון שורה: quantity, merchandiseId (החלפת וריאנט), sellingPlanId (מנוי), attributes

### מגבלות (Shopify)

- **Checkout רגיל:** עובד ב-Information / Shipping / Payment + Order summary (לרוב **Shopify Plus** בלבד ב-production).
- **Accelerated checkout (Apple Pay / Google Pay):** `applyCartLinesChange` עלול להיכשל – חובה לבדוק `instructions.lines.canAddCartLine` / `canRemoveCartLine` / `canUpdateCartLine` לפני קריאה.
- **Cart Transform / Bundle components:** במקרים מסוימים הסרת שורת באנדל לא מתנהגת כמו צפוי.
- **IDs של cart lines** לא יציבים אחרי שינויים – יש לעבוד תמיד עם ה־line המעודכן לפני פעולה נוספת.

---

## 2. פעולות אפשריות בממשק (API של Shopify)

| פעולה | סוג ב-API | פרמטרים | תיאור |
|--------|-----------|----------|--------|
| **החלף מוצר בוריאנט אחר** | `updateCartLine` עם `merchandiseId` או `removeCartLine` + `addCartLine` | variant GID | החלפת הוריאנט בשורה הנוכחית (או הסרה + הוספת באנדל) |
| **הוסף מוצר לעגלה** | `addCartLine` | variant GID, quantity | הוספת וריאנט כשורה חדשה (למשל "הוסף לבאנדל") |
| **הסר שורה** | `removeCartLine` | line id, quantity | הסרת השורה (כולה או כמות חלקית) |
| **עדכן כמות** | `updateCartLine` עם `quantity` | quantity | שינוי כמות בשורה הנוכחית |
| **שדרג למנוי** | `updateCartLine` עם `sellingPlanId` | selling plan GID | הוספת מנוי לוריאנט (כבר קיים כ־"Upgrade to subscription") |
| **הסר מנוי (פעם אחת)** | `updateCartLine` עם `sellingPlanId: null` | — | המרה חזרה לרכישה חד-פעמית |

באדמין נגדיר כל "כפתור" לפי **סוג פעולה** + **פרמטרים** (וריאנט יעד, כמות, מנוי וכו') + **חוקיות הצגה** (לפי מוצר/אוסף/תגיות/SKU וכו').

---

## 3. תכנון במערכת הניהול

### 3.1 ישות: "פעולות לשורת מוצר" (Cart Line Actions)

- **קישור:** שייכות ל-**Checkout Experience** (או ל-Shop כ-default).
- **כל רשומה = כפתור אחד** שמוצג מתחת לשורת מוצר כשהחוקיות מתקיימת.

שדות מוצעים:

| שדה | סוג | תיאור |
|-----|-----|--------|
| `checkout_experience_id` | FK | ניהול לפי חוויית checkout |
| `name` | string | שם פנימי (לאדמין) |
| `label` | string | טקסט הכפתור (למשל "שדרג לבאנדל") |
| `message` | text, אופציונלי | הודעה מעל/מתחת לכפתור |
| `action_type` | enum | ראה טבלה למטה |
| `target_variant_gid` | string, אופציונלי | עבור replace/add – וריאנט יעד |
| `target_quantity` | int | עבור add/update – כמות (ברירת מחדל 1) |
| `target_selling_plan_id` | string, אופציונלי | עבור switch_to_subscription |
| `rule_mode` | all / include / exclude | מתי להציג (כמו quantity/subscription) |
| `include_product_ids` | JSON | מוצרים להכללה |
| `exclude_product_ids` | JSON | מוצרים exclusion |
| `include_collection_ids` | JSON | אוספים להכללה |
| `exclude_collection_ids` | JSON | אוספים exclusion |
| `include_tags` | JSON | תגיות להכללה |
| `exclude_tags` | JSON | תגיות exclusion |
| `include_vendors` | JSON | ספקים |
| `exclude_vendors` | JSON | ספקים exclusion |
| `include_product_types` | JSON | סוגי מוצר |
| `exclude_product_types` | JSON | סוגי מוצר exclusion |
| `require_subscription_state` | enum | all / with_subscription / without_subscription |
| `min_subtotal` / `max_subtotal` | decimal | מגבלות סל |
| `min_cart_items` / `max_cart_items` | int | מגבלות מספר פריטים |
| `sort_order` | int | סדר הצגת כפתורים |

### 3.2 ערכי `action_type`

| ערך | משמעות | שדות רלוונטיים |
|-----|--------|-----------------|
| `replace_with_variant` | החלף שורה בוריאנט אחר | `target_variant_gid` |
| `add_variant` | הוסף וריאנט לעגלה | `target_variant_gid`, `target_quantity` |
| `remove_line` | הסר את השורה | — |
| `update_quantity` | עדכן כמות לשורה | `target_quantity` |
| `switch_to_subscription` | שדרג למנוי | `target_selling_plan_id` (או בחירה מתוך מנויים לוריאנט הנוכחי) |
| `switch_to_one_time` | הסר מנוי (פעם אחת) | — |

### 3.4 אדמין

- **מקום:** או משאב נפרד "פעולות לשורת מוצר" (Cart Line Actions) עם פילטר לפי Checkout Experience, או **טאב/סקשן** בתוך עריכת Checkout Experience.
- **טופס:** לכל פעולה – בחירת סוג פעולה, שדות יעד (וריאנט/כמות/מנוי), וחוקיות (include/exclude) באותו סגנון של "Cart line rules" הקיים (Quantity / Subscription).
- **תצוגה מקדימה:** אופציונלי – טקסט שמסביר מה יקרה בלחיצה (למשל "יסיר שורה ויוסיף וריאנט X").

---

## 4. API ל-Extension

- **Endpoint קיים:** `POST /api/checkout/experience` – מחזיר כבר `show_quantity`, `show_subscription`, `cart_line_ui`.
- **הרחבה:** להוסיף במענה שדה `cart_line_actions`: מערך של אובייקטים, אחד לכל כפתור שמוצג לשורה הנוכחית:
  - `id`, `label`, `message`, `action_type`, `target_variant_gid`, `target_quantity`, `target_selling_plan_id`.
- **לוגיקה:** עבור ה־line הנוכחי (product_id, variant_id, metadata) + חוויית ה-checkout – להריץ את `CartLineRulesEvaluator` (או evaluator דומה) לכל פעולה, ולהחזיר רק פעולות שעברו את החוקיות.

---

## 5. Extension (React)

- **רנדור:** מתחת לכפתור/איזור הקיים (כמות / שדרוג מנוי) – לולאה על `cart_line_actions` ורנדור `Text` (message) + `Button` לכל פעולה.
- **לחיצה:** לפי `action_type`:
  - `replace_with_variant`: `updateCartLine({ id: line.id, merchandiseId: target_variant_gid })` או `removeCartLine` + `addCartLine`.
  - `add_variant`: `addCartLine({ merchandiseId, quantity })`.
  - `remove_line`: `removeCartLine({ id: line.id, quantity: line.quantity })`.
  - `update_quantity`: `updateCartLine({ id: line.id, quantity: target_quantity })`.
  - `switch_to_subscription`: `updateCartLine({ id: line.id, sellingPlanId: target_selling_plan_id })`.
  - `switch_to_one_time`: `updateCartLine({ id: line.id, sellingPlanId: null })`.
- **בדיקת instructions:** לפני כל `applyCartLinesChange` לבדוק `instructions.lines.canAddCartLine` / `canRemoveCartLine` / `canUpdateCartLine` (לפי סוג הפעולה) – ואם false, לא להציג את הכפתור או להציג הודעה מתאימה.

---

## 6. סיכום

- **אפשרי:** כן – היעד והפונקציה כבר בשימוש בפרויקט.
- **אדמין:** מקום אחד לניהול "כפתורי שידרוג" – כל כפתור = פעולה אחת (החלפה, הוספה, הסרה, עדכון כמות, מנוי/פעם אחת) + חוקיות הצגה מלאות.
- **פעולות:** כל מה ש־Shopify מאפשר דרך `applyCartLinesChange` – כיסינו בממשק הניהול והמסמך.

השלב הבא: מימוש טבלה + מודל, ממשק אדמין, הרחבת API וה-extension לפי הסעיפים למעלה.
