<x-filament-panels::page>
    @php
        $connection = $this->connection();
        $stats = $this->stats();
    @endphp

    <div class="grid gap-6 lg:grid-cols-3">
        <x-filament::section class="lg:col-span-1">
            <x-slot name="heading">Connection</x-slot>
            @if ($connection['configured'])
                <div class="flex items-center gap-2">
                    <x-filament::badge color="success" icon="heroicon-m-check-circle">Connected</x-filament::badge>
                    <span class="text-sm text-gray-600 dark:text-gray-400">to {{ $connection['host'] ?? 'the configured address' }}</span>
                </div>
                <ul class="mt-4 space-y-1.5 text-sm text-gray-600 dark:text-gray-400">
                    <li>Signed requests: <strong class="text-gray-900 dark:text-white">{{ $connection['signed'] ? 'yes (HMAC)' : 'no' }}</strong></li>
                    <li>Failure alerts by email: <strong class="text-gray-900 dark:text-white">{{ $connection['alerts'] ? 'on' : 'off' }}</strong></li>
                    <li>Sends: {{ implode(', ', $connection['sources']) ?: 'nothing (no sources enabled)' }}</li>
                    <li>Last delivered: <strong class="text-gray-900 dark:text-white">{{ $stats['last'] ?? 'never' }}</strong></li>
                </ul>
            @else
                <x-filament::badge color="warning" icon="heroicon-m-exclamation-triangle">Not connected</x-filament::badge>
                <p class="mt-3 text-sm text-gray-600 dark:text-gray-400">
                    Leads are recorded on the website as usual, but nothing is sent to FynnOn LMS until the server has
                    <code class="rounded bg-gray-100 px-1 text-xs dark:bg-white/10">FYNNON_LMS_URL</code> and
                    <code class="rounded bg-gray-100 px-1 text-xs dark:bg-white/10">FYNNON_LMS_TOKEN</code>.
                    Ask your developer to set them; leads that arrive in the meantime can then be sent with "Send undelivered leads".
                </p>
            @endif
        </x-filament::section>

        <x-filament::section class="lg:col-span-2">
            <x-slot name="heading">Delivery status by source</x-slot>
            <x-slot name="description">A lead counts as delivered only when FynnOn LMS confirms it.</x-slot>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-100 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-white/5 dark:text-gray-400">
                        <tr>
                            <th class="py-2.5 pe-4">Source</th>
                            <th class="py-2.5 pe-4 text-right">Leads</th>
                            <th class="py-2.5 pe-4 text-right">Delivered</th>
                            <th class="py-2.5 pe-4 text-right">Not sent yet</th>
                            <th class="py-2.5 pe-4 text-right">Retrying</th>
                            <th class="py-2.5 text-right">Failed</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-white/5 dark:text-gray-300">
                        @foreach ($stats['rows'] as $row)
                            <tr>
                                <td class="py-2.5 pe-4 font-medium text-gray-950 dark:text-white">
                                    {{ $row['label'] }}
                                    @unless ($row['enabled'])
                                        <span class="ms-1 text-xs font-normal text-gray-400">(not sent)</span>
                                    @endunless
                                </td>
                                <td class="py-2.5 pe-4 text-right tabular-nums">{{ number_format($row['total']) }}</td>
                                <td class="py-2.5 pe-4 text-right tabular-nums text-success-600 dark:text-success-400">{{ number_format($row['delivered']) }}</td>
                                <td class="py-2.5 pe-4 text-right tabular-nums">{{ number_format($row['pending']) }}</td>
                                <td class="py-2.5 pe-4 text-right tabular-nums text-warning-600 dark:text-warning-400">{{ number_format($row['failed']) }}</td>
                                <td class="py-2.5 text-right tabular-nums text-danger-600 dark:text-danger-400">{{ number_format($row['abandoned']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="border-t border-gray-200 font-semibold text-gray-950 dark:border-white/10 dark:text-white">
                        <tr>
                            <td class="py-2.5 pe-4">Total</td>
                            <td class="py-2.5 pe-4 text-right tabular-nums">{{ number_format($stats['totals']['total']) }}</td>
                            <td class="py-2.5 pe-4 text-right tabular-nums text-success-600 dark:text-success-400">{{ number_format($stats['totals']['delivered']) }}</td>
                            <td class="py-2.5 pe-4 text-right tabular-nums">{{ number_format($stats['totals']['pending']) }}</td>
                            <td class="py-2.5 pe-4 text-right tabular-nums text-warning-600 dark:text-warning-400">{{ number_format($stats['totals']['failed']) }}</td>
                            <td class="py-2.5 text-right tabular-nums text-danger-600 dark:text-danger-400">{{ number_format($stats['totals']['abandoned']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </x-filament::section>
    </div>

    {{ $this->table }}

    <x-filament::section heading="What the LMS receives (for the FynnOn team)" collapsible collapsed>
        <div class="space-y-2 text-sm text-gray-600 dark:text-gray-400">
            <p><strong>POST</strong> to the configured URL, JSON body, schema version {{ $this->payloadVersion() }}. Headers: <code>Authorization: Bearer …</code>, <code>Idempotency-Key</code>, <code>X-FynnEdge-Event</code>, <code>X-FynnEdge-Timestamp</code> and, when a signing secret is set, <code>X-FynnEdge-Signature: sha256=HMAC(timestamp + "." + body)</code>.</p>
            <ul class="list-disc space-y-1 ps-5">
                @foreach ($this->payloadFields() as $field)
                    <li>{{ $field }}</li>
                @endforeach
            </ul>
            <p>Reply with 2xx and JSON containing <code>reference</code>, <code>id</code>, <code>lead_id</code> or <code>"success": true</code>; 409 means "already have it". Timeouts, 429 and 5xx are retried (1 min, 5 min, 15 min, 1 h); other 4xx are not. Example reference: <code>{{ $this->exampleReference() }}</code>.</p>
        </div>
    </x-filament::section>
</x-filament-panels::page>
