/**
 * Post-purchase funnel: multiple offers one after another.
 * ShouldRender returns offers[] + funnel config; user can Accept or Decline (next offer).
 */
import {
  reactExtension,
  useApi,
  BlockStack,
  Button,
  Text,
  Image,
  Divider,
  InlineLayout,
  Stepper,
  Link,
  useApplyCartLinesChange,
  useApplyDiscountCodeChange,
} from '@shopify/ui-extensions-react/checkout';
import { useEffect, useState } from 'react';

export default reactExtension('purchase.post_purchase.render', () => <PostPurchaseFunnel />);

function PostPurchaseFunnel() {
  const { extension } = useApi();
  const applyCartLinesChange = useApplyCartLinesChange();
  const applyDiscountCodeChange = useApplyDiscountCodeChange?.() ?? null;
  const [offers, setOffers] = useState([]);
  const [funnel, setFunnel] = useState(null);
  const [loading, setLoading] = useState(true);
  const [currentStep, setCurrentStep] = useState(0);
  const [quantity, setQuantity] = useState(1);
  const [accepted, setAccepted] = useState(false);
  const [declinedAll, setDeclinedAll] = useState(false);
  const [timeLeft, setTimeLeft] = useState(null);

  const apiUrl = extension?.settings?.api_url?.value ?? '';
  const secret = extension?.settings?.extension_secret?.value ?? '';
  const shop = extension?.settings?.shop_domain?.value ?? '';
  const orderId = extension?.order?.id ?? '';
  const customer = extension?.customer ?? {};
  const first_name = customer?.firstName ?? customer?.first_name ?? 'there';

  useEffect(() => {
    if (!apiUrl || !secret || !shop) {
      setLoading(false);
      return;
    }
    fetch(`${apiUrl}/api/post-purchase/should-render`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-Extension-Secret': secret,
      },
      body: JSON.stringify({
        shop,
        order_id: orderId,
        order: extension?.order,
        line_items: extension?.order?.lineItems,
        customer: extension?.customer,
        shipping_address: extension?.shippingAddress,
      }),
    })
      .then((r) => (r.ok ? r.json() : {}))
      .then((data) => {
        if (data.render && Array.isArray(data.offers) && data.offers.length > 0) {
          setOffers(data.offers);
          setFunnel(data.funnel || {});
          const qDef = data.funnel?.quantity_default ?? 1;
          const qMin = data.funnel?.quantity_min ?? 1;
          const qMax = data.funnel?.quantity_max ?? 10;
          setQuantity(Math.max(qMin, Math.min(qMax, qDef)));
          if (data.funnel?.show_timer && data.funnel?.timer_seconds > 0) {
            setTimeLeft(data.funnel.timer_seconds);
          }
        } else if (data.render && data.offerId) {
          setOffers([{
            offerId: data.offerId,
            variantId: data.variantId,
            title: data.message,
            description: data.description,
            image_url: data.image_url,
            price: data.price,
            discounted_price: null,
            save_percent: null,
            offer_type: data.offer_type ?? 'one_time',
            selling_plan_id: data.selling_plan_id ?? null,
            allow_subscription_in_post_purchase: data.allow_subscription_in_post_purchase ?? false,
          }]);
          setFunnel({});
          setQuantity(1);
        }
      })
      .catch(() => {})
      .finally(() => setLoading(false));
  }, [apiUrl, secret, shop, orderId, extension?.order, extension?.customer, extension?.shippingAddress]);

  useEffect(() => {
    if (timeLeft == null || timeLeft <= 0 || !funnel?.show_timer) return;
    const t = setInterval(() => setTimeLeft((prev) => (prev <= 0 ? 0 : prev - 1)), 1000);
    return () => clearInterval(t);
  }, [timeLeft, funnel?.show_timer]);

  const handleAccept = async () => {
    const offer = offers[currentStep];
    if (!offer) return;
    const idempotencyKey = `pp_${orderId}_${offer.offerId}`;
    try {
      const res = await fetch(`${apiUrl}/api/post-purchase/accept`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Extension-Secret': secret,
        },
        body: JSON.stringify({
          shop,
          order_id: orderId,
          offer_id: offer.offerId,
          variant_id: offer.variantId,
          quantity: Math.max(1, Math.min(quantity, funnel?.quantity_max ?? 10)),
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
      if (changeset.discountCodes?.add?.length && applyDiscountCodeChange) {
        const code = changeset.discountCodes.add[0]?.code ?? changeset.discountCodes.add[0];
        if (typeof code === 'string') {
          await applyDiscountCodeChange({ type: 'addDiscountCode', code });
        }
      }
      setAccepted(true);
    } catch (_) {}
  };

  const handleDecline = () => {
    if (currentStep + 1 >= offers.length) {
      setDeclinedAll(true);
    } else {
      setCurrentStep((s) => s + 1);
      const qDef = funnel?.quantity_default ?? 1;
      const qMin = funnel?.quantity_min ?? 1;
      const qMax = funnel?.quantity_max ?? 10;
      setQuantity(Math.max(qMin, Math.min(qMax, qDef)));
      if (funnel?.show_timer && funnel?.timer_seconds > 0) {
        setTimeLeft(funnel.timer_seconds);
      }
    }
  };

  const formatTime = (sec) => {
    const m = Math.floor(sec / 60);
    const s = sec % 60;
    return `${m}:${s < 10 ? '0' : ''}${s}`;
  };

  if (loading || offers.length === 0) return null;
  if (declinedAll) {
    return (
      <BlockStack spacing="loose">
        <Text appearance="subdued">Thanks! Continue to your order.</Text>
      </BlockStack>
    );
  }
  if (accepted) {
    return (
      <BlockStack spacing="loose">
        <Text emphasis="bold">Added to your order!</Text>
      </BlockStack>
    );
  }

  const offer = offers[currentStep];
  const stepLabels = funnel?.step_labels?.length ? funnel.step_labels : ['Order', 'Offer', 'Bonus', 'Done'];
  const totalSteps = Math.max(offers.length + 1, stepLabels.length);
  const showProgress = funnel?.show_progress_steps !== false;
  const headline = (funnel?.headline_template || '')
    .replace(/\{first_name\}/gi, first_name)
    .replace(/\{order_id\}/gi, orderId || '');
  const isSubscription = offer.allow_subscription_in_post_purchase && (offer.offer_type === 'subscription' || offer.offer_type === 'both');
  const ctaText = funnel?.cta_text || (isSubscription ? 'Add as subscription' : 'Pay Now');
  const declineText = funnel?.decline_text || 'Decline offer';
  const qMin = Math.max(1, funnel?.quantity_min ?? 1);
  const qMax = Math.max(1, funnel?.quantity_max ?? 10);

  return (
    <BlockStack spacing="loose">
      {headline ? (
        <Text size="large" emphasis="bold">{headline}</Text>
      ) : null}

      {showProgress && totalSteps > 0 && (
        <InlineLayout spacing="tight" blockAlignment="center">
          {Array.from({ length: Math.min(totalSteps, 4) }).map((_, i) => (
            <Text
              key={i}
              size="small"
              appearance={i <= currentStep ? 'default' : 'subdued'}
              emphasis={i === currentStep ? 'bold' : undefined}
            >
              {stepLabels[i] ?? `Step ${i + 1}`}
            </Text>
          ))}
        </InlineLayout>
      )}

      {funnel?.show_timer && timeLeft != null && (
        <Text size="small" appearance="subdued">
          {funnel.timer_label || 'For a limited time'} {formatTime(timeLeft)}
        </Text>
      )}

      <BlockStack spacing="tight">
        {offer.image_url && (
          <Image
            source={offer.image_url}
            accessibilityDescription={offer.title || 'Offer'}
          />
        )}
        <Text size="medium" emphasis="bold">{offer.title}</Text>
        {offer.description && (
          <Text appearance="subdued" size="small">{offer.description}</Text>
        )}
        <InlineLayout spacing="tight" blockAlignment="center">
          {offer.discounted_price != null && offer.discounted_price !== '' && (
            <Text emphasis="bold">${offer.discounted_price}</Text>
          )}
          {offer.price != null && offer.price !== '' && offer.discounted_price != null && (
            <Text appearance="subdued" size="small" decoration="lineThrough">${offer.price}</Text>
          )}
          {offer.price != null && offer.discounted_price == null && (
            <Text emphasis="bold">${offer.price}</Text>
          )}
          {offer.save_percent != null && offer.save_percent > 0 && (
            <Text appearance="success" size="small">(Save {offer.save_percent}%)</Text>
          )}
        </InlineLayout>
        {(qMax > 1 || qMin > 1) && (
          <BlockStack spacing="extraTight">
            <Text size="small" appearance="subdued">Quantity</Text>
            <Stepper
              value={quantity}
              min={qMin}
              max={qMax}
              onChange={(v) => setQuantity(typeof v === 'number' ? Math.max(qMin, Math.min(qMax, v)) : quantity)}
            />
          </BlockStack>
        )}
        {isSubscription && (
          <Text appearance="subdued" size="small">Subscribe &amp; save</Text>
        )}
        {funnel?.urgency_message ? (
          <Text appearance="subdued" size="small">{funnel.urgency_message}</Text>
        ) : null}
      </BlockStack>

      <BlockStack spacing="tight">
        <Button kind="primary" onPress={handleAccept}>
          {ctaText}
        </Button>
        <Link onPress={handleDecline}>{declineText}</Link>
      </BlockStack>
    </BlockStack>
  );
}
