<x-filament-panels::page>
    @if($step === 1)
        <form wire:submit="generate">
            {{ $this->form }}
            @if(($type ?? null) === 'checkout_upgrade_card')
                <div class="mt-4 rounded-xl border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40 p-4 space-y-3">
                    <div>
                        <p class="text-sm font-medium text-gray-900 dark:text-white">Product / Variant helper</p>
                        <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                            Type <code class="px-1 py-0.5 rounded bg-gray-100 dark:bg-gray-800">@</code> in the prompt, then start typing a product/variant name.
                            Click a result to insert the Variant ID. After selecting a variant, selling plan IDs will appear (if any) and you can click to insert.
                        </p>
                    </div>

                    @if($mentionOpen)
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 p-3">
                            <p class="text-xs text-gray-600 dark:text-gray-300 mb-2">Search: <span class="font-mono">@{{ $mentionQuery }}</span></p>
                            @if(empty($mentionResults))
                                <p class="text-xs text-gray-500 dark:text-gray-400">Type at least 2 characters after @ to search variants.</p>
                            @else
                                <div class="space-y-1">
                                    @foreach($mentionResults as $r)
                                        <button
                                            type="button"
                                            wire:click="selectMentionVariant(@js($r['id']))"
                                            class="w-full text-left rounded-md px-2 py-1 text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
                                        >
                                            <span class="block text-gray-900 dark:text-white">{{ $r['label'] }}</span>
                                            <span class="block text-xs font-mono text-gray-500 dark:text-gray-400 break-all">{{ $r['id'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($mentionSelectedVariantId)
                        <div class="rounded-lg border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800/40 p-3 space-y-2">
                            <p class="text-xs text-gray-600 dark:text-gray-300">
                                Selected variant:
                                <span class="font-mono break-all">{{ $mentionSelectedVariantId }}</span>
                            </p>

                            @if(!empty($mentionSellingPlans))
                                <p class="text-xs font-medium text-gray-700 dark:text-gray-200">Selling plans (click to insert ID)</p>
                                <div class="space-y-1">
                                    @foreach($mentionSellingPlans as $sp)
                                        <button
                                            type="button"
                                            wire:click="insertSellingPlanId(@js($sp['id']))"
                                            class="w-full text-left rounded-md px-2 py-1 text-sm hover:bg-gray-100 dark:hover:bg-gray-700"
                                        >
                                            <span class="block text-gray-900 dark:text-white">{{ $sp['name'] ?? $sp['id'] }}</span>
                                            <span class="block text-xs font-mono text-gray-500 dark:text-gray-400 break-all">{{ $sp['id'] }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @else
                                <p class="text-xs text-gray-500 dark:text-gray-400">No selling plans found for this variant.</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endif
            <div class="mt-6 flex gap-3">
                <x-filament::button type="submit" color="primary">
                    Generate with AI
                </x-filament::button>
                <x-filament::button tag="a" href="{{ \App\Filament\Resources\BlockResource::getUrl('index') }}" color="gray" outline>
                    Cancel
                </x-filament::button>
            </div>
        </form>
    @else
        <div class="space-y-6">
            <div class="rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4">
                <h3 class="font-semibold text-gray-900 dark:text-white">{{ $generated['name'] ?? 'Widget' }}</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">{{ $generated['description'] ?? '' }}</p>
            </div>

            <div>
                <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Config (JSON)</h4>
                <pre class="mt-1 rounded-lg bg-gray-100 dark:bg-gray-800 p-4 text-xs overflow-x-auto whitespace-pre-wrap">{{ json_encode($generated['config'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>

            @if(!empty($generated['php_snippet'] ?? ''))
                <div>
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">PHP / logic (for reference)</h4>
                    <details class="mt-1 rounded-lg border border-gray-200 dark:border-gray-700 bg-white dark:bg-gray-900/40">
                        <summary class="cursor-pointer px-3 py-2 text-xs font-medium text-gray-700 dark:text-gray-300">Open full generated PHP / logic code</summary>
                        <pre class="rounded-b-lg bg-gray-100 dark:bg-gray-800 p-4 text-xs overflow-x-auto whitespace-pre font-mono">{{ e($generated['php_snippet']) }}</pre>
                    </details>
                </div>
            @endif

            <div class="flex flex-wrap gap-3">
                <x-filament::button wire:click="runTest" color="gray">
                    Run test
                </x-filament::button>
                <x-filament::button wire:click="saveWidget" color="primary">
                    Save widget
                </x-filament::button>
                <x-filament::button wire:click="backToStep1" color="gray" variant="outline">
                    Back
                </x-filament::button>
            </div>

            @if($testLog !== '')
                <div>
                    <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Test log</h4>
                    <pre class="mt-1 rounded-lg bg-gray-100 dark:bg-gray-800 p-4 text-xs overflow-x-auto whitespace-pre-wrap">{{ e($testLog) }}</pre>
                </div>
            @endif

            @if($testSummary)
                <div class="rounded-lg bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 p-4">
                    <h4 class="text-sm font-medium text-primary-800 dark:text-primary-200">Summary</h4>
                    <p class="mt-1 text-sm text-primary-700 dark:text-primary-300">{{ e($testSummary) }}</p>
                </div>
            @endif
        </div>
    @endif
</x-filament-panels::page>
