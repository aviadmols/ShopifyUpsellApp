<x-filament-panels::page>
    @if($step === 1)
        <form wire:submit="generate">
            {{ $this->form }}
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
