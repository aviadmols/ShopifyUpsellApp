<x-filament-panels::page>
    <div class="space-y-6">
        <x-filament::tabs wire:model="activeTab" class="fi-ta-tabs">
            <x-filament::tabs.tab value="settings" label="Settings" />
            <x-filament::tabs.tab value="logs" label="Logs" />
        </x-filament::tabs>

        @if($activeTab === 'settings')
            <form wire:submit="save">
                {{ $this->form }}
                <div class="mt-6">
                    <x-filament::button type="submit">Save</x-filament::button>
                </div>
            </form>
        @else
            {{-- Logs tab: filters + table --}}
            <div class="space-y-4" x-data="{ copyFeedback: null }">
                <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                    <div class="p-4 flex flex-wrap gap-4 items-end">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Flow</label>
                            <select wire:model.live="logFlow" class="fi-input block w-full rounded-lg border-gray-300 dark:border-white/20 dark:bg-white/5 text-sm">
                                <option value="">All</option>
                                @foreach(\App\Models\AiRequestLog::flows() as $f)
                                    <option value="{{ $f }}">{{ $f }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Status</label>
                            <select wire:model.live="logStatus" class="fi-input block w-full rounded-lg border-gray-300 dark:border-white/20 dark:bg-white/5 text-sm">
                                <option value="">All</option>
                                <option value="ok">ok</option>
                                <option value="error">error</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Model</label>
                            <input type="text" wire:model.live.debounce.300ms="logModel" placeholder="Filter by model" class="fi-input block w-full rounded-lg border-gray-300 dark:border-white/20 dark:bg-white/5 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">From date</label>
                            <input type="date" wire:model.live="logDateFrom" class="fi-input block w-full rounded-lg border-gray-300 dark:border-white/20 dark:bg-white/5 text-sm" />
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">To date</label>
                            <input type="date" wire:model.live="logDateTo" class="fi-input block w-full rounded-lg border-gray-300 dark:border-white/20 dark:bg-white/5 text-sm" />
                        </div>
                    </div>
                </div>

                <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10 overflow-hidden">
                    <table class="fi-ta-table w-full table-auto divide-y divide-gray-200 dark:divide-white/5">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">ID</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Flow</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Model</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Status</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Duration</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Created</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 dark:divide-white/5">
                            @forelse($this->getAiLogs() as $log)
                                <tr class="dark:bg-white/5">
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $log->id }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $log->flow }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $log->model ?? '—' }}</td>
                                    <td class="px-4 py-3">
                                        <span class="fi-badge fi-badge-{{ $log->status === 'ok' ? 'success' : 'danger' }}">{{ $log->status }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $log->duration_ms !== null ? $log->duration_ms . ' ms' : '—' }}</td>
                                    <td class="px-4 py-3 text-sm text-gray-700 dark:text-gray-300">{{ $log->created_at?->format('Y-m-d H:i:s') }}</td>
                                    <td class="px-4 py-3">
                                        <button type="button" wire:click="openLogModal({{ $log->id }})" class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus:ring-2 rounded-lg fi-btn-color-gray fi-btn-size-sm fi-btn-outline">
                                            View
                                        </button>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No AI request logs yet.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                    @if($this->getAiLogs()->hasPages())
                        <div class="px-4 py-3 border-t border-gray-200 dark:border-white/5">
                            {{ $this->getAiLogs()->links() }}
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>

    {{-- Modal: log detail + copy prompt --}}
    @if($viewingLogId)
        @php $viewLog = $this->getViewingLog(); @endphp
        @if($viewLog)
            <x-filament::modal id="ai-log-detail" width="4xl" :close-button="true" wire:close="closeLogModal">
                <x-slot name="heading">AI Request #{{ $viewLog->id }}</x-slot>
                <div class="space-y-4 max-h-[70vh] overflow-y-auto">
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Flow / Model / Status / Duration</h4>
                        <p class="text-sm text-gray-600 dark:text-gray-400">{{ $viewLog->flow }} · {{ $viewLog->model ?? '—' }} · {{ $viewLog->status }} · {{ $viewLog->duration_ms !== null ? $viewLog->duration_ms . ' ms' : '—' }} · {{ $viewLog->created_at?->format('Y-m-d H:i:s') }}</p>
                    </div>
                    <div class="flex gap-2 flex-wrap">
                        @php
                            $userPrompt = $this->getUserPromptFromRequest($viewLog->request_payload);
                            $systemPrompt = $this->getSystemPromptFromRequest($viewLog->request_payload);
                        @endphp
                        @if($userPrompt !== '')
                            <button type="button" x-data x-on:click="navigator.clipboard.writeText(@js($userPrompt)); $dispatch('notify', { message: 'User prompt copied' })" class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus:ring-2 rounded-lg fi-btn-color-gray fi-btn-size-sm fi-btn-outline">
                                Copy user prompt
                            </button>
                        @endif
                        @if($systemPrompt !== '')
                            <button type="button" x-data x-on:click="navigator.clipboard.writeText(@js($systemPrompt)); $dispatch('notify', { message: 'System prompt copied' })" class="fi-btn relative grid-flow-col items-center justify-center font-semibold outline-none transition duration-75 focus:ring-2 rounded-lg fi-btn-color-gray fi-btn-size-sm fi-btn-outline">
                                Copy system prompt
                            </button>
                        @endif
                    </div>
                    @if($viewLog->error)
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Error</h4>
                            <pre class="text-sm bg-gray-100 dark:bg-gray-800 p-3 rounded overflow-x-auto">{{ $viewLog->error }}</pre>
                        </div>
                    @endif
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Request payload</h4>
                        <pre class="text-xs bg-gray-100 dark:bg-gray-800 p-3 rounded overflow-x-auto max-h-48 overflow-y-auto">{{ $viewLog->request_payload ? (is_string($viewLog->request_payload) && \Illuminate\Support\Str::isJson($viewLog->request_payload) ? json_encode(json_decode($viewLog->request_payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : e($viewLog->request_payload)) : '—' }}</pre>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Response payload</h4>
                        <pre class="text-xs bg-gray-100 dark:bg-gray-800 p-3 rounded overflow-x-auto max-h-48 overflow-y-auto">{{ $viewLog->response_payload ? (\Illuminate\Support\Str::isJson($viewLog->response_payload) ? json_encode(json_decode($viewLog->response_payload), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : e($viewLog->response_payload)) : '—' }}</pre>
                    </div>
                    @if($viewLog->parsed_output)
                        <div>
                            <h4 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">Parsed output</h4>
                            <pre class="text-xs bg-gray-100 dark:bg-gray-800 p-3 rounded overflow-x-auto max-h-48 overflow-y-auto">{{ $viewLog->parsed_output ? (is_string($viewLog->parsed_output) && \Illuminate\Support\Str::isJson($viewLog->parsed_output) ? json_encode(json_decode($viewLog->parsed_output), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) : e($viewLog->parsed_output)) : '—' }}</pre>
                        </div>
                    @endif
                </div>
            </x-filament::modal>
        @endif
    @endif

</x-filament-panels::page>
