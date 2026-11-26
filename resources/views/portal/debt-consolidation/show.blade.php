@use('App\Modules\DebtConsolidation\Services\DebtPresenter', 'P')
@use('App\Modules\DebtConsolidation\Enums\ClosureStatus')
<x-layouts.portal :title="$application->reference" :heading="__('Debt consolidation — :reference', ['reference' => $application->reference])" :customer="$customer">
    @php
        $results = $snapshot?->results;
        $recommended = $snapshot?->scenario();
        $names = $snapshot ? P::liabilityNames($snapshot->inputs['liabilities'] ?? []) : [];
        $card = 'rounded-2xl border border-line bg-surface p-5 shadow-sm';
        $h2 = 'font-display text-lg font-semibold text-ink';
    @endphp

    <div class="flex flex-col gap-6">
        @if (session('status'))
            <x-ui.alert tone="pass">{{ session('status') }}</x-ui.alert>
        @endif
        @if ($errors->any())
            <x-ui.alert tone="warn">{{ $errors->first() }}</x-ui.alert>
        @endif

        {{-- Status --}}
        <div class="{{ $card }}">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <p class="text-xs text-ink-muted">{{ __('Current status') }}</p>
                    <p class="font-display text-xl font-semibold text-ink">{{ $application->status->getLabel() }}</p>
                </div>
                @if ($assessmentUrl)
                    <x-ui.button tag="a" :href="$assessmentUrl" size="sm">{{ $application->isSubmitted() ? __('Update my details') : __('Continue my assessment') }}</x-ui.button>
                @endif
            </div>
            <p class="mt-3 text-sm text-ink-muted">
                @if ($application->assignee)
                    {{ __('Your advisor: :name.', ['name' => $application->assignee->name]) }}
                @endif
                @if ($contactPhone)
                    {{ __('Questions? Call us on :phone and quote :reference.', ['phone' => $contactPhone, 'reference' => $application->reference]) }}
                @else
                    {{ __('Questions? Contact us and quote :reference.', ['reference' => $application->reference]) }}
                @endif
            </p>
        </div>

        {{-- Liabilities --}}
        <div class="{{ $card }}">
            <h2 class="{{ $h2 }}">{{ __('Your existing loans and cards') }}</h2>
            <div class="mt-3 overflow-x-auto">
                <table class="w-full min-w-[34rem] text-left text-sm">
                    <thead class="text-xs text-ink-muted"><tr><th class="py-2">{{ __('Lender') }}</th><th>{{ __('Type') }}</th><th class="text-right">{{ __('Outstanding') }}</th><th class="text-right">{{ __('EMI / payment') }}</th><th class="text-right">{{ __('Rate') }}</th><th>{{ __('Checked by us') }}</th></tr></thead>
                    <tbody class="divide-y divide-line">
                        @forelse ($application->liabilities as $liability)
                            <tr><td class="py-2 text-ink">{{ $liability->lender_name }}</td><td>{{ $liability->liability_type->getLabel() }}</td><td class="text-right">{{ P::inr($liability->outstanding_principal) }}</td><td class="text-right">{{ P::inr($liability->monthly_payment) }}</td><td class="text-right">{{ P::percent($liability->interest_rate) }}</td><td>{{ $liability->verification_status->getLabel() }}</td></tr>
                        @empty
                            <tr><td colspan="6" class="py-3 text-ink-muted">{{ __('No loans added yet.') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        {{-- Assessment, suitability, savings --}}
        @if ($results)
            <div class="{{ $card }}">
                <h2 class="{{ $h2 }}">{{ __('Your assessment') }}</h2>
                <p class="text-xs text-ink-faint">{{ __('Calculated :date', ['date' => $snapshot->created_at->timezone('Asia/Kolkata')->translatedFormat('j M Y')]) }}</p>
                <dl class="mt-4 grid grid-cols-2 gap-4 text-sm sm:grid-cols-4">
                    <div><dt class="text-xs text-ink-muted">{{ __('Total debt') }}</dt><dd class="font-semibold text-ink">{{ P::inr($results['portfolio']['total_outstanding']) }}</dd></div>
                    <div><dt class="text-xs text-ink-muted">{{ __('Monthly repayments') }}</dt><dd class="font-semibold text-ink">{{ P::inr($results['portfolio']['total_monthly_obligation']) }}</dd></div>
                    <div><dt class="text-xs text-ink-muted">{{ __('Average interest rate') }}</dt><dd class="font-semibold text-ink">{{ P::percent($results['portfolio']['weighted_average_rate']) }}</dd></div>
                    <div><dt class="text-xs text-ink-muted">{{ $results['current_affordability']['metric'] === 'foir' ? __('Income going to EMIs') : __('Debt-service cover') }}</dt><dd class="font-semibold text-ink">{{ $results['current_affordability']['current_value'] === null ? '—' : ($results['current_affordability']['metric'] === 'foir' ? P::percent($results['current_affordability']['current_value']) : $results['current_affordability']['current_value'].'×') }}</dd></div>
                </dl>

                <h3 class="mt-6 text-sm font-semibold text-ink">{{ __('Consolidation options compared') }}</h3>
                <div class="mt-2 overflow-x-auto">
                    <table class="w-full min-w-[30rem] text-left text-sm">
                        <thead class="text-xs text-ink-muted"><tr><th class="py-2"></th><th class="text-right">{{ __('Keep as is') }}</th>@foreach ($results['scenarios'] as $row)<th class="text-right">{{ __($row['scenario']['label']) }}</th>@endforeach</tr></thead>
                        <tbody class="divide-y divide-line">
                            @php $first = $results['scenarios'][0]['result'] ?? null; @endphp
                            <tr><td class="py-1.5 text-ink-muted">{{ __('Monthly outgo') }}</td><td class="text-right">{{ P::inr($first['existing_monthly_outflow'] ?? null) }}</td>@foreach ($results['scenarios'] as $row)<td class="text-right">{{ P::inr($row['result']['proposed_monthly_outflow']) }}</td>@endforeach</tr>
                            <tr><td class="py-1.5 text-ink-muted">{{ __('Total repayment') }}</td><td class="text-right">{{ P::inr($first['existing_total_outflow'] ?? null) }}</td>@foreach ($results['scenarios'] as $row)<td class="text-right">{{ P::inr($row['result']['proposed_total_outflow']) }}</td>@endforeach</tr>
                            <tr><td class="py-1.5 text-ink-muted">{{ __('Fees and charges') }}</td><td class="text-right">—</td>@foreach ($results['scenarios'] as $row)<td class="text-right">{{ P::inr($row['result']['total_consolidation_costs']) }}</td>@endforeach</tr>
                            <tr class="font-semibold"><td class="py-1.5 text-ink">{{ __('Net saving') }}</td><td class="text-right">—</td>@foreach ($results['scenarios'] as $row)<td @class(['text-right', 'text-pass' => $row['result']['net_financial_benefit'] > 0, 'text-warn' => $row['result']['net_financial_benefit'] <= 0])>{{ P::inr($row['result']['net_financial_benefit']) }}</td>@endforeach</tr>
                            <tr><td class="py-1.5 text-ink-muted">{{ __('Suitability') }}</td><td class="text-right">—</td>@foreach ($results['scenarios'] as $row)<td class="text-right text-xs">{{ number_format($row['suitability']['score'], 0) }}/100 · {{ \App\Modules\DebtConsolidation\Enums\SuitabilityBand::from($row['suitability']['band'])->getLabel() }}</td>@endforeach</tr>
                        </tbody>
                    </table>
                </div>
                <p class="mt-3 text-xs text-ink-muted">{{ \App\Modules\DebtConsolidation\Services\DebtConsolidationSettings::text('suitability_disclaimer') }}</p>
            </div>
        @endif

        {{-- Lenders: consent to shortlisted ones, then application status --}}
        @if ($shortlisted->isNotEmpty() || $application->lenderApplications->isNotEmpty())
            <div class="{{ $card }}">
                <h2 class="{{ $h2 }}">{{ __('Lenders') }}</h2>
                @foreach ($shortlisted as $match)
                    <form method="POST" action="{{ route('portal.debt.lender-consent', $application) }}" class="mt-4 rounded-xl border border-accent bg-accent-soft p-4">
                        @csrf
                        <input type="hidden" name="match" value="{{ $match->public_id }}">
                        <p class="font-semibold text-ink">{{ __('Your advisor suggests :lender', ['lender' => $match->lenderProduct?->lender?->name]) }} — {{ $match->lenderProduct?->loanProduct?->name }}</p>
                        <label class="mt-2 flex items-start gap-2 text-sm text-ink">
                            <input type="checkbox" name="agree" value="1" class="mt-0.5 h-4 w-4 rounded border-line-strong text-accent">
                            <span>{{ \App\Modules\DebtConsolidation\Services\DebtConsolidationSettings::consentText(\App\Modules\DebtConsolidation\Enums\ConsentPurpose::LenderSharing, $match->lenderProduct?->lender?->name) }}</span>
                        </label>
                        <x-ui.button type="submit" size="sm" class="mt-3">{{ __('Give consent for this lender') }}</x-ui.button>
                    </form>
                @endforeach
                @foreach ($application->lenderApplications as $lenderApplication)
                    <div class="mt-4 flex flex-wrap items-center justify-between gap-2 border-t border-line pt-4">
                        <div><p class="font-medium text-ink">{{ $lenderApplication->lender_name }}</p><p class="text-xs text-ink-muted">{{ $lenderApplication->product_name }}</p></div>
                        <x-ui.badge :tone="$lenderApplication->status->isLenderApproved() ? 'pass' : 'accent'">{{ $lenderApplication->status->getLabel() }}</x-ui.badge>
                    </div>
                @endforeach
                <p class="mt-4 text-xs text-ink-muted">{{ __('Each lender decides on its own application. FynnEdge does not approve, sanction or disburse loans.') }}</p>
            </div>
        @endif

        {{-- Closure progress --}}
        @if ($application->closures->isNotEmpty())
            <div class="{{ $card }}">
                <h2 class="{{ $h2 }}">{{ __('Closing your old loans') }}</h2>
                @foreach ($application->closures as $closure)
                    <div class="mt-4">
                        <div class="flex justify-between text-sm"><span class="text-ink">{{ $closure->liability?->lender_name }}</span><span class="text-ink-muted">{{ $closure->status->getLabel() }}</span></div>
                        <div class="mt-1.5 h-1.5 rounded-full bg-surface-2"><div @class(['h-1.5 rounded-full', 'bg-pass' => $closure->status === ClosureStatus::ClosureVerified, 'bg-accent' => $closure->status !== ClosureStatus::ClosureVerified]) style="width: {{ round(($closure->status->step() ?? 0) / ClosureStatus::stepCount() * 100) }}%"></div></div>
                    </div>
                @endforeach
                <p class="mt-4 text-xs text-ink-muted">{{ __('A loan is marked closed only once its payment is confirmed and, for full closure, the lender\'s NOC is received and checked. Please also check your credit report shows each closed loan as closed.') }}</p>
            </div>
        @endif

        {{-- Documents --}}
        <div class="{{ $card }}">
            <h2 class="{{ $h2 }}">{{ __('Documents') }}</h2>
            <p class="mt-1 text-sm text-ink-muted">{{ __('Upload statements, foreclosure letters or NOCs your advisor asks for. PDF, JPG or PNG, up to :size MB. Files are stored encrypted and read only by our team.', ['size' => (int) (config('debt_consolidation.documents.max_kilobytes') / 1024)]) }}</p>
            <ul class="mt-4 divide-y divide-line text-sm">
                @forelse ($application->documents as $document)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span class="text-ink">{{ $document->title() }} <span class="text-xs text-ink-faint">v{{ $document->version }}</span></span>
                        <span class="flex items-center gap-3">
                            <x-ui.badge :tone="match ($document->status->value) { 'verified' => 'pass', 'rejected' => 'warn', default => 'muted' }">{{ match ($document->status->value) { 'verified' => __('Accepted'), 'rejected' => __('Not accepted'), default => __('Being checked') } }}</x-ui.badge>
                            <a href="{{ route('portal.debt.documents.show', [$application, $document->public_id]) }}" class="text-accent hover:underline">{{ __('Download') }}</a>
                        </span>
                        @if ($document->rejection_reason)
                            <span class="w-full text-xs text-warn">{{ $document->rejection_reason }}</span>
                        @endif
                    </li>
                @empty
                    <li class="py-2 text-ink-muted">{{ __('No documents yet.') }}</li>
                @endforelse
            </ul>
            @if ($application->status->isOpen())
            <form method="POST" action="{{ route('portal.debt.documents.store', $application) }}" enctype="multipart/form-data" class="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_auto] sm:items-end">
                @csrf
                <label class="block text-xs font-medium text-ink-muted">{{ __('Document') }}
                    <select name="document_type_id" required class="mt-1 w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink">
                        @foreach ($documentTypes as $type)
                            <option value="{{ $type->id }}">{{ $type->label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block text-xs font-medium text-ink-muted">{{ __('File') }}
                    <input type="file" name="file" required accept=".pdf,.jpg,.jpeg,.png" class="mt-1 block w-full text-sm text-ink">
                </label>
                <x-ui.button type="submit" size="sm">{{ __('Upload') }}</x-ui.button>
            </form>
            @endif
        </div>

        {{-- Reports --}}
        @if ($application->reports->isNotEmpty())
            <div class="{{ $card }}">
                <h2 class="{{ $h2 }}">{{ __('Reports') }}</h2>
                <ul class="mt-3 divide-y divide-line text-sm">
                    @foreach ($application->reports as $report)
                        <li class="flex justify-between py-2"><span class="text-ink">{{ $report->report_type->getLabel() }} <span class="text-xs text-ink-faint">v{{ $report->version }} · {{ $report->generated_at->timezone('Asia/Kolkata')->translatedFormat('j M Y') }}</span></span><a href="{{ route('portal.debt.reports.show', [$application, $report->public_id]) }}" class="text-accent hover:underline">{{ __('Download PDF') }}</a></li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Messages from the advisor --}}
        @if ($application->notes->isNotEmpty())
            <div class="{{ $card }}">
                <h2 class="{{ $h2 }}">{{ __('Messages from your advisor') }}</h2>
                @foreach ($application->notes as $note)
                    <div class="mt-3 border-t border-line pt-3 text-sm"><p class="text-xs text-ink-faint">{{ $note->created_at->timezone('Asia/Kolkata')->translatedFormat('j M Y') }}</p><p class="text-ink">{{ $note->subject }}</p><p class="text-ink-muted">{{ $note->body }}</p></div>
                @endforeach
            </div>
        @endif

        {{-- Consents --}}
        <details class="{{ $card }}">
            <summary class="cursor-pointer font-semibold text-ink">{{ __('Consents you have given') }}</summary>
            <ul class="mt-3 divide-y divide-line text-sm">
                @foreach ($application->consents as $consent)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2">
                        <span class="text-ink">{{ $consent->purpose->getLabel() }}{{ $consent->lenderProduct ? ' — '.$consent->lenderProduct->lender?->name : '' }} <span class="text-xs text-ink-faint">{{ $consent->granted_at->timezone('Asia/Kolkata')->translatedFormat('j M Y') }}</span></span>
                        @if ($consent->isActive())
                            <form method="POST" action="{{ route('portal.debt.consents.withdraw', [$application, $consent->public_id]) }}">@csrf<button type="submit" class="text-xs text-warn hover:underline">{{ __('Withdraw') }}</button></form>
                        @else
                            <span class="text-xs text-ink-faint">{{ __('Withdrawn') }}</span>
                        @endif
                    </li>
                @endforeach
            </ul>
        </details>
    </div>
</x-layouts.portal>
