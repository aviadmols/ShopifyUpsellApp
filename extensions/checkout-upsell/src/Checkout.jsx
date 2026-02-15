/**
 * Checkout upsell: fetches offers from Laravel and adds selected variant to cart.
 * Debug panel (BUILD_ID, API, Status) only when no offers or error, and only if block setting show_debug_when_empty is true.
 */
import {
  reactExtension,
  useSettings,
  useApi,
  useCheckoutToken,
  BlockStack,
  Button,
  Link,
  Text,
  Image,
  Divider,
  Progress,
  Grid,
  InlineLayout,
  useApplyCartLinesChange,
  useSubtotalAmount,
} from '@shopify/ui-extensions-react/checkout';
import { useEffect, useRef, useState } from 'react';

const BUILD_ID = 'zyg-upsell-checkout-20260212-widgets';
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

/** Read a setting value; supports both flat (useSettings) and nested .value shape (e.g. editor). */
function getSetting(settings, key) {
  if (!settings || typeof settings !== 'object') return undefined;
  const raw = settings[key];
  if (raw === undefined || raw === null) return undefined;
  if (typeof raw === 'object' && raw !== null && 'value' in raw) return raw.value;
  return raw;
}

/** Send a log payload to the server (fire-and-forget). No sensitive data in payload. */
function sendLog(apiUrl, secret, payload) {
  if (!apiUrl || !secret) return;
  const url = `${apiUrl.replace(/\/$/, '')}/api/checkout/logs`;
  const body = {
    ts: new Date().toISOString(),
    build_id: BUILD_ID,
    ...payload,
  };
  fetch(url, {
    method: 'POST',
    headers: { 'X-Extension-Secret': secret, 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify(body),
  }).catch(() => {});
}

export default reactExtension('purchase.checkout.block.render', () => <CheckoutUpsell />);

function ContentBlockRender({ block }) {
  const type = block?.type || '';
  const config = block?.config || {};
  const spacing = config.spacing === 'loose' ? 'loose' : 'tight';
  const textSize = ['small', 'medium', 'large'].includes(config.text_size) ? config.text_size : 'medium';
  const buttonKind = ['primary', 'secondary', 'plain'].includes(config.button_kind) ? config.button_kind : 'secondary';

  if (type === 'content_icon_features') {
    const items = Array.isArray(config.icon_features) ? config.icon_features : [];
    return (
      <BlockStack spacing="loose">
        {items.map((item, i) => (
          <BlockStack key={i} spacing="tight">
            {item.title && <Text size="medium" emphasis="bold">{item.title}</Text>}
            {item.subtitle && <Text appearance="subdued" size="small">{item.subtitle}</Text>}
          </BlockStack>
        ))}
      </BlockStack>
    );
  }

  if (type === 'content_banner' || type === 'content_rich_text' || type === 'content_button') {
    return (
      <BlockStack spacing={spacing}>
        {config.image_url && type === 'content_banner' && (
          <Image source={config.image_url} alt="" />
        )}
        {config.title && <Text size={textSize} emphasis="bold">{config.title}</Text>}
        {config.body && <Text appearance="subdued" size="small">{config.body}</Text>}
        {config.button_label && config.button_url && (
          <Link to={config.button_url}>{config.button_label}</Link>
        )}
      </BlockStack>
    );
  }

  if (type === 'content_product_card') {
    return (
      <BlockStack spacing={spacing}>
        {config.image_url && <Image source={config.image_url} alt="" />}
        {config.title && <Text size={textSize} emphasis="bold">{config.title}</Text>}
        {config.body && <Text appearance="subdued" size="small">{config.body}</Text>}
        {config.show_price !== false && config.price_text && (
          <Text appearance="subdued" size="small">{config.price_text}</Text>
        )}
        {config.badge_text && <Text appearance="subdued" size="small">{config.badge_text}</Text>}
        {config.button_label && config.button_url && (
          <Link to={config.button_url}>{config.button_label}</Link>
        )}
      </BlockStack>
    );
  }

  return null;
}

function normalizeLineItemsForApi(lines) {
  if (!Array.isArray(lines)) return [];
  return lines.map((line) => {
    const merch = line?.merchandise ?? line;
    const id = merch?.id ?? line?.id;
    const productId = merch?.product?.id ?? line?.product_id;
    const variantId = merch?.id ?? line?.variant_id ?? id;
    return {
      id: line?.id,
      quantity: line?.quantity ?? 1,
      merchandiseId: variantId,
      product_id: productId,
      variant_id: variantId,
    };
  });
}

function CheckoutUpsell() {
  const settings = useSettings();
  const api = useApi();
  const applyCartLinesChange = useApplyCartLinesChange();
  const subtotalMoney = useSubtotalAmount();
  const [offers, setOffers] = useState([]);
  const [contentBlocks, setContentBlocks] = useState([]);
  const [loading, setLoading] = useState(true);
  const [added, setAdded] = useState(new Set());
  const [status, setStatus] = useState({
    type: 'idle',
    message: 'Initializing…',
    detail: '',
  });

  const apiUrl = (getSetting(settings, 'api_url') || DEFAULT_API_URL || '').replace(/\/$/, '');
  const secret = (getSetting(settings, 'extension_secret') || '').trim();
  const shopDomain = (getSetting(settings, 'shop_domain') || '').trim();
  const runtimeShop = (typeof api?.shop?.myshopifyDomain === 'string' && api.shop.myshopifyDomain) ? api.shop.myshopifyDomain : null;
  const shop = shopDomain || runtimeShop || DEFAULT_SHOP;
  const blockIdRaw = getSetting(settings, 'block_id');
  const blockId = blockIdRaw != null && String(blockIdRaw).trim() !== '' ? String(blockIdRaw).trim() : undefined;
  const checkoutExperienceIdRaw = getSetting(settings, 'checkout_experience_id');
  const checkoutExperienceId = (checkoutExperienceIdRaw != null && String(checkoutExperienceIdRaw).trim() !== '')
    ? (parseInt(String(checkoutExperienceIdRaw).trim(), 10) || undefined)
    : undefined;
  const showDebugWhenEmpty = getSetting(settings, 'show_debug_when_empty') === true;

  const checkoutToken = useCheckoutToken();
  const experienceSetSentRef = useRef(false);
  const [experienceSetStatus, setExperienceSetStatus] = useState('idle'); // idle | no_token | sent | ok | error
  const experienceOnly = !blockId && checkoutExperienceId > 0 && !!shop;

  useEffect(() => {
    if (!experienceOnly || !apiUrl || !secret || !shop || checkoutExperienceId < 1) return;
    const sessionKey = (typeof checkoutToken === 'string' && checkoutToken) ? checkoutToken : '';
    if (sessionKey === '') {
      setExperienceSetStatus('no_token');
      return;
    }
    if (experienceSetSentRef.current) return;
    experienceSetSentRef.current = true;
    setExperienceSetStatus('sent');
    fetch(`${apiUrl}/api/checkout/experience/set`, {
      method: 'POST',
      headers: { 'X-Extension-Secret': secret, 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ shop, session_key: sessionKey, checkout_experience_id: checkoutExperienceId }),
    })
      .then((r) => {
        setExperienceSetStatus(r.ok ? 'ok' : 'error');
      })
      .catch(() => setExperienceSetStatus('error'));
  }, [experienceOnly, apiUrl, secret, shop, checkoutExperienceId, checkoutToken]);

  const [displayMode, setDisplayMode] = useState('stacked');
  const [quantityConfig, setQuantityConfig] = useState({
    enabled: false,
    default: 1,
    min: 1,
    max: 10,
  });
  const [offerQuantities, setOfferQuantities] = useState({});
  const [ui, setUi] = useState({
    display_mode: 'stacked',
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

  const cartLines = (typeof api?.lines?.current === 'function' ? api.lines.current() : api?.lines?.current) ?? api?.lines ?? [];
  const lineItems = Array.isArray(cartLines) ? cartLines : (cartLines?.value ? (Array.isArray(cartLines.value) ? cartLines.value : []) : []);

  useEffect(() => {
    if (experienceOnly) {
      setLoading(false);
      setStatus({ type: 'connected', message: 'Checkout Experience active', detail: '' });
      return;
    }
    if (!apiUrl || !secret) {
      setLoading(false);
      setStatus({
        type: 'not_configured',
        message: 'Block not connected',
        detail: 'Set "Extension secret" in block settings (required). API URL and Shop domain are optional.',
      });
      return;
    }
    setStatus({ type: 'loading', message: 'Connecting to app…', detail: '' });

    const lineItemsNormalized = normalizeLineItemsForApi(lineItems);
    sendLog(apiUrl, secret, {
      phase: 'load',
      block_id: blockId ?? null,
      shop,
      line_items_count: lineItemsNormalized.length,
      subtotal: subtotalMoney?.amount ?? 0,
    });

    const body = {
      shop,
      ...(blockId !== undefined && { block_id: parseInt(blockId, 10) || blockId }),
      ...(checkoutExperienceId !== undefined && checkoutExperienceId > 0 && { checkout_experience_id: checkoutExperienceId }),
      subtotal: subtotalMoney?.amount ?? 0,
      line_items: lineItemsNormalized,
    };

    fetch(`${apiUrl}/api/checkout/offers`, {
      method: 'POST',
      headers: { 'X-Extension-Secret': secret, 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify(body),
    })
      .then((r) => {
        if (r.ok) {
          return r.json().then((data) => {
            setOffers(data.offers || []);
            setContentBlocks(Array.isArray(data.blocks) ? data.blocks : []);
            const dm = String(data.display_mode ?? data.ui?.display_mode ?? 'stacked').toLowerCase().trim();
            setDisplayMode(
              dm === 'single' ? 'single' : dm === 'grid' ? 'grid' : dm === 'row' ? 'row' : 'stacked'
            );
            if (data.ui && typeof data.ui === 'object') {
              setUi((prev) => ({ ...prev, ...data.ui }));
            }
            if (data.quantity && typeof data.quantity === 'object') {
              const q = data.quantity;
              setQuantityConfig({
                enabled: Boolean(q.enabled),
                default: Math.max(1, Math.min(100, parseInt(q.default, 10) || 1)),
                min: Math.max(1, Math.min(100, parseInt(q.min, 10) || 1)),
                max: Math.max(1, Math.min(100, parseInt(q.max, 10) || 10)),
              });
            }
            const count = (data.offers || []).length;
            const blockCount = (data.blocks || []).length;
            const blockError = data.block_error || data.error;
            sendLog(apiUrl, secret, {
              phase: 'fetch_success',
              block_id: blockId ?? null,
              shop,
              offers_count: count,
              blocks_count: blockCount,
              block_error: blockError || null,
              status_type: blockError ? 'block_error' : (count ? 'offers' : blockCount ? 'content' : 'no_offers'),
            });
            if (blockError) {
              setStatus({ type: 'connected', message: blockError, detail: '' });
            } else {
              setStatus({
                type: 'connected',
                message: count ? `Connected — ${count} offer(s)` : blockCount ? 'Content widget' : 'Connected — no offers for this cart',
                detail: count ? 'Upsell active' : blockCount ? 'Content widget' : 'Add a widget in Admin → Widgets and set Widget ID here.',
              });
            }
          });
        }
        const statusCode = r.status;
        let detail = `HTTP ${statusCode}`;
        if (statusCode === 401) detail = 'Invalid Extension secret. Check CHECKOUT_EXTENSION_SECRET in Railway.';
        else if (statusCode === 404) detail = 'Shop not found. Check Shop domain or add shop in the app.';
        else if (statusCode >= 500) detail = 'Server error. Check app logs on Railway.';
        sendLog(apiUrl, secret, {
          phase: 'fetch_error',
          block_id: blockId ?? null,
          shop,
          status_code: statusCode,
          detail,
        });
        setStatus({ type: 'error', message: 'Connection failed', detail });
        setOffers([]);
        setContentBlocks([]);
      })
      .catch((err) => {
        sendLog(apiUrl, secret, {
          phase: 'fetch_exception',
          block_id: blockId ?? null,
          shop,
          message: err && err.message ? err.message : 'Network error',
        });
        setStatus({
          type: 'error',
          message: 'Connection failed',
          detail: err && err.message ? err.message : 'Network error. Check API URL and CORS.',
        });
        setOffers([]);
        setContentBlocks([]);
      })
      .finally(() => setLoading(false));
  }, [experienceOnly, apiUrl, secret, shopDomain, blockId, subtotalMoney?.amount, JSON.stringify(normalizeLineItemsForApi(lineItems))]);

  const getQuantityForOffer = (variantId) => {
    const q = offerQuantities[variantId];
    if (typeof q === 'number' && !Number.isNaN(q)) {
      return Math.max(quantityConfig.min, Math.min(quantityConfig.max, q));
    }
    return Math.max(quantityConfig.min, Math.min(quantityConfig.max, quantityConfig.default));
  };

  const setQuantityForOffer = (variantId, value) => {
    const num = parseInt(value, 10);
    if (Number.isNaN(num)) return;
    const clamped = Math.max(quantityConfig.min, Math.min(quantityConfig.max, num));
    setOfferQuantities((prev) => ({ ...prev, [variantId]: clamped }));
  };

  const addToCart = async (variantId, sellingPlanId = null, qty = null) => {
    if (added.has(variantId)) return;
    const quantity = quantityConfig.enabled
      ? (typeof qty === 'number' ? Math.max(1, qty) : getQuantityForOffer(variantId))
      : 1;
    try {
      const line = {
        type: 'addCartLine',
        merchandiseId: variantId,
        quantity: Math.max(1, quantity),
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
            ? (status.message || (offers.length ? 'Connected' : 'No offers'))
            : status.message;

  const fullDebugBlock = (
    <BlockStack spacing="extraTight">
      <Text size="medium" emphasis="bold">Checkout Upsell</Text>
      <Text appearance="subdued" size="small">{BUILD_ID}</Text>
      <Text appearance="subdued" size="small">API: {shortenUrl(apiUrl)}</Text>
      <Text appearance="subdued" size="small">Shop: {shortenShop(shop)}</Text>
      <Text appearance="subdued" size="small">Widget ID: {blockId ?? '(not set)'}</Text>
      <Text appearance="subdued" size="small">Offers: {offers.length}</Text>
      <Text appearance="subdued" size="small">Blocks: {contentBlocks.length}</Text>
      <Text appearance="subdued" size="small">Status: {statusLine}</Text>
      {status.message && (
        <Text appearance="subdued" size="small">Message: {status.message}</Text>
      )}
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
      {status.type === 'not_configured' ? 'Not configured' : 'Connection error'}
    </Text>
  );

  const hasContent = contentBlocks.length > 0;
  const showEmptyOrError = loading || status.type === 'not_configured' || status.type === 'error' || (offers.length === 0 && !hasContent);
  const debugContent = showDebugWhenEmpty ? fullDebugBlock : minimalMessage;
  const hideWhenNoOffers = !loading && status.type === 'connected' && offers.length === 0 && !hasContent && !showDebugWhenEmpty;

  if (experienceOnly) {
    if (!showDebugWhenEmpty) return null;
    const debugLines = [
      { label: 'Checkout Experience', value: BUILD_ID },
      { label: 'API', value: shortenUrl(apiUrl) },
      { label: 'Shop', value: shortenShop(shop) },
      { label: 'Experience ID', value: String(checkoutExperienceId) },
      { label: 'Status', value: experienceSetStatus === 'ok' ? 'Experience set for session' : experienceSetStatus === 'sent' ? 'Sending…' : experienceSetStatus === 'no_token' ? 'No checkout token yet' : experienceSetStatus === 'error' ? 'Set request failed' : experienceSetStatus },
    ];
    return (
      <BlockStack spacing="tight">
        <Text appearance="subdued" size="small">
          Checkout Experience (quantity and subscription) active for this checkout.
        </Text>
        <BlockStack spacing="extraTight">
          {debugLines.map(({ label, value }) => (
            <Text key={label} appearance="subdued" size="small">{label}: {value}</Text>
          ))}
        </BlockStack>
      </BlockStack>
    );
  }

  if (hasContent && !loading && status.type !== 'error' && status.type !== 'not_configured') {
    return (
      <BlockStack spacing="loose">
        {contentBlocks.map((blk) => (
          <ContentBlockRender key={blk.id || blk.type} block={blk} />
        ))}
        {progressBar && (
          <BlockStack spacing="tight">
            <Text size="medium" emphasis="bold">{progressMessage}</Text>
            <Progress value={progress} max={1} accessibilityLabel={progressMessage} />
          </BlockStack>
        )}
      </BlockStack>
    );
  }

  if (offers.length > 0 && !loading && status.type !== 'error' && status.type !== 'not_configured') {
    // Hide offers that were already added to cart so they disappear from the block
    const offersNotAdded = offers.filter((o) => !added.has(o.variant_id));
    if (offersNotAdded.length === 0) {
      if (progressBar) {
        return (
          <BlockStack spacing="tight">
            <Text size="medium" emphasis="bold">{progressMessage}</Text>
            <Progress value={progress} max={1} accessibilityLabel={progressMessage} />
          </BlockStack>
        );
      }
      return <BlockStack spacing="none" />;
    }
    // Prefer ui.display_mode from API so layout matches server even if state was set by older extension code
    const dmStr = String(ui.display_mode || '').toLowerCase().trim();
    const effectiveDisplayMode =
      displayMode === 'grid' || dmStr === 'grid'
        ? 'grid'
        : displayMode === 'row' || dmStr === 'row'
          ? 'row'
          : displayMode === 'single'
            ? 'single'
            : 'stacked';
    const offersToShow = effectiveDisplayMode === 'single' ? offersNotAdded.slice(0, 1) : offersNotAdded;
    const sectionSpacing = ui.card_spacing === 'tight' ? 'tight' : ui.card_spacing === 'extraLoose' ? 'extraLoose' : 'loose';
    const cardSpacing = ui.card_spacing === 'tight' ? 'tight' : ui.card_spacing === 'extraLoose' ? 'extraLoose' : 'loose';
    const titleSize = ['small', 'medium', 'large', 'extraLarge'].includes(ui.title_size) ? ui.title_size : 'medium';
    const titleAppearance = ui.title_appearance && ui.title_appearance !== 'default' ? ui.title_appearance : undefined;
    const buttonKind = ['primary', 'secondary', 'plain'].includes(ui.button_kind) ? ui.button_kind : 'secondary';
    const buttonAppearance = ui.button_appearance && ui.button_appearance !== 'default' ? ui.button_appearance : undefined;

    const renderOfferCard = (offer, index, showDivider) => (
      <BlockStack key={offer.id} spacing={cardSpacing}>
        {showDivider && ui.divider_between_cards && <Divider />}
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
          {quantityConfig.enabled && (
            <InlineLayout spacing="tight" blockAlignment="center">
              <Button kind="plain" onPress={() => setQuantityForOffer(offer.variant_id, getQuantityForOffer(offer.variant_id) - 1)} disabled={getQuantityForOffer(offer.variant_id) <= quantityConfig.min}>−</Button>
              <Text>{String(getQuantityForOffer(offer.variant_id))}</Text>
              <Button kind="plain" onPress={() => setQuantityForOffer(offer.variant_id, getQuantityForOffer(offer.variant_id) + 1)} disabled={getQuantityForOffer(offer.variant_id) >= quantityConfig.max}>+</Button>
            </InlineLayout>
          )}
          <Button
            kind={buttonKind}
            {...(buttonAppearance ? { appearance: buttonAppearance } : {})}
            onPress={() => addToCart(offer.variant_id, offer.selling_plan_id || null, getQuantityForOffer(offer.variant_id))}
            disabled={added.has(offer.variant_id)}
          >
            {added.has(offer.variant_id) ? 'Added' : (offer.offer_type === 'subscription' ? 'Add as subscription' : 'Add to order')}
          </Button>
        </BlockStack>
      </BlockStack>
    );

    // Row layout: [small thumbnail] [title + price] [Add button on the right] — like "You may also like"
    const rowImageAspectRatio = ui.image_aspect_ratio ? (parseFloat(ui.image_aspect_ratio) || 1) : 1;
    const rowImageSize = 80; // fixed width in px so image stays thumbnail-sized
    const renderOfferRow = (offer, index, showDivider) => (
      <BlockStack key={offer.id} spacing="tight">
        {showDivider && ui.divider_between_cards && <Divider />}
        <InlineLayout columns={[rowImageSize, 'fill', 'auto']} spacing="base" blockAlignment="center">
          {offer.image_url ? (
            <Image
              source={offer.image_url}
              accessibilityDescription={offer.title || 'Product'}
              aspectRatio={rowImageAspectRatio}
              {...(ui.image_fit && { fit: ui.image_fit })}
              {...(ui.image_corner_radius && { cornerRadius: ui.image_corner_radius })}
            />
          ) : (
            <BlockStack spacing="none" />
          )}
          <BlockStack spacing="extraTight">
            <Text size={titleSize} emphasis="bold" {...(titleAppearance ? { appearance: titleAppearance } : {})}>
              {offer.title}
            </Text>
            {ui.show_price && offer.price != null && offer.price !== '' && (
              <Text appearance="subdued" size="small">${offer.price}</Text>
            )}
            {(offer.offer_type === 'subscription' || offer.offer_type === 'both') && (
              <Text appearance="subdued" size="small">Subscribe & save</Text>
            )}
            {ui.show_description && offer.description && (
              <Text appearance="subdued" size="small">{offer.description}</Text>
            )}
          </BlockStack>
          <BlockStack spacing="extraTight">
            {quantityConfig.enabled && (
              <InlineLayout spacing="tight" blockAlignment="center">
                <Button kind="plain" onPress={() => setQuantityForOffer(offer.variant_id, getQuantityForOffer(offer.variant_id) - 1)} disabled={getQuantityForOffer(offer.variant_id) <= quantityConfig.min}>−</Button>
                <Text size="small">{String(getQuantityForOffer(offer.variant_id))}</Text>
                <Button kind="plain" onPress={() => setQuantityForOffer(offer.variant_id, getQuantityForOffer(offer.variant_id) + 1)} disabled={getQuantityForOffer(offer.variant_id) >= quantityConfig.max}>+</Button>
              </InlineLayout>
            )}
            <Button
              kind={buttonKind}
              {...(buttonAppearance ? { appearance: buttonAppearance } : {})}
              onPress={() => addToCart(offer.variant_id, offer.selling_plan_id || null, getQuantityForOffer(offer.variant_id))}
              disabled={added.has(offer.variant_id)}
            >
              {added.has(offer.variant_id) ? 'Added' : (offer.offer_type === 'subscription' ? 'Add as subscription' : 'Add to order')}
            </Button>
          </BlockStack>
        </InlineLayout>
      </BlockStack>
    );

    const offersContent =
      effectiveDisplayMode === 'grid' ? (
        <Grid columns={['fill', 'fill']} spacing={sectionSpacing}>
          {offersToShow.map((offer, index) => renderOfferCard(offer, index, index > 0))}
        </Grid>
      ) : effectiveDisplayMode === 'row' ? (
        offersToShow.map((offer, index) => renderOfferRow(offer, index, index > 0))
      ) : (
        offersToShow.map((offer, index) => renderOfferCard(offer, index, index > 0))
      );

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
        {offersContent}
      </BlockStack>
    );
  }

  if (hideWhenNoOffers) {
    if (progressBar) {
      return (
        <BlockStack spacing="tight">
          <Text size="medium" emphasis="bold">{progressMessage}</Text>
          <Progress value={progress} max={1} accessibilityLabel={progressMessage} />
        </BlockStack>
      );
    }
    return <BlockStack spacing="none" />;
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
      {showDebugWhenEmpty && offers.length === 0 && !hasContent && !loading && status.type === 'connected' && (
        <BlockStack spacing="tight">
          {!blockId && (
            <Text size="small" emphasis="bold">Widget ID is required.</Text>
          )}
          <Text size="small" appearance="subdued">
            {!blockId
              ? 'In Admin → Widgets create a widget with Surface = Checkout and Type = Upsell, add offers, then copy its ID (table column ID) into "Widget ID" above. Checkout Experience ID is separate — it only enables quantity/subscription for that widget.'
              : 'Add a widget in Admin → Widgets and set Widget ID in this block settings.'}
          </Text>
        </BlockStack>
      )}
    </BlockStack>
  );
}
