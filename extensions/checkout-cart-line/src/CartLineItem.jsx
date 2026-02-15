import {
  reactExtension,
  useSettings,
  useApi,
  useCheckoutToken,
  useCartLineTarget,
  useSubtotalAmount,
  BlockStack,
  Text,
  Button,
  InlineLayout,
  Popover,
  Icon,
  useApplyCartLinesChange,
} from '@shopify/ui-extensions-react/checkout';
import { useCallback, useEffect, useRef, useState } from 'react';

const DEFAULT_API_URL = 'https://shopifyupsellapp-production.up.railway.app';
const DEFAULT_EXTENSION_SECRET = '89987874564648484';
const CART_LINE_BUILD_ID = 'zyg-cart-line-20260212';

function getSetting(settings, key) {
  if (!settings || typeof settings !== 'object') return undefined;
  const raw = settings[key];
  if (raw === undefined || raw === null) return undefined;
  if (typeof raw === 'object' && raw !== null && 'value' in raw) return raw.value;
  return raw;
}

async function safeJson(res) {
  const text = await res.text();
  try {
    return { ok: res.ok, status: res.status, data: JSON.parse(text) };
  } catch {
    return { ok: res.ok, status: res.status, data: null, raw: text };
  }
}

function sendLog(apiUrl, secret, payload) {
  if (!apiUrl) return;
  const url = `${String(apiUrl).replace(/\/$/, '')}/api/checkout/logs`;
  const headers = { 'Content-Type': 'application/json', Accept: 'application/json' };
  if (secret) headers['X-Extension-Secret'] = secret;
  fetch(url, {
    method: 'POST',
    headers,
    body: JSON.stringify({
      ts: new Date().toISOString(),
      build_id: CART_LINE_BUILD_ID,
      secret_present: !!secret,
      ...payload,
    }),
  }).catch(() => {});
}

export default reactExtension('purchase.checkout.cart-line-item.render-after', () => <CartLineItem />);

function CartLineItem() {
  const settings = useSettings();
  const api = useApi();
  const applyCartLinesChange = useApplyCartLinesChange();
  const checkoutToken = useCheckoutToken();
  const line = useCartLineTarget();

  const apiUrl = (getSetting(settings, 'api_url') || DEFAULT_API_URL || '').replace(/\/$/, '');
  const secret = (getSetting(settings, 'extension_secret') || DEFAULT_EXTENSION_SECRET).trim();
  const shopDomain = (getSetting(settings, 'shop_domain') || '').trim();
  const checkoutExperienceIdRaw = getSetting(settings, 'checkout_experience_id');
  const checkoutExperienceId = (checkoutExperienceIdRaw != null && String(checkoutExperienceIdRaw).trim() !== '')
    ? (parseInt(String(checkoutExperienceIdRaw).trim(), 10) || 0)
    : 0;
  const runtimeShop = typeof api?.shop?.myshopifyDomain === 'string' ? api.shop.myshopifyDomain : null;
  const shop = shopDomain || runtimeShop || '';
  const sessionKey = typeof checkoutToken === 'string' && checkoutToken ? checkoutToken : '';

  const subtotalAmount = useSubtotalAmount();
  const cartLines = (typeof api?.lines?.current === 'function' ? api?.lines?.current() : api?.lines?.current) ?? api?.lines ?? [];
  const cartLinesArray = Array.isArray(cartLines) ? cartLines : (cartLines?.value ? (Array.isArray(cartLines.value) ? cartLines.value : []) : []);

  const lineProductId = line?.merchandise?.product?.id ?? line?.merchandise?.product?.gid ?? null;
  const lineVariantId = line?.merchandise?.id ?? null;
  const lineHasSellingPlan = Boolean(line?.sellingPlanAllocation?.sellingPlan?.id);
  const cartSubtotal = subtotalAmount?.amount != null ? Number(subtotalAmount.amount) : null;
  const cartItemsCount = Array.isArray(cartLinesArray) ? cartLinesArray.length : 0;

  const [experience, setExperience] = useState({
    quantity_in_cart_enabled: false,
    show_quantity: false,
    show_subscription: false,
    subscription_upgrade: { enabled: false, cta: 'Upgrade to subscription' },
    cart_line_ui: {
      modify_alignment: 'left',
      show_chevron: true,
      quantity_size: 'medium',
      popover_width: { mode: 'preset', preset: 'md', px: null, padding_x: 'base' },
      quantity_label: { text: 'Quantity', size: 'medium', alignment: 'left' },
      plus_minus: { kind: 'plain', appearance: 'monochrome', size: 'small', corner_radius: 'base' },
    },
  });
  const [sellingPlans, setSellingPlans] = useState([]);
  const [upgrading, setUpgrading] = useState(false);
  const [qtyLoading, setQtyLoading] = useState(false);
  const [popoverOpen, setPopoverOpen] = useState(false);

  const retryRef = useRef(null);

  useEffect(() => {
    const urlForLog = apiUrl || DEFAULT_API_URL;
    sendLog(urlForLog, secret, {
      phase: 'cart_line_mount',
      has_api: !!apiUrl,
      has_secret: !!secret,
      has_shop: !!shop,
      has_session_key: !!sessionKey,
      shop: shop || null,
    });
  }, [apiUrl, secret, shop, sessionKey]);

  const fetchExperience = useCallback(async () => {
    if (!apiUrl || !secret || !shop) return;

    if (retryRef.current) {
      clearTimeout(retryRef.current);
      retryRef.current = null;
    }

    const body = { shop };
    if (sessionKey) body.session_key = sessionKey;
    if (checkoutExperienceId >= 1) body.checkout_experience_id = checkoutExperienceId;
    if (lineProductId != null && lineProductId !== '') body.line_product_id = lineProductId;
    if (lineVariantId != null && lineVariantId !== '') body.line_variant_id = lineVariantId;
    body.line_has_selling_plan = lineHasSellingPlan;
    if (cartSubtotal != null) body.cart_subtotal = cartSubtotal;
    if (cartItemsCount != null) body.cart_items_count = cartItemsCount;

    try {
      const res = await fetch(`${apiUrl}/api/checkout/experience`, {
        method: 'POST',
        headers: { 'X-Extension-Secret': secret, 'Content-Type': 'application/json', Accept: 'application/json' },
        body: JSON.stringify(body),
      });

      const parsed = await safeJson(res);
      const data = parsed.data || {};

      const ui = data?.cart_line_ui && typeof data.cart_line_ui === 'object' ? data.cart_line_ui : {};
      const popoverWidth = ui.popover_width && typeof ui.popover_width === 'object' ? ui.popover_width : { mode: 'preset', preset: 'md', px: null, padding_x: 'base' };
      const quantityLabel = ui.quantity_label && typeof ui.quantity_label === 'object' ? ui.quantity_label : { text: 'Quantity', size: 'medium', alignment: 'left' };
      const plusMinus = ui.plus_minus && typeof ui.plus_minus === 'object' ? ui.plus_minus : { kind: 'plain', appearance: 'monochrome', size: 'small', corner_radius: 'base' };
      const next = {
        quantity_in_cart_enabled: Boolean(data?.quantity_in_cart_enabled),
        show_quantity: typeof data?.show_quantity === 'boolean' ? data.show_quantity : Boolean(data?.quantity_in_cart_enabled),
        show_subscription: typeof data?.show_subscription === 'boolean' ? data.show_subscription : Boolean(data?.subscription_upgrade?.enabled),
        subscription_upgrade:
          data?.subscription_upgrade && typeof data.subscription_upgrade === 'object'
            ? data.subscription_upgrade
            : { enabled: false, headline: '', cta: 'Upgrade to subscription' },
        cart_line_ui: {
          modify_alignment: ['left', 'center', 'right'].includes(ui.modify_alignment) ? ui.modify_alignment : 'left',
          show_chevron: Boolean(ui.show_chevron !== false),
          quantity_size: ['small', 'medium', 'large'].includes(ui.quantity_size) ? ui.quantity_size : 'medium',
          popover_width: popoverWidth,
          quantity_label: quantityLabel,
          plus_minus: plusMinus,
        },
      };

      setExperience(next);

      sendLog(apiUrl, secret, {
        phase: 'cart_line_experience_response',
        ok: parsed.ok,
        status: parsed.status,
        show_quantity: next.show_quantity,
        show_subscription: next.show_subscription,
        non_json_response: parsed.data ? false : true,
      });

      if (sessionKey && !next.show_quantity && !next.show_subscription) {
        retryRef.current = setTimeout(() => fetchExperience(), 2000);
      }
    } catch (e) {
      sendLog(apiUrl, secret, {
        phase: 'cart_line_experience_error',
        error: String(e?.message ?? e),
      });
    }
  }, [apiUrl, secret, shop, sessionKey, checkoutExperienceId, lineProductId, lineVariantId, lineHasSellingPlan, cartSubtotal, cartItemsCount]);

  useEffect(() => {
    fetchExperience();
    return () => {
      if (retryRef.current) clearTimeout(retryRef.current);
    };
  }, [fetchExperience]);

  const hasSellingPlan = Boolean(line?.sellingPlanAllocation?.sellingPlan?.id);

  useEffect(() => {
    if (!experience.show_subscription || !line?.merchandise?.id || hasSellingPlan) {
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
  }, [experience.show_subscription, line?.merchandise?.id, hasSellingPlan, apiUrl, secret, shop]);

  if (!line || !line.id || !applyCartLinesChange) return null;

  const quantity = Number(line.quantity) || 1;
  const showQuantity = Boolean(experience.show_quantity);
  const showUpgrade = Boolean(experience.show_subscription) && !hasSellingPlan && sellingPlans.length > 0;
  const firstPlan = sellingPlans[0];

  const handleQuantityChange = async (newQty) => {
    const n = Math.max(1, parseInt(newQty, 10));
    if (!Number.isFinite(n) || n === quantity || qtyLoading) return;

    sendLog(apiUrl, secret, {
      phase: 'cart_line_qty_click',
      line_id: line.id,
      from_qty: quantity,
      to_qty: n,
    });

    setQtyLoading(true);
    try {
      await applyCartLinesChange({ type: 'updateCartLine', id: line.id, quantity: n });
      sendLog(apiUrl, secret, { phase: 'cart_line_qty_result', line_id: line.id });
    } catch (e) {
      sendLog(apiUrl, secret, { phase: 'cart_line_qty_error', line_id: line.id, error: String(e?.message ?? e) });
    } finally {
      setQtyLoading(false);
    }
  };

  const handleUpgradeToSubscription = async () => {
    if (!firstPlan || upgrading) return;

    setUpgrading(true);
    try {
      await applyCartLinesChange({
        type: 'addCartLine',
        merchandiseId: line.merchandise.id,
        quantity,
        sellingPlanId: firstPlan.id,
      });
      await applyCartLinesChange({ type: 'removeCartLine', id: line.id, quantity });
      sendLog(apiUrl, secret, { phase: 'cart_line_upgrade_ok', line_id: line.id, selling_plan_id: firstPlan.id });
    } catch (e) {
      sendLog(apiUrl, secret, { phase: 'cart_line_upgrade_error', line_id: line.id, error: String(e?.message ?? e) });
    } finally {
      setUpgrading(false);
    }
  };

  const handleRemove = async () => {
    sendLog(apiUrl, secret, { phase: 'cart_line_remove_click', line_id: line.id });
    try {
      await applyCartLinesChange({ type: 'removeCartLine', id: line.id, quantity });
      sendLog(apiUrl, secret, { phase: 'cart_line_remove_ok', line_id: line.id });
    } catch (e) {
      sendLog(apiUrl, secret, { phase: 'cart_line_remove_error', line_id: line.id, error: String(e?.message ?? e) });
    }
  };

  if (!showQuantity && !showUpgrade) return null;

  const upgradeCta = experience.subscription_upgrade?.cta || 'Upgrade to Subscribe and save';
  const ui = experience.cart_line_ui || {};
  const modifyAlignment = ui.modify_alignment === 'center' ? 'center' : ui.modify_alignment === 'right' ? 'end' : 'start';
  const showChevron = ui.show_chevron !== false;
  const quantitySize = ['small', 'medium', 'large'].includes(ui.quantity_size) ? ui.quantity_size : 'medium';
  const quantitySpacing = quantitySize === 'large' ? 'loose' : quantitySize === 'medium' ? 'base' : 'tight';

  const pw = ui.popover_width || {};
  const presetToPx = { sm: 240, md: 320, lg: 400, xl: 480 };
  const popoverPx = pw.mode === 'custom' && typeof pw.px === 'number' ? pw.px : (presetToPx[pw.preset] ?? 320);
  const popoverWidthPx = `${popoverPx}px`;
  const popoverPaddingX = ['none', 'tight', 'base', 'loose'].includes(pw.padding_x) ? pw.padding_x : 'base';
  const labelCfg = ui.quantity_label || {};
  const quantityLabelText = String(labelCfg.text || 'Quantity');
  const quantityLabelSize = ['small', 'medium', 'large'].includes(labelCfg.size) ? labelCfg.size : quantitySize;
  const quantityLabelAlignment = labelCfg.alignment === 'center' ? 'center' : labelCfg.alignment === 'right' ? 'end' : 'start';
  const pm = ui.plus_minus || {};
  const plusMinusKind = ['plain', 'secondary', 'primary'].includes(pm.kind) ? pm.kind : 'plain';
  const plusMinusAppearance = ['default', 'monochrome', 'critical'].includes(pm.appearance) ? pm.appearance : 'monochrome';
  const plusMinusSize = ['small', 'medium', 'large'].includes(pm.size) ? pm.size : 'small';

  return (
    <BlockStack spacing="tight">
      {showQuantity && (
        <InlineLayout spacing="tight" blockAlignment={modifyAlignment} inlineAlignment={modifyAlignment}>
          <Button
            kind={plusMinusKind}
            size={plusMinusSize}
            appearance={plusMinusAppearance}
            overlay={
              <Popover
                position="blockEnd"
                onOpen={() => setPopoverOpen(true)}
                onClose={() => setPopoverOpen(false)}
                minInlineSize={popoverWidthPx}
                maxInlineSize={popoverWidthPx}
                padding={['base', popoverPaddingX]}
              >
                <BlockStack spacing={quantitySpacing}>
                  <InlineLayout inlineAlignment={quantityLabelAlignment}>
                    <Text appearance="subdued" size={quantityLabelSize}>{quantityLabelText}</Text>
                  </InlineLayout>
                  <InlineLayout spacing={quantitySpacing} blockAlignment="center">
                    <Button kind={plusMinusKind} size={plusMinusSize} appearance={plusMinusAppearance} onPress={() => handleQuantityChange(quantity - 1)} disabled={quantity <= 1 || qtyLoading}>−</Button>
                    <Text size={quantitySize}>{String(quantity)}</Text>
                    <Button kind={plusMinusKind} size={plusMinusSize} appearance={plusMinusAppearance} onPress={() => handleQuantityChange(quantity + 1)} disabled={qtyLoading}>+</Button>
                  </InlineLayout>
                  <Button kind="plain" size="small" appearance="monochrome" onPress={handleRemove}>Remove</Button>
                </BlockStack>
              </Popover>
            }
          >
            <InlineLayout spacing="extraTight" blockAlignment="center" inlineAlignment="center">
              <Text>Modify</Text>
              {showChevron && (
                <Icon source={popoverOpen ? 'chevronUp' : 'chevronDown'} size="small" accessibilityLabel={popoverOpen ? 'Close' : 'Open'} />
              )}
            </InlineLayout>
          </Button>
        </InlineLayout>
      )}

      {showUpgrade && (
        <Button kind="primary" size="small" onPress={handleUpgradeToSubscription} disabled={upgrading}>
          {upgradeCta}
        </Button>
      )}
    </BlockStack>
  );
}
