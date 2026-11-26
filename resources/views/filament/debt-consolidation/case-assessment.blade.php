@php
    use App\Modules\DebtConsolidation\Services\DebtPresenter as P;
@endphp
@if (! $snapshot)
    @include('filament.debt-consolidation._no-snapshot')
@else
    @php
        $results = $snapshot->results;
        $portfolio = $results['portfolio'];
        $now = $results['current_affordability'];
        $names = P::liabilityNames($snapshot->inputs['liabilities'] ?? []);
    @endphp
    <div class="flex flex-col gap-6">
        <x-admin.stat-band :columns="4" :items="[
            ['label' => 'Total outstanding', 'value' => P::inr($portfolio['total_outstanding']), 'description' => $portfolio['liability_count'].' liabilities'],
            ['label' => 'Monthly obligations', 'value' => P::inr($portfolio['total_monthly_obligation']), 'description' => 'EMIs, card payments and interest'],
            ['label' => 'Weighted average rate', 'value' => P::percent($portfolio['weighted_average_rate']), 'description' => 'Highest '.P::percent($portfolio['highest_rate'])],
            ['label' => $now['metric'] === 'foir' ? 'FOIR' : 'DSCR', 'value' => $now['current_value'] === null ? '—' : ($now['metric'] === 'foir' ? P::percent($now['current_value']) : $now['current_value'].'×'), 'description' => \App\Modules\DebtConsolidation\Enums\AffordabilityBand::tryFrom($now['current_band'])?->getLabel(), 'tone' => in_array($now['current_band'], ['stretched', 'unaffordable'], true) ? 'danger' : 'success'],
            ['label' => 'Secured share', 'value' => P::percent($portfolio['secured_share'] ?? 0), 'description' => P::inr($portfolio['secured_outstanding']).' secured'],
            ['label' => 'Remaining repayment', 'value' => P::inr($portfolio['remaining_repayment']), 'description' => P::inr($portfolio['remaining_interest']).' of it interest'],
            ['label' => 'Overdue', 'value' => P::inr($portfolio['total_overdue']), 'description' => $portfolio['liabilities_in_arrears'].' in arrears', 'tone' => $portfolio['liabilities_in_arrears'] > 0 ? 'warning' : 'success'],
            ['label' => 'Largest lender share', 'value' => P::percent($portfolio['largest_lender_share']), 'description' => 'Concentration index '.($portfolio['concentration_index'] ?? '—')],
        ]" />

        <x-filament::section heading="Liabilities in this calculation">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs text-gray-500"><th class="py-2">Liability</th><th class="text-right">Outstanding</th><th class="text-right">Rate used</th><th class="text-right">Monthly</th><th class="text-right">Months</th><th class="text-right">Remaining cost</th><th>Consolidatable now</th><th>Routes</th></tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($snapshot->inputs['liabilities'] as $liability)
                        @php $row = $results['liabilities'][$liability['key']] ?? []; @endphp
                        <tr>
                            <td class="py-2">{{ $names[$liability['key']] ?? $liability['key'] }}<br><span class="text-xs text-gray-500">{{ ucfirst($liability['mode']) }}{{ $liability['account_status'] !== 'regular' ? ' · '.$liability['account_status'] : '' }}</span></td>
                            <td class="text-right">{{ P::inr($liability['outstanding_principal'], true) }}</td>
                            <td class="text-right">{{ P::percent($row['projection']['effective_annual_rate'] ?? null) }}@if (($row['projection']['implied_annual_rate'] ?? null) !== null)<br><span class="text-xs text-gray-500">implied {{ P::percent($row['projection']['implied_annual_rate']) }}</span>@endif</td>
                            <td class="text-right">{{ P::inr($row['projection']['monthly_obligation'] ?? null, true) }}</td>
                            <td class="text-right">{{ $row['projection']['remaining_months'] ?? '—' }}{{ ($row['projection']['repayment_assumed'] ?? false) ? ' (assumed)' : '' }}</td>
                            <td class="text-right">{{ P::inr($row['projection']['remaining_repayment'] ?? null, true) }}</td>
                            <td>{{ ($row['classification']['consolidatable_now'] ?? true) ? 'Yes' : 'No' }}</td>
                            <td class="text-xs">{{ collect($row['classification']['eligible_routes'] ?? [])->map(fn ($route) => \App\Modules\DebtConsolidation\Enums\FinancingRoute::tryFrom($route)?->getLabel())->filter()->implode(', ') ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>

        @if ($results['flags'] !== [])
            <x-filament::section heading="Flags">
                <ul class="flex flex-col gap-2 text-sm">
                    @foreach ($results['flags'] as $flag)
                        <li class="flex gap-2"><x-filament::badge :color="\App\Modules\DebtConsolidation\Enums\RiskFlag::tryFrom($flag['flag'])?->getColor() ?? 'gray'">{{ $flag['severity'] }}</x-filament::badge> {{ P::flagSentence($flag, $names) }}</li>
                    @endforeach
                </ul>
            </x-filament::section>
        @endif
        <p class="text-xs text-gray-500">Snapshot {{ $snapshot->public_id }} · formula {{ $snapshot->formula_version }} · scoring {{ $snapshot->scoring_version }} · {{ $snapshot->created_at->timezone('Asia/Kolkata')->format('j M Y, g:i a') }} · by {{ $snapshot->created_by_type->getLabel() }} · inputs {{ $snapshot->inputsIntact() ? 'intact' : 'CHANGED — do not rely on this snapshot' }}</p>
    </div>
@endif
