@use('App\Filament\Resources\ExperianCreditRequests\ExperianCreditRequestResource')

<x-filament-panels::page>
    @php
        $integration = $this->integration();
        $statTones = [
            'Successful' => 'success',
            'Failed' => 'danger',
            'In progress' => 'warning',
            'Needs reconciliation' => 'warning',
            'Duplicates prevented' => 'gray',
            'Results reused' => 'gray',
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
        <x-filament::callout color="warning" icon="heroicon-o-exclamation-triangle" heading="Experian requests are currently not possible.">
            <x-slot name="description">
                @unless ($integration['enabled'])
                    <span class="ms-4 list-item list-disc">Experian requests are switched off in Experian Settings.</span>
                @endunless
                @unless ($integration['configured'])
                    <span class="ms-4 list-item list-disc">The Surepass API token is not set in this server's environment (SUREPASS_EXPERIAN_TOKEN, or the shared SUREPASS_BEARER_TOKEN).</span>
                @endunless
            </x-slot>
        </x-filament::callout>
    @endif

    <x-admin.stat-band :columns="5" :items="$statItems" />

    <p class="text-xs text-gray-500 dark:text-gray-400">
        Reuse periods: scores {{ $integration['score_days'] }} days · full reports and PDFs {{ $integration['report_days'] }} days · consent valid {{ $integration['consent_days'] }} days · public score page {{ $integration['public'] ? 'shows real scores' : 'shows demo scores' }}.
    </p>

    <div class="grid gap-6 lg:grid-cols-2">
        <x-filament::section heading="Requests by employee">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-100 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-white/5 dark:text-gray-400">
                        <tr>
                            <th class="py-2.5 pe-4">Employee</th>
                            <th class="py-2.5 pe-4 text-right">Scores</th>
                            <th class="py-2.5 pe-4 text-right">Reports</th>
                            <th class="py-2.5 pe-4 text-right">PDFs</th>
                            <th class="py-2.5 pe-4 text-right">Successful</th>
                            <th class="py-2.5 text-right">Failed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-white/5 dark:text-gray-300">
                        @forelse ($this->byEmployee() as $row)
                            <tr>
                                <td class="py-2.5 pe-4 font-medium text-gray-950 dark:text-white">{{ $row['name'] }}</td>
                                <td class="py-2.5 pe-4 text-right tabular-nums">{{ $row['scores'] }}</td>
                                <td class="py-2.5 pe-4 text-right tabular-nums">{{ $row['reports'] }}</td>
                                <td class="py-2.5 pe-4 text-right tabular-nums">{{ $row['pdfs'] }}</td>
                                <td class="py-2.5 pe-4 text-right tabular-nums">{{ $row['success'] }}</td>
                                <td class="py-2.5 text-right tabular-nums">{{ $row['failed'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-6 text-center text-gray-500 dark:text-gray-400">No Experian requests yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>

        <x-filament::section heading="Provider errors">
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

    <x-filament::section heading="Latest requests">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-100 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-white/5 dark:text-gray-400">
                    <tr>
                        <th class="py-2.5 pe-4">Requested</th>
                        <th class="py-2.5 pe-4">Customer</th>
                        <th class="py-2.5 pe-4">Type</th>
                        <th class="py-2.5 pe-4">Status</th>
                        <th class="py-2.5 pe-4 text-right">Score</th>
                        <th class="py-2.5">PAN</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-white/5 dark:text-gray-300">
                    @forelse ($this->latestRequests() as $request)
                        <tr>
                            <td class="whitespace-nowrap py-2.5 pe-4 font-medium text-gray-950 dark:text-white">{{ $request->requested_at?->format('d M Y, H:i') }}</td>
                            <td class="py-2.5 pe-4">{{ $request->customer?->full_name ?? '—' }}</td>
                            <td class="py-2.5 pe-4">{{ $request->product->getLabel() }}</td>
                            <td class="py-2.5 pe-4">{{ $request->status->getLabel() }}</td>
                            <td class="py-2.5 pe-4 text-right tabular-nums">{{ $request->score ?? '—' }}</td>
                            <td class="py-2.5"><a class="text-primary-600 underline dark:text-primary-400" href="{{ ExperianCreditRequestResource::getUrl('view', ['record' => $request]) }}">{{ $request->pan_masked }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-6 text-center text-gray-500 dark:text-gray-400">No Experian requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <x-filament::section heading="Recent provider errors">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-100 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-white/5 dark:text-gray-400">
                    <tr>
                        <th class="py-2.5 pe-4">When</th>
                        <th class="py-2.5 pe-4">Type</th>
                        <th class="py-2.5 pe-4">Error</th>
                        <th class="py-2.5 pe-4">HTTP</th>
                        <th class="py-2.5">PAN</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-white/5 dark:text-gray-300">
                    @forelse ($this->recentErrors() as $request)
                        <tr>
                            <td class="whitespace-nowrap py-2.5 pe-4 font-medium text-gray-950 dark:text-white">{{ $request->updated_at?->format('d M Y, H:i') }}</td>
                            <td class="py-2.5 pe-4">{{ $request->product->getLabel() }}</td>
                            <td class="py-2.5 pe-4">{{ $request->error_code?->getLabel() }}</td>
                            <td class="py-2.5 pe-4 tabular-nums text-gray-500 dark:text-gray-400">{{ $request->http_status ?? '—' }}</td>
                            <td class="py-2.5"><a class="text-primary-600 underline dark:text-primary-400" href="{{ ExperianCreditRequestResource::getUrl('view', ['record' => $request]) }}">{{ $request->pan_masked }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-6 text-center text-gray-500 dark:text-gray-400">No provider errors.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

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
                                    <a class="text-primary-600 underline dark:text-primary-400" href="{{ ExperianCreditRequestResource::getUrl('view', ['record' => $log->auditable]) }}">{{ $log->auditable->pan_masked }}</a>
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
