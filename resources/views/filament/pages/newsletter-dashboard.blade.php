<x-filament-panels::page>
    @php
        $statTones = [
            'Active' => 'success',
            'Pending confirmation' => 'warning',
            'Unsubscribed' => 'gray',
            'New in last 30 days' => 'info',
        ];
        $statItems = collect($this->stats())->map(fn (array $stat, string $label): array => [
            'label' => $label,
            'value' => number_format($stat['value']),
            'description' => $stat['description'],
            'tone' => $statTones[$label] ?? 'primary',
        ])->values()->all();
    @endphp

    <x-admin.stat-band :columns="5" :items="$statItems" />

    <x-filament::section heading="Signups per week" description="Last 12 weeks.">
        {{-- A CSS bar chart: no charting library, and it reads fine when the numbers are all zero. --}}
        <div class="flex h-32 items-end gap-2">
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
    </x-filament::section>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section heading="Which pages win subscribers" description="Signups grouped by the page they came from.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-100 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-white/5 dark:text-gray-400">
                        <tr>
                            <th class="py-2.5 pe-4">Page</th>
                            <th class="py-2.5 pe-4">Source</th>
                            <th class="py-2.5 text-right">Signups</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-white/5 dark:text-gray-300">
                        @forelse ($this->topPages() as $page)
                            <tr>
                                <td class="py-2.5 pe-4 font-medium text-gray-950 dark:text-white">{{ $page['url'] }}</td>
                                <td class="py-2.5 pe-4 text-gray-500 dark:text-gray-400">{{ $page['source'] }}</td>
                                <td class="py-2.5 text-right font-semibold tabular-nums text-gray-950 dark:text-white">{{ $page['total'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-gray-500 dark:text-gray-400">No subscribers yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section heading="Recent campaigns" description="Opens are counted by a tracking image, which many mail clients block — treat them as a minimum, not an exact figure.">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-100 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-white/5 dark:text-gray-400">
                        <tr>
                            <th class="py-2.5 pe-4">Campaign</th>
                            <th class="py-2.5 pe-4 text-right">Sent</th>
                            <th class="py-2.5 text-right">Opened</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-white/5 dark:text-gray-300">
                        @forelse ($this->recentCampaigns() as $campaign)
                            <tr>
                                <td class="py-2.5 pe-4 font-medium text-gray-950 dark:text-white">{{ $campaign->name }}</td>
                                <td class="py-2.5 pe-4 text-right tabular-nums">{{ $campaign->recipients_count }}</td>
                                <td class="py-2.5 text-right tabular-nums">{{ $campaign->opened_count }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-gray-500 dark:text-gray-400">No campaigns sent yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
