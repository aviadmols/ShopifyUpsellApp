<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex flex-wrap items-end gap-4 rounded-xl bg-gray-50 dark:bg-gray-800/50 p-4">
            <div class="min-w-[200px]">
                <label class="filament-forms-field-wrapper-label block text-sm font-medium text-gray-950 dark:text-white mb-1">Shop</label>
                <select
                    wire:model.live="shop_id"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="">Select shop</option>
                    @foreach($this->shops as $shop)
                        <option value="{{ $shop->id }}">{{ $shop->shop_domain }}</option>
                    @endforeach
                </select>
            </div>
            <x-filament::button wire:click="loadProducts" icon="heroicon-o-arrow-down-tray">
                Load products
            </x-filament::button>
        </div>

        @if($loadError)
            <div class="rounded-lg border border-danger-200 bg-danger-50 dark:border-danger-800 dark:bg-danger-900/20 p-4 text-sm text-danger-700 dark:text-danger-400">
                {{ $loadError }}
            </div>
        @endif

        @if(empty($products) && !$loadError && $shop_id)
            <p class="text-sm text-gray-500 dark:text-gray-400">Select a shop and click "Load products" to see products, variants and selling plans.</p>
        @endif

        @if(!empty($products))
            <p class="text-sm text-gray-600 dark:text-gray-300">Showing {{ count($products) }} products. Copy a variant ID or selling plan ID to use in Upgrade card and other fields.</p>
            <div class="space-y-6">
                @foreach($products as $product)
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
                        <div class="p-4 flex items-start gap-4 border-b border-gray-200 dark:border-gray-700">
                            @if(!empty($product['image_url']))
                                <img src="{{ $product['image_url'] }}" alt="" class="w-16 h-16 object-cover rounded-lg" />
                            @endif
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ $product['title'] }}</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 font-mono break-all">Product: {{ $product['id'] }}</p>
                            </div>
                        </div>
                        <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-200 dark:divide-gray-700">
                            <div class="p-4">
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Variants</h4>
                                @if(empty($product['variants']))
                                    <p class="text-xs text-gray-500">No variants</p>
                                @else
                                    <ul class="space-y-2 text-sm">
                                        @foreach($product['variants'] as $v)
                                            <li class="flex flex-wrap items-center gap-2">
                                                <span class="text-gray-700 dark:text-gray-300">{{ $v['title'] }}</span>
                                                @if($v['price'] !== null)
                                                    <span class="text-gray-500">{{ $v['price'] }}</span>
                                                @endif
                                                <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded break-all" title="Copy">{{ $v['id'] }}</code>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <div class="p-4">
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Selling Plans (Recharge / Shopify)</h4>
                                @if(empty($product['selling_plans']))
                                    <p class="text-xs text-gray-500">No selling plans</p>
                                @else
                                    <ul class="space-y-2 text-sm">
                                        @foreach($product['selling_plans'] as $sp)
                                            <li class="flex flex-wrap items-center gap-2">
                                                <span class="text-gray-700 dark:text-gray-300">{{ $sp['name'] }}</span>
                                                @if(isset($sp['discount_percent']) && $sp['discount_percent'] !== null)
                                                    <span class="text-green-600 dark:text-green-400 font-medium" title="Discount % (from Recharge/Shopify)">{{ number_format((float) $sp['discount_percent'], 1) }}% off</span>
                                                @else
                                                    <span class="text-gray-400 dark:text-gray-500 text-xs">—</span>
                                                @endif
                                                <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded break-all" title="Copy">{{ $sp['id'] }}</code>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>
