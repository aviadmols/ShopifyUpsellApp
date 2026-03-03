<x-filament-panels::page>
    <div class="space-y-6">
        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="p-6">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Filters</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Choose a date range and optionally filter by <code class="rounded bg-gray-100 px-1 dark:bg-gray-800">checkout_attributes.igTestGroups</code>.
                </p>
                <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    {{ $this->form }}
                </div>
                <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">
                    Showing data: <strong>{{ $this->getDateRangeLabel() }}</strong>
                    @if (trim($this->ig_test_groups ?? '') !== '')
                        · igTestGroups: <strong>{{ e($this->ig_test_groups) }}</strong>
                    @endif
                </p>
            </div>
        </div>

        @php
            $sessionsSummary = $this->getSessionsSummary();
        @endphp
        <div>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Sessions</h3>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Total distinct sessions</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($sessionsSummary['total_sessions']) }}</p>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Sessions where widget was shown</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($sessionsSummary['sessions_viewed']) }}</p>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">Sessions with at least one click</p>
                    <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($sessionsSummary['sessions_clicked']) }}</p>
                </div>
            </div>
        </div>

        @php
            $summary = $this->getSummary();
        @endphp
        <div>
            <h3 class="mb-3 text-sm font-semibold uppercase tracking-wider text-gray-500 dark:text-gray-400">Events</h3>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        @if ($summary['filtered'])
                            Times displayed (with this igTestGroups)
                        @else
                            Total times displayed
                        @endif
                    </p>
                    <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['total_displays']) }}</p>
                </div>
                <div class="rounded-xl bg-white p-6 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                    <p class="text-sm font-medium text-gray-500 dark:text-gray-400">
                        @if ($summary['filtered'])
                            Button clicks (sessions with this igTestGroups)
                        @else
                            Total button clicks
                        @endif
                    </p>
                    <p class="mt-2 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['total_clicks']) }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Per block</h3>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 dark:divide-gray-700">
                    <thead class="bg-gray-50 dark:bg-gray-800/50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Block</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Times displayed</th>
                            <th scope="col" class="px-6 py-3 text-right text-xs font-medium uppercase tracking-wider text-gray-500 dark:text-gray-400">Button clicks</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                        @forelse ($this->getBlocksDisplayData() as $row)
                            <tr class="bg-white dark:bg-gray-900">
                                <td class="whitespace-nowrap px-6 py-4 text-sm font-medium text-gray-950 dark:text-white">
                                    {{ $row['block_name'] }}
                                    <span class="text-gray-400 dark:text-gray-500">(#{{ $row['block_id'] }})</span>
                                </td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-600 dark:text-gray-300">{{ number_format($row['displays']) }}</td>
                                <td class="whitespace-nowrap px-6 py-4 text-right text-sm text-gray-600 dark:text-gray-300">{{ number_format($row['clicks']) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="3" class="px-6 py-8 text-center text-sm text-gray-500 dark:text-gray-400">No block data yet. Display and click events will appear here.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
