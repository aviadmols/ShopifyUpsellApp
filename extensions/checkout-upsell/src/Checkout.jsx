/**
 * Checkout upsell: fetches offers from Laravel and adds selected variant to cart.
 * Requires extension settings: api_url, extension_secret.
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

const DEFAULT_API_URL = 'https://shopifyupsellapp-production.up.railway.app';
const DEFAULT_SHOP_DOMAIN = 'millsdailypacks.myshopify.com';

export default reactExtension('purchase.checkout.block.render', () => <CheckoutUpsell />);

function CheckoutUpsell() {
  const settings = useSettings();
  const applyCartLinesChange = useApplyCartLinesChange();
  const [offers, setOffers] = useState([]);
  const [loading, setLoading] = useState(true);
  const [added, setAdded] = useState(new Set());

  const apiUrl = (settings.api_url || DEFAULT_API_URL || '').replace(/\/$/, '');
  const secret = settings.extension_secret || '';
  const shopDomain = settings.shop_domain || DEFAULT_SHOP_DOMAIN;

  useEffect(() => {
    if (!apiUrl || !secret || !shopDomain) {
      setLoading(false);
      return;
    }
    fetch(`${apiUrl}/api/checkout/offers?shop=${encodeURIComponent(shopDomain)}`, {
      headers: { 'X-Extension-Secret': secret, Accept: 'application/json' },
    })
      .then((r) => (r.ok ? r.json() : { offers: [] }))
      .then((data) => setOffers(data.offers || []))
      .catch(() => setOffers([]))
      .finally(() => setLoading(false));
  }, [apiUrl, secret, shopDomain]);

  const addToCart = async (variantId) => {
    if (added.has(variantId)) return;
    try {
      await applyCartLinesChange({
        type: 'addCartLine',
        merchandiseId: variantId,
        quantity: 1,
      });
      setAdded((s) => new Set([...s, variantId]));
    } catch (_) {}
  };

  if (loading || offers.length === 0) return null;

  return (
    <BlockStack spacing="loose">
      <Text size="medium" emphasis="bold">
        Add to your order
      </Text>
      <Divider />
      {offers.map((offer) => (
        <BlockStack key={offer.id} spacing="tight">
          {offer.image_url && <Image url={offer.image_url} alt={offer.title} />}
          <Text>{offer.title}</Text>
          {offer.description && (
            <Text appearance="subdued">{offer.description}</Text>
          )}
          <Button
            kind="secondary"
            onPress={() => addToCart(offer.variant_id)}
            disabled={added.has(offer.variant_id)}
          >
            {added.has(offer.variant_id) ? 'Added' : 'Add to order'}
          </Button>
        </BlockStack>
      ))}
    </BlockStack>
  );
}
