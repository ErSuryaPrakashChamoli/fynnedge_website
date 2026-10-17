{{-- A list of the customer's own genuine credit checks. Each row names its bureau: scores from different bureaus are never compared. --}}
@if ($reports->isEmpty())
    <x-portal.empty-state class="mt-4" :title="__('No credit reports yet')" :body="__('Reports from your credit score checks are saved here. Opening them never runs a new check.')">
        <x-ui.button tag="a" :href="route('portal.credit-score')" size="sm">{{ __('Check my credit score') }}</x-ui.button>
    </x-portal.empty-state>
@else
    <div class="mt-4 overflow-x-auto">
        <table class="w-full min-w-[34rem] text-left text-sm" data-report-table>
            <thead class="text-xs text-ink-faint">
                <tr>
                    <th class="py-2 pr-3 font-medium">{{ __('Bureau') }}</th>
                    <th class="py-2 pr-3 font-medium">{{ __('Score') }}</th>
                    <th class="py-2 pr-3 font-medium">{{ __('Report date') }}</th>
                    <th class="py-2 pr-3 font-medium">{{ __('Status') }}</th>
                    <th class="py-2 font-medium"><span class="sr-only">{{ __('Action') }}</span></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @foreach ($reports as $report)
                    <tr>
                        <td class="py-2.5 pr-3 text-ink">
                            {{ $report->sourceBureau()->getLabel() }}
                            @if ($report->sourceBureau() !== $report->bureau)
                                <span class="block text-xs text-ink-faint">{{ __('on the :page page', ['page' => $report->bureau->getLabel()]) }}</span>
                            @endif
                        </td>
                        <td class="py-2.5 pr-3 font-display text-base font-semibold text-ink">{{ $report->score ?? '—' }}</td>
                        <td class="py-2.5 pr-3 text-ink-muted">{{ ($report->completed_at ?? $report->requested_at)?->translatedFormat('d M Y') }}</td>
                        <td class="py-2.5 pr-3"><x-portal.check-status :check="$report" /></td>
                        <td class="py-2.5 text-right"><a href="{{ route('portal.reports.show', $report->public_id) }}" class="font-medium text-accent underline">{{ __('View') }}</a></td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
