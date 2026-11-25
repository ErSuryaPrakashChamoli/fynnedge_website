<x-filament-panels::page>
    @php
        $report = $this->report;
        $maxTrend = max(1, collect($report['trend'])->max('total'));
        $sourceColors = [
            'enquiry' => 'bg-primary-500',
            'credit_score_check' => 'bg-info-500',
            'online_application' => 'bg-warning-500',
            'loan_application' => 'bg-success-500',
        ];
        $kpiItems = collect($report['kpis'])->map(fn (array $kpi): array => [
            'label' => $kpi['label'],
            'value' => $kpi['value'],
            'description' => match (true) {
                $kpi['change'] !== null => ($kpi['change'] >= 0 ? '▲' : '▼').' '.abs($kpi['change']).'% vs '.$kpi['previous'].' in the previous period',
                $kpi['previous'] !== null => $kpi['previous'].' in the previous period',
                default => $kpi['hint'],
            },
            'tone' => match (true) {
                $kpi['change'] === null => 'primary',
                $kpi['change'] >= 0 => 'success',
                default => 'danger',
            },
            'hint' => $kpi['hint'],
        ])->all();
    @endphp

    <x-filament::section>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
            <label class="block">
                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Period</span>
                <x-filament::input.wrapper class="mt-1">
                    <x-filament::input.select wire:model.live="range">
                        @foreach ($this->rangeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            @if ($range === 'custom')
                <label class="block">
                    <span class="text-xs font-medium text-gray-600 dark:text-gray-400">From</span>
                    <x-filament::input.wrapper class="mt-1"><x-filament::input type="date" wire:model.live="from" /></x-filament::input.wrapper>
                </label>
                <label class="block">
                    <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Until</span>
                    <x-filament::input.wrapper class="mt-1"><x-filament::input type="date" wire:model.live="until" /></x-filament::input.wrapper>
                </label>
            @endif
            <label class="block">
                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Loan product</span>
                <x-filament::input.wrapper class="mt-1">
                    <x-filament::input.select wire:model.live="loanProductId">
                        <option value="">All products</option>
                        @foreach ($this->productOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="block">
                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Source</span>
                <x-filament::input.wrapper class="mt-1">
                    <x-filament::input.select wire:model.live="source">
                        <option value="">All sources</option>
                        @foreach ($this->sourceOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
            <label class="block">
                <span class="text-xs font-medium text-gray-600 dark:text-gray-400">Campaign (UTM)</span>
                <x-filament::input.wrapper class="mt-1">
                    <x-filament::input.select wire:model.live="campaign">
                        <option value="">All traffic</option>
                        @foreach ($this->campaignOptions() as $value)
                            <option value="{{ $value }}">{{ $value }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>
        </div>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
            {{ $this->periodLabel() }} (India time), compared with the same length of time just before it.
            @if ($report['tracking_since']) Page views counted since {{ $report['tracking_since'] }}. @endif
        </p>
    </x-filament::section>

    <x-admin.stat-band :columns="3" :items="$kpiItems" />

    @if ($report['insights'] !== [])
        <x-filament::section>
            <x-slot name="heading">What the numbers say</x-slot>
            <ul class="space-y-2">
                @foreach ($report['insights'] as $insight)
                    <li class="flex items-start gap-2 text-sm text-gray-700 dark:text-gray-300">
                        <span @class([
                            'mt-1.5 h-2 w-2 shrink-0 rounded-full',
                            'bg-success-500' => $insight['tone'] === 'success',
                            'bg-warning-500' => $insight['tone'] === 'warning',
                            'bg-danger-500' => $insight['tone'] === 'danger',
                            'bg-info-500' => $insight['tone'] === 'info',
                        ])></span>
                        {{ $insight['text'] }}
                    </li>
                @endforeach
            </ul>
        </x-filament::section>
    @endif

    <x-filament::section>
        <x-slot name="heading">Leads over time</x-slot>
        <x-slot name="afterHeader">
            <x-filament::input.wrapper>
                <x-filament::input.select wire:model.live="granularity">
                    <option value="day">Daily</option>
                    <option value="week">Weekly</option>
                    <option value="month">Monthly</option>
                </x-filament::input.select>
            </x-filament::input.wrapper>
        </x-slot>
        <div class="overflow-x-auto">
            <div class="flex h-48 min-w-full items-end gap-1" role="img" aria-label="Leads per {{ $granularity }}">
                @foreach ($report['trend'] as $bucket)
                    <div class="group flex min-w-[10px] flex-1 flex-col-reverse" title="{{ $bucket['label'] }}: {{ $bucket['total'] }} lead(s)">
                        @foreach ($bucket['by_source'] as $sourceKey => $count)
                            <div class="{{ $sourceColors[$sourceKey] ?? 'bg-gray-400' }} first:rounded-b-sm last:rounded-t-sm" style="height: {{ $count / $maxTrend * 11 }}rem"></div>
                        @endforeach
                        @if ($bucket['total'] === 0)
                            <div class="h-px bg-gray-200 dark:bg-white/10"></div>
                        @endif
                    </div>
                @endforeach
            </div>
            <div class="mt-2 flex justify-between text-[11px] text-gray-500">
                <span>{{ $report['trend'][0]['label'] ?? '' }}</span>
                <span>{{ last($report['trend'])['label'] ?? '' }}</span>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap gap-4 text-xs text-gray-600 dark:text-gray-400">
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-primary-500"></span>Enquiries</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-info-500"></span>Credit score checks</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-warning-500"></span>Online applications</span>
            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-success-500"></span>Loan applications</span>
        </div>
    </x-filament::section>

    <x-filament::section>
        <x-slot name="heading">Page performance</x-slot>
        <x-slot name="description">Which pages bring leads. Conversion = leads ÷ page views, shown only where views were recorded.</x-slot>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-100 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-white/5 dark:text-gray-400">
                    <tr>
                        <th class="py-2.5 pe-4">Page</th>
                        <th class="py-2.5 pe-4 text-right">Views</th>
                        <th class="py-2.5 pe-4 text-right">Leads</th>
                        <th class="py-2.5 pe-4 text-right">Conversion</th>
                        <th class="py-2.5 pe-4 text-right">OTP verified</th>
                        <th class="py-2.5 pe-4 text-right">LMS delivered</th>
                        <th class="py-2.5 text-right">LMS failed</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-white/5 dark:text-gray-300">
                    @forelse (array_slice($report['pages'], 0, 50) as $page)
                        <tr>
                            <td class="max-w-xs truncate py-2.5 pe-4 font-medium text-gray-950 dark:text-white" title="{{ $page['page'] }}">{{ $page['page'] }}</td>
                            <td class="py-2.5 pe-4 text-right tabular-nums">{{ $page['views'] !== null ? number_format($page['views']) : '—' }}</td>
                            <td class="py-2.5 pe-4 text-right font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($page['leads']) }}</td>
                            <td class="py-2.5 pe-4 text-right tabular-nums">{{ $page['conversion'] ?? '—' }}</td>
                            <td class="py-2.5 pe-4 text-right tabular-nums">{{ $page['verified'] }} @if ($page['verified_rate'])<span class="text-xs text-gray-500 dark:text-gray-400">({{ $page['verified_rate'] }})</span>@endif</td>
                            <td class="py-2.5 pe-4 text-right tabular-nums">{{ $page['delivered'] }}</td>
                            <td @class(['py-2.5 text-right tabular-nums', 'font-semibold text-danger-600 dark:text-danger-400' => $page['failed'] > 0])>{{ $page['failed'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-gray-500 dark:text-gray-400">No leads or page views in this period.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>

    <div class="grid gap-6 xl:grid-cols-2">
        @if ($report['otp'])
            <x-filament::section>
                <x-slot name="heading">OTP verification</x-slot>
                <x-slot name="description">Codes sent vs verified (portal sign-ins excluded).</x-slot>
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div><p class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ number_format($report['otp']['requested']) }}</p><p class="text-xs text-gray-500 dark:text-gray-400">Codes sent</p></div>
                    <div><p class="text-2xl font-semibold tabular-nums text-success-600 dark:text-success-400">{{ number_format($report['otp']['verified']) }}</p><p class="text-xs text-gray-500 dark:text-gray-400">Verified</p></div>
                    <div><p class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $report['otp']['rate'] ?? '—' }}</p><p class="text-xs text-gray-500 dark:text-gray-400">Completion</p></div>
                </div>
                <ul class="mt-4 space-y-2 text-sm">
                    @foreach ($report['otp']['by_purpose'] as $purpose)
                        <li class="flex justify-between border-b border-gray-100 pb-1 dark:border-white/5"><span>{{ $purpose['label'] }}</span><span class="tabular-nums text-gray-600 dark:text-gray-400">{{ $purpose['verified'] }}/{{ $purpose['requested'] }} · {{ $purpose['rate'] ?? '—' }}</span></li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif

        <x-filament::section>
            <x-slot name="heading">By form / placement</x-slot>
            <ul class="space-y-2 text-sm">
                @forelse ($report['forms'] as $row)
                    <li>
                        <div class="flex justify-between"><span>{{ $row['label'] }}</span><span class="tabular-nums font-semibold">{{ $row['leads'] }} <span class="font-normal text-gray-500">({{ $row['share'] }})</span></span></div>
                        <div class="mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-white/10"><div class="h-1.5 rounded-full bg-primary-500" style="width: {{ (float) $row['share'] }}%"></div></div>
                    </li>
                @empty
                    <li class="text-gray-500 dark:text-gray-400">No leads in this period.</li>
                @endforelse
            </ul>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">By loan product</x-slot>
            <ul class="space-y-2 text-sm">
                @forelse ($report['products'] as $row)
                    <li>
                        <div class="flex justify-between"><span>{{ $row['label'] }}</span><span class="tabular-nums font-semibold">{{ $row['leads'] }} <span class="font-normal text-gray-500">({{ $row['share'] }})</span></span></div>
                        <div class="mt-1 h-1.5 rounded-full bg-gray-100 dark:bg-white/10"><div class="h-1.5 rounded-full bg-success-500" style="width: {{ (float) $row['share'] }}%"></div></div>
                    </li>
                @empty
                    <li class="text-gray-500 dark:text-gray-400">No leads in this period.</li>
                @endforelse
            </ul>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">By campaign (UTM)</x-slot>
            <x-slot name="description">Only applications store campaign tags; enquiry forms do not record them.</x-slot>
            <ul class="space-y-2 text-sm">
                @forelse ($report['campaigns'] as $row)
                    <li class="flex justify-between border-b border-gray-100 pb-1 dark:border-white/5">
                        <span>{{ $row['campaign'] }} @if ($row['source'])<span class="text-xs text-gray-500">· {{ $row['source'] }}</span>@endif</span>
                        <span class="tabular-nums">{{ $row['leads'] }} lead(s), {{ $row['verified'] }} verified</span>
                    </li>
                @empty
                    <li class="text-gray-500 dark:text-gray-400">No campaign-tagged leads in this period.</li>
                @endforelse
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>
