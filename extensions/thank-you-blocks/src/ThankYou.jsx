/**
 * Thank you page: fetches blocks from Laravel GET /api/thankyou/blocks and renders by type.
 * Types: banner, text, button, product_card. Product card "Buy now" links to product/checkout.
 */
import reactExtension from '@shopify/ui-extensions-react/checkout';
import {
  useSettings,
  BlockStack,
  Button,
  Text,
  Image,
  Divider,
  Link,
} from '@shopify/ui-extensions-react/checkout';
import { useEffect, useState } from 'react';

export default reactExtension('purchase.thank-you.block.render', () => <ThankYouBlocks />);

function ThankYouBlocks() {
  const settings = useSettings();
  const [blocks, setBlocks] = useState([]);

  const apiUrl = (settings.api_url || '').replace(/\/$/, '');
  const secret = settings.extension_secret || '';
  const shopDomain = settings.shop_domain || '';

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
  if (type === 'banner') {
    return (
      <BlockStack spacing="tight">
        {config?.image_url && <Image url={config.image_url} alt={config?.title || ''} />}
        {config?.title && <Text emphasis="bold">{config.title}</Text>}
        {config?.button_url && (
          <Link to={config.button_url}>
            <Button kind="secondary">{config?.body || 'Learn more'}</Button>
          </Link>
        )}
      </BlockStack>
    );
  }
  if (type === 'text') {
    return (
      <BlockStack spacing="tight">
        {config?.title && <Text emphasis="bold">{config.title}</Text>}
        {config?.body && <Text>{config.body}</Text>}
      </BlockStack>
    );
  }
  if (type === 'button') {
    return (
      <Link to={config?.button_url || '#'}>
        <Button kind="secondary">{config?.body || config?.title || 'Click'}</Button>
      </Link>
    );
  }
  if (type === 'product_card') {
    const productUrl = config?.button_url || (config?.product_id ? `/products/${config.product_id}` : '#');
    return (
      <BlockStack spacing="tight">
        {config?.image_url && <Image url={config.image_url} alt={config?.title || ''} />}
        {config?.title && <Text emphasis="bold">{config.title}</Text>}
        <Link to={productUrl}>
          <Button kind="secondary">Buy now</Button>
        </Link>
      </BlockStack>
    );
  }
  return null;
}
