@php
  $record = $record ?? null;
  if (!$record) {
    return;
  }
  $quantityPayload = $record->quantityPayload();
  $subscriptionPayload = $record->subscriptionUpgradePayload();
  $cartPayload = [
    'quantity_in_cart_enabled' => (bool) $record->quantity_in_cart_enabled,
    'subscription_upgrade' => $subscriptionPayload,
  ];
@endphp
<div class="space-y-4">
  <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
    Zyg Cart Line extension: set <strong>Checkout Experience ID</strong> to <code>{{ $record->id }}</code> in Checkout Editor to use this experience for quantity and subscription on cart lines.
  </p>
  <p class="text-sm text-gray-700 dark:text-gray-300">
    When this Experience is used in Checkout (Zyg Cart Line with this ID), Cart Line Item will show:
  </p>
  <ul class="text-sm text-gray-600 dark:text-gray-400 list-disc list-inside space-y-1">
    <li><strong>Quantity (+/−) on cart lines:</strong> {{ $record->quantity_in_cart_enabled ? 'On' : 'Off' }}</li>
    <li><strong>Upgrade to subscription button:</strong> {{ $record->subscription_upgrade_enabled ? 'On' : 'Off' }}</li>
  </ul>
  <div>
    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Upsell block (quantity selector)</p>
    <p class="text-xs text-gray-500 dark:text-gray-400">Quantity in upsell: {{ $record->quantity_in_upsell_enabled ? 'On' : 'Off' }} — default {{ $quantityPayload['default'] }}, min {{ $quantityPayload['min'] }}, max {{ $quantityPayload['max'] }}</p>
  </div>
  <div>
    <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">API response for Cart Line Item (<code>POST /api/checkout/experience</code>)</p>
    <pre class="text-xs bg-gray-100 dark:bg-gray-800 p-3 rounded overflow-x-auto">{{ json_encode($cartPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
  </div>
</div>
