/**
 * Thank you page: fetches blocks from Laravel GET /api/thankyou/blocks and renders by type.
 * Types: banner, text, button, product_card. Debug panel only when no blocks or error, and only if show_debug_when_empty is true.
 */
import {
  reactExtension,
  useSettings,
  BlockStack,
  Button,
  Text,
  Image,
  Divider,
  Link,
} from '@shopify/ui-extensions-react/checkout';
import { useEffect, useState } from 'react';

const BUILD_ID = 'zyg-thankyou-20260210';
const DEFAULT_API_URL = 'https://shopifyupsellapp-production.up.railway.app';
const DEFAULT_SHOP_DOMAIN = 'millsdailypacks.myshopify.com';

export default reactExtension('purchase.thank-you.block.render', () => <ThankYouBlocks />);

function ThankYouBlocks() {
  const settings = useSettings();
  const [blocks, setBlocks] = useState([]);
  const [status, setStatus] = useState('loading');

  const apiUrl = (settings.api_url || DEFAULT_API_URL || '').replace(/\/$/, '');
  const secret = (settings.extension_secret || '').trim();
  const shopDomain = (settings.shop_domain || DEFAULT_SHOP_DOMAIN).trim() || DEFAULT_SHOP_DOMAIN;
  const blockId = settings.block_id != null && String(settings.block_id).trim() !== '' ? String(settings.block_id).trim() : undefined;
  const showDebugWhenEmpty = settings.show_debug_when_empty === true;

  useEffect(() => {
    if (!apiUrl || !secret) {
      setStatus('no_config');
      return;
    }
    setStatus('loading');
    const params = new URLSearchParams({ shop: shopDomain });
    if (blockId) params.set('block_id', blockId);
    fetch(`${apiUrl}/api/thankyou/blocks?${params.toString()}`, {
      headers: { 'X-Extension-Secret': secret, Accept: 'application/json' },
    })
      .then((r) => (r.ok ? r.json() : { blocks: [] }))
      .then((data) => {
        setBlocks(data.blocks || []);
        setStatus('ok');
      })
      .catch(() => {
        setBlocks([]);
        setStatus('error');
      });
  }, [apiUrl, secret, shopDomain, blockId]);

  const statusLine =
    status === 'no_config'
      ? 'Not configured (set API URL + Extension secret in Block settings)'
      : status === 'loading'
        ? 'Loading…'
        : status === 'error'
          ? 'Connection error'
          : blocks.length === 0
            ? 'No blocks for this order'
            : `${blocks.length} block(s)`;

  const fullDebugBlock = (
    <BlockStack spacing="extraTight">
      <Text size="small" appearance="subdued">Thank You Blocks · {BUILD_ID}</Text>
      <Text size="small" appearance="subdued">Status: {statusLine}</Text>
    </BlockStack>
  );

  const minimalMessage = (
    <Text appearance="subdued" size="small">
      {status === 'no_config' ? 'Not configured' : status === 'error' ? 'Connection error' : 'No blocks for this order'}
    </Text>
  );

  if (blocks.length > 0) {
    return (
      <BlockStack spacing="loose">
        {blocks.map((block) => (
          <Block key={block.id} block={block} />
        ))}
      </BlockStack>
    );
  }

  return (
    <BlockStack spacing="tight">
      {showDebugWhenEmpty ? fullDebugBlock : minimalMessage}
      {showDebugWhenEmpty && status === 'no_config' && (
        <Text size="small" appearance="subdued">Add blocks in the app and set Extension secret in this block.</Text>
      )}
    </BlockStack>
  );
}

function blockTypeForRender(type) {
  if (type === 'content_rich_text') return 'text';
  if (type?.startsWith('content_')) return type.replace('content_', '');
  return type;
}

function Block({ block }) {
  const { type, config } = block;
  const renderType = blockTypeForRender(type);
  const spacing = config?.spacing === 'loose' ? 'loose' : 'tight';
  const titleAppearance = config?.text_appearance === 'subdued' ? 'subdued' : undefined;
  const titleSize = ['small', 'medium', 'large'].includes(config?.text_size)
    ? config?.text_size
    : undefined;
  const titleEmphasis = config?.title_bold === false ? undefined : 'bold';
  const buttonKind = config?.button_kind === 'primary' ? 'primary' : 'secondary';

  const titleText = config?.title ? (
    <Text
      emphasis={titleEmphasis}
      appearance={titleAppearance}
      size={titleSize}
    >
      {config.title}
    </Text>
  ) : null;

  const maybeDividerBefore = config?.divider_before ? <Divider /> : null;
  const maybeDividerAfter = config?.divider_after ? <Divider /> : null;

  if (renderType === 'banner') {
    return (
      <BlockStack spacing={spacing}>
        {maybeDividerBefore}
        {config?.image_url && <Image url={config.image_url} alt={config?.title || ''} />}
        {titleText}
        {config?.body && <Text appearance={titleAppearance} size={titleSize}>{config.body}</Text>}
        {config?.button_url && (
          <Link to={config.button_url}>
            <Button kind={buttonKind}>{config?.button_label || config?.body || 'Learn more'}</Button>
          </Link>
        )}
        {maybeDividerAfter}
      </BlockStack>
    );
  }
  if (renderType === 'text') {
    return (
      <BlockStack spacing={spacing}>
        {maybeDividerBefore}
        {titleText}
        {config?.body && <Text appearance={titleAppearance} size={titleSize}>{config.body}</Text>}
        {maybeDividerAfter}
      </BlockStack>
    );
  }
  if (renderType === 'button') {
    return (
      <BlockStack spacing={spacing}>
        {maybeDividerBefore}
        {titleText}
        {config?.body && <Text appearance={titleAppearance} size={titleSize}>{config.body}</Text>}
        <Link to={config?.button_url || '#'}>
          <Button kind={buttonKind}>{config?.button_label || config?.body || config?.title || 'Click'}</Button>
        </Link>
        {maybeDividerAfter}
      </BlockStack>
    );
  }
  if (renderType === 'product_card') {
    const productUrl = config?.button_url || (config?.product_id ? `/products/${config.product_id}` : '#');
    const isSubscription = config?.offer_type === 'subscription' || config?.offer_type === 'both';
    return (
      <BlockStack spacing={spacing}>
        {maybeDividerBefore}
        {config?.image_url && <Image url={config.image_url} alt={config?.title || ''} />}
        {titleText}
        {isSubscription && <Text appearance="subdued" size="small">Subscribe & save</Text>}
        {config?.badge_text && <Text appearance="subdued">{config.badge_text}</Text>}
        {config?.body && <Text appearance={titleAppearance} size={titleSize}>{config.body}</Text>}
        {config?.show_price !== false && config?.price_text && (
          <Text emphasis="bold">{config.price_text}</Text>
        )}
        <Link to={productUrl}>
          <Button kind={buttonKind}>{config?.button_label || 'Buy now'}</Button>
        </Link>
        {maybeDividerAfter}
      </BlockStack>
    );
  }
  if (renderType === 'icon_features' && Array.isArray(config?.icon_features) && config.icon_features.length > 0) {
    return (
      <BlockStack spacing={spacing}>
        {maybeDividerBefore}
        {config.icon_features.map((item, i) => (
          <BlockStack key={i} spacing="extraTight">
            {item?.title && <Text emphasis="bold">{item.title}</Text>}
            {item?.subtitle && <Text appearance="subdued" size="small">{item.subtitle}</Text>}
          </BlockStack>
        ))}
        {maybeDividerAfter}
      </BlockStack>
    );
  }
  return null;
}
