/**
 * Upgrade card: app block in Checkout.
 * Fetches payload from API (headline, items, plans, CTA, actions) and runs applyCartLinesChange sequentially on CTA.
 */
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
  InlineLayout,
  Select,
  View,
  Image,
} from '@shopify/ui-extensions-react/checkout';
import { useEffect, useState, useCallback, useRef } from 'react';

const BUILD_ID = 'zyg-upgrade-card-20260215';
const DEFAULT_API_URL = 'https://shopifyupsellapp-production.up.railway.app';
const DEFAULT_EXTENSION_SECRET = '89987874564648484';
const MAX_ITEMS_VISIBLE = 3;
/** Debounce ms before fetching upgrade payload after cart/context change so we don't get conflicting responses. */
const FETCH_DEBOUNCE_MS = 280;

function getSetting(settings, key) {
  if (!settings || typeof settings !== 'object') return undefined;
  const raw = settings[key];
  if (raw === undefined || raw === null) return undefined;
  if (typeof raw === 'object' && raw !== null && 'value' in raw) return raw.value;
  return raw;
}

function sendClickLog(apiUrl, secret, { shop, block_id, session_key, click_target, meta }) {
  if (!apiUrl || !secret) return;
  const url = `${String(apiUrl).replace(/\/$/, '')}/api/checkout/logs`;
  fetch(url, {
    method: 'POST',
    headers: { 'X-Extension-Secret': secret, 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({
      event: 'widget_click',
      shop: shop ?? undefined,
      block_id: block_id ?? undefined,
      session_key: session_key ?? undefined,
      click_target: click_target ?? 'upgrade_cta',
      meta: meta && typeof meta === 'object' ? meta : undefined,
    }),
  }).catch(() => {});
}

function getCheckoutAttributes(api) {
  const out = {};
  try {
    const a = api?.attributes;
    if (!a) return out;
    const raw = typeof a.current === 'function' ? a.current() : (a.value !== undefined ? a.value : a.current);
    if (Array.isArray(raw)) {
      raw.forEach((item) => {
        if (item && item.key != null) out[String(item.key)] = String(item.value ?? '');
      });
    }
  } catch (_) {}
  return out;
}

function getContextPayloadFromApi(api) {
  const payload = {};
  try {
    const addr = api?.shippingAddress ?? api?.addresses?.shippingAddress;
    if (addr) {
      const raw = typeof addr.current === 'function' ? addr.current() : (addr.value !== undefined ? addr.value : addr.current);
      if (raw && typeof raw === 'object' && raw.countryCode) {
        payload.shipping_country = String(raw.countryCode);
      }
    }
  } catch (_) {}
  try {
    const cust = api?.customer;
    if (cust) {
      const raw = typeof cust.current === 'function' ? cust.current() : (cust.value !== undefined ? cust.value : cust.current);
      if (raw && typeof raw === 'object') {
        const tags = raw.tags ?? raw.tags_array;
        payload.customer = Array.isArray(tags) ? { tags } : { tags: [] };
      }
    }
  } catch (_) {}
  return payload;
}

function normalizeLineItemsForApi(lines) {
  if (!Array.isArray(lines)) return [];
  const toPropertiesObject = (props) => {
    if (!props) return {};
    if (Array.isArray(props)) {
      return props.reduce((acc, item) => {
        if (item && typeof item === 'object') {
          const key = item.key ?? item.name;
          const value = item.value;
          if (key != null && value != null && String(key).trim() !== '') {
            acc[String(key)] = String(value);
          }
        }
        return acc;
      }, {});
    }
    if (typeof props === 'object') {
      return Object.entries(props).reduce((acc, [key, value]) => {
        if (value != null && String(key).trim() !== '') {
          acc[String(key)] = String(value);
        }
        return acc;
      }, {});
    }
    return {};
  };

  return lines.map((line) => {
    const merch = line?.merchandise ?? line;
    const id = merch?.id ?? line?.id;
    const productId = merch?.product?.id ?? line?.product_id;
    const variantId = merch?.id ?? line?.variant_id ?? id;
    const productTitle = merch?.product?.title ?? line?.product_title ?? line?.productTitle ?? '';
    const variantTitle = merch?.title ?? line?.variant_title ?? line?.variantTitle ?? '';
    const sku = merch?.sku ?? line?.sku ?? '';
    const sellingPlanAllocation = line?.sellingPlanAllocation ?? merch?.sellingPlanAllocation;
    const sellingPlanId = sellingPlanAllocation?.sellingPlan?.id ?? merch?.sellingPlan?.id ?? line?.selling_plan_id ?? line?.sellingPlanId ?? '';
    const properties = toPropertiesObject(
      line?.properties ?? line?.attributes ?? line?.customAttributes ?? merch?.customAttributes ?? merch?.attributes
    );
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
    };
  });
}

export default reactExtension('purchase.checkout.block.render', () => <UpgradeCard />);

function UpgradeCard() {
  const settings = useSettings();
  const api = useApi();
  const checkoutToken = useCheckoutToken();
  const subtotalMoney = useSubtotalAmount();
  const applyCartLinesChange = useApplyCartLinesChange();

  const apiUrl = (getSetting(settings, 'api_url') || DEFAULT_API_URL || '').replace(/\/$/, '');
  const secret = (getSetting(settings, 'extension_secret') || DEFAULT_EXTENSION_SECRET).trim();
  const shopDomain = (getSetting(settings, 'shop_domain') || '').trim();
  const runtimeShop =
    typeof api?.shop?.myshopifyDomain === 'string' && api.shop.myshopifyDomain ? api.shop.myshopifyDomain : null;
  const shop = shopDomain || runtimeShop || '';
  const blockIdRaw = getSetting(settings, 'block_id');
  const blockId =
    blockIdRaw != null && String(blockIdRaw).trim() !== '' ? String(blockIdRaw).trim() : undefined;
  const sessionKey = typeof checkoutToken === 'string' && checkoutToken ? checkoutToken : '';

  const cartLines =
    (typeof api?.lines?.current === 'function' ? api.lines.current() : api?.lines?.current) ?? api?.lines ?? [];
  const lineItems = Array.isArray(cartLines)
    ? cartLines
    : cartLines?.value
      ? Array.isArray(cartLines.value)
        ? cartLines.value
        : []
      : [];

  const [payload, setPayload] = useState(null);
  const [loading, setLoading] = useState(true);
  const [applying, setApplying] = useState(false);
  const [selectedPlanId, setSelectedPlanId] = useState('');
  const [errorMessage, setErrorMessage] = useState('');
  const fetchPayloadRef = useRef(() => {});
  const fetchTimeoutRef = useRef(null);

  const instructions = api?.instructions ?? {};
  const linesInstructions = instructions?.lines ?? {};
  const canAdd = linesInstructions.canAddCartLine !== false;
  const canRemove = linesInstructions.canRemoveCartLine !== false;
  const canUpdate = linesInstructions.canUpdateCartLine !== false;
  const cartEditable = canAdd && canRemove && canUpdate;

  const fetchPayload = useCallback(() => {
    if (!apiUrl || !secret) {
      setLoading(false);
      setPayload({ enabled: false });
      return;
    }
    if (!blockId) {
      setLoading(false);
      setPayload({ enabled: false });
      return;
    }

    setLoading(true);
    setErrorMessage('');
    const lineItemsNormalized = normalizeLineItemsForApi(lineItems);
    const attributesForRequest = getCheckoutAttributes(api);
    const contextPayload = getContextPayloadFromApi(api);
    const body = {
      shop: shop || undefined,
      block_id: blockId,
      session_key: sessionKey || undefined,
      subtotal: subtotalMoney?.amount ?? 0,
      line_items: lineItemsNormalized,
      ...(Object.keys(attributesForRequest).length > 0 && { attributes: attributesForRequest }),
      ...contextPayload,
    };

    fetch(`${apiUrl}/api/checkout/upgrade-card`, {
      method: 'POST',
      headers: {
        'X-Extension-Secret': secret,
        'Content-Type': 'application/json',
        Accept: 'application/json',
      },
      body: JSON.stringify(body),
    })
      .then((r) => {
        if (r.ok) return r.json();
        setPayload({ enabled: false });
        if (r.status === 401) setErrorMessage('Invalid extension secret.');
        else if (r.status >= 500) setErrorMessage('Server error.');
        return null;
      })
      .then((data) => {
        if (data && typeof data === 'object') {
          setPayload(data);
          const plans = data.plans;
          if (Array.isArray(plans) && plans.length > 0 && !selectedPlanId) {
            const first = plans[0];
            setSelectedPlanId(String(first.id ?? first.value ?? ''));
          }
        } else {
          setPayload({ enabled: false });
        }
      })
      .catch(() => {
        setPayload({ enabled: false });
        setErrorMessage('Connection failed.');
      })
      .finally(() => setLoading(false));
  }, [apiUrl, secret, shop, blockId, sessionKey, subtotalMoney?.amount, JSON.stringify(normalizeLineItemsForApi(lineItems))]);

  fetchPayloadRef.current = fetchPayload;
  useEffect(() => {
    if (fetchTimeoutRef.current) clearTimeout(fetchTimeoutRef.current);
    fetchTimeoutRef.current = setTimeout(() => {
      fetchPayloadRef.current?.();
      fetchTimeoutRef.current = null;
    }, FETCH_DEBOUNCE_MS);
    return () => {
      if (fetchTimeoutRef.current) {
        clearTimeout(fetchTimeoutRef.current);
        fetchTimeoutRef.current = null;
      }
    };
  }, [fetchPayload]);

  const runActions = useCallback(async () => {
    const actions = payload?.actions;
    if (!Array.isArray(actions) || actions.length === 0 || !cartEditable) return;
    setApplying(true);
    setErrorMessage('');

    try {
      for (let i = 0; i < actions.length; i++) {
        const action = actions[i];
        const type = action?.type;
        if (type === 'removeCartLine' && action?.lineId) {
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
      setPayload((prev) => (prev ? { ...prev, enabled: false } : prev));
      sendClickLog(apiUrl, secret, {
        shop,
        block_id: blockId,
        session_key: sessionKey,
        click_target: 'upgrade_cta',
        meta: { source: 'checkout_upgrade_card' },
      });
    } catch (err) {
      setErrorMessage(err?.message || 'Update failed.');
    } finally {
      setApplying(false);
    }
  }, [payload?.actions, cartEditable, applyCartLinesChange, apiUrl, secret, blockId, sessionKey, shop]);

  if (loading) {
    return null;
  }

  const enabled = payload?.enabled === true;
  const items = Array.isArray(payload?.items) ? payload.items : [];
  const plans = Array.isArray(payload?.plans) ? payload.plans : [];
  const headline = payload?.headline ?? '';
  const description = payload?.description ?? '';
  const ctaLabel = payload?.cta_label ?? 'Upgrade';
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
  const itemsMaxVisible = Number.isFinite(itemsMaxVisibleRaw) ? Math.max(1, Math.min(10, Math.floor(itemsMaxVisibleRaw))) : MAX_ITEMS_VISIBLE;

  if (!enabled || items.length === 0) {
    return null;
  }

  const displayMode = String(ui.display_mode ?? 'text');
  const imageMode = displayMode === 'image';
  const imageUrl = imageMode && ui.image_url ? String(ui.image_url).trim() : '';
  const showPlans = plans.length > 0;
  const planOptions = plans.map((p) => ({
    value: String(p.id ?? p.value ?? ''),
    label: p.label ?? p.name ?? String(p.id ?? p.value ?? ''),
  }));
  const visibleItems = items.slice(0, itemsMaxVisible);
  const extraCount = items.length - itemsMaxVisible;
  const ctaDisabled = !cartEditable || applying;

  return (
    <View padding={padding} border={showBorder ? 'base' : undefined} borderRadius={borderRadius}>
      <BlockStack spacing={spacing}>
        {imageMode && imageUrl ? (
          <Image source={imageUrl} accessibilityDescription={ctaLabel || 'Upgrade offer'} />
        ) : null}
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
            {visibleItems.map((item, idx) => (
              <Text key={item.line_id ?? idx} size="small" appearance="subdued">
                {item.product_title ?? item.title ?? 'Item'}
                {item.variant_title ? ` — ${item.variant_title}` : ''}
              </Text>
            ))}
            {extraCount > 0 ? (
              <Text size="small" appearance="subdued">
                See {extraCount} more item{extraCount !== 1 ? 's' : ''}
              </Text>
            ) : null}
          </BlockStack>
        ) : null}
        {showPlans && planOptions.length > 0 ? (
          <Select
            label={planLabel}
            options={planOptions}
            value={selectedPlanId}
            onChange={setSelectedPlanId}
          />
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
        <Button
          kind={buttonKind}
          onPress={runActions}
          loading={applying}
          disabled={ctaDisabled}
          accessibilityLabel={ctaLabel}
        >
          {ctaLabel}
        </Button>
      </BlockStack>
    </View>
  );
}
