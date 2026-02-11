<x-filament-panels::page>
    <x-filament-panels::form
        id="form"
        :wire:key="$this->getId() . '.forms.data'"
        wire:submit="run"
    >
        {{ $this->form }}

        <x-filament-panels::form.actions
            :actions="$this->getCachedFormActions()"
            :full-width="$this->hasFullWidthFormActions()"
        />
    </x-filament-panels::form>

    @if ($result !== null)
        <div class="mt-6 rounded-xl bg-white dark:bg-white/5 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="p-6">
                <h3 class="text-lg font-semibold mb-2">Result</h3>
                @if (isset($result['error']))
                    <p class="text-danger-600 dark:text-danger-400">{{ $result['error'] }}</p>
                @elseif (! empty($result['message']))
                    <p class="text-gray-600 dark:text-gray-400">{{ $result['message'] }}</p>
                @endif

                @if (! isset($result['error']) && ! empty($result['eligible']))
                    <p class="text-success-600 dark:text-success-400 mb-3">
                        {{ count($result['eligible']) }} offer(s) would render
                        @if (! empty($result['placement_type']))
                            for <strong>{{ $result['placement_type'] }}</strong>
                        @endif
                        @if (! empty($result['display_mode']))
                            ({{ $result['display_mode'] }})
                        @endif
                    </p>
                    <div class="space-y-3 mb-4">
                        @if (($result['placement_type'] ?? '') === 'checkout' && ($result['display_mode'] ?? '') === 'single' && count($result['eligible']) > 0)
                            @php $previewOffer = $result['eligible'][0]; @endphp
                            <div class="border border-gray-200 dark:border-white/10 rounded-lg p-4 flex gap-4 max-w-md">
                                @if (! empty($previewOffer['image_url']))
                                    <img src="{{ $previewOffer['image_url'] }}" alt="" class="w-20 h-20 object-cover rounded" />
                                @else
                                    <div class="w-20 h-20 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center text-xs">No image</div>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <p class="font-medium truncate">{{ $previewOffer['title'] ?? 'Offer' }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">Variant: {{ $previewOffer['variantId'] ?? '-' }}</p>
                                </div>
                            </div>
                        @else
                            @foreach ($result['eligible'] as $offer)
                                <div class="border border-gray-200 dark:border-white/10 rounded-lg p-4 flex gap-4 max-w-md">
                                    @if (! empty($offer['image_url']))
                                        <img src="{{ $offer['image_url'] }}" alt="" class="w-20 h-20 object-cover rounded" />
                                    @else
                                        <div class="w-20 h-20 bg-gray-200 dark:bg-gray-700 rounded flex items-center justify-center text-xs">No image</div>
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <p class="font-medium truncate">{{ $offer['title'] ?? 'Offer' }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">Variant: {{ $offer['variantId'] ?? '-' }}</p>
                                    </div>
                                </div>
                            @endforeach
                        @endif
                    </div>
                @elseif (! isset($result['error']) && ! empty($result['block_ids']))
                    <p class="text-gray-600 dark:text-gray-400 mb-2">Thank you placement uses {{ count($result['block_ids']) }} block(s). Configure blocks in Thank You Blocks resource.</p>
                @elseif (! isset($result['error']) && empty($result['eligible']) && empty($result['block_ids']))
                    <p class="text-gray-600 dark:text-gray-400">No offer would render for this context.</p>
                @endif

                <details class="mt-4">
                    <summary class="cursor-pointer text-sm text-gray-500 dark:text-gray-400">Raw result (JSON)</summary>
                    <pre class="mt-2 p-4 bg-gray-100 dark:bg-gray-800 rounded text-xs overflow-auto">{{ json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
                </details>
            </div>
        </div>
    @endif
</x-filament-panels::page>
