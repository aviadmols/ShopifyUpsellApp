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
                @elseif (! empty($result['match']))
                    <p class="text-success-600 dark:text-success-400">
                        Match: Offer #{{ $result['match']['offerId'] }} – {{ $result['match']['title'] }}
                        (variant: {{ $result['match']['variantId'] }})
                    </p>
                @else
                    <p class="text-gray-600 dark:text-gray-400">
                        {{ $result['message'] ?? 'No offer would render for this context.' }}
                    </p>
                @endif
                <pre class="mt-2 p-4 bg-gray-100 dark:bg-gray-800 rounded text-xs overflow-auto">{{ json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>
            </div>
        </div>
    @endif
</x-filament-panels::page>
