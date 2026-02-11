/**
 * Post-purchase extension: ShouldRender calls Laravel should-render;
 * Render shows one offer; on accept calls Laravel accept and applies changeset.
 * Configure in Shopify app: extension settings for api_url, extension_secret.
 * Uses Shopify post-purchase extension API (run.*).
 */
import {
  reactExtension,
  useApi,
  BlockStack,
  Button,
  Text,
  Image,
  Divider,
  useApplyCartLinesChange,
  useApplyDiscountCodeChange,
} from '@shopify/ui-extensions-react/checkout';
import { useEffect, useState } from 'react';

// Post-purchase surface uses a different target. This file is a reference;
// actual post-purchase flows use the Run API (ShouldRender + Render).
// When using shopify app generate extension -> Post-purchase, Shopify creates
// the entry that calls your backend for should-render and accept.

export default reactExtension('purchase.post_purchase.render', () => <PostPurchaseOffer />);

function PostPurchaseOffer() {
  const { extension, query } = useApi();
  const applyCartLinesChange = useApplyCartLinesChange();
  const [offer, setOffer] = useState(null);
  const [loading, setLoading] = useState(true);
  const [accepted, setAccepted] = useState(false);

  useEffect(() => {
    const apiUrl = extension?.settings?.api_url?.value ?? '';
    const secret = extension?.settings?.extension_secret?.value ?? '';
    const shop = extension?.settings?.shop_domain?.value ?? '';
    if (!apiUrl || !secret || !shop) {
      setLoading(false);
      return;
    }
    // In real post-purchase, order/cart is provided by Shopify run; here we call should-render.
    fetch(`${apiUrl}/api/post-purchase/should-render`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Extension-Secret': secret,
      },
      body: JSON.stringify({
        shop: shop,
        order_id: extension?.order?.id,
        order: extension?.order,
        line_items: extension?.order?.lineItems,
        customer: extension?.customer,
        shipping_address: extension?.shippingAddress,
      }),
    })
      .then((r) => (r.ok ? r.json() : {}))
      .then((data) => {
        if (data.render && data.offerId) setOffer(data);
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [extension]);

  const handleAccept = async () => {
    if (!offer) return;
    const apiUrl = extension?.settings?.api_url?.value ?? '';
    const secret = extension?.settings?.extension_secret?.value ?? '';
    const shop = extension?.settings?.shop_domain?.value ?? '';
    const idempotencyKey = `pp_${extension?.order?.id}_${offer.offerId}`;
    try {
      const res = await fetch(`${apiUrl}/api/post-purchase/accept`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Extension-Secret': secret,
        },
        body: JSON.stringify({
          shop,
          order_id: extension?.order?.id,
          offer_id: offer.offerId,
          variant_id: offer.variantId,
          idempotency_key: idempotencyKey,
        }),
      });
      const changeset = await res.json();
      if (changeset.cartLines?.add?.length) {
        for (const line of changeset.cartLines.add) {
          await applyCartLinesChange({
            type: 'addCartLine',
            merchandiseId: line.variantId,
            quantity: line.quantity || 1,
          });
        }
      }
      if (changeset.discountCodes?.add?.length) {
        await useApplyDiscountCodeChange?.()?.({ type: 'addDiscountCode', code: changeset.discountCodes.add[0].code });
      }
      setAccepted(true);
    } catch (_) {}
  };

  if (loading || !offer) return null;

  return (
    <BlockStack spacing="loose">
      <Text size="medium" emphasis="bold">
        {offer.message || offer.title}
      </Text>
      <Divider />
      {offer.image_url && <Image url={offer.image_url} alt={offer.message} />}
      {offer.description && <Text appearance="subdued">{offer.description}</Text>}
      <Button kind="primary" onPress={handleAccept} disabled={accepted}>
        {accepted ? 'Added to order' : 'Add to order'}
      </Button>
      <Button kind="secondary" onPress={() => {}}>
        No thanks
      </Button>
    </BlockStack>
  );
}
