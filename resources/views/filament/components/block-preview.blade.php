@php
  $surface = $surface ?? '';
  $type = $type ?? '';
  $config = $config ?? [];
  $preview_offers = $preview_offers ?? [];
  $show_price = !empty($config['show_price']);
  $show_description = !empty($config['show_description']);
@endphp
<div class="rounded-xl border border-gray-200 dark:border-white/10 bg-gray-50 dark:bg-gray-900/50 p-6">
  <p class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-3">Widget preview — {{ $surface ?: '—' }} / {{ $type ?: '—' }}</p>
  <div class="max-w-md mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-sm p-4 space-y-4">

    @if($surface === 'checkout' && $type === 'upsell')
      <p class="font-semibold text-gray-900 dark:text-white">{{ $config['section_heading'] ?? 'Add to your order' }}</p>
      @if(count($preview_offers) > 0)
        @php $offersToShow = ($config['display_mode'] ?? 'stacked') === 'single' ? array_slice($preview_offers, 0, 1) : $preview_offers; @endphp
        @foreach($offersToShow as $offer)
          <div class="border border-gray-200 dark:border-white/10 rounded-lg p-3 flex gap-3">
            <div class="w-16 h-16 rounded bg-gray-200 dark:bg-gray-600 shrink-0 overflow-hidden flex items-center justify-center">
              @if(!empty($offer['image_url']))
                <img src="{{ $offer['image_url'] }}" alt="" class="w-full h-full object-cover" onerror="this.style.display='none'">
              @endif
            </div>
            <div class="min-w-0 flex-1">
              <p class="font-medium text-gray-900 dark:text-white">{{ $offer['title'] ?: 'Offer' }}</p>
              @if($show_price && isset($offer['price']))<p class="text-sm text-gray-600 dark:text-gray-300">{{ $offer['price'] }}</p>@endif
              @if($show_description && !empty($offer['description']))<p class="text-xs text-gray-500 dark:text-gray-400">{{ Str::limit($offer['description'], 80) }}</p>@endif
              <button type="button" class="mt-2 text-sm px-3 py-1.5 rounded {{ ($config['button_kind'] ?? 'secondary') === 'primary' ? 'bg-primary-600 text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-white' }}">Add to order</button>
            </div>
          </div>
        @endforeach
      @else
        <div class="border border-gray-200 dark:border-white/10 rounded-lg p-3 flex gap-3">
          <div class="w-16 h-16 rounded bg-gray-200 dark:bg-gray-600 shrink-0"></div>
          <div class="min-w-0 flex-1">
            <p class="font-medium text-gray-900 dark:text-white">Sample offer</p>
            @if($show_price)<p class="text-sm text-gray-600 dark:text-gray-300">$9.99</p>@endif
            @if($show_description)<p class="text-xs text-gray-500 dark:text-gray-400">Description text</p>@endif
            <button type="button" class="mt-2 text-sm px-3 py-1.5 rounded bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-white">Add to order</button>
          </div>
        </div>
        @if(($config['display_mode'] ?? 'stacked') === 'stacked')
          <div class="border border-gray-200 dark:border-white/10 rounded-lg p-3 flex gap-3">
            <div class="w-16 h-16 rounded bg-gray-200 dark:bg-gray-600 shrink-0"></div>
            <div class="min-w-0 flex-1">
              <p class="font-medium text-gray-900 dark:text-white">Second offer</p>
              <p class="text-sm text-gray-600 dark:text-gray-300">$12.00</p>
              <button type="button" class="mt-2 text-sm px-3 py-1.5 rounded bg-gray-200 dark:bg-gray-600">Add to order</button>
            </div>
          </div>
        @endif
      @endif
    @endif

    @if($surface === 'checkout' && $type === 'checkout_upgrade_card')
      @php
        $headline = (string) ($config['headline'] ?? 'Upgrade to a subscription and save!');
        $description = (string) ($config['description'] ?? 'Upgrade eligible items in your cart to a subscription.');
        $cta = (string) ($config['cta_label'] ?? 'Upgrade');
        $plans = is_array($config['plans'] ?? null) ? $config['plans'] : [];
        $mappings = is_array($config['upgrade_mappings'] ?? null) ? $config['upgrade_mappings'] : [];

        $previewItems = [];
        foreach ($mappings as $m) {
          if (!is_array($m)) continue;
          $match = is_array($m['match'] ?? null) ? $m['match'] : [];
          $target = (string) ($m['target_variant_id'] ?? '');
          $actionType = (string) ($m['action_type'] ?? 'subscription');

          $parts = [];
          if (!empty($match['product_id'])) $parts[] = 'product_id=' . $match['product_id'];
          if (!empty($match['variant_id'])) $parts[] = 'variant_id=' . $match['variant_id'];
          if (!empty($match['sku_segment'])) $parts[] = 'sku_contains=' . $match['sku_segment'];
          if (!empty($match['sku_regex'])) $parts[] = 'sku_regex';
          if (!empty($match['line_item_property_exists'])) $parts[] = 'prop_exists=' . $match['line_item_property_exists'];
          if (!empty($match['line_item_property_equals']) && is_array($match['line_item_property_equals'])) $parts[] = 'prop_equals';

          $matchLabel = $parts ? implode(', ', $parts) : 'No match conditions (will never show)';
          $previewItems[] = [
            'match' => $matchLabel,
            'action' => $actionType,
            'target' => $target !== '' ? $target : '—',
          ];
        }

        if (count($previewItems) === 0) {
          $previewItems = [
            ['match' => 'variant_id=gid://shopify/ProductVariant/…', 'action' => 'subscription', 'target' => 'gid://shopify/ProductVariant/…'],
            ['match' => 'sku_contains=SUB', 'action' => 'bundle_swap', 'target' => 'gid://shopify/ProductVariant/…'],
          ];
        }

        $planOptions = [];
        foreach ($plans as $p) {
          if (!is_array($p)) continue;
          $id = (string) ($p['id'] ?? $p['value'] ?? '');
          $label = (string) ($p['label'] ?? $p['name'] ?? $id);
          if ($id !== '') $planOptions[] = $label;
        }
      @endphp

      <div class="border border-gray-200 dark:border-white/10 rounded-lg p-4 space-y-3">
        @if($headline !== '')
          <p class="font-semibold text-gray-900 dark:text-white">{{ $headline }}</p>
        @endif
        @if($description !== '')
          <p class="text-sm text-gray-600 dark:text-gray-300">{{ Str::limit($description, 140) }}</p>
        @endif

        <div class="space-y-1">
          @foreach(array_slice($previewItems, 0, 3) as $it)
            <p class="text-xs text-gray-600 dark:text-gray-300">
              <span class="font-medium">Match:</span> {{ $it['match'] }}
              <span class="mx-1">→</span>
              <span class="font-medium">Action:</span> {{ $it['action'] }}
              <span class="mx-1">→</span>
              <span class="font-medium">Target:</span> <span class="font-mono break-all">{{ $it['target'] }}</span>
            </p>
          @endforeach
          @if(count($previewItems) > 3)
            <p class="text-xs text-gray-500 dark:text-gray-400">+{{ count($previewItems) - 3 }} more mapping(s)…</p>
          @endif
        </div>

        @if(count($planOptions) > 0)
          <div>
            <p class="text-xs font-medium text-gray-700 dark:text-gray-300 mb-1">Plan</p>
            <div class="w-full rounded border border-gray-300 dark:border-white/20 bg-white dark:bg-white/5 px-3 py-2 text-sm text-gray-700 dark:text-gray-200">
              {{ $planOptions[0] }}
            </div>
          </div>
        @endif

        <button type="button" class="w-full text-sm px-3 py-2 rounded bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-900">
          {{ $cta }}
        </button>

        <p class="text-xs text-gray-500 dark:text-gray-400">
          Preview only. This card appears in Checkout only when a cart line matches your mapping conditions.
        </p>
      </div>
    @endif

    @if($surface === 'checkout' && $type === 'checkout_upgrade_all_otp')
      @php
        $headline = (string) ($config['headline'] ?? 'UPGRADE TO SUBSCRIPTION AND SAVE');
        $subtext = (string) ($config['subtext'] ?? '');
        $productListLabel = (string) ($config['product_list_label'] ?? 'Deliver every {{frequency}}:');
        $cta = (string) ($config['cta_label'] ?? 'SUBSCRIBE & SAVE');
      @endphp
      <div class="border border-gray-200 dark:border-white/10 rounded-lg p-4 space-y-3">
        @if($headline !== '')
          <p class="font-semibold text-gray-900 dark:text-white">{{ $headline }}</p>
        @endif
        @if($subtext !== '')
          <p class="text-sm text-gray-600 dark:text-gray-300 whitespace-pre-line">{{ Str::limit($subtext, 200) }}</p>
        @endif
        @if($productListLabel !== '')
          <p class="text-xs font-medium text-gray-700 dark:text-gray-300">{{ str_replace('{{frequency}}', '30 days', $productListLabel) }}</p>
        @endif
        <button type="button" class="w-full text-sm px-3 py-2 rounded bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-900">
          {{ $cta }}
        </button>
        <p class="text-xs text-gray-500 dark:text-gray-400">
          Preview only. Shown in Checkout when cart has no subscriptions; one click converts all eligible items to subscription.
        </p>
      </div>
    @endif

    @if($surface === 'checkout' && $type === 'progress_bar')
      @php
        $goal = (float)($config['progress_bar_goal'] ?? 100);
        $remaining = 45.00;
      @endphp
      <p class="font-medium text-gray-900 dark:text-white">{{ str_replace(['{amount}', '{goal}'], ['$' . number_format($remaining, 2), '$' . number_format($goal, 2)], $config['progress_bar_message_below'] ?? "You're {amount} away from free shipping!") }}</p>
      <div class="h-2 w-full bg-gray-200 dark:bg-gray-600 rounded-full overflow-hidden">
        <div class="h-full bg-primary-500 rounded-full" style="width: {{ $goal > 0 ? min(100, (($goal - $remaining) / $goal) * 100) : 0 }}%"></div>
      </div>
    @endif

    @if($type === 'content_icon_features')
      @php $items = $config['icon_features'] ?? []; @endphp
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
        @forelse($items as $item)
          <div class="text-center p-2">
            <div class="w-10 h-10 mx-auto rounded-full bg-primary-100 dark:bg-primary-900/30 flex items-center justify-center text-primary-600 dark:text-primary-400 text-lg mb-2">●</div>
            <p class="font-semibold text-gray-900 dark:text-white text-sm">{{ $item['title'] ?? '—' }}</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $item['subtitle'] ?? '' }}</p>
          </div>
        @empty
          <p class="text-sm text-gray-500 dark:text-gray-400 col-span-full">Add items in Icon features section.</p>
        @endforelse
      </div>
    @endif

    @if(in_array($type, ['content_banner', 'content_rich_text', 'content_button']))
      @if(!empty($config['image_url']) && $type === 'content_banner')
        <img src="{{ $config['image_url'] }}" alt="" class="w-full h-24 object-cover rounded-lg" onerror="this.style.display='none'">
      @endif
      @if(!empty($config['title']))
        <p class="font-semibold text-gray-900 dark:text-white">{{ $config['title'] }}</p>
      @endif
      @if(!empty($config['body']))
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ Str::limit($config['body'], 120) }}</p>
      @endif
      @if(!empty($config['button_label']))
        <button type="button" class="text-sm px-3 py-2 rounded {{ ($config['button_kind'] ?? 'secondary') === 'primary' ? 'bg-primary-600 text-white' : 'bg-gray-200 dark:bg-gray-600 text-gray-800 dark:text-white' }}">{{ $config['button_label'] }}</button>
      @endif
    @endif

    @if($type === 'content_product_card')
      @if(!empty($config['image_url']))
        <img src="{{ $config['image_url'] }}" alt="" class="w-full h-32 object-cover rounded-lg" onerror="this.style.display='none'">
      @else
        <div class="w-full h-32 rounded-lg bg-gray-200 dark:bg-gray-600"></div>
      @endif
      @if(!empty($config['title']))<p class="font-semibold text-gray-900 dark:text-white">{{ $config['title'] }}</p>@endif
      @if(!empty($config['body']))<p class="text-sm text-gray-600 dark:text-gray-300">{{ Str::limit($config['body'], 80) }}</p>@endif
      @if(!empty($config['badge_text']))<span class="inline-block text-xs px-2 py-0.5 rounded bg-success-100 dark:bg-success-900/30 text-success-700 dark:text-success-400">{{ $config['badge_text'] }}</span>@endif
      @if(!empty($config['show_price']) && !empty($config['price_text']))<p class="font-medium">{{ $config['price_text'] }}</p>@endif
      @if(!empty($config['button_label']))<button type="button" class="text-sm px-3 py-2 rounded bg-gray-200 dark:bg-gray-600">{{ $config['button_label'] }}</button>@endif
    @endif

    @if($surface === 'post_purchase' && $type === 'post_purchase_funnel')
      @if(!empty($config['funnel_headline_template']))
        <p class="font-semibold text-gray-900 dark:text-white">{{ str_replace(['{first_name}', '{order_id}'], ['Customer', '123'], $config['funnel_headline_template']) }}</p>
      @endif
      @if(!empty($config['funnel_show_progress']))
        @php $labels = array_filter(array_map('trim', explode(',', $config['funnel_step_labels'] ?? 'Order, Offer, Bonus, Done'))); @endphp
        <div class="flex gap-2 text-xs">
          @foreach(array_slice($labels, 0, 4) as $i => $l)
            <span class="px-2 py-1 rounded {{ $i === 1 ? 'bg-primary-100 dark:bg-primary-900/30 font-medium' : 'bg-gray-100 dark:bg-gray-700' }}">{{ $l }}</span>
          @endforeach
        </div>
      @endif
      @if(!empty($config['show_timer']))
        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $config['timer_label'] ?? 'For a limited time' }} 4:55</p>
      @endif
      @if(count($preview_offers) > 0)
        @foreach($preview_offers as $offer)
          <div class="border border-gray-200 dark:border-white/10 rounded-lg p-3 flex gap-3">
            <div class="w-20 h-20 rounded bg-gray-200 dark:bg-gray-600 shrink-0 overflow-hidden flex items-center justify-center">
              @if(!empty($offer['image_url']))
                <img src="{{ $offer['image_url'] }}" alt="" class="w-full h-full object-cover" onerror="this.style.display='none'">
              @endif
            </div>
            <div class="min-w-0 flex-1">
              <p class="font-medium text-gray-900 dark:text-white">{{ $offer['title'] ?: 'Offer product' }}</p>
              <p class="text-sm">{{ $offer['price'] ?? '$8.00' }} <span class="text-success-600">Save 20%</span></p>
              @if(!empty($offer['description']))<p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ Str::limit($offer['description'], 60) }}</p>@endif
              @if(!empty($config['urgency_message']))<p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ Str::limit($config['urgency_message'], 60) }}</p>@endif
              <div class="mt-2 flex flex-wrap gap-2">
                <button type="button" class="text-sm px-3 py-2 rounded bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-900">{{ $config['cta_text'] ?? 'Pay Now' }}</button>
                <button type="button" class="text-sm text-primary-600 dark:text-primary-400">{{ $config['decline_text'] ?? 'Decline offer' }}</button>
              </div>
            </div>
          </div>
        @endforeach
      @else
        <div class="border border-gray-200 dark:border-white/10 rounded-lg p-3 flex gap-3">
          <div class="w-20 h-20 rounded bg-gray-200 dark:bg-gray-600 shrink-0"></div>
          <div class="min-w-0 flex-1">
            <p class="font-medium text-gray-900 dark:text-white">Offer product</p>
            <p class="text-sm">$8.00 <span class="text-success-600">Save 20%</span></p>
            @if(!empty($config['urgency_message']))<p class="text-xs text-gray-500 dark:text-gray-400 mt-1">{{ Str::limit($config['urgency_message'], 60) }}</p>@endif
            <div class="mt-2 flex flex-wrap gap-2">
              <button type="button" class="text-sm px-3 py-2 rounded bg-gray-800 dark:bg-gray-200 text-white dark:text-gray-900">{{ $config['cta_text'] ?? 'Pay Now' }}</button>
              <button type="button" class="text-sm text-primary-600 dark:text-primary-400">{{ $config['decline_text'] ?? 'Decline offer' }}</button>
            </div>
          </div>
        </div>
      @endif
    @endif

    @if(empty($surface) || empty($type))
      <p class="text-sm text-gray-500 dark:text-gray-400">Select Surface and Type in the form, then click "Preview widget" to see the preview.</p>
    @endif
  </div>
</div>
