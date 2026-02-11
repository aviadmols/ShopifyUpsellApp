<x-filament-panels::page>
    <div class="space-y-4">
        @if(! $fileExists)
            <p class="text-sm text-gray-500 dark:text-gray-400">Log file not created yet. Perform an action that calls Shopify (e.g. variant search in Offers, or load checkout) and then refresh.</p>
        @endif
        <div class="rounded-lg bg-gray-100 dark:bg-gray-800 p-4 font-mono text-xs overflow-x-auto whitespace-pre-wrap break-all">{{ e($logContent ?? 'No content') }}</div>
    </div>
</x-filament-panels::page>
