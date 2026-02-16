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
} from '@shopify/ui-extensions-react/checkout';
import { useEffect, useState, useCallback } from 'react';

const BUILD_ID = 'zyg-upgrade-card-20260215';
const DEFAULT_API_URL = 'https://shopifyupsellapp-production.up.railway.app';
const DEFAULT_EXTENSION_SECRET = '89987874564648484';
const MAX_ITEMS_VISIBLE = 3;

function getSetting(settings, key) {
  if (!settings || typeof settings !== 'object') return undefined;
  const raw = settings[key];
  if (raw === undefined || raw === null) return undefined;
  if (typeof raw === 'object' && raw !== null && 'value' in raw) return raw.value;
  return raw;
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
    const properties = toPropertiesObject(
      line?.properties ?? line?.attributes ?? line?.customAttributes ?? merch?.customAttributes ?? merch?.attributes
    );
    return {
      id: line?.id,
      quantity: line?.quantity ?? 1,
      merchandiseId: variantId,
      product_id: productId,
      variant_id: variantId,
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
    const body = {
      shop: shop || undefined,
      block_id: blockId,
      session_key: sessionKey || undefined,
      subtotal: subtotalMoney?.amount ?? 0,
      line_items: lineItemsNormalized,
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

  useEffect(() => {
    fetchPayload();
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
          };
          if (action.sellingPlanId) change.sellingPlanId = action.sellingPlanId;
          await applyCartLinesChange(change);
        }
      }
      setPayload((prev) => (prev ? { ...prev, enabled: false } : prev));
    } catch (err) {
      setErrorMessage(err?.message || 'Update failed.');
    } finally {
      setApplying(false);
    }
  }, [payload?.actions, cartEditable, applyCartLinesChange]);

  if (loading) {
    return (
      <View padding="base">
        <Text appearance="subdued" size="small">
          Loading…
        </Text>
      </View>
    );
  }

  const enabled = payload?.enabled === true;
  const items = Array.isArray(payload?.items) ? payload.items : [];
  const plans = Array.isArray(payload?.plans) ? payload.plans : [];
  const headline = payload?.headline ?? '';
  const description = payload?.description ?? '';
  const ctaLabel = payload?.cta_label ?? 'Upgrade';

  if (!enabled || items.length === 0) {
    return null;
  }

  const showPlans = plans.length > 0;
  const planOptions = plans.map((p) => ({
    value: String(p.id ?? p.value ?? ''),
    label: p.label ?? p.name ?? String(p.id ?? p.value ?? ''),
  }));
  const visibleItems = items.slice(0, MAX_ITEMS_VISIBLE);
  const extraCount = items.length - MAX_ITEMS_VISIBLE;
  const ctaDisabled = !cartEditable || applying;

  return (
    <View padding="base" border="base" borderRadius="base">
      <BlockStack spacing="tight">
        {headline ? (
          <Text size="medium" emphasis="bold">
            {headline}
          </Text>
        ) : null}
        {description ? (
          <Text appearance="subdued" size="small">
            {description}
          </Text>
        ) : null}
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
        {showPlans && planOptions.length > 0 ? (
          <Select
            label="Plan"
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
          kind="secondary"
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
