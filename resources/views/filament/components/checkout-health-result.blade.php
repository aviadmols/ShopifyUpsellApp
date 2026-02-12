@php
  $result = $result ?? [];
  $error = (bool) ($result['error'] ?? false);
  $message = $result['message'] ?? '';
  $success = (bool) ($result['success'] ?? false);
  $count = (int) ($result['count'] ?? 0);
  $blockError = $result['block_error'] ?? null;
  $displaySettings = $result['display_settings'] ?? [];
  $experience = $result['experience'] ?? null;
@endphp
<div class="space-y-4">
  @if($experience)
    <div class="rounded-lg border border-gray-200 dark:border-white/10 p-3 bg-gray-50 dark:bg-gray-800/50">
      <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Checkout Experience</p>
      @if($experience['exists'] ?? false)
        <p class="text-sm text-success-600 dark:text-success-400 mb-2">Configured (ID: {{ $experience['id'] ?? '—' }})</p>
        <ul class="text-sm text-gray-600 dark:text-gray-400 list-disc list-inside space-y-0.5">
          <li>Quantity in upsell block: {{ ($experience['quantity_upsell'] ?? false) ? 'On' : 'Off' }}</li>
          <li>Quantity on cart lines: {{ ($experience['quantity_cart'] ?? false) ? 'On' : 'Off' }}</li>
          <li>Subscription upgrade: {{ ($experience['subscription_upgrade'] ?? false) ? 'On' : 'Off' }}</li>
        </ul>
        <p class="text-xs text-gray-500 dark:text-gray-400 mt-2">{{ $experience['message'] ?? '' }}</p>
      @else
        <p class="text-sm text-warning-600 dark:text-warning-400">{{ $experience['message'] ?? 'Not configured.' }}</p>
      @endif
    </div>
  @endif
  @if($error)
    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
  @else
    <div>
      <p class="text-sm font-medium text-gray-700 dark:text-gray-300">
        @if($blockError)
          Block is configured but: {{ $blockError }}
        @else
          {{ $count > 0 ? "Block is OK. Would return {$count} offer(s) for this cart context." : 'Block is OK. No offers for empty cart context (add products to cart to see offers).' }}
        @endif
      </p>
    </div>
    @if(count($displaySettings) > 0)
      <div>
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Display settings that will be sent to Checkout:</p>
        <div class="rounded-lg border border-gray-200 dark:border-white/10 overflow-hidden">
          <table class="min-w-full divide-y divide-gray-200 dark:divide-white/10 text-sm">
            <tbody class="divide-y divide-gray-200 dark:divide-white/10 bg-white dark:bg-gray-800">
              @foreach($displaySettings as $key => $value)
                <tr>
                  <td class="px-3 py-2 font-medium text-gray-500 dark:text-gray-400 whitespace-nowrap">{{ $key }}</td>
                  <td class="px-3 py-2 text-gray-900 dark:text-gray-100">
                    @if(is_bool($value))
                      {{ $value ? 'true' : 'false' }}
                    @elseif(is_array($value))
                      (array)
                    @else
                      {{ \Illuminate\Support\Str::limit(is_string($value) ? $value : json_encode($value), 80) }}
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      </div>
    @endif
  @endif
</div>
