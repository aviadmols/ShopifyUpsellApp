<x-filament-panels::page>
    <div class="space-y-4">
        <div class="rounded-xl bg-gray-100 dark:bg-gray-800 border border-gray-200 dark:border-gray-700 p-4">
            <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Extension secret (Checkout)</p>
            <div class="mt-2 flex flex-wrap items-center gap-3">
                @if($extensionSecret !== '')
                    <code class="flex-1 min-w-0 font-mono text-sm break-all select-all">{{ e($extensionSecret) }}</code>
                    <button
                        type="button"
                        x-data="{ copied: false }"
                        x-on:click="navigator.clipboard.writeText(@js($extensionSecret)); copied = true; setTimeout(() => copied = false, 2000)"
                        class="shrink-0 inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-700 border border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.666 3.888A2.25 2.25 0 0013.5 2.25h-3c-1.03 0-1.9.693-2.166 1.638m7.332 0c.055.194.084.4.084.612v0a.75.75 0 01-.75.75H9a.75.75 0 01-.75-.75v0c0-.212.03-.418.084-.612m7.332 0c.646.049 1.288.11 1.927.184 1.1.128 1.907 1.077 1.907 2.185V19.5a2.25 2.25 0 01-2.25 2.25H6.75A2.25 2.25 0 014.5 19.5V6.257c0-1.108.806-2.057 1.907-2.185a48.208 48.208 0 011.927-.184" /></svg>
                        <span x-text="copied ? 'Copied!' : 'Copy'">Copy</span>
                    </button>
                @else
                    <span class="text-gray-500 dark:text-gray-400">Not set. Set CHECKOUT_EXTENSION_SECRET in .env or Railway.</span>
                @endif
            </div>
        </div>

        @if(! $fileExists)
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Log file not created yet. Open Checkout (or the checkout editor) to generate widget logs, then refresh.
            </p>
        @endif

        <div class="rounded-lg bg-gray-100 dark:bg-gray-800 p-4 font-mono text-xs overflow-x-auto whitespace-pre-wrap break-all">
            {{ e($logContent ?? 'No content') }}
        </div>
    </div>
</x-filament-panels::page>

