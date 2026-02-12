/**
 * Dedicated block: only sets Checkout Experience for this session so Cart Line Item uses it.
 * Renders minimal or no UI. Add this block in Checkout, set Checkout Experience ID in settings.
 */
import {
  reactExtension,
  useSettings,
  useApi,
  useCheckoutToken,
  BlockStack,
  Text,
} from '@shopify/ui-extensions-react/checkout';
import { useEffect, useRef } from 'react';

const DEFAULT_API_URL = 'https://shopifyupsellapp-production.up.railway.app';

function getSetting(settings, key) {
  if (!settings || typeof settings !== 'object') return undefined;
  const raw = settings[key];
  if (raw === undefined || raw === null) return undefined;
  if (typeof raw === 'object' && raw !== null && 'value' in raw) return raw.value;
  return raw;
}

export default reactExtension('purchase.checkout.block.render', () => <CheckoutExperienceBlock />);

function CheckoutExperienceBlock() {
  const settings = useSettings();
  const api = useApi();
  const checkoutToken = useCheckoutToken();

  const apiUrl = (getSetting(settings, 'api_url') || DEFAULT_API_URL || '').replace(/\/$/, '');
  const secret = (getSetting(settings, 'extension_secret') || '').trim();
  const shopDomain = (getSetting(settings, 'shop_domain') || '').trim();
  const runtimeShop = typeof api?.shop?.myshopifyDomain === 'string' ? api.shop.myshopifyDomain : null;
  const shop = shopDomain || runtimeShop || '';
  const experienceIdRaw = getSetting(settings, 'checkout_experience_id');
  const experienceId = (experienceIdRaw != null && String(experienceIdRaw).trim() !== '')
    ? (parseInt(String(experienceIdRaw).trim(), 10) || 0)
    : 0;

  const sentRef = useRef(false);

  useEffect(() => {
    if (!apiUrl || !secret || !shop || experienceId < 1) return;
    const sessionKey = (typeof checkoutToken === 'string' && checkoutToken) ? checkoutToken : '';
    if (sessionKey === '') return;
    if (sentRef.current) return;
    sentRef.current = true;
    fetch(`${apiUrl}/api/checkout/experience/set`, {
      method: 'POST',
      headers: { 'X-Extension-Secret': secret, 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ shop, session_key: sessionKey, checkout_experience_id: experienceId }),
    }).catch(() => {});
  }, [apiUrl, secret, shop, experienceId, checkoutToken]);

  if (experienceId < 1 || !shop) return null;

  return (
    <BlockStack spacing="tight">
      <Text appearance="subdued" size="small">
        Checkout Experience (quantity and subscription) active for this checkout.
      </Text>
    </BlockStack>
  );
}
