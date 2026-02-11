# Checkout Upsell + Thank You Blocks – Why there is no indicator and where to find logs

## 0. Checkout extension permissions

In Shopify, the scope `write_checkout_ui_extensions` is **not valid** as optional_scope (returns Validation error on deploy). Checkout UI extensions are controlled via the app extensions, not via scopes – so there is no need to add this scope. The reason the block may not appear is usually **adding the block in the Checkout editor** or the version not being Released.

---

## 0. Console errors in Checkout (channel / prototype)

If the Console shows:

- **`ExtensionUsageError: Cannot read properties of undefined (reading 'channel')`**
- **`ExtensionUsageError: Cannot read properties of undefined (reading 'prototype')`** (sometimes with `setupOnce` / `_setupIntegrations` – similar to Sentry)

Extensions run in a **WebWorker** with a limited environment. These errors occur when code (e.g. React scheduler or monitoring library) assumes `MessageChannel` / `window` / or other objects that do not exist in the Worker.

**Note:** Shopify requires Checkout UI versions 2025-04 or later (2024-01 is no longer valid). We use **2025-04**. If channel/prototype errors persist after deploy, a script on the Checkout page (Sentry/analytics) may be involved – try temporarily disabling apps that inject scripts into Checkout.

**401 on `private_access_tokens`:** That is an internal Shopify request (not from your app). A 401 there can be due to store/theme settings; it does not necessarily prevent the extension from running after fixing the channel/prototype issue.

---

## 1. Why is there nothing in Webhooks?

The link you opened is **Webhook logs**:
`https://dev.shopify.com/dashboard/.../logs/webhooks`

- **Webhooks** = Shopify sends events **to your app** (order created, app uninstalled, etc.).
- **Checkout UI extension** = The code runs **in the customer's browser** at Checkout, and **the extension** calls **your API** (`/api/checkout/offers`). The request is **POST** with JSON body: `shop`, `block_id` (optional), `subtotal`, `line_items` – so rules by cart/amount can run.

So:
- If the extension does not run or does not call the API – **nothing will appear** in Webhook logs.
- Webhook logs are only relevant when Shopify sends an HTTP request to your app (e.g. after order creation).

**Where you do see Checkout extension activity:**

| Place | What you see |
|-------|--------------|
| **Railway (Laravel logs)** | When the browser calls `/api/checkout/offers` – HTTP request and whatever the server logs. |
| **Browser → Network** | On the Checkout page: open DevTools → Network, search for `checkout/offers` or your Railway URL. If there is no request – the extension never reached fetch (did not run or crashed before). |
| **Browser → Console** | If there is `ExtensionUsageError` or other errors – the extension crashed before rendering. |

---

## 2. Checklist – Why the block does not appear in Checkout

### a. Block not added in Checkout Customizer

The extension must be **added as an App block** in the Checkout editor:

1. Store admin → **Settings** → **Checkout** (or **Online Store** → **Themes** → **Customize** and then select Checkout if available).
2. In the Checkout editor – search for **"App block"** / **"Add block"**.
3. Select your app (Zyg Upsell / Checkout Upsell) and add the block to the desired area.
4. **Widget ID**: In each block instance you can enter **Widget ID** (from Admin → Widgets). Each instance can show a different widget: Upsell, Progress bar, or Content (icons, banner, button, etc.). If no ID is entered – Legacy Placement is used.

If the block is not added – **the extension will not be shown** in Checkout, even if deploy is correct.

### b. Store not on Plus (or plan limit)

Block-type targets (`purchase.checkout.block.render`) work on **non-Plus** stores too. Only certain extension targets (for specific Checkout steps) require Plus. If you still do not see "Add block" / App block – it may be a plan limit or different UI.

### c. App version not Released

After `shopify app deploy` you must in Partner Dashboard:

- **Apps** → Your app → **App version** / **Releases**
- Ensure the latest version is **Released** (not just Draft).

If the version is not Released, the store may keep showing an old version or nothing.

### d. Extension secret not set (only for indicator / logic)

Without **Extension secret** in Block settings our block shows "Not configured" but **should still show** BUILD_ID and status. If **nothing** appears on screen – the cause is likely one of the items above (block not added / version / plan).

### e. Blocks and block_id (new setup)

- **Blocks** = Managing blocks by ID in Admin (Blocks → create blocks for Checkout / Thank you / Post-purchase). Each block has a **number (ID)**.
- **In Checkout**: In the block settings in the Checkout editor you can enter **Block ID** – the ID of the block to show in that instance. If not entered – **Legacy** Placement is used.
- If you enter a Block ID that does not exist or belongs to another store – the server returns fallback to Placement or an empty array. In Network check that the request to `/api/checkout/offers` is **POST** and the body includes `shop` and (optionally) `block_id`.

---

## 3. Recommended order of actions

1. **Ensure the App block is added** in Checkout Customizer (section 2a).
2. **Check in the browser** on a real Checkout page:
   - **Console**: Any `ExtensionUsageError` or red errors.
   - **Network**: Is there a request to `/api/checkout/offers` (or your Railway URL). If not – the extension never reached fetch.
3. **On Railway**: If there is a request to `/api/checkout/offers` – logs will appear there, not in Shopify Webhooks.
4. If you have **Plus**, ensure the version is **Released** in Partner Dashboard.

---

## 4. Minimal version for testing

If after the full checklist you still see nothing, you can deploy a **minimal block** that only shows text (no fetch, no settings):

- If you **do** see the text – the issue is in our code (hooks/fetch/settings).
- If you **do not** see it – the issue is placement/plan/version (sections 2a–2c).

**Using the minimal version:** In `extensions/checkout-upsell/shopify.extension.toml` change the line to point to the minimal entry, then run `shopify app deploy`. If after deploy (and Release) you see "Checkout Upsell (minimal)" and BUILD_ID in Checkout – the extension runs and the issue is in the full code. Then switch back to the main module.

---

## 5. Blocked by another app?

- **App blocks** in Checkout generally **do not block** each other – each app shows its block in the configured place. If the Zyg Upsell block **is added** in the Checkout editor, another app should not hide it.
- **Checkout rules / Validation apps** – there are reports that when **two apps** change Checkout rules (validation, shipping), sometimes **only one works**. That does not hide visual blocks, but if you have such an app it may be worth checking.
- **What to check:**
  1. **Checkout editor** – Settings → Checkout → Customize. Ensure "Zyg Upsell" / "Checkout Upsell" **appears** in the block list and is **not disabled** or removed.
  2. **Checkout apps** – In Settings → Apps see which apps affect Checkout. If in doubt, **temporarily disable** another Checkout app, refresh Checkout, and see if the Zyg Upsell block appears.
  3. **Block position** – The block may be added but **below the fold** or in an area you do not scroll to. In the editor check which section it is in and try moving it up.
