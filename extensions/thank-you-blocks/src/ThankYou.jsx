/**
 * Thank you page: fetches blocks from Laravel GET /api/thankyou/blocks and renders by type.
 * Types: banner, text, button, product_card. Product card "Buy now" links to product/checkout.
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

const DEFAULT_API_URL = 'https://shopifyupsellapp-production.up.railway.app';
const DEFAULT_SHOP_DOMAIN = 'millsdailypacks.myshopify.com';

export default reactExtension('purchase.thank-you.block.render', () => <ThankYouBlocks />);

function ThankYouBlocks() {
  const settings = useSettings();
  const [blocks, setBlocks] = useState([]);

  const apiUrl = (settings.api_url || DEFAULT_API_URL || '').replace(/\/$/, '');
  const secret = settings.extension_secret || '';
  const shopDomain = settings.shop_domain || DEFAULT_SHOP_DOMAIN;

  useEffect(() => {
    if (!apiUrl || !secret || !shopDomain) return;
    fetch(`${apiUrl}/api/thankyou/blocks?shop=${encodeURIComponent(shopDomain)}`, {
      headers: { 'X-Extension-Secret': secret, Accept: 'application/json' },
    })
      .then((r) => (r.ok ? r.json() : { blocks: [] }))
      .then((data) => setBlocks(data.blocks || []))
      .catch(() => setBlocks([]));
  }, [apiUrl, secret, shopDomain]);

  if (blocks.length === 0) return null;

  return (
    <BlockStack spacing="loose">
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
    return (
      <BlockStack spacing={spacing}>
        {maybeDividerBefore}
        {config?.image_url && <Image url={config.image_url} alt={config?.title || ''} />}
        {titleText}
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
