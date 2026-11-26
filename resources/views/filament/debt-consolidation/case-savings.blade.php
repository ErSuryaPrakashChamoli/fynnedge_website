@php use App\Modules\DebtConsolidation\Services\DebtPresenter as P; @endphp
@if (! $snapshot)
    @include('filament.debt-consolidation._no-snapshot')
@else
    @php
        $scenarios = $snapshot->results['scenarios'];
        $first = $scenarios[0]['result'] ?? null;
        $names = P::liabilityNames($snapshot->inputs['liabilities'] ?? []);
        $line = fn (string $label, $keep, callable $value, bool $strong = false) => compact('label', 'keep', 'value', 'strong');
        $lines = [
            $line('Rate', P::percent($first['weighted_existing_rate'] ?? null), fn ($r) => P::percent($r['annual_interest_rate'])),
            $line('Monthly outgo', P::inr($first['existing_monthly_outflow'] ?? null, true), fn ($r) => P::inr($r['proposed_monthly_outflow'], true)),
            $line('Principal closed', '—', fn ($r) => P::inr($r['principal_closed'], true)),
            $line('Dues cleared', '—', fn ($r) => P::inr($r['dues_cleared'], true)),
            $line('Foreclosure charges + GST', '—', fn ($r) => P::inr($r['closure_costs'], true).($r['closure_costs_financed'] ? '' : ' (paid upfront)')),
            $line('Loan amount', '—', fn ($r) => P::inr($r['loan_amount'], true)),
            $line('Processing fee + GST', '—', fn ($r) => P::inr($r['processing_fee'] + $r['processing_fee_tax'], true)),
            $line('Net disbursal', '—', fn ($r) => P::inr($r['net_disbursal'], true)),
            $line('Upfront cash', '—', fn ($r) => P::inr($r['upfront_cash'], true)),
            $line('New EMI', '—', fn ($r) => P::inr($r['new_emi'], true)),
            $line('Total repayment', P::inr($first['existing_total_outflow'] ?? null, true), fn ($r) => P::inr($r['proposed_total_outflow'], true)),
            $line('Interest', P::inr($first['existing_interest'] ?? null, true), fn ($r) => P::inr($r['proposed_interest'], true)),
            $line('Months to debt-free', $first['existing_months'] ?? '—', fn ($r) => $r['proposed_months']),
            $line('Net financial benefit', '—', fn ($r) => P::inr($r['net_financial_benefit'], true).' ('.P::percent($r['benefit_percent']).')', true),
            $line('Cost break-even', '—', fn ($r) => $r['cost_break_even_month'] === null ? 'Not recovered' : 'Month '.$r['cost_break_even_month'].($r['cost_recovery_holds'] ? '' : ' — not sustained')),
            $line('Cash break-even', '—', fn ($r) => $r['cash_break_even_month'] === null ? 'Never' : 'Month '.$r['cash_break_even_month']),
        ];
    @endphp
    <div class="flex flex-col gap-6">
        <x-filament::section heading="Existing versus proposed">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead><tr class="text-left text-xs text-gray-500"><th class="py-2"></th><th class="text-right">Keep as is</th>@foreach ($scenarios as $row)<th class="text-right">{{ $row['scenario']['label'] }}<br><span class="font-normal">{{ $row['scenario']['tenure_months'] }} m · fee {{ $row['scenario']['processing_fee_percent'] }}%</span></th>@endforeach</tr></thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($lines as $item)
                            <tr @class(['font-semibold' => $item['strong']])><td class="py-1.5">{{ $item['label'] }}</td><td class="text-right">{{ $item['keep'] }}</td>@foreach ($scenarios as $row)<td @class(['text-right', 'text-success-600' => $item['strong'] && $row['result']['net_financial_benefit'] > 0, 'text-danger-600' => $item['strong'] && $row['result']['net_financial_benefit'] <= 0])>{{ ($item['value'])($row['result']) }}</td>@endforeach</tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <p class="mt-3 text-xs text-gray-500">Scenarios are edited in the "Scenarios" tab; press "Recalculate" afterwards. Kept liabilities are outside this comparison but inside affordability.</p>
        </x-filament::section>
        @foreach ($scenarios as $row)
            @if ($row['result']['flags'] !== [])
                <x-filament::section :heading="'Flags — '.$row['scenario']['label']" collapsible collapsed>
                    <ul class="flex flex-col gap-1 text-sm">@foreach ($row['result']['flags'] as $flag)<li>• {{ P::flagSentence($flag, $names) }}</li>@endforeach</ul>
                </x-filament::section>
            @endif
        @endforeach
    </div>
@endif
