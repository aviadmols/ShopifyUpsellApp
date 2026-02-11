/**
 * Checkout upsell: fetches offers from Laravel and adds selected variant to cart.
 * Debug panel (BUILD_ID, API, Status) only when no offers or error, and only if block setting show_debug_when_empty is true.
 */
import {
  reactExtension,
  useSettings,
  BlockStack,
  Button,
  Text,
  Image,
  Divider,
  Progress,
  useApplyCartLinesChange,
  useSubtotalAmount,
} from '@shopify/ui-extensions-react/checkout';
import { useEffect, useState } from 'react';

const BUILD_ID = 'zyg-upsell-checkout-20260210';
const DEFAULT_API_URL = 'https://shopifyupsellapp-production.up.railway.app';
const DEFAULT_SHOP = 'millsdailypacks.myshopify.com';

function shortenUrl(url) {
  if (!url || url.length < 40) return url || '';
  return url.slice(0, 24) + '…' + url.slice(-12);
}

function shortenShop(shop) {
  if (!shop || shop.length <= 25) return shop || '';
  return shop.slice(0, 12) + '…' + shop.slice(-10);
}

export default reactExtension('purchase.checkout.block.render', () => <CheckoutUpsell />);

function CheckoutUpsell() {
  const settings = useSettings();
  const applyCartLinesChange = useApplyCartLinesChange();
  const subtotalMoney = useSubtotalAmount();
  const [offers, setOffers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [added, setAdded] = useState(new Set());
  const [status, setStatus] = useState({
    type: 'idle',
    message: 'Initializing…',
    detail: '',
  });

  const apiUrl = (settings.api_url || DEFAULT_API_URL || '').replace(/\/$/, '');
  const secret = (settings.extension_secret || '').trim();
  const shopDomain = (settings.shop_domain || '').trim();
  const shop = shopDomain || DEFAULT_SHOP;
  const showDebugWhenEmpty = settings.show_debug_when_empty === true;

  const [displayMode, setDisplayMode] = useState('stacked');
  const [ui, setUi] = useState({
    section_heading: 'Add to your order',
    title_size: 'medium',
    title_appearance: 'default',
    show_price: true,
    show_description: true,
    image_aspect_ratio: '',
    image_fit: 'cover',
    image_corner_radius: 'base',
    button_kind: 'secondary',
    button_appearance: 'default',
    card_spacing: 'loose',
    divider_between_cards: false,
  });

  useEffect(() => {
    if (!apiUrl || !secret) {
      setLoading(false);
      setStatus({
        type: 'not_configured',
        message: 'Block not connected',
        detail: 'Set "Extension secret" in Block settings (required). API URL and Shop domain are optional.',
      });
      return;
    }
    setStatus({ type: 'loading', message: 'Connecting to app…', detail: '' });

    fetch(`${apiUrl}/api/checkout/offers?shop=${encodeURIComponent(shop)}`, {
      headers: { 'X-Extension-Secret': secret, Accept: 'application/json' },
    })
      .then((r) => {
        if (r.ok) {
          return r.json().then((data) => {
            setOffers(data.offers || []);
            const dm = data.display_mode ?? data.ui?.display_mode ?? 'stacked';
            setDisplayMode(dm === 'single' ? 'single' : 'stacked');
            if (data.ui && typeof data.ui === 'object') {
              setUi((prev) => ({ ...prev, ...data.ui }));
            }
            const count = (data.offers || []).length;
            setStatus({
              type: 'connected',
              message: count ? `Connected — ${count} offer(s)` : 'Connected — no offers for this cart',
              detail: count ? 'Upsell active' : 'Add offers in Admin → Offers and enable Checkout placement.',
            });
          });
        }
        const statusCode = r.status;
        let detail = `HTTP ${statusCode}`;
        if (statusCode === 401) detail = 'Invalid Extension secret. Check CHECKOUT_EXTENSION_SECRET in Railway.';
        else if (statusCode === 404) detail = 'Shop not found. Check Shop domain or add shop in the app.';
        else if (statusCode >= 500) detail = 'Server error. Check app logs on Railway.';
        setStatus({ type: 'error', message: 'Connection failed', detail });
        setOffers([]);
      })
      .catch((err) => {
        setStatus({
          type: 'error',
          message: 'Connection failed',
          detail: err && err.message ? err.message : 'Network error. Check API URL and CORS.',
        });
        setOffers([]);
      })
      .finally(() => setLoading(false));
  }, [apiUrl, secret, shopDomain]);

  const addToCart = async (variantId, sellingPlanId = null) => {
    if (added.has(variantId)) return;
    try {
      const line = {
        type: 'addCartLine',
        merchandiseId: variantId,
        quantity: 1,
      };
      if (sellingPlanId) {
        line.sellingPlanId = sellingPlanId;
      }
      await applyCartLinesChange(line);
      setAdded((s) => new Set([...s, variantId]));
    } catch (_) {}
  };

  const progressBar = ui.progress_bar && ui.progress_bar.enabled && Number(ui.progress_bar.goal) > 0 ? ui.progress_bar : null;
  const currentSubtotal = Number(subtotalMoney?.amount) || 0;
  const goalAmount = progressBar ? Number(progressBar.goal) : 0;
  const progress = goalAmount > 0 ? Math.min(currentSubtotal / goalAmount, 1) : 0;
  const remaining = Math.max(0, goalAmount - currentSubtotal);
  const currencyCode = subtotalMoney?.currencyCode || 'USD';
  const formatMoney = (value) => `${currencyCode} ${Number(value).toFixed(2)}`;
  const progressMessage = progressBar
    ? (currentSubtotal >= goalAmount
        ? (progressBar.message_achieved || "You've unlocked free shipping!")
        : (progressBar.message_below || "You're {amount} away from free shipping!")
            .replace(/{amount}/g, formatMoney(remaining))
            .replace(/{goal}/g, formatMoney(goalAmount)))
    : '';

  const statusLine =
    status.type === 'not_configured'
      ? 'Not configured'
      : status.type === 'loading'
        ? 'Loading'
        : status.type === 'error'
          ? (status.detail || 'Error')
          : status.type === 'connected'
            ? (offers.length ? 'Connected' : 'No offers')
            : status.message;

  const fullDebugBlock = (
    <BlockStack spacing="extraTight">
      <Text size="medium" emphasis="bold">Checkout Upsell</Text>
      <Text appearance="subdued" size="small">{BUILD_ID}</Text>
      <Text appearance="subdued" size="small">API: {shortenUrl(apiUrl)}</Text>
      <Text appearance="subdued" size="small">Shop: {shortenShop(shop)}</Text>
      <Text appearance="subdued" size="small">Status: {statusLine}</Text>
      {status.detail && status.type === 'error' && (
        <Text appearance="subdued" size="small">{status.detail}</Text>
      )}
      {status.detail && status.type === 'not_configured' && (
        <Text appearance="subdued" size="small">{status.detail}</Text>
      )}
    </BlockStack>
  );

  const minimalMessage = (
    <Text appearance="subdued" size="small">
      {status.type === 'not_configured' ? 'Not configured' : status.type === 'error' ? 'Connection error' : 'No offers right now'}
    </Text>
  );

  const showEmptyOrError = loading || status.type === 'not_configured' || status.type === 'error' || offers.length === 0;
  const debugContent = showDebugWhenEmpty ? fullDebugBlock : minimalMessage;

  if (offers.length > 0 && !loading && status.type !== 'error' && status.type !== 'not_configured') {
    const offersToShow = displayMode === 'single' ? offers.slice(0, 1) : offers;
    const sectionSpacing = ui.card_spacing === 'tight' ? 'tight' : ui.card_spacing === 'extraLoose' ? 'extraLoose' : 'loose';
    const cardSpacing = ui.card_spacing === 'tight' ? 'tight' : ui.card_spacing === 'extraLoose' ? 'extraLoose' : 'loose';
    const titleSize = ['small', 'medium', 'large', 'extraLarge'].includes(ui.title_size) ? ui.title_size : 'medium';
    const titleAppearance = ui.title_appearance && ui.title_appearance !== 'default' ? ui.title_appearance : undefined;
    const buttonKind = ['primary', 'secondary', 'plain'].includes(ui.button_kind) ? ui.button_kind : 'secondary';
    const buttonAppearance = ui.button_appearance && ui.button_appearance !== 'default' ? ui.button_appearance : undefined;

    return (
      <BlockStack spacing={sectionSpacing}>
        {progressBar && (
          <BlockStack spacing="tight">
            <Text size="medium" emphasis="bold">{progressMessage}</Text>
            <Progress value={progress} max={1} accessibilityLabel={progressMessage} />
          </BlockStack>
        )}
        <Text size={titleSize} emphasis="bold" {...(titleAppearance ? { appearance: titleAppearance } : {})}>
          {ui.section_heading || 'Add to your order'}
        </Text>
        {offersToShow.map((offer, index) => (
          <BlockStack key={offer.id} spacing={cardSpacing}>
            {index > 0 && ui.divider_between_cards && <Divider />}
            {offer.image_url && (
              <Image
                source={offer.image_url}
                accessibilityDescription={offer.title || 'Product'}
                {...(ui.image_aspect_ratio && { aspectRatio: parseFloat(ui.image_aspect_ratio) || undefined })}
                {...(ui.image_fit && { fit: ui.image_fit })}
                {...(ui.image_corner_radius && { cornerRadius: ui.image_corner_radius })}
              />
            )}
            <Text size={titleSize} {...(titleAppearance ? { appearance: titleAppearance } : {})}>
              {offer.title}
            </Text>
            {ui.show_price && offer.price != null && offer.price !== '' && (
              <Text appearance="subdued" size="small">${offer.price}</Text>
            )}
            {(offer.offer_type === 'subscription' || offer.offer_type === 'both') && (
              <Text appearance="subdued" size="small">Subscribe & save</Text>
            )}
            {ui.show_description && offer.description && (
              <Text appearance="subdued">{offer.description}</Text>
            )}
            <BlockStack spacing="tight">
              <Button
                kind={buttonKind}
                {...(buttonAppearance ? { appearance: buttonAppearance } : {})}
                onPress={() => addToCart(offer.variant_id, offer.selling_plan_id || null)}
                disabled={added.has(offer.variant_id)}
              >
                {added.has(offer.variant_id) ? 'Added' : (offer.offer_type === 'subscription' ? 'Add as subscription' : 'Add to order')}
              </Button>
            </BlockStack>
          </BlockStack>
        ))}
      </BlockStack>
    );
  }

  return (
    <BlockStack spacing="tight">
      {progressBar && (
        <BlockStack spacing="tight">
          <Text size="medium" emphasis="bold">{progressMessage}</Text>
          <Progress value={progress} max={1} accessibilityLabel={progressMessage} />
        </BlockStack>
      )}
      {debugContent}
      {showDebugWhenEmpty && status.type === 'error' && (
        <Text size="small" appearance="subdued">Fix Block settings or check the app, then refresh checkout.</Text>
      )}
      {showDebugWhenEmpty && offers.length === 0 && !loading && status.type === 'connected' && (
        <Text size="small" appearance="subdued">Add offers in Admin → Offers and enable Checkout placement for this shop.</Text>
      )}
    </BlockStack>
  );
}
