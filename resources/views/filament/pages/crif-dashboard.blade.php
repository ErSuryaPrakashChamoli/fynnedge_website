@use('App\Filament\Resources\CrifReports\CrifReportResource')

<x-filament-panels::page>
    @php
        $integration = $this->integration();
        $max = $this->maxByDay();
    @endphp

    @if (! $integration['enabled'] || ! $integration['configured'])
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
            <p class="font-semibold">CRIF requests are currently not possible.</p>
            <ul class="mt-1 list-disc ps-5">
                @unless ($integration['enabled'])
                    <li>The integration is switched off in CRIF Settings.</li>
                @endunless
                @unless ($integration['configured'])
                    <li>The Surepass API token is not set in this server's environment (SUREPASS_BEARER_TOKEN).</li>
                @endunless
            </ul>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($this->stats() as $label => $stat)
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
                <p class="mt-1 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($stat['value']) }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $stat['description'] }}</p>
            </div>
        @endforeach
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <p class="text-sm font-medium text-gray-950 dark:text-white">Requests per day</p>
        <p class="text-xs text-gray-500 dark:text-gray-400">Last 14 days. Reuse period {{ $integration['reuse_days'] }} days · consent valid {{ $integration['consent_days'] }} days.</p>

        <div class="mt-4 flex h-32 items-end gap-2">
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
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-950 dark:text-white">Requests by employee</p>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="py-2">Employee</th>
                            <th class="py-2 text-right">Requests</th>
                            <th class="py-2 text-right">Successful</th>
                            <th class="py-2 text-right">Failed</th>
                            <th class="py-2 text-right">Fresh</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->byEmployee() as $row)
                            <tr>
                                <td class="py-2 text-gray-950 dark:text-white">{{ $row['name'] }}</td>
                                <td class="py-2 text-right">{{ $row['total'] }}</td>
                                <td class="py-2 text-right">{{ $row['success'] }}</td>
                                <td class="py-2 text-right">{{ $row['failed'] }}</td>
                                <td class="py-2 text-right">{{ $row['fresh'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="py-3 text-gray-500 dark:text-gray-400">No CRIF requests yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-950 dark:text-white">Provider and API errors</p>
            <p class="text-xs text-gray-500 dark:text-gray-400">"Uncertain" errors may have been charged by Surepass and go to reconciliation.</p>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->errors() as $error)
                            <tr>
                                <td class="py-2 text-gray-950 dark:text-white">{{ $error['label'] }}</td>
                                <td class="py-2 text-xs text-gray-500 dark:text-gray-400">{{ $error['uncertain'] ? 'Uncertain' : '' }}</td>
                                <td class="py-2 text-right font-medium text-gray-950 dark:text-white">{{ $error['total'] }}</td>
                            </tr>
                        @empty
                            <tr><td class="py-3 text-gray-500 dark:text-gray-400">No errors recorded.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <p class="text-sm font-medium text-gray-950 dark:text-white">Recent activity</p>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr>
                        <th class="py-2">When</th>
                        <th class="py-2">Who</th>
                        <th class="py-2">What</th>
                        <th class="py-2">PAN</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($this->recentActivity() as $log)
                        <tr>
                            <td class="py-2 text-gray-500 dark:text-gray-400">{{ $log->created_at?->format('d M Y, H:i') }}</td>
                            <td class="py-2 text-gray-950 dark:text-white">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="py-2 text-gray-950 dark:text-white">{{ str_replace('_', ' ', $log->action) }}</td>
                            <td class="py-2">
                                @if ($log->auditable)
                                    <a class="text-primary-600 underline dark:text-primary-400" href="{{ CrifReportResource::getUrl('view', ['record' => $log->auditable]) }}">{{ $log->auditable->pan_masked }}</a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-3 text-gray-500 dark:text-gray-400">No activity yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-filament-panels::page>
