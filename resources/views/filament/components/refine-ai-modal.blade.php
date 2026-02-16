@php
  $refinePreview = $refinePreview ?? null;
  $refineError = $refineError ?? null;
@endphp
<div class="space-y-4">
  @if($refineError)
    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $refineError }}</p>
  @endif

  @if(!$refinePreview)
    <div>
      <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">What should we change?</label>
      <textarea
        wire:model="refinePrompt"
        rows="4"
        class="block w-full rounded-lg border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white shadow-sm focus:border-primary-500 focus:ring-primary-500 text-sm font-mono"
        placeholder="e.g. Only show when cart has a product with SKU matching XXX-XXX-{number}-XXX where number is between 100-300, and show message X; if 300-400 show message Z."
      ></textarea>
      <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Describe the logic or text changes. The AI will update the rule conditions JSON and the PHP reference snippet.</p>
    </div>
    <div class="flex gap-2">
      <x-filament::button wire:click="runRefinePreview" color="primary">Generate preview</x-filament::button>
      <x-filament::button wire:click="clearRefinePreview" color="gray" variant="outline">Cancel</x-filament::button>
    </div>
  @else
    <div class="space-y-3">
      @if(!empty($refinePreview['explanation']))
        <p class="text-sm text-gray-700 dark:text-gray-300"><strong>Explanation:</strong> {{ $refinePreview['explanation'] }}</p>
      @endif
      @if(!empty($refinePreview['warnings']))
        <ul class="text-sm text-warning-600 dark:text-warning-400 list-disc list-inside">
          @foreach($refinePreview['warnings'] as $w)
            <li>{{ $w }}</li>
          @endforeach
        </ul>
      @endif

      <div>
        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Updated rule conditions (JSON)</p>
        <pre class="rounded-lg bg-gray-100 dark:bg-gray-800 p-3 text-xs overflow-x-auto whitespace-pre font-mono">{{ json_encode($refinePreview['updated_rule_conditions'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
      </div>
      <div>
        <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Updated PHP snippet (reference)</p>
        <pre class="rounded-lg bg-gray-100 dark:bg-gray-800 p-3 text-xs overflow-x-auto whitespace-pre font-mono max-h-40 overflow-y-auto">{{ e($refinePreview['updated_php_snippet'] ?? '') }}</pre>
      </div>
      @if(!empty($refinePreview['updated_text_fields']))
        <div>
          <p class="text-xs font-medium text-gray-500 dark:text-gray-400 mb-1">Updated text fields</p>
          <pre class="rounded-lg bg-gray-100 dark:bg-gray-800 p-3 text-xs overflow-x-auto whitespace-pre-wrap font-mono">{{ json_encode($refinePreview['updated_text_fields'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
        </div>
      @endif
    </div>
    <div class="flex gap-2 pt-2">
      <x-filament::button wire:click="applyRefinePreview" color="primary">Apply and save</x-filament::button>
      <x-filament::button wire:click="clearRefinePreview" color="gray" variant="outline">Back</x-filament::button>
    </div>
  @endif
</div>
