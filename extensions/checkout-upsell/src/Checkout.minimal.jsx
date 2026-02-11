/**
 * Minimal checkout block: only BUILD_ID, no settings/fetch.
 * Use to verify the extension runs at all. To use: in shopify.extension.toml
 * set module = "./src/Checkout.minimal.jsx" and deploy.
 */
import {
  reactExtension,
  BlockStack,
  Text,
} from '@shopify/ui-extensions-react/checkout';

const BUILD_ID = 'zyg-upsell-checkout-minimal-20260210';

export default reactExtension('purchase.checkout.block.render', () => (
  <BlockStack spacing="tight">
    <Text size="medium" emphasis="bold">Checkout Upsell (minimal)</Text>
    <Text appearance="subdued" size="small">{BUILD_ID}</Text>
    <Text appearance="subdued" size="small">If you see this, the extension is rendering.</Text>
  </BlockStack>
));
