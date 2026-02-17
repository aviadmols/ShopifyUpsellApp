<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit="generate" class="space-y-4">
            {{ $this->form }}
            <div class="flex gap-3 items-center">
                <x-filament::button type="submit" color="primary" :disabled="$loading">
                    {{ $loading ? 'Generating…' : 'Generate AI Summary' }}
                </x-filament::button>
                @if($session_key)
                    <span class="text-xs text-gray-500 dark:text-gray-400">Session: <span class="font-mono break-all">{{ $session_key }}</span></span>
                @endif
            </div>
        </form>

        @if($summary)
            <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10 p-4 space-y-2">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-medium text-gray-900 dark:text-white">Summary (English)</h3>
                    <x-filament::button
                        type="button"
                        color="gray"
                        outline
                        x-data
                        x-on:click="navigator.clipboard.writeText(@js($summary)); $dispatch('notify', { message: 'Summary copied' })"
                    >
                        Copy
                    </x-filament::button>
                </div>
                @php
                    $summaryLines = array_filter(array_map('trim', explode("\n", (string) $summary)));
                @endphp
                @if(count($summaryLines) > 0)
                    <ul class="list-disc list-inside space-y-1.5 text-sm text-gray-700 dark:text-gray-200">
                        @foreach($summaryLines as $line)
                            <li>{{ e(ltrim($line, "- •\t ")) }}</li>
                        @endforeach
                    </ul>
                @else
                    <pre class="text-sm whitespace-pre-wrap text-gray-700 dark:text-gray-200">{{ e($summary) }}</pre>
                @endif
            </div>
        @endif

        @if($debug_payload)
            <details class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10 p-4">
                <summary class="cursor-pointer text-sm font-medium text-gray-900 dark:text-white">Debug payload (JSON sent to AI)</summary>
                <pre class="mt-3 text-xs bg-gray-100 dark:bg-gray-800 p-3 rounded overflow-x-auto whitespace-pre-wrap">{{ e($debug_payload) }}</pre>
            </details>
        @endif
    </div>
</x-filament-panels::page>

