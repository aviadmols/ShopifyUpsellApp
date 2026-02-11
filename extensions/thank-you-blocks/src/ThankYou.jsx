/**
 * Thank you page: fetches blocks from Laravel GET /api/thankyou/blocks and renders by type.
 * Types: banner, text, button, product_card. Always shows BUILD_ID so you can verify the extension runs.
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
  const [status, setStatus] = useState('loading'); // loading | ok | no_config | error

  const apiUrl = (settings.api_url || DEFAULT_API_URL || '').replace(/\/$/, '');
  const secret = (settings.extension_secret || '').trim();
  const shopDomain = (settings.shop_domain || DEFAULT_SHOP_DOMAIN).trim() || DEFAULT_SHOP_DOMAIN;

  useEffect(() => {
    if (!apiUrl || !secret) {
      setStatus('no_config');
      return;
    }
    setStatus('loading');
    fetch(`${apiUrl}/api/thankyou/blocks?shop=${encodeURIComponent(shopDomain)}`, {
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
  }, [apiUrl, secret, shopDomain]);

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

  const debugBlock = (
    <BlockStack spacing="extraTight">
      <Text size="small" appearance="subdued">Thank You Blocks · {BUILD_ID}</Text>
      <Text size="small" appearance="subdued">Status: {statusLine}</Text>
    </BlockStack>
  );

  if (blocks.length === 0) {
    return (
      <BlockStack spacing="loose">
        {debugBlock}
        {status === 'no_config' && (
          <Text size="small" appearance="subdued">Add blocks in the app and set Extension secret in this block.</Text>
        )}
      </BlockStack>
    );
  }

  return (
    <BlockStack spacing="loose">
      {debugBlock}
      <Divider />
      {blocks.map((block) => (
        <Block key={block.id} block={block} />
      ))}
    </BlockStack>
  );
}

function Block({ block }) {
  const { type, config } = block;
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

  if (type === 'banner') {
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
  if (type === 'text') {
    return (
      <BlockStack spacing={spacing}>
        {maybeDividerBefore}
        {titleText}
        {config?.body && <Text appearance={titleAppearance} size={titleSize}>{config.body}</Text>}
        {maybeDividerAfter}
      </BlockStack>
    );
  }
  if (type === 'button') {
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
  if (type === 'product_card') {
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
  return null;
}
