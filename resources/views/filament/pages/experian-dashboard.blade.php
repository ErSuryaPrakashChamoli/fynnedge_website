@use('App\Filament\Resources\ExperianCreditRequests\ExperianCreditRequestResource')

<x-filament-panels::page>
    @php
        $integration = $this->integration();
    @endphp

    @if (! $integration['enabled'] || ! $integration['configured'])
        <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900 dark:border-amber-800 dark:bg-amber-950 dark:text-amber-100">
            <p class="font-semibold">Experian requests are currently not possible.</p>
            <ul class="mt-1 list-disc ps-5">
                @unless ($integration['enabled'])
                    <li>Experian requests are switched off in Experian Settings.</li>
                @endunless
                @unless ($integration['configured'])
                    <li>The Surepass API token is not set in this server's environment (SUREPASS_EXPERIAN_TOKEN, or the shared SUREPASS_BEARER_TOKEN).</li>
                @endunless
            </ul>
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        @foreach ($this->stats() as $label => $stat)
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
                <p class="text-sm font-medium text-gray-500 dark:text-gray-400">{{ $label }}</p>
                <p class="mt-1 text-3xl font-semibold text-gray-950 dark:text-white">{{ number_format($stat['value']) }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $stat['description'] }}</p>
            </div>
        @endforeach
    </div>

    <p class="text-xs text-gray-500 dark:text-gray-400">
        Reuse periods: scores {{ $integration['score_days'] }} days · full reports and PDFs {{ $integration['report_days'] }} days · consent valid {{ $integration['consent_days'] }} days · public score page {{ $integration['public'] ? 'shows real scores' : 'shows demo scores' }}.
    </p>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-950 dark:text-white">Requests by employee</p>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                        <tr>
                            <th class="py-2">Employee</th>
                            <th class="py-2 text-right">Scores</th>
                            <th class="py-2 text-right">Reports</th>
                            <th class="py-2 text-right">PDFs</th>
                            <th class="py-2 text-right">Successful</th>
                            <th class="py-2 text-right">Failed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @forelse ($this->byEmployee() as $row)
                            <tr>
                                <td class="py-2 text-gray-950 dark:text-white">{{ $row['name'] }}</td>
                                <td class="py-2 text-right">{{ $row['scores'] }}</td>
                                <td class="py-2 text-right">{{ $row['reports'] }}</td>
                                <td class="py-2 text-right">{{ $row['pdfs'] }}</td>
                                <td class="py-2 text-right">{{ $row['success'] }}</td>
                                <td class="py-2 text-right">{{ $row['failed'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="py-3 text-gray-500 dark:text-gray-400">No Experian requests yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
            <p class="text-sm font-medium text-gray-950 dark:text-white">Provider errors</p>
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
        <p class="text-sm font-medium text-gray-950 dark:text-white">Latest requests</p>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr><th class="py-2">Requested</th><th class="py-2">Customer</th><th class="py-2">Type</th><th class="py-2">Status</th><th class="py-2 text-right">Score</th><th class="py-2">PAN</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($this->latestRequests() as $request)
                        <tr>
                            <td class="py-2 text-gray-500 dark:text-gray-400">{{ $request->requested_at?->format('d M Y, H:i') }}</td>
                            <td class="py-2 text-gray-950 dark:text-white">{{ $request->customer?->full_name ?? '—' }}</td>
                            <td class="py-2 text-gray-950 dark:text-white">{{ $request->product->getLabel() }}</td>
                            <td class="py-2 text-gray-950 dark:text-white">{{ $request->status->getLabel() }}</td>
                            <td class="py-2 text-right text-gray-950 dark:text-white">{{ $request->score ?? '—' }}</td>
                            <td class="py-2"><a class="text-primary-600 underline dark:text-primary-400" href="{{ ExperianCreditRequestResource::getUrl('view', ['record' => $request]) }}">{{ $request->pan_masked }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="py-3 text-gray-500 dark:text-gray-400">No Experian requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <p class="text-sm font-medium text-gray-950 dark:text-white">Recent provider errors</p>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr><th class="py-2">When</th><th class="py-2">Type</th><th class="py-2">Error</th><th class="py-2">HTTP</th><th class="py-2">PAN</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($this->recentErrors() as $request)
                        <tr>
                            <td class="py-2 text-gray-500 dark:text-gray-400">{{ $request->updated_at?->format('d M Y, H:i') }}</td>
                            <td class="py-2 text-gray-950 dark:text-white">{{ $request->product->getLabel() }}</td>
                            <td class="py-2 text-gray-950 dark:text-white">{{ $request->error_code?->getLabel() }}</td>
                            <td class="py-2 text-gray-500 dark:text-gray-400">{{ $request->http_status ?? '—' }}</td>
                            <td class="py-2"><a class="text-primary-600 underline dark:text-primary-400" href="{{ ExperianCreditRequestResource::getUrl('view', ['record' => $request]) }}">{{ $request->pan_masked }}</a></td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="py-3 text-gray-500 dark:text-gray-400">No provider errors.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <p class="text-sm font-medium text-gray-950 dark:text-white">Recent activity</p>
        <div class="mt-3 overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase text-gray-500 dark:text-gray-400">
                    <tr><th class="py-2">When</th><th class="py-2">Who</th><th class="py-2">What</th><th class="py-2">PAN</th></tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($this->recentActivity() as $log)
                        <tr>
                            <td class="py-2 text-gray-500 dark:text-gray-400">{{ $log->created_at?->format('d M Y, H:i') }}</td>
                            <td class="py-2 text-gray-950 dark:text-white">{{ $log->user?->name ?? 'System' }}</td>
                            <td class="py-2 text-gray-950 dark:text-white">{{ str_replace('_', ' ', $log->action) }}</td>
                            <td class="py-2">
                                @if ($log->auditable)
                                    <a class="text-primary-600 underline dark:text-primary-400" href="{{ ExperianCreditRequestResource::getUrl('view', ['record' => $log->auditable]) }}">{{ $log->auditable->pan_masked }}</a>
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
