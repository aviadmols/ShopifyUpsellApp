<x-filament-panels::page>
    <div class="space-y-6">
        <form wire:submit="viewEvents" class="space-y-4">
            {{ $this->form }}
            <x-filament::button type="submit" color="primary">
                View events
            </x-filament::button>
        </form>
        <p class="text-sm text-gray-500 dark:text-gray-400">
            You can also filter by this ID directly on the <a href="{{ \App\Filament\Resources\WidgetSessionEventResource::getUrl('index') }}" class="text-primary-600 dark:text-primary-400 underline">Widget session logs</a> page using the «User ID (_zyxel_user_id)» filter.
        </p>
    </div>
</x-filament-panels::page>
