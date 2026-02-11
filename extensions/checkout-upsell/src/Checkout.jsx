/**
 * Checkout upsell: fetches offers from Laravel and adds selected variant to cart.
 * Always shows BUILD_ID and status so you can verify deploy and debug connection.
 */
import {
  reactExtension,
  useSettings,
  BlockStack,
  Button,
  Text,
  Image,
  Divider,
  useApplyCartLinesChange,
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

  const [displayMode, setDisplayMode] = useState('stacked');

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
            setDisplayMode(data.display_mode === 'single' ? 'single' : 'stacked');
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

  const statusBlock = (
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

  if (loading) {
    return (
      <BlockStack spacing="tight">
        {statusBlock}
      </BlockStack>
    );
  }

  if (status.type === 'not_configured') {
    return (
      <BlockStack spacing="tight">
        {statusBlock}
      </BlockStack>
    );
  }

  if (status.type === 'error') {
    return (
      <BlockStack spacing="loose">
        {statusBlock}
        <Text size="small" appearance="subdued">Fix Block settings or check the app, then refresh checkout.</Text>
      </BlockStack>
    );
  }

  if (offers.length === 0) {
    return (
      <BlockStack spacing="loose">
        {statusBlock}
        <Text size="small" appearance="subdued">Add offers in Admin → Offers and enable Checkout placement for this shop.</Text>
      </BlockStack>
    );
  }

  const offersToShow = displayMode === 'single' ? offers.slice(0, 1) : offers;

  return (
    <BlockStack spacing="loose">
      {statusBlock}
      <Divider />
      <Text size="medium" emphasis="bold">Add to your order</Text>
      {offersToShow.map((offer) => (
        <BlockStack key={offer.id} spacing="tight">
          {offer.image_url && <Image url={offer.image_url} alt={offer.title} />}
          <Text>{offer.title}</Text>
          {(offer.offer_type === 'subscription' || offer.offer_type === 'both') && (
            <Text appearance="subdued" size="small">Subscribe & save</Text>
          )}
          {offer.description && (
            <Text appearance="subdued">{offer.description}</Text>
          )}
          <BlockStack spacing="tight">
            <Button
              kind="secondary"
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
