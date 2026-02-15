/**
 * Cart line item extension: "Modify" (Disclosure with quantity +/-) and "Upgrade to subscription" per line.
 * Target: purchase.checkout.cart-line-item.render-after
 */
import {
  reactExtension,
  useSettings,
  useApi,
  useCheckoutToken,
  useCartLineTarget,
  BlockStack,
  Text,
  Button,
  InlineLayout,
  Disclosure,
  useApplyCartLinesChange,
} from '@shopify/ui-extensions-react/checkout';
import { useCallback, useEffect, useRef, useState } from 'react';

const DEFAULT_API_URL = 'https://shopifyupsellapp-production.up.railway.app';
const CART_LINE_BUILD_ID = 'zyg-cart-line-20260212';

function getSetting(settings, key) {
  if (!settings || typeof settings !== 'object') return undefined;
  const raw = settings[key];
  if (raw === undefined || raw === null) return undefined;
  if (typeof raw === 'object' && raw !== null && 'value' in raw) return raw.value;
  return raw;
}

function sendLog(apiUrl, secret, payload) {
  if (!apiUrl || !secret) return;
  const url = `${String(apiUrl).replace(/\/$/, '')}/api/checkout/logs`;
  fetch(url, {
    method: 'POST',
    headers: { 'X-Extension-Secret': secret, 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ ts: new Date().toISOString(), build_id: CART_LINE_BUILD_ID, ...payload }),
  }).catch(() => {});
}

export default reactExtension('purchase.checkout.cart-line-item.render-after', () => <CartLineItem />);

function CartLineItem() {
  const settings = useSettings();
  const api = useApi();
  const applyCartLinesChange = useApplyCartLinesChange();
  const checkoutToken = useCheckoutToken();

  const apiUrl = (getSetting(settings, 'api_url') || DEFAULT_API_URL || '').replace(/\/$/, '');
  const secret = (getSetting(settings, 'extension_secret') || '').trim();
  const shopDomain = (getSetting(settings, 'shop_domain') || '').trim();
  const runtimeShop = typeof api?.shop?.myshopifyDomain === 'string' ? api.shop.myshopifyDomain : null;
  const shop = shopDomain || runtimeShop || '';
  const sessionKey = (typeof checkoutToken === 'string' && checkoutToken) ? checkoutToken : '';

  const [experience, setExperience] = useState({ quantity_in_cart_enabled: false, subscription_upgrade: { enabled: false, cta: 'Upgrade to subscription' } });
  const [sellingPlans, setSellingPlans] = useState([]);
  const [upgrading, setUpgrading] = useState(false);
  const [qtyLoading, setQtyLoading] = useState(false);

  const line = useCartLineTarget();
  const retryRef = useRef(null);

  useEffect(() => {
    sendLog(apiUrl, secret, {
      phase: 'cart_line_mount',
      has_api: !!apiUrl,
      has_secret: !!secret,
      has_shop: !!shop,
      has_session_key: !!sessionKey,
      shop_preview: shop ? `${shop.slice(0, 8)}…` : '',
    });
  }, [apiUrl, secret, shop, sessionKey]);

  const fetchExperience = useCallback(() => {
    if (!apiUrl || !secret || !shop) return;
    const body = { shop };
    if (sessionKey) body.session_key = sessionKey;
    fetch(`${apiUrl}/api/checkout/experience`, {
      method: 'POST',
      headers: { 'X-Extension-Secret': secret, 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(body),
    })
      .then((r) => {
        const ok = r.ok;
        return r.json().then((data) => ({ ok, status: r.status, data }));
      })
      .then(({ ok, status, data }) => {
        const next = {
          quantity_in_cart_enabled: Boolean(data?.quantity_in_cart_enabled),
          subscription_upgrade: data?.subscription_upgrade && typeof data.subscription_upgrade === 'object'
            ? data.subscription_upgrade
            : { enabled: false, headline: '', cta: 'Upgrade to subscription' },
        };
        setExperience(next);
        sendLog(apiUrl, secret, {
          phase: 'cart_line_experience_response',
          status,
          ok,
          quantity_in_cart_enabled: next.quantity_in_cart_enabled,
          subscription_upgrade_enabled: next.subscription_upgrade?.enabled,
        });
        if (sessionKey && !next.quantity_in_cart_enabled && !next.subscription_upgrade?.enabled) {
          retryRef.current = setTimeout(() => fetchExperience(), 2000);
        }
      })
      .catch(() => {});
  }, [apiUrl, secret, shop, sessionKey]);

  useEffect(() => {
    fetchExperience();
    return () => {
      if (retryRef.current) clearTimeout(retryRef.current);
    };
  }, [fetchExperience]);

  const hasSellingPlan = Boolean(line?.merchandise?.sellingPlan ?? line?.sellingPlanAllocation?.sellingPlan?.id);

  useEffect(() => {
    if (!experience.subscription_upgrade?.enabled || !line?.merchandise?.id || hasSellingPlan) {
      setSellingPlans([]);
      return;
    }
    if (!apiUrl || !secret || !shop) return;
    fetch(`${apiUrl}/api/checkout/selling-plans-for-variant`, {
      method: 'POST',
      headers: { 'X-Extension-Secret': secret, 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ shop, variant_id: line.merchandise.id }),
    })
      .then((r) => (r.ok ? r.json() : {}))
      .then((data) => setSellingPlans(Array.isArray(data.selling_plans) ? data.selling_plans : []))
      .catch(() => setSellingPlans([]));
  }, [experience.subscription_upgrade?.enabled, line?.merchandise?.id, hasSellingPlan, apiUrl, secret, shop]);

  const canChangeCart = true;

  if (!line || !line.id || !applyCartLinesChange) return null;

  const quantity = Number(line.quantity) || 1;
  const showQuantity = experience.quantity_in_cart_enabled && canChangeCart;
  const showUpgrade = experience.subscription_upgrade?.enabled && !hasSellingPlan && sellingPlans.length > 0 && canChangeCart;
  const firstPlan = sellingPlans[0];

  const handleQuantityChange = async (newQty) => {
    const n = Math.max(1, parseInt(newQty, 10));
    if (n === quantity || qtyLoading) return;
    if (typeof console !== 'undefined' && console.log) console.log('qty_change', { lineId: line.id, from: quantity, to: n });
    setQtyLoading(true);
    try {
      const res = await applyCartLinesChange({ type: 'updateCartLine', id: line.id, quantity: n });
      if (typeof console !== 'undefined' && console.log) console.log('qty_change_result', res);
    } catch (e) {
      if (typeof console !== 'undefined' && console.log) console.log('qty_change_error', String(e?.message ?? e));
    } finally {
      setQtyLoading(false);
    }
  };

  const handleUpgradeToSubscription = async () => {
    if (!firstPlan || upgrading) return;
    if (typeof console !== 'undefined' && console.log) console.log('upgrade_start', { lineId: line.id, sellingPlanId: firstPlan.id });
    setUpgrading(true);
    try {
      await applyCartLinesChange({
        type: 'addCartLine',
        merchandiseId: line.merchandise.id,
        quantity,
        sellingPlanId: firstPlan.id,
      });
      if (typeof console !== 'undefined' && console.log) console.log('upgrade_add_ok');
      await applyCartLinesChange({ type: 'removeCartLine', id: line.id, quantity });
      if (typeof console !== 'undefined' && console.log) console.log('upgrade_remove_ok');
    } catch (e) {
      if (typeof console !== 'undefined' && console.log) console.log('upgrade_error', String(e?.message ?? e));
    }
    setUpgrading(false);
  };

  if (!showQuantity && !showUpgrade) return null;

  const upgradeCta = experience.subscription_upgrade.cta || 'Upgrade to Subscribe and save';

  return (
    <BlockStack spacing="tight">
      {showQuantity && (
        <Disclosure defaultOpen={false}>
          <Button kind="plain" size="small" appearance="monochrome">
            Modify
          </Button>
          <BlockStack spacing="tight" id="modify-qty-content">
            <InlineLayout spacing="tight" blockAlignment="center">
              <Text appearance="subdued" size="small">Qty</Text>
              <Button kind="plain" size="small" onPress={() => handleQuantityChange(quantity - 1)} disabled={quantity <= 1 || qtyLoading}>−</Button>
              <Text size="small">{String(quantity)}</Text>
              <Button kind="plain" size="small" onPress={() => handleQuantityChange(quantity + 1)} disabled={qtyLoading}>+</Button>
            </InlineLayout>
          </BlockStack>
        </Disclosure>
      )}
      {showUpgrade && (
        <Button kind="primary" size="small" onPress={handleUpgradeToSubscription} disabled={upgrading}>
          {upgradeCta}
        </Button>
      )}
    </BlockStack>
  );
}
