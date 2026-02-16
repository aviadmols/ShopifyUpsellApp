<x-filament-panels::page>
    <div class="space-y-6">
        <div class="flex flex-wrap items-end gap-4 rounded-xl bg-gray-50 dark:bg-gray-800/50 p-4">
            <div class="min-w-[200px]">
                <label class="filament-forms-field-wrapper-label block text-sm font-medium text-gray-950 dark:text-white mb-1">חנות</label>
                <select
                    wire:model.live="shop_id"
                    class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white text-sm focus:border-primary-500 focus:ring-primary-500"
                >
                    <option value="">בחר חנות</option>
                    @foreach($this->shops as $shop)
                        <option value="{{ $shop->id }}">{{ $shop->shop_domain }}</option>
                    @endforeach
                </select>
            </div>
            <x-filament::button wire:click="loadProducts" icon="heroicon-o-arrow-down-tray">
                טען מוצרים
            </x-filament::button>
        </div>

        @if($loadError)
            <div class="rounded-lg border border-danger-200 bg-danger-50 dark:border-danger-800 dark:bg-danger-900/20 p-4 text-sm text-danger-700 dark:text-danger-400">
                {{ $loadError }}
            </div>
        @endif

        @if(empty($products) && !$loadError && $shop_id)
            <p class="text-sm text-gray-500 dark:text-gray-400">בחר חנות ולחץ «טען מוצרים» כדי לראות מוצרים, וריאנטים ותוכניות מכירה (Selling Plans).</p>
        @endif

        @if(!empty($products))
            <p class="text-sm text-gray-600 dark:text-gray-300">מציג {{ count($products) }} מוצרים. העתק מזהה וריאנט או תוכנית מכירה לשימוש בשדות Upgrade card וכדומה.</p>
            <div class="space-y-6">
                @foreach($products as $product)
                    <div class="rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-800 overflow-hidden">
                        <div class="p-4 flex items-start gap-4 border-b border-gray-200 dark:border-gray-700">
                            @if(!empty($product['image_url']))
                                <img src="{{ $product['image_url'] }}" alt="" class="w-16 h-16 object-cover rounded-lg" />
                            @endif
                            <div class="flex-1 min-w-0">
                                <h3 class="font-semibold text-gray-900 dark:text-white">{{ $product['title'] }}</h3>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 font-mono break-all">מוצר: {{ $product['id'] }}</p>
                            </div>
                        </div>
                        <div class="grid md:grid-cols-2 divide-y md:divide-y-0 md:divide-x divide-gray-200 dark:divide-gray-700">
                            <div class="p-4">
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">וריאנטים</h4>
                                @if(empty($product['variants']))
                                    <p class="text-xs text-gray-500">אין וריאנטים</p>
                                @else
                                    <ul class="space-y-2 text-sm">
                                        @foreach($product['variants'] as $v)
                                            <li class="flex flex-wrap items-center gap-2">
                                                <span class="text-gray-700 dark:text-gray-300">{{ $v['title'] }}</span>
                                                @if($v['price'] !== null)
                                                    <span class="text-gray-500">{{ $v['price'] }}</span>
                                                @endif
                                                <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded break-all" title="להעתקה">{{ $v['id'] }}</code>
                                            </li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>
                            <div class="p-4">
                                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Selling Plans</h4>
                                @if(empty($product['selling_plans']))
                                    <p class="text-xs text-gray-500">אין תוכניות מכירה</p>
                                @else
                                    <ul class="space-y-2 text-sm">
                                        @foreach($product['selling_plans'] as $sp)
                                            <li class="flex flex-wrap items-center gap-2">
                                                <span class="text-gray-700 dark:text-gray-300">{{ $sp['name'] }}</span>
                                                <code class="text-xs bg-gray-100 dark:bg-gray-700 px-1.5 py-0.5 rounded break-all" title="להעתקה">{{ $sp['id'] }}</code>
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
