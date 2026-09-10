<x-filament-panels::page>
    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        @foreach ($this->stats() as $label => $stat)
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
                <p class="mt-1 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($stat['value']) }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $stat['description'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <p class="text-sm font-medium text-gray-950 dark:text-white">Signups per week</p>
        <p class="text-xs text-gray-500 dark:text-gray-400">Last 12 weeks.</p>

        {{-- A CSS bar chart: no charting library, and it reads fine when the numbers are all zero. --}}
        <div class="mt-4 flex h-32 items-end gap-2">
            @php $max = $this->maxGrowth(); @endphp
            @foreach ($this->growth() as $week)
                <div class="flex flex-1 flex-col items-center gap-1">
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $week['total'] ?: '' }}</span>
                    <div
                        class="w-full rounded-t bg-primary-500/80"
                        style="height: {{ max(2, (int) round($week['total'] / $max * 100)) }}%"
                        title="{{ $week['total'] }} signups in the week of {{ $week['label'] }}"
                    ></div>
                    <span class="text-[0.65rem] text-gray-400">{{ $week['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-950 dark:text-white">Which pages win subscribers</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">Signups grouped by the page they came from.</p>

            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="py-2">Page</th>
                            <th class="py-2">Source</th>
                            <th class="py-2 text-right">Signups</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->topPages() as $page)
                            <tr>
                                <td class="py-2 text-gray-950 dark:text-white">{{ $page['url'] }}</td>
                                <td class="py-2 text-gray-500 dark:text-gray-400">{{ $page['source'] }}</td>
                                <td class="py-2 text-right font-medium text-gray-950 dark:text-white">{{ $page['total'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-3 text-gray-500 dark:text-gray-400">No subscribers yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-950 dark:text-white">Recent campaigns</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Opens are counted by a tracking image, which many mail clients block — treat them as a minimum, not an exact figure.
            </p>

            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="py-2">Campaign</th>
                            <th class="py-2 text-right">Sent</th>
                            <th class="py-2 text-right">Opened</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->recentCampaigns() as $campaign)
                            <tr>
                                <td class="py-2 text-gray-950 dark:text-white">{{ $campaign->name }}</td>
                                <td class="py-2 text-right">{{ $campaign->recipients_count }}</td>
                                <td class="py-2 text-right">{{ $campaign->opened_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-3 text-gray-500 dark:text-gray-400">No campaigns sent yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>
