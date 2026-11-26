@php use App\Modules\DebtConsolidation\Services\DebtPresenter as P; @endphp
@if (! $snapshot)
    @include('filament.debt-consolidation._no-snapshot')
@else
    @php
        $names = P::liabilityNames($snapshot->inputs['liabilities'] ?? []);
        $total = 0;
    @endphp
    <x-filament::section heading="Closure requirement per liability (full closure)">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead><tr class="text-left text-xs text-gray-500"><th class="py-2">Liability</th><th class="text-right">Principal</th><th class="text-right">Overdue</th><th class="text-right">Pending EMI</th><th class="text-right">Accrued</th><th class="text-right">Charge</th><th class="text-right">GST</th><th class="text-right">Other</th><th class="text-right">Total</th><th>Basis</th></tr></thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($snapshot->results['liabilities'] as $key => $row)
                        @php $closure = $row['full_closure']; $total += (float) ($closure['total'] ?? 0); @endphp
                        @if ($closure)
                            <tr>
                                <td class="py-1.5">{{ $names[$key] ?? $key }}</td>
                                <td class="text-right">{{ P::inr($closure['principal'], true) }}</td>
                                <td class="text-right">{{ P::inr($closure['overdue'], true) }}</td>
                                <td class="text-right">{{ P::inr($closure['pending_instalment'], true) }}</td>
                                <td class="text-right">{{ P::inr($closure['accrued_interest'], true) }}</td>
                                <td class="text-right">{{ P::inr($closure['charges'], true) }}</td>
                                <td class="text-right">{{ P::inr($closure['tax'], true) }}</td>
                                <td class="text-right">{{ P::inr($closure['other_charges'], true) }}</td>
                                <td class="text-right font-semibold">{{ P::inr($closure['total'], true) }}</td>
                                <td class="text-xs">
                                    @if ($closure['source'] === 'quotation')
                                        <x-filament::badge :color="$closure['verified'] ? 'success' : ($closure['quotation_expired'] ? 'danger' : 'warning')">{{ $closure['verified'] ? 'Verified quotation' : ($closure['quotation_expired'] ? 'Quotation expired' : 'Quotation — verify') }}</x-filament::badge>
                                        @if ($closure['valid_until']) <span class="text-gray-500">to {{ $closure['valid_until'] }}</span> @endif
                                    @else
                                        <x-filament::badge color="gray">Estimate</x-filament::badge>
                                    @endif
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
                <tfoot><tr class="font-semibold"><td class="py-2">Total if every liability is closed</td><td colspan="7"></td><td class="text-right">{{ P::inr($total, true) }}</td><td></td></tr></tfoot>
            </table>
        </div>
        <p class="mt-3 text-xs text-gray-500">Record each lender's foreclosure letter from the Existing liabilities tab ("Record quotation"), verify it against the uploaded letter, then Recalculate. GST at {{ $snapshot->inputs['assumptions']['gst_rate_percent'] }}% on charges.</p>
    </x-filament::section>
@endif
