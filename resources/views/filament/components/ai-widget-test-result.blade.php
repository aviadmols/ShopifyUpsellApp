<div class="space-y-4">
    @if(!empty($result['log']))
        <div>
            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300">Log</h4>
            <pre class="mt-1 rounded-lg bg-gray-100 dark:bg-gray-800 p-4 text-xs overflow-x-auto whitespace-pre-wrap">{{ e($result['log']) }}</pre>
        </div>
    @endif
    @if(!empty($result['summary']))
        <div class="rounded-lg bg-primary-50 dark:bg-primary-900/20 border border-primary-200 dark:border-primary-800 p-4">
            <h4 class="text-sm font-medium text-primary-800 dark:text-primary-200">Summary</h4>
            <p class="mt-1 text-sm text-primary-700 dark:text-primary-300">{{ e($result['summary']) }}</p>
        </div>
    @endif
</div>
