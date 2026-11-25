@use('App\Filament\Resources\CrifReports\CrifReportResource')

<x-filament-panels::page>
    @php
        $integration = $this->integration();
        $max = $this->maxByDay();
        $statTones = [
            'Successful' => 'success',
            'Failed' => 'danger',
            'In progress' => 'warning',
            'Needs reconciliation' => 'warning',
            'Duplicates prevented' => 'gray',
            'Reports reused' => 'gray',
            'Fresh requests' => 'info',
        ];
        $statItems = collect($this->stats())->map(fn (array $stat, string $label): array => [
            'label' => $label,
            'value' => number_format($stat['value']),
            'description' => $stat['description'],
            'tone' => $statTones[$label] ?? 'primary',
        ])->values()->all();
    @endphp

    @if (! $integration['enabled'] || ! $integration['configured'])
        <x-filament::callout color="warning" icon="heroicon-o-exclamation-triangle" heading="CRIF requests are currently not possible.">
            <x-slot name="description">
                @unless ($integration['enabled'])
                    <span class="ms-4 list-item list-disc">The integration is switched off in CRIF Settings.</span>
                @endunless
                @unless ($integration['configured'])
                    <span class="ms-4 list-item list-disc">The Surepass API token is not set in this server's environment (SUREPASS_BEARER_TOKEN).</span>
                @endunless
            </x-slot>
        </x-filament::callout>
    @endif

    <x-admin.stat-band :columns="4" :items="$statItems" />

    <x-filament::section heading="Requests per day">
        <x-slot name="description">Last 14 days. Reuse period {{ $integration['reuse_days'] }} days · consent valid {{ $integration['consent_days'] }} days.</x-slot>

        <div class="flex h-32 items-end gap-2">
            @foreach ($this->byDay() as $day)
                <div class="flex flex-1 flex-col items-center gap-1">
                    <span class="text-xs text-gray-500 dark:text-gray-400">{{ $day['total'] ?: '' }}</span>
                    <div
                        class="w-full rounded-t bg-primary-500/80"
                        style="height: {{ max(2, (int) round($day['total'] / $max * 100)) }}%"
                        title="{{ $day['total'] }} requests on {{ $day['label'] }}"
                    ></div>
                    <span class="text-[0.65rem] text-gray-400">{{ $day['label'] }}</span>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section heading="Requests by employee">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-100 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-white/5 dark:text-gray-400">
                        <tr>
                            <th class="py-2.5 pe-4">Employee</th>
                            <th class="py-2.5 pe-4 text-right">Requests</th>
                            <th class="py-2.5 pe-4 text-right">Successful</th>
                            <th class="py-2.5 pe-4 text-right">Failed</th>
                            <th class="py-2.5 text-right">Fresh</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-white/5 dark:text-gray-300">
                        @forelse ($this->byEmployee() as $row)
                            <tr>
                                <td class="py-2.5 pe-4 font-medium text-gray-950 dark:text-white">{{ $row['name'] }}</td>
                                <td class="py-2.5 pe-4 text-right tabular-nums">{{ $row['total'] }}</td>
                                <td class="py-2.5 pe-4 text-right tabular-nums">{{ $row['success'] }}</td>
                                <td class="py-2.5 pe-4 text-right tabular-nums">{{ $row['failed'] }}</td>
                                <td class="py-2.5 text-right tabular-nums">{{ $row['fresh'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-6 text-center text-gray-500 dark:text-gray-400">No CRIF requests yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section heading="Provider and API errors">
            <x-slot name="description">"Uncertain" errors may have been charged by Surepass and go to reconciliation.</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-white/5 dark:text-gray-300">
                        @forelse ($this->errors() as $error)
                            <tr>
                                <td class="py-2.5 pe-4 font-medium text-gray-950 dark:text-white">{{ $error['label'] }}</td>
                                <td class="py-2.5 pe-4">
                                    @if ($error['uncertain'])
                                        <x-filament::badge color="warning" size="sm">Uncertain</x-filament::badge>
                                    @endif
                                </td>
                                <td class="py-2.5 text-right font-semibold tabular-nums text-gray-950 dark:text-white">{{ $error['total'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="py-6 text-center text-gray-500 dark:text-gray-400">No errors recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    </div>

    <x-filament::section heading="Recent activity">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-100 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-white/5 dark:text-gray-400">
                    <tr>
                        <th class="py-2.5 pe-4">When</th>
                        <th class="py-2.5 pe-4">Who</th>
                        <th class="py-2.5 pe-4">What</th>
                        <th class="py-2.5">PAN</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-white/5 dark:text-gray-300">
                    @forelse ($this->recentActivity() as $log)
                        <tr>
                            <td class="whitespace-nowrap py-2.5 pe-4 font-medium text-gray-950 dark:text-white">{{ $log->created_at?->format('d M Y, H:i') }}</td>
                            <td class="py-2.5 pe-4">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="py-2.5 pe-4">{{ str_replace('_', ' ', $log->action) }}</td>
                            <td class="py-2.5">
                                @if ($log->auditable)
                                    <a class="text-primary-600 underline dark:text-primary-400" href="{{ CrifReportResource::getUrl('view', ['record' => $log->auditable]) }}">{{ $log->auditable->pan_masked }}</a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-gray-500 dark:text-gray-400">No activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-panels::page>
