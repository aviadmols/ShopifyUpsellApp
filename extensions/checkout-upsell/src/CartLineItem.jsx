/**
 * Cart line item extension: quantity +/- and "Upgrade to subscription" per line.
 * Target: purchase.checkout.cart-line-item.render-after
 */
import {
  reactExtension,
  useSettings,
  useApi,
  BlockStack,
  Text,
  Button,
  InlineLayout,
  useApplyCartLinesChange,
} from '@shopify/ui-extensions-react/checkout';
import { useEffect, useState } from 'react';

const DEFAULT_API_URL = 'https://shopifyupsellapp-production.up.railway.app';

function getSetting(settings, key) {
  if (!settings || typeof settings !== 'object') return undefined;
  const raw = settings[key];
  if (raw === undefined || raw === null) return undefined;
  if (typeof raw === 'object' && raw !== null && 'value' in raw) return raw.value;
  return raw;
}

export default reactExtension('purchase.checkout.cart-line-item.render-after', () => <CartLineItem />);

function CartLineItem() {
  const settings = useSettings();
  const api = useApi();
  const applyCartLinesChange = useApplyCartLinesChange();

  const apiUrl = (getSetting(settings, 'api_url') || DEFAULT_API_URL || '').replace(/\/$/, '');
  const secret = (getSetting(settings, 'extension_secret') || '').trim();
  const shopDomain = (getSetting(settings, 'shop_domain') || '').trim();
  const runtimeShop = typeof api?.shop?.myshopifyDomain === 'string' ? api.shop.myshopifyDomain : null;
  const shop = shopDomain || runtimeShop || '';

  const [line, setLine] = useState(null);
  const [experience, setExperience] = useState({ quantity_in_cart_enabled: false, subscription_upgrade: { enabled: false, cta: 'Upgrade to subscription' } });
  const [sellingPlans, setSellingPlans] = useState([]);
  const [upgrading, setUpgrading] = useState(false);

  const target = api?.target;
  useEffect(() => {
    if (!target) return;
    const current = target.current ?? target.value ?? target;
    setLine(current);
    const unsub = typeof target.subscribe === 'function' ? target.subscribe((v) => setLine(v)) : () => {};
    return () => {
      if (typeof unsub === 'function') unsub();
    };
  }, [target]);

  useEffect(() => {
    if (!apiUrl || !secret || !shop) return;
    fetch(`${apiUrl}/api/checkout/experience`, {
      method: 'POST',
      headers: { 'X-Extension-Secret': secret, 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ shop }),
    })
      .then((r) => (r.ok ? r.json() : {}))
      .then((data) => {
        setExperience({
          quantity_in_cart_enabled: Boolean(data.quantity_in_cart_enabled),
          subscription_upgrade: data.subscription_upgrade && typeof data.subscription_upgrade === 'object'
            ? data.subscription_upgrade
            : { enabled: false, headline: '', cta: 'Upgrade to subscription' },
        });
      })
      .catch(() => {});
  }, [apiUrl, secret, shop]);

  useEffect(() => {
    if (!experience.subscription_upgrade?.enabled || !line?.merchandise?.id || line.merchandise.sellingPlan) {
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
  }, [experience.subscription_upgrade?.enabled, line?.merchandise?.id, line?.merchandise?.sellingPlan, apiUrl, secret, shop]);

  const canChangeCart = true;

  if (!line || !line.id || !applyCartLinesChange) return null;

  const quantity = Number(line.quantity) || 1;
  const hasSellingPlan = line.merchandise?.sellingPlan != null;
  const showQuantity = experience.quantity_in_cart_enabled && canChangeCart;
  const showUpgrade = experience.subscription_upgrade?.enabled && !hasSellingPlan && sellingPlans.length > 0 && canChangeCart;
  const firstPlan = sellingPlans[0];

  const handleQuantityChange = (newQty) => {
    const n = Math.max(1, parseInt(newQty, 10));
    if (n === quantity) return;
    applyCartLinesChange({ type: 'updateCartLine', id: line.id, quantity: n }).catch(() => {});
  };

  const handleUpgradeToSubscription = async () => {
    if (!firstPlan || upgrading) return;
    setUpgrading(true);
    try {
      await applyCartLinesChange({ type: 'removeCartLine', id: line.id, quantity });
      await applyCartLinesChange({
        type: 'addCartLine',
        merchandiseId: line.merchandise.id,
        quantity,
        sellingPlanId: firstPlan.id,
      });
    } catch (_) {}
    setUpgrading(false);
  };

  if (!showQuantity && !showUpgrade) return null;

  return (
    <BlockStack spacing="tight">
      {showQuantity && (
        <InlineLayout spacing="tight" blockAlignment="center">
          <Text appearance="subdued" size="small">Qty</Text>
          <Button kind="plain" size="small" onPress={() => handleQuantityChange(quantity - 1)} disabled={quantity <= 1}>−</Button>
          <Text size="small">{String(quantity)}</Text>
          <Button kind="plain" size="small" onPress={() => handleQuantityChange(quantity + 1)}>+</Button>
        </InlineLayout>
      )}
      {showUpgrade && (
        <Button kind="plain" size="small" onPress={handleUpgradeToSubscription} disabled={upgrading}>
          {experience.subscription_upgrade.cta || 'Upgrade to subscription'}
        </Button>
      )}
    </BlockStack>
  );
}
