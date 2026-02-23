import {
  reactExtension,
  useSettings,
  useApi,
  useCheckoutToken,
  useSubtotalAmount,
  useApplyCartLinesChange,
  BlockStack,
  Text,
  Button,
  Select,
  View,
  Image,
} from '@shopify/ui-extensions-react/checkout';
import { useEffect, useMemo, useState, useCallback, useRef } from 'react';

const BUILD_ID = 'zyg-upgrade-card-20260215';
const DEFAULT_API_URL = 'https://shopifyupsellapp-production.up.railway.app';
const DEFAULT_EXTENSION_SECRET = '89987874564648484';
const MAX_ITEMS_VISIBLE = 3;
const FETCH_DEBOUNCE_MS = 280;
const REFRESH_AFTER_ACTION_MS = 350;

const UPGRADED_BY_APP_KEY = '_zyg_upgraded_subscription';
const UPGRADED_BY_APP_VALUE = '1';

const PIXEL_URL = 'https://zyxel.nolos.io/r';

const readValue = (maybe) => {
  if (!maybe) return undefined;
  if (typeof maybe?.current === 'function') return maybe.current();
  if (maybe?.value !== undefined) return maybe.value;
  if (maybe?.current !== undefined) return maybe.current;
  return maybe;
};

const getSetting = (settings, key) => {
  if (!settings || typeof settings !== 'object') return undefined;
  const raw = settings[key];
  if (raw === undefined || raw === null) return undefined;
  if (typeof raw === 'object' && raw !== null && 'value' in raw) return raw.value;
  return raw;
};

const sendClickLog = (apiUrl, secret, { shop, block_id, session_key, click_target, meta }) => {
  if (!apiUrl || !secret) return;
  const url = `${String(apiUrl).replace(/\/$/, '')}/api/checkout/logs`;

  fetch(url, {
    method: 'POST',
    headers: {
      'X-Extension-Secret': secret,
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
    body: JSON.stringify({
      event: 'widget_click',
      shop: shop ?? undefined,
      block_id: block_id ?? undefined,
      session_key: session_key ?? undefined,
      click_target: click_target ?? 'upgrade_cta',
      meta: meta && typeof meta === 'object' ? meta : undefined,
    }),
  }).catch(() => {});
};

const getCheckoutAttributes = (api) => {
  const out = {};
  try {
    const raw = readValue(api?.attributes);
    if (!Array.isArray(raw)) return out;
    raw.forEach((item) => {
      if (item && item.key != null) out[String(item.key)] = String(item.value ?? '');
    });
  } catch (_) {}
  return out;
};

const getContextPayloadFromApi = (api) => {
  const payload = {};
  try {
    const addr = api?.shippingAddress ?? api?.addresses?.shippingAddress;
    const raw = readValue(addr);
    if (raw && typeof raw === 'object' && raw.countryCode) payload.shipping_country = String(raw.countryCode);
  } catch (_) {}

  try {
    const raw = readValue(api?.customer);
    if (raw && typeof raw === 'object') {
      const tags = raw.tags ?? raw.tags_array;
      payload.customer = Array.isArray(tags) ? { tags } : { tags: [] };
    }
  } catch (_) {}

  return payload;
};

/** Extract numeric id from Shopify GID (e.g. gid://shopify/Product/123 -> 123). */
function numericIdFromGid(gid) {
  if (!gid || typeof gid !== 'string') return '';
  const m = String(gid).match(/\d+/);
  return m ? m[0] : String(gid).replace(/\D/g, '') || '';
};

const buildLineItemsForPixel = (lineItems) => {
  if (!Array.isArray(lineItems)) return [];
  return lineItems.map((line) => {
    const merch = line?.merchandise ?? line;
    const productId = merch?.product?.id ?? line?.product_id ?? '';
    const variantId = merch?.id ?? line?.variant_id ?? '';
    const productTitle = merch?.product?.title ?? line?.product_title ?? line?.productTitle ?? '';
    const variantTitle = merch?.title ?? line?.variant_title ?? line?.variantTitle ?? '';
    const sku = merch?.sku ?? line?.sku ?? '';
    const quantity = Number(line?.quantity ?? 1);
    const cost = line?.cost ?? merch?.cost;
    const ta = cost != null && typeof cost === 'object' ? cost.totalAmount : undefined;

    const price =
      typeof ta === 'object' && ta != null && typeof ta.amount === 'number'
        ? Number(ta.amount)
        : typeof ta === 'number'
          ? Number(ta)
          : 0;

    const id = numericIdFromGid(productId) || productId;
    const title = (variantTitle || productTitle || '').trim() || '1 Month';

    return {
      product_id: String(productId),
      product_title: String(productTitle),
      variant_id: String(variantId),
      variant_title: String(variantTitle),
      sku: String(sku),
      price,
      quantity,
      id: String(id),
      title: String(title),
    };
  });
};

const buildPixelPayload = (lineItems, attributes, subtotalMoney, blockId, shop, sessionKey, contextPayload) => {
  const client_id = String(attributes?._zyxel_user_id || attributes?._axon_client_id || attributes?.igId || '');
  const total_price = Number(subtotalMoney?.amount ?? 0);
  const currency = String(subtotalMoney?.currencyCode || 'USD').toUpperCase();

  const rawLineItems = buildLineItemsForPixel(lineItems);
  const line_items = rawLineItems.map((item) => ({
    sku: item.sku,
    title: item.title,
    id: item.id,
    quantity: item.quantity,
  }));

  const now = new Date();

  return {
    client_id,
    total_price,
    currency,
    line_items,
    line_items_count: line_items.length,
    customer_id: null,
    event_timestamp: now.toISOString(),
    page_timestamp: now.getTime(),
    block_id: blockId ?? undefined,
    shop: shop ?? undefined,
    session_key: sessionKey ?? undefined,
    checkout_attributes: attributes && Object.keys(attributes).length > 0 ? attributes : undefined,
    shipping_country: contextPayload?.shipping_country ?? undefined,
  };
};

const sendPixelEvent = (eventName, payload) => {
  if (!eventName || typeof eventName !== 'string') return;

  const event_value = JSON.stringify({
    client_id: payload.client_id,
    total_price: payload.total_price,
    currency: payload.currency,
    line_items: payload.line_items,
    customer_id: payload.customer_id ?? null,
  });

  const body = {
    type: 'funnel',
    event: eventName,
    event_category: 'funnel',
    event_name: eventName,
    event_timestamp: payload.event_timestamp ?? new Date().toISOString(),
    page_timestamp: payload.page_timestamp ?? Date.now(),
    event_value,
    ...payload,
  };

  fetch(PIXEL_URL, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(body),
  }).catch(() => {});
};

const toPropertiesObject = (props) => {
  if (!props) return {};
  if (Array.isArray(props)) {
    return props.reduce((acc, item) => {
      if (item && typeof item === 'object') {
        const key = item.key ?? item.name;
        const value = item.value;
        if (key != null && value != null && String(key).trim() !== '') acc[String(key)] = String(value);
      }
      return acc;
    }, {});
  }
  if (typeof props === 'object') {
    return Object.entries(props).reduce((acc, [key, value]) => {
      if (value != null && String(key).trim() !== '') acc[String(key)] = String(value);
      return acc;
    }, {});
  }
  return {};
};

const normalizeLineItemsForApi = (lines) => {
  if (!Array.isArray(lines)) return [];
  return lines.map((line) => {
    const merch = line?.merchandise ?? line;
    const id = merch?.id ?? line?.id;
    const productId = merch?.product?.id ?? line?.product_id;
    const variantId = merch?.id ?? line?.variant_id ?? id;
    const productTitle = merch?.product?.title ?? line?.product_title ?? line?.productTitle ?? '';
    const variantTitle = merch?.title ?? line?.variant_title ?? line?.variantTitle ?? '';
    const sku = merch?.sku ?? line?.sku ?? '';
    const sellingPlanAllocation = line?.sellingPlanAllocation ?? merch?.sellingPlanAllocation;
    const sellingPlanId =
      sellingPlanAllocation?.sellingPlan?.id ??
      merch?.sellingPlan?.id ??
      line?.selling_plan_id ??
      line?.sellingPlanId ??
      '';
    const properties = toPropertiesObject(
      line?.properties ?? line?.attributes ?? line?.customAttributes ?? merch?.customAttributes ?? merch?.attributes
    );

    const cost = line?.cost ?? merch?.cost;
    const lineTotal =
      cost != null && typeof cost === 'object' && typeof cost.totalAmount === 'number'
        ? cost.totalAmount
        : undefined;

    return {
      id: line?.id,
      quantity: line?.quantity ?? 1,
      merchandiseId: variantId,
      product_id: productId,
      variant_id: variantId,
      product_title: productTitle,
      variant_title: variantTitle,
      sku,
      selling_plan_id: sellingPlanId || undefined,
      properties,
      ...(lineTotal !== undefined && { cost: { totalAmount: lineTotal } }),
    };
  });
};

const sameId = (a, b) => {
  if (!a || !b) return false;
  const sa = String(a);
  const sb = String(b);
  if (sa === sb) return true;
  const na = sa.match(/\d+/g)?.join('') || '';
  const nb = sb.match(/\d+/g)?.join('') || '';
  return na !== '' && nb !== '' && na === nb;
};

const hasSellingPlan = (line) =>
  Boolean(line?.sellingPlanAllocation?.sellingPlan?.id || line?.merchandise?.sellingPlan?.id || line?.selling_plan_id);

const getResultErrorMessage = (res) => {
  if (!res) return 'Change failed.';
  if (typeof res === 'object') {
    if (res.message) return String(res.message);
    if (Array.isArray(res.errors) && res.errors[0]?.message) return String(res.errors[0].message);
  }
  return 'Change failed.';
};

const applyChangeStrict = async (applyCartLinesChange, change) => {
  const res = await applyCartLinesChange(change);

  if (Array.isArray(res)) {
    const err = res.find((r) => r && typeof r === 'object' && r.type === 'error');
    if (err) throw new Error(getResultErrorMessage(err));
    return;
  }

  if (res && typeof res === 'object' && res.type === 'error') {
    throw new Error(getResultErrorMessage(res));
  }
};

const applyBatchStrict = async (applyCartLinesChange, changes) => {
  if (!Array.isArray(changes) || changes.length === 0) return;
  if (changes.length === 1) {
    await applyChangeStrict(applyCartLinesChange, changes[0]);
    return;
  }

  const res = await applyCartLinesChange(changes);

  if (Array.isArray(res)) {
    const err = res.find((r) => r && typeof r === 'object' && r.type === 'error');
    if (err) throw new Error(getResultErrorMessage(err));
    return;
  }

  if (res && typeof res === 'object' && res.type === 'error') {
    throw new Error(getResultErrorMessage(res));
  }
};

const buildAttributesWithUpgradeMarker = (line) => {
  const out = [];
  const raw = line?.attributes ?? line?.properties;

  if (Array.isArray(raw)) {
    raw.forEach((item) => {
      if (item && typeof item === 'object' && item.key != null && item.key !== UPGRADED_BY_APP_KEY) {
        out.push({ key: String(item.key), value: String(item.value ?? '') });
      }
    });
  } else if (raw && typeof raw === 'object') {
    Object.entries(raw).forEach(([k, v]) => {
      if (k !== UPGRADED_BY_APP_KEY && k != null && String(k).trim() !== '') {
        out.push({ key: String(k), value: v != null ? String(v) : '' });
      }
    });
  }

  out.push({ key: UPGRADED_BY_APP_KEY, value: UPGRADED_BY_APP_VALUE });
  return out;
};

const dedupeChanges = (changes) => {
  const seen = new Set();
  const out = [];

  for (const c of Array.isArray(changes) ? changes : []) {
    const key = JSON.stringify({
      t: c?.type,
      id: c?.id,
      mid: c?.merchandiseId,
      q: c?.quantity,
      sp: c?.sellingPlanId ?? '__none__',
    });
    if (seen.has(key)) continue;
    seen.add(key);
    out.push(c);
  }

  return out;
};

const expandActionsToChanges = (actions, lines) => {
  const out = [];
  const safeLines = Array.isArray(lines) ? lines : [];

  for (const action of Array.isArray(actions) ? actions : []) {
    const type = action?.type;

    if (type === 'updateCartLine') {
      const sellingPlanId = 'sellingPlanId' in (action || {}) ? action.sellingPlanId : undefined;
      const isUndo = 'sellingPlanId' in (action || {}) && action.sellingPlanId == null;

      if (action?.lineId) {
        const targets = safeLines.filter((l) => sameId(l?.id, action.lineId));
        for (const line of targets) {
          if (isUndo ? !hasSellingPlan(line) : hasSellingPlan(line)) continue;

          const change = { type: 'updateCartLine', id: line.id };
          if ('sellingPlanId' in (action || {})) change.sellingPlanId = sellingPlanId;
          if (sellingPlanId != null) change.attributes = buildAttributesWithUpgradeMarker(line);
          out.push(change);
        }
        continue;
      }

      if (action?.merchandiseId) {
        const targets = safeLines.filter((l) => sameId(l?.merchandise?.id ?? l?.variant_id, action.merchandiseId));
        for (const line of targets) {
          if (isUndo ? !hasSellingPlan(line) : hasSellingPlan(line)) continue;

          const change = { type: 'updateCartLine', id: line.id };
          if ('sellingPlanId' in (action || {})) change.sellingPlanId = sellingPlanId;
          if (sellingPlanId != null) change.attributes = buildAttributesWithUpgradeMarker(line);
          out.push(change);
        }
      }

      continue;
    }

    if (type === 'removeCartLine' && action?.lineId) {
      const targets = safeLines.filter((l) => sameId(l?.id, action.lineId));
      for (const line of targets) {
        out.push({ type: 'removeCartLine', id: line.id, quantity: action.quantity ?? 1 });
      }
      continue;
    }

    if (type === 'addCartLine' && action?.merchandiseId) {
      out.push({
        type: 'addCartLine',
        merchandiseId: action.merchandiseId,
        quantity: Math.max(1, action.quantity ?? 1),
        attributes: [{ key: '_zyg_source', value: 'checkout_upgrade_card' }],
        ...(action.sellingPlanId ? { sellingPlanId: action.sellingPlanId } : {}),
      });
      continue;
    }
  }

  return dedupeChanges(out);
};

const getCartLinesFromApi = (api) => {
  const raw = readValue(api?.lines);
  if (Array.isArray(raw)) return raw;
  if (Array.isArray(api?.lines)) return api.lines;
  if (Array.isArray(api?.lines?.value)) return api.lines.value;
  return [];
};

export default reactExtension('purchase.checkout.block.render', () => <UpgradeCard />);

function UpgradeCard() {
  const settings = useSettings();
  const api = useApi();
  const checkoutToken = useCheckoutToken();
  const subtotalMoney = useSubtotalAmount();
  const applyCartLinesChange = useApplyCartLinesChange();

  const apiUrl = useMemo(
    () => String(getSetting(settings, 'api_url') || DEFAULT_API_URL || '').replace(/\/$/, ''),
    [settings]
  );

  const secret = useMemo(
    () => String(getSetting(settings, 'extension_secret') || DEFAULT_EXTENSION_SECRET || '').trim(),
    [settings]
  );

  const shop = useMemo(() => {
    const s = api?.shop?.myshopifyDomain;
    return typeof s === 'string' && s ? s : '';
  }, [api]);

  const blockId = useMemo(() => {
    const raw = getSetting(settings, 'block_id');
    const v = raw != null ? String(raw).trim() : '';
    return v ? v : undefined;
  }, [settings]);

  const sessionKey = useMemo(() => (typeof checkoutToken === 'string' && checkoutToken ? checkoutToken : ''), [checkoutToken]);

  const lineItems = getCartLinesFromApi(api);

  const lineItemsNormalized = useMemo(() => normalizeLineItemsForApi(lineItems), [lineItems]);
  const lineItemsNormalizedJson = useMemo(() => JSON.stringify(lineItemsNormalized), [lineItemsNormalized]);

  const instructions = api?.instructions ?? {};
  const linesInstructions = instructions?.lines ?? {};
  const canAdd = linesInstructions.canAddCartLine !== false;
  const canRemove = linesInstructions.canRemoveCartLine !== false;
  const canUpdate = linesInstructions.canUpdateCartLine !== false;
  const cartEditable = canAdd && canRemove && canUpdate;

  const [payload, setPayload] = useState(null);
  const [initialLoading, setInitialLoading] = useState(true);
  const [fetching, setFetching] = useState(false);
  const [applying, setApplying] = useState(false);
  const [selectedPlanId, setSelectedPlanId] = useState('');
  const [errorMessage, setErrorMessage] = useState('');

  const applyingRef = useRef(false);
  const debounceRef = useRef(null);
  const abortRef = useRef(null);
  const pixelShownSentRef = useRef(false);
  const blockLoadedSentRef = useRef(false);

  const doFetchPayload = useCallback(
    async (opts = {}) => {
      const { reason } = opts;

      if (!apiUrl || !secret || !shop || !blockId) {
        setInitialLoading(false);
        setFetching(false);
        setPayload({ enabled: false });
        return;
      }

      if (abortRef.current) abortRef.current.abort();
      const controller = new AbortController();
      abortRef.current = controller;

      setFetching(true);
      if (initialLoading) setInitialLoading(true);
      setErrorMessage('');

      const attributesForRequest = getCheckoutAttributes(api);
      const contextPayload = getContextPayloadFromApi(api);

      const body = {
        shop,
        block_id: blockId,
        session_key: sessionKey || undefined,
        subtotal: subtotalMoney?.amount ?? 0,
        line_items: lineItemsNormalized,
        selected_plan_id: selectedPlanId || undefined,
        ...(Object.keys(attributesForRequest).length > 0 ? { attributes: attributesForRequest } : {}),
        ...contextPayload,
        reason: reason || undefined,
        build_id: BUILD_ID,
      };

      try {
        const res = await fetch(`${apiUrl}/api/checkout/upgrade-card`, {
          method: 'POST',
          headers: {
            'X-Extension-Secret': secret,
            'Content-Type': 'application/json',
            Accept: 'application/json',
          },
          body: JSON.stringify(body),
          signal: controller.signal,
        });

        if (!res.ok) {
          if (res.status === 401) setErrorMessage('Invalid extension secret.');
          else if (res.status >= 500) setErrorMessage('Server error.');
          else setErrorMessage('Request failed.');
          setPayload((prev) => prev ?? { enabled: false });
          return;
        }

        const data = await res.json();

        if (!data || typeof data !== 'object') {
          setPayload((prev) => prev ?? { enabled: false });
          return;
        }

        setPayload(data);

        const plans = Array.isArray(data.plans) ? data.plans : [];
        if (!selectedPlanId && plans.length > 0) {
          const first = plans[0];
          const firstId = String(first?.id ?? first?.value ?? '');
          if (firstId) setSelectedPlanId(firstId);
        }

        const attrs = getCheckoutAttributes(api);
        const ctx = getContextPayloadFromApi(api);
        const pixelPayload = buildPixelPayload(lineItems, attrs, subtotalMoney, blockId, shop, sessionKey, ctx);

        if (!blockLoadedSentRef.current) {
          blockLoadedSentRef.current = true;
          sendPixelEvent('block_loaded', { ...pixelPayload, has_shown: false });
        }
      } catch (e) {
        if (e?.name === 'AbortError') return;
        setErrorMessage('Connection failed.');
        setPayload((prev) => prev ?? { enabled: false });
      } finally {
        setInitialLoading(false);
        setFetching(false);
      }
    },
    [
      api,
      apiUrl,
      secret,
      shop,
      blockId,
      sessionKey,
      subtotalMoney?.amount,
      subtotalMoney?.currencyCode,
      lineItemsNormalizedJson,
      selectedPlanId,
      initialLoading,
    ]
  );

  useEffect(() => {
    if (applyingRef.current) return;
    if (debounceRef.current) clearTimeout(debounceRef.current);

    debounceRef.current = setTimeout(() => {
      doFetchPayload({ reason: 'debounced' });
      debounceRef.current = null;
    }, FETCH_DEBOUNCE_MS);

    return () => {
      if (debounceRef.current) {
        clearTimeout(debounceRef.current);
        debounceRef.current = null;
      }
    };
  }, [
    doFetchPayload,
    apiUrl,
    secret,
    shop,
    blockId,
    sessionKey,
    subtotalMoney?.amount,
    subtotalMoney?.currencyCode,
    lineItemsNormalizedJson,
    selectedPlanId,
  ]);

  useEffect(() => {
    if (pixelShownSentRef.current) return;

    const payloadData = payload;
    if (!payloadData || typeof payloadData !== 'object') return;

    const enabled = payloadData.enabled === true;
    const items = Array.isArray(payloadData.items) ? payloadData.items : [];
    const mode = payloadData.mode;

    const isCartWideOffer = mode === 'cart_wide_offer';
    const isCartWideSuccess = mode === 'cart_wide_success';
    const showCard = enabled && (items.length > 0 || isCartWideOffer || isCartWideSuccess);

    if (!showCard) return;

    pixelShownSentRef.current = true;

    const attrs = getCheckoutAttributes(api);
    const ctx = getContextPayloadFromApi(api);
    const pixelPayload = buildPixelPayload(lineItems, attrs, subtotalMoney, blockId, shop, sessionKey, ctx);

    sendPixelEvent('block_shown', { ...pixelPayload, has_offers: true, items_count: items.length });
  }, [payload, api, lineItems, subtotalMoney, blockId, shop, sessionKey]);

  const runActions = useCallback(async () => {
    const actions = payload?.actions;
    if (!Array.isArray(actions) || actions.length === 0 || !cartEditable) return;

    const attrs = getCheckoutAttributes(api);
    const ctx = getContextPayloadFromApi(api);
    const currentLines =
      typeof api?.lines?.current === 'function' ? api.lines.current() : api?.lines?.value ?? api?.lines ?? [];
    const linesArray = Array.isArray(currentLines) ? currentLines : [];
    const pixelPayload = buildPixelPayload(linesArray, attrs, subtotalMoney, blockId, shop, sessionKey, ctx);
    const pixelEventName = payload?.mode === 'cart_wide_success' ? 'subscription_undo_clicked' : 'subscription_upgrade_clicked';
    sendPixelEvent(pixelEventName, pixelPayload);

    applyingRef.current = true;
    setApplying(true);
    setErrorMessage('');

    const getCurrentLines = () => {
      const raw = typeof api?.lines?.current === 'function' ? api.lines.current() : api?.lines?.value ?? api?.lines ?? [];
      return Array.isArray(raw) ? raw : [];
    };

    const resolveLineIdForUpdate = (action) => {
      const currentLines = getCurrentLines();
      const norm = (id) => (id == null ? '' : String(id).trim().replace(/\D/g, ''));
      const lineIdNorm = norm(action?.lineId);
      const merchIdNorm = norm(action?.merchandiseId);
      const isUndo = action && 'sellingPlanId' in action && action.sellingPlanId == null;
      for (const line of currentLines) {
        const lid = line?.id ?? line?.merchandise?.id;
        if (lineIdNorm && norm(lid) === lineIdNorm) return line.id;
        if (merchIdNorm && norm(line?.merchandise?.id ?? line?.variant_id) === merchIdNorm) {
          const hasPlan = line?.merchandise?.sellingPlan?.id ?? line?.sellingPlanAllocation?.sellingPlan?.id ?? line?.selling_plan_id;
          if (isUndo ? hasPlan : !hasPlan) return line.id;
        }
      }
      return action?.lineId;
    };

    const buildAttributesWithUpgradeMarker = (line) => {
      const attrs = [];
      const raw = line?.attributes ?? line?.properties;
      if (Array.isArray(raw)) {
        raw.forEach((item) => {
          if (item && typeof item === 'object' && item.key != null && item.key !== UPGRADED_BY_APP_KEY) {
            attrs.push({ key: String(item.key), value: String(item.value ?? '') });
          }
        });
      } else if (raw && typeof raw === 'object') {
        Object.entries(raw).forEach(([k, v]) => {
          if (k !== UPGRADED_BY_APP_KEY && k != null && String(k).trim() !== '') {
            attrs.push({ key: String(k), value: v != null ? String(v) : '' });
          }
        });
      }
      attrs.push({ key: UPGRADED_BY_APP_KEY, value: UPGRADED_BY_APP_VALUE });
      return attrs;
    };

    try {
      for (let i = 0; i < actions.length; i++) {
        const action = actions[i];
        const type = action?.type;
        if (type === 'updateCartLine') {
          const lineId = resolveLineIdForUpdate(action) || action?.lineId;
          if (lineId) {
            const change = { type: 'updateCartLine', id: lineId };
            if (action && 'sellingPlanId' in action) {
              change.sellingPlanId = action.sellingPlanId;
              if (action.sellingPlanId != null) {
                const currentLines = getCurrentLines();
                const line = currentLines.find((l) => (l?.id ?? l?.merchandise?.id) === lineId);
                if (line) {
                  change.attributes = buildAttributesWithUpgradeMarker(line);
                }
              }
            }
            await applyCartLinesChange(change);
          }
        } else if (type === 'removeCartLine' && action?.lineId) {
          await applyCartLinesChange({
            type: 'removeCartLine',
            id: action.lineId,
            quantity: action.quantity ?? 1,
          });
        } else if (type === 'addCartLine' && action?.merchandiseId) {
          const change = {
            type: 'addCartLine',
            merchandiseId: action.merchandiseId,
            quantity: Math.max(1, action.quantity ?? 1),
            attributes: [{ key: '_zyg_source', value: 'checkout_upgrade_card' }],
          };
          if (action.sellingPlanId) change.sellingPlanId = action.sellingPlanId;
          await applyCartLinesChange(change);
        }
      }
      sendClickLog(apiUrl, secret, {
        shop,
        block_id: blockId,
        session_key: sessionKey,
        click_target: 'upgrade_cta',
        meta: { source: 'checkout_upgrade_card' },
      });
      setTimeout(() => fetchPayloadRef.current?.(), 350);
    } catch (err) {
      setErrorMessage(err?.message || 'Update failed.');
    } finally {
      applyingRef.current = false;
      setApplying(false);
    }
  }, [
    payload,
    cartEditable,
    applyCartLinesChange,
    api,
    apiUrl,
    secret,
    blockId,
    sessionKey,
    shop,
    subtotalMoney,
    doFetchPayload,
  ]);

  if (initialLoading) return null;

  const mode = payload?.mode;
  const enabled = payload?.enabled === true;
  const items = Array.isArray(payload?.items) ? payload.items : [];
  const plans = Array.isArray(payload?.plans) ? payload.plans : [];

  const headline = payload?.headline ?? '';
  const description = payload?.description ?? '';
  const subtext = payload?.subtext ?? '';

  const ctaLabel = payload?.cta_label ?? 'Upgrade';
  const undoLabel = payload?.undo_label ?? 'Undo savings';

  const undoStyleRaw = payload?.undo_style ?? 'plain';
  const undoKind = ['plain', 'secondary', 'primary'].includes(String(undoStyleRaw)) ? String(undoStyleRaw) : 'plain';

  const ui = payload?.ui && typeof payload.ui === 'object' ? payload.ui : {};
  const headlineSize = ['small', 'medium', 'large'].includes(String(ui.title_size)) ? String(ui.title_size) : 'medium';
  const buttonKind = ['primary', 'secondary', 'plain'].includes(String(ui.button_kind)) ? String(ui.button_kind) : 'secondary';
  const spacing = String(ui.spacing) === 'loose' ? 'loose' : 'tight';

  const showBorder = ui.show_border !== false;
  const borderRadius = ['none', 'base', 'large'].includes(String(ui.border_radius)) ? String(ui.border_radius) : 'base';
  const padding = ['none', 'tight', 'base', 'loose'].includes(String(ui.padding)) ? String(ui.padding) : 'base';

  const showItems = ui.show_items !== false;
  const planLabel = String(ui.plan_label || 'Plan');

  const itemsMaxVisibleRaw = Number(ui.items_max_visible ?? MAX_ITEMS_VISIBLE);
  const itemsMaxVisible = Number.isFinite(itemsMaxVisibleRaw)
    ? Math.max(1, Math.min(10, Math.floor(itemsMaxVisibleRaw)))
    : MAX_ITEMS_VISIBLE;

  const isCartWideOffer = mode === 'cart_wide_offer';
  const isCartWideSuccess = mode === 'cart_wide_success';

  const showCard = enabled && (items.length > 0 || isCartWideOffer || isCartWideSuccess);
  if (!showCard) return null;

  const displayMode = String(ui.display_mode ?? 'text');
  const imageMode = displayMode === 'image';
  const imageUrl = imageMode && ui.image_url ? String(ui.image_url).trim() : '';

  const showPlans = plans.length > 0;
  const planOptions = useMemo(
    () =>
      plans.map((p) => ({
        value: String(p?.id ?? p?.value ?? ''),
        label: p?.label ?? p?.name ?? String(p?.id ?? p?.value ?? ''),
      })),
    [plans]
  );

  const visibleItems = items.slice(0, itemsMaxVisible);
  const extraCount = items.length - itemsMaxVisible;

  const ctaDisabled = !cartEditable || applying || fetching;

  if (isCartWideSuccess) {
    return (
      <View padding={padding} border={showBorder ? 'base' : undefined} borderRadius={borderRadius}>
        <BlockStack spacing={spacing}>
          {headline ? (
            <Text size={headlineSize} emphasis="bold">
              {headline}
            </Text>
          ) : null}

          {cartEditable ? (
            <Button
              kind={undoKind}
              onPress={runActions}
              loading={applying}
              disabled={applying || fetching}
              accessibilityLabel={undoLabel}
            >
              {undoLabel}
            </Button>
          ) : null}

          {errorMessage ? (
            <Text size="small" appearance="critical">
              {errorMessage}
            </Text>
          ) : null}
        </BlockStack>
      </View>
    );
  }

  if (isCartWideOffer) {
    const subtextLines = subtext ? subtext.split(/\r?\n/).filter((s) => s.trim() !== '') : [];

    return (
      <View padding={padding} border={showBorder ? 'base' : undefined} borderRadius={borderRadius}>
        <BlockStack spacing={spacing}>
          {headline ? (
            <Text size={headlineSize} emphasis="bold">
              {headline}
            </Text>
          ) : null}

          {subtextLines.length > 0 ? (
            <BlockStack spacing="extraTight">
              {subtextLines.map((line, idx) => {
                const trimmed = line.replace(/^[\s\-•]+\s?/, '').trim();
                const isFirstLine = idx === 0;
                return (
                  <Text key={idx} size="small" appearance="subdued">
                    {isFirstLine ? trimmed : `• ${trimmed}`}
                  </Text>
                );
              })}
            </BlockStack>
          ) : null}

          {items.length > 0 ? (
            <BlockStack spacing="extraTight">
              <Text size="small" emphasis="bold">
                Items:
              </Text>
              {items.map((item, idx) => {
                const productName = String(item?.product_title ?? item?.title ?? '').trim();
                const variantName = String(item?.variant_title ?? '').trim();
                const displayName = productName && productName.toLowerCase() !== 'item' ? productName : variantName;
                return (
                  <Text key={item?.line_id ?? idx} size="small" appearance="subdued">
                    {displayName}
                    {item?.frequency ? ` - ${item.frequency}` : ''}
                  </Text>
                );
              })}
            </BlockStack>
          ) : null}

          {!cartEditable ? (
            <Text size="small" appearance="subdued">
              Cart cannot be changed in this checkout.
            </Text>
          ) : null}

          {errorMessage ? (
            <Text size="small" appearance="critical">
              {errorMessage}
            </Text>
          ) : null}

          <Button kind={buttonKind} onPress={runActions} loading={applying} disabled={ctaDisabled} accessibilityLabel={ctaLabel}>
            {ctaLabel}
          </Button>
        </BlockStack>
      </View>
    );
  }

  return (
    <View padding={padding} border={showBorder ? 'base' : undefined} borderRadius={borderRadius}>
      <BlockStack spacing={spacing}>
        {imageMode && imageUrl ? <Image source={imageUrl} accessibilityDescription={ctaLabel || 'Upgrade offer'} /> : null}

        {!imageMode && headline ? (
          <Text size={headlineSize} emphasis="bold">
            {headline}
          </Text>
        ) : null}

        {!imageMode && description ? (
          <Text appearance="subdued" size="small">
            {description}
          </Text>
        ) : null}

        {!imageMode && showItems ? (
          <BlockStack spacing="extraTight">
            <Text size="small" emphasis="bold">
              Items:
            </Text>

            {visibleItems.map((item, idx) => {
              const productName = String(item?.product_title ?? item?.title ?? '').trim();
              const variantName = String(item?.variant_title ?? '').trim();
              const nameForDisplay = productName && productName.toLowerCase() !== 'item' ? productName : variantName;
              const display = nameForDisplay
                ? variantName && nameForDisplay !== variantName
                  ? `${nameForDisplay} - ${variantName}`
                  : nameForDisplay
                : variantName;

              return (
                <Text key={item?.line_id ?? idx} size="small" appearance="subdued">
                  {display || ''}
                </Text>
              );
            })}

            {extraCount > 0 ? (
              <Text size="small" appearance="subdued">
                See {extraCount} more item{extraCount !== 1 ? 's' : ''}
              </Text>
            ) : null}
          </BlockStack>
        ) : null}

        {showPlans && planOptions.length > 0 ? (
          <Select label={planLabel} options={planOptions} value={selectedPlanId} onChange={setSelectedPlanId} />
        ) : null}

        {!cartEditable ? (
          <Text size="small" appearance="subdued">
            Cart cannot be changed in this checkout (e.g. express checkout).
          </Text>
        ) : null}

        {errorMessage ? (
          <Text size="small" appearance="critical">
            {errorMessage}
          </Text>
        ) : null}

        <Button kind={buttonKind} onPress={runActions} loading={applying} disabled={ctaDisabled} accessibilityLabel={ctaLabel}>
          {ctaLabel}
        </Button>
      </BlockStack>
    </View>
  );
}