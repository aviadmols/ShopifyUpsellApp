@php
  $diagnosis = $diagnosis ?? null;
  $payload = $payload ?? null;
  $error = $error ?? null;
  $loading = $loading ?? false;
@endphp
<div class="space-y-4">
  @if($loading)
    <p class="text-sm text-gray-600 dark:text-gray-300">Running diagnosis…</p>
  @endif

  @if($error && !$loading)
    <p class="text-sm text-danger-600 dark:text-danger-400">{{ $error }}</p>
  @endif

  @if($diagnosis)
    <div class="rounded-lg bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 p-4">
      <h4 class="text-sm font-medium text-primary-800 dark:text-primary-200 mb-2">Diagnosis</h4>
      <div class="text-sm text-primary-700 dark:text-primary-300 whitespace-pre-wrap">{{ $diagnosis }}</div>
    </div>
  @endif

  @if($payload && ($diagnosis || $error))
    <details class="border border-gray-200 dark:border-gray-700 rounded-lg overflow-hidden">
      <summary class="cursor-pointer px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 bg-gray-50 dark:bg-gray-800 hover:bg-gray-100 dark:hover:bg-gray-700 list-none flex items-center gap-2">
        <span>▾</span>
        <span>Debug payload</span>
      </summary>
      <div class="border-t border-gray-200 dark:border-gray-700">
        <pre class="p-4 text-xs overflow-x-auto whitespace-pre-wrap font-mono bg-gray-100 dark:bg-gray-900 max-h-80 overflow-y-auto">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
      </div>
    </details>
  @endif

  @if(!$diagnosis && !$error && !$loading)
    <div>
      <p class="text-sm text-gray-500 dark:text-gray-400 mb-3">Run the AI diagnosis for this event (rule + context + OpenRouter).</p>
      <x-filament::button wire:click="runAiDiagnosis" color="primary">
        Run diagnosis
      </x-filament::button>
    </div>
  @endif
</div>
