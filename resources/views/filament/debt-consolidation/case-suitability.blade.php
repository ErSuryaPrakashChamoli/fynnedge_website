@php use App\Modules\DebtConsolidation\Services\DebtPresenter as P; @endphp
@if (! $snapshot)
    @include('filament.debt-consolidation._no-snapshot')
@else
    <div class="flex flex-col gap-6">
        <x-filament::callout color="info" icon="heroicon-o-information-circle">
            <x-slot name="description">Three separate answers: financial suitability (this score), repayment affordability (FOIR / DSCR) and lender eligibility (Lender matching tab). None of them is a loan approval.</x-slot>
        </x-filament::callout>
        @foreach ($snapshot->results['scenarios'] as $row)
            @php $suitability = $row['suitability']; $affordability = $row['affordability']; @endphp
            <x-filament::section :heading="$row['scenario']['label'].($snapshot->results['recommended_scenario_key'] === $row['scenario']['key'] ? ' — recommended' : '')">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($suitability['score'], 1) }} / 100</span>
                    <x-filament::badge :color="\App\Modules\DebtConsolidation\Enums\SuitabilityBand::from($suitability['band'])->getColor()">{{ \App\Modules\DebtConsolidation\Enums\SuitabilityBand::from($suitability['band'])->getLabel() }}</x-filament::badge>
                    @if ($suitability['requires_advisor_review']) <x-filament::badge color="warning">Advisor review required</x-filament::badge> @endif
                    <span class="text-sm text-gray-500">Affordability: {{ $affordability['metric'] === 'foir' ? 'FOIR '.P::percent($affordability['proposed_value']) : 'DSCR '.($affordability['proposed_value'] ?? '∞').'×' }} ({{ \App\Modules\DebtConsolidation\Enums\AffordabilityBand::tryFrom($affordability['proposed_band'])?->getLabel() }})</span>
                </div>
                <table class="mt-4 w-full text-sm">
                    <thead><tr class="text-left text-xs text-gray-500"><th class="py-1">Dimension</th><th class="text-right">Measure</th><th class="text-right">Score</th><th class="text-right">Weight</th><th class="text-right">Contribution</th></tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($suitability['dimensions'] as $dimension => $values)
                            <tr><td class="py-1">{{ str_replace('_', ' ', ucfirst($dimension)) }}</td><td class="text-right">{{ $values['measure'] ?? '—' }}</td><td class="text-right">{{ $values['score'] }}</td><td class="text-right">{{ $values['weight'] }}%</td><td class="text-right font-medium">{{ $values['weighted'] }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </x-filament::section>
        @endforeach
        <p class="text-xs text-gray-500">Scored with configuration {{ $snapshot->scoring_version }}. Measures: benefit % of existing repayment; proposed FOIR % or DSCR; rate cut in points; costs % of loan; extra months.</p>
    </div>
@endif
