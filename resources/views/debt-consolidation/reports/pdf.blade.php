{{--
    Debt Consolidation reports (GenerateDebtReport), rendered by dompdf: plain
    HTML tables and CSS 2.1 only, nothing remote, always English. Calculation
    figures come ONLY from the stored snapshot's results, never recomputed.
--}}
@php
    use App\Modules\DebtConsolidation\Services\DebtPresenter as P;
    use App\Modules\DebtConsolidation\Enums\ReportType as T;

    $results = $snapshot?->results ?? [];
    $inputs = $snapshot?->inputs ?? [];
    $portfolio = $results['portfolio'] ?? null;
    $scenarios = $results['scenarios'] ?? [];
    $recommendedKey = $results['recommended_scenario_key'] ?? null;
    $recommended = collect($scenarios)->firstWhere('scenario.key', $recommendedKey) ?? ($scenarios[0] ?? null);
    $profile = $application->financialProfile;
    $liabilitiesVerified = $application->liabilities->where('verification_status', \App\Modules\DebtConsolidation\Enums\VerificationStatus::Verified)->count();
    $flags = $results['flags'] ?? [];
    $bandLabel = fn (?string $band) => $band ? (\App\Modules\DebtConsolidation\Enums\SuitabilityBand::tryFrom($band)?->getLabel() ?? $band) : '—';
    $typeLabel = fn (?string $type) => \App\Modules\DebtConsolidation\Enums\LiabilityType::tryFrom((string) $type)?->getLabel() ?? (string) $type;
    $show = fn (T ...$types) => in_array($type, $types, true);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $type->getLabel() }} — {{ $application->reference }}</title>
    <style>
        @page { margin: 24mm 15mm 20mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9pt; color: #101828; line-height: 1.45; }
        h1, h2, h3, p { margin: 0; }
        .masthead { position: fixed; top: -16mm; left: 0; right: 0; height: 10mm; border-bottom: 1.5pt solid #163a63; }
        .masthead td { vertical-align: bottom; padding-bottom: 2mm; }
        .brand { font-size: 13pt; font-weight: bold; color: #163a63; }
        .muted { color: #4c5a70; }
        .faint { color: #838fa1; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; height: 8mm; font-size: 7pt; color: #838fa1; border-top: 0.5pt solid #e2e5ec; padding-top: 1.5mm; }
        .page-number:after { content: counter(page); }
        table { width: 100%; border-collapse: collapse; }
        h1 { font-size: 16pt; color: #163a63; }
        h2 { margin-top: 16pt; padding-bottom: 3pt; border-bottom: 0.75pt solid #c7cdd9; font-size: 11pt; color: #163a63; }
        .meta td { padding: 2pt 0; vertical-align: top; }
        .meta td.label { width: 28%; color: #838fa1; }
        .grid td { padding: 6pt 8pt; background: #eef1f6; border: 2pt solid #ffffff; vertical-align: top; width: 25%; }
        .grid .value { font-size: 11pt; font-weight: bold; }
        .rows th { text-align: left; font-size: 7.5pt; color: #4c5a70; padding: 4pt 4pt 4pt 0; border-bottom: 0.75pt solid #c7cdd9; }
        .rows td { padding: 3.5pt 4pt 3.5pt 0; border-bottom: 0.5pt solid #e2e5ec; vertical-align: top; }
        .num { text-align: right; }
        .total td { font-weight: bold; border-top: 0.75pt solid #163a63; }
        .note { margin-top: 8pt; padding: 6pt 9pt; border-left: 2.5pt solid #163a63; background: #e1e8f4; color: #0d2340; font-size: 8pt; }
        .warn { margin-top: 4pt; padding: 4pt 8pt; border-left: 2.5pt solid #b3791f; background: #f7ecd4; font-size: 8pt; }
        .good { color: #1f7a4d; } .bad { color: #b3261e; }
        .small { font-size: 7.5pt; }
        .keep { page-break-inside: avoid; }
    </style>
</head>
<body>
    <table class="masthead"><tr><td class="brand">FynnEdge Advisory</td><td class="muted" style="text-align: right;">{{ $type->getLabel() }} · {{ $application->reference }}</td></tr></table>
    <div class="footer"><table><tr>
        <td>FynnEdge Advisory (OPC) Pvt Ltd · Report v{{ $version }} · generated {{ $generatedAt->timezone('Asia/Kolkata')->format('d M Y, h:i A') }} IST{{ $snapshot ? ' · calculation '.$snapshot->public_id.' (formula '.$snapshot->formula_version.', scoring '.($snapshot->scoring_version ?? '—').')' : '' }}{{ $type->isInternal() ? ' · INTERNAL — NOT FOR CUSTOMER' : '' }}</td>
        <td style="width: 10%; text-align: right;">Page <span class="page-number"></span></td>
    </tr></table></div>

    <h1>{{ $type->getLabel() }}</h1>
    <table class="meta" style="margin-top: 8pt;">
        <tr><td class="label">Application ID</td><td>{{ $application->reference }}</td><td class="label">Report date</td><td>{{ $generatedAt->timezone('Asia/Kolkata')->format('d M Y') }}</td></tr>
        <tr><td class="label">{{ $application->applicant_type->value === 'business' ? 'Business' : 'Customer' }}</td><td>{{ $application->displayName() }}</td><td class="label">Applicant type</td><td>{{ ucfirst($application->applicant_type->value) }}</td></tr>
        <tr><td class="label">Mobile</td><td>+91 {{ $application->maskedMobile() }}</td><td class="label">Case status</td><td>{{ $application->status->getLabel() }}</td></tr>
        <tr><td class="label">Verification</td><td>{{ $liabilitiesVerified }} of {{ $application->liabilities->count() }} loans verified against documents</td><td class="label">Figures as of</td><td>{{ $inputs['assumptions']['as_of'] ?? $generatedAt->toDateString() }}</td></tr>
    </table>

    @if ($snapshot === null && $type->needsSnapshot())
        <p class="warn">No calculation is stored for this case yet.</p>
    @endif

    @if ($portfolio && $show(T::PreliminaryAssessment, T::PortfolioSummary, T::AdvisorAssessment))
        <h2>Debt portfolio</h2>
        <table class="grid"><tr>
            <td><span class="faint">Total outstanding</span><br><span class="value">{{ P::inr($portfolio['total_outstanding'], true) }}</span></td>
            <td><span class="faint">Liabilities</span><br><span class="value">{{ $portfolio['liability_count'] }}</span></td>
            <td><span class="faint">Monthly obligations</span><br><span class="value">{{ P::inr($portfolio['total_monthly_obligation'], true) }}</span></td>
            <td><span class="faint">Weighted average rate</span><br><span class="value">{{ P::percent($portfolio['weighted_average_rate']) }}</span></td>
        </tr><tr>
            <td><span class="faint">Secured / unsecured</span><br><span class="value">{{ P::inr($portfolio['secured_outstanding']) }} / {{ P::inr($portfolio['unsecured_outstanding']) }}</span></td>
            <td><span class="faint">Remaining repayment</span><br><span class="value">{{ P::inr($portfolio['remaining_repayment'], true) }}</span></td>
            <td><span class="faint">Remaining interest</span><br><span class="value">{{ P::inr($portfolio['remaining_interest'], true) }}</span></td>
            <td><span class="faint">Overdue</span><br><span class="value">{{ P::inr($portfolio['total_overdue'], true) }} ({{ $portfolio['liabilities_in_arrears'] }})</span></td>
        </tr></table>
    @endif

    @if ($snapshot && $show(T::PortfolioSummary, T::AdvisorAssessment, T::ForeclosureAnalysis))
        <h2>{{ $show(T::ForeclosureAnalysis) ? 'Closure requirement by liability' : 'Liabilities' }}</h2>
        <table class="rows">
            <tr><th>Liability</th><th class="num">Outstanding</th><th class="num">Rate</th><th class="num">Payment</th><th class="num">Months left</th>@if ($show(T::ForeclosureAnalysis))<th class="num">Dues</th><th class="num">Charges + GST</th><th class="num">To close</th><th>Basis</th>@else<th class="num">To close</th><th>In scope</th>@endif</tr>
            @php $closeTotal = 0; @endphp
            @foreach ($inputs['liabilities'] ?? [] as $liability)
                @php
                    $row = $results['liabilities'][$liability['key']] ?? [];
                    $closure = $row['full_closure'] ?? null;
                    $closeTotal += (float) ($closure['total'] ?? 0);
                @endphp
                <tr class="keep">
                    <td>{{ $names[$liability['key']] ?? $typeLabel($liability['type']) }}</td>
                    <td class="num">{{ P::inr($liability['outstanding_principal'], true) }}</td>
                    <td class="num">{{ P::percent($row['projection']['effective_annual_rate'] ?? $liability['annual_interest_rate']) }}</td>
                    <td class="num">{{ P::inr($row['projection']['monthly_obligation'] ?? null, true) }}</td>
                    <td class="num">{{ $row['projection']['remaining_months'] ?? '—' }}</td>
                    @if ($show(T::ForeclosureAnalysis))
                        <td class="num">{{ P::inr(($closure['overdue'] ?? 0) + ($closure['pending_instalment'] ?? 0) + ($closure['accrued_interest'] ?? 0), true) }}</td>
                        <td class="num">{{ P::inr($closure['closure_costs'] ?? 0, true) }}</td>
                        <td class="num">{{ P::inr($closure['total'] ?? null, true) }}</td>
                        <td class="small">{{ ($closure['source'] ?? '') === 'quotation' ? (($closure['verified'] ?? false) ? 'Verified quotation' : (($closure['quotation_expired'] ?? false) ? 'Quotation expired' : 'Quotation, unverified')).(($closure['valid_until'] ?? null) ? ', valid to '.$closure['valid_until'] : '') : 'Estimate' }}</td>
                    @else
                        <td class="num">{{ P::inr($closure['total'] ?? null, true) }}</td>
                        <td>{{ ($row['classification']['consolidatable_now'] ?? true) ? ucfirst($liability['mode']) : 'Not now' }}</td>
                    @endif
                </tr>
            @endforeach
            @if ($show(T::ForeclosureAnalysis))
                <tr class="total"><td>Total</td><td colspan="6"></td><td class="num">{{ P::inr($closeTotal, true) }}</td><td></td></tr>
            @endif
        </table>
        @if ($show(T::ForeclosureAnalysis))
            <p class="note">Closure amount = principal closed + overdue and pending instalments + accrued interest + foreclosure charge + GST at {{ $inputs['assumptions']['gst_rate_percent'] ?? 18 }}% on the charge + other lender charges. A lender's foreclosure letter replaces the estimate; only the amount on a current letter is payable. Quotations expire — check the validity date before paying.</p>
        @endif
    @endif

    @if ($recommended && $show(T::PreliminaryAssessment, T::SavingsComparison, T::AdvisorAssessment))
        <h2>{{ $show(T::SavingsComparison) ? 'Existing versus proposed' : 'Consolidation estimate — '.$recommended['scenario']['label'] }}</h2>
        <table class="rows">
            <tr><th></th><th class="num">Keep as is</th>@foreach ($show(T::SavingsComparison, T::AdvisorAssessment) ? $scenarios : [$recommended] as $row)<th class="num">{{ $row['scenario']['label'] }}</th>@endforeach</tr>
            @php $columns = $show(T::SavingsComparison, T::AdvisorAssessment) ? $scenarios : [$recommended]; $first = $columns[0]['result']; @endphp
            <tr><td>Interest rate</td><td class="num">{{ P::percent($first['weighted_existing_rate']) }}</td>@foreach ($columns as $row)<td class="num">{{ P::percent($row['result']['annual_interest_rate']) }}</td>@endforeach</tr>
            <tr><td>Monthly outgo</td><td class="num">{{ P::inr($first['existing_monthly_outflow'], true) }}</td>@foreach ($columns as $row)<td class="num">{{ P::inr($row['result']['proposed_monthly_outflow'], true) }}</td>@endforeach</tr>
            <tr><td>Loan amount</td><td class="num">—</td>@foreach ($columns as $row)<td class="num">{{ P::inr($row['result']['loan_amount'], true) }}</td>@endforeach</tr>
            <tr><td>Processing fee + GST</td><td class="num">—</td>@foreach ($columns as $row)<td class="num">{{ P::inr($row['result']['processing_fee'] + $row['result']['processing_fee_tax'], true) }}</td>@endforeach</tr>
            <tr><td>Foreclosure charges + GST</td><td class="num">—</td>@foreach ($columns as $row)<td class="num">{{ P::inr($row['result']['closure_costs'], true) }}</td>@endforeach</tr>
            <tr><td>Total repayment from today</td><td class="num">{{ P::inr($first['existing_total_outflow'], true) }}</td>@foreach ($columns as $row)<td class="num">{{ P::inr($row['result']['proposed_total_outflow'], true) }}</td>@endforeach</tr>
            <tr><td>Interest from today</td><td class="num">{{ P::inr($first['existing_interest'], true) }}</td>@foreach ($columns as $row)<td class="num">{{ P::inr($row['result']['proposed_interest'], true) }}</td>@endforeach</tr>
            <tr><td>Repayment period</td><td class="num">{{ P::months($first['existing_months']) }}</td>@foreach ($columns as $row)<td class="num">{{ P::months($row['result']['proposed_months']) }}</td>@endforeach</tr>
            <tr class="total"><td>Net financial benefit</td><td class="num">—</td>@foreach ($columns as $row)<td class="num {{ $row['result']['net_financial_benefit'] > 0 ? 'good' : 'bad' }}">{{ P::inr($row['result']['net_financial_benefit'], true) }}</td>@endforeach</tr>
            <tr><td>Costs recovered (interest saved)</td><td class="num">—</td>@foreach ($columns as $row)<td class="num">{{ $row['result']['cost_break_even_month'] === null ? 'Not recovered' : 'Month '.$row['result']['cost_break_even_month'].($row['result']['cost_recovery_holds'] ? '' : ' (not sustained)') }}</td>@endforeach</tr>
        </table>
        <p class="note">Net benefit = everything payable from today if the loans are kept, minus everything payable if they are consolidated (upfront cash, new loan repayments and payments on any balance left). Principal counts once on each side. Rates and fees are assumptions for comparison, not lender offers.</p>
        @if ($show(T::SavingsComparison))
            <h2>Cumulative repayment by year — {{ $recommended['scenario']['label'] }}</h2>
            <table class="rows"><tr><th>Year</th><th class="num">Keep as is</th><th class="num">Consolidated</th><th class="num">Cumulative, keep</th><th class="num">Cumulative, consolidated</th></tr>
                @foreach ($recommended['result']['yearly'] as $year)
                    <tr><td>{{ $year['year'] }}</td><td class="num">{{ P::inr($year['existing'], true) }}</td><td class="num">{{ P::inr($year['proposed'], true) }}</td><td class="num">{{ P::inr($year['cumulative_existing'], true) }}</td><td class="num">{{ P::inr($year['cumulative_proposed'], true) }}</td></tr>
                @endforeach
            </table>
        @endif
    @endif

    @if ($scenarios && $show(T::PreliminaryAssessment, T::Suitability, T::AdvisorAssessment))
        <h2>Consolidation suitability</h2>
        @foreach ($show(T::Suitability, T::AdvisorAssessment) ? $scenarios : [$recommended] as $row)
            <div class="keep" style="margin-top: 6pt;">
                <p><strong>{{ $row['scenario']['label'] }}:</strong> {{ number_format($row['suitability']['score'], 1) }}/100 — {{ $bandLabel($row['suitability']['band']) }}{{ $row['suitability']['requires_advisor_review'] ? ' · advisor review required' : '' }}</p>
                @if ($show(T::Suitability, T::AdvisorAssessment))
                    <table class="rows"><tr><th>Dimension</th><th class="num">Measure</th><th class="num">Score</th><th class="num">Weight</th><th class="num">Weighted</th></tr>
                        @foreach ($row['suitability']['dimensions'] as $dimension => $values)
                            <tr><td>{{ str_replace('_', ' ', ucfirst($dimension)) }}</td><td class="num">{{ $values['measure'] ?? '—' }}</td><td class="num">{{ $values['score'] }}</td><td class="num">{{ $values['weight'] }}%</td><td class="num">{{ $values['weighted'] }}</td></tr>
                        @endforeach
                    </table>
                @endif
            </div>
        @endforeach
        @php $affordability = $recommended['affordability'] ?? $results['current_affordability'] ?? null; @endphp
        @if ($affordability)
            <p style="margin-top: 6pt;"><strong>Repayment affordability:</strong> {{ $affordability['metric'] === 'foir' ? 'FOIR' : 'Debt-service cover' }} {{ $affordability['current_value'] ?? '—' }}{{ $affordability['metric'] === 'foir' ? '%' : '×' }} today → {{ $affordability['proposed_value'] ?? '—' }}{{ $affordability['metric'] === 'foir' ? '%' : '×' }} after ({{ \App\Modules\DebtConsolidation\Enums\AffordabilityBand::tryFrom($affordability['proposed_band'])?->getLabel() }}).</p>
        @endif
        <p class="note">{{ $suitabilityDisclaimer }} Financial suitability, repayment affordability and lender eligibility are separate: only a lender decides eligibility.</p>
    @endif

    @if ($flags && $show(T::PreliminaryAssessment, T::Suitability, T::AdvisorAssessment, T::SavingsComparison))
        <h2>Points to note</h2>
        @foreach ($recommended ? array_merge($flags, $recommended['result']['flags']) : $flags as $flag)
            <p class="warn">{{ P::flagSentence($flag, $names) }}</p>
        @endforeach
    @endif

    @if ($show(T::AdvisorAssessment))
        <h2>Lender matching</h2>
        @forelse ($application->lenderMatches as $match)
            <div class="keep" style="margin-top: 5pt;">
                <p><strong>{{ $match->lenderProduct?->lender?->name }} — {{ $match->lenderProduct?->loanProduct?->name }}</strong>: {{ $match->match_status->getLabel() }} · {{ $match->advisor_status->getLabel() }}</p>
                @foreach ($match->reasons ?? [] as $reason)<p class="small {{ $reason['passed'] ? 'good' : 'bad' }}">{{ $reason['passed'] ? '✓' : '✗' }} {{ $reason['message'] }}</p>@endforeach
                @foreach ($match->missing_information ?? [] as $missing)<p class="small faint">? Missing: {{ $missing }}</p>@endforeach
            </div>
        @empty
            <p class="muted">Lender matching has not been run.</p>
        @endforelse
        @if ($profile)
            <h2>Profile</h2>
            <table class="meta">
                @if ($application->applicant_type->value === 'business')
                    <tr><td class="label">Constitution</td><td>{{ $profile->business_constitution?->getLabel() ?? '—' }}</td><td class="label">Vintage</td><td>{{ P::months($profile->business_vintage_months) }}</td></tr>
                    <tr><td class="label">Monthly revenue</td><td>{{ P::inr($profile->average_monthly_revenue) }}</td><td class="label">Operating expenses</td><td>{{ P::inr($profile->monthly_operating_expenses) }}</td></tr>
                @else
                    <tr><td class="label">Employment</td><td>{{ $profile->employment_type?->getLabel() ?? '—' }}</td><td class="label">Net monthly income</td><td>{{ P::inr($profile->net_monthly_income) }}</td></tr>
                    <tr><td class="label">Age</td><td>{{ $profile->ageInYears() ?? '—' }}</td><td class="label">Declared credit score</td><td>{{ $profile->declared_credit_score ?? '—' }} (not bureau-verified)</td></tr>
                @endif
            </table>
        @endif
    @endif

    @if ($show(T::LenderApplicationSummary))
        <h2>Applications facilitated to lenders</h2>
        @forelse ($application->lenderApplications as $lenderApplication)
            <div class="keep" style="margin-top: 6pt;">
                <p><strong>{{ $lenderApplication->label() }}</strong> · {{ $lenderApplication->status->getLabel() }}{{ $lenderApplication->lender_reference ? ' · ref. '.$lenderApplication->lender_reference : '' }}</p>
                <table class="meta">
                    <tr><td class="label">Applied amount</td><td>{{ P::inr($lenderApplication->applied_amount) }}</td><td class="label">Sanctioned</td><td>{{ P::inr($lenderApplication->sanctioned_amount) }}{{ $lenderApplication->sanctioned_rate ? ' at '.P::percent($lenderApplication->sanctioned_rate) : '' }}{{ $lenderApplication->sanctioned_tenure_months ? ' for '.P::months($lenderApplication->sanctioned_tenure_months) : '' }}</td></tr>
                    <tr><td class="label">Submitted</td><td>{{ $lenderApplication->submitted_at?->timezone('Asia/Kolkata')->format('d M Y') ?? '—' }}</td><td class="label">Disbursed</td><td>{{ P::inr($lenderApplication->disbursed_amount) }}{{ $lenderApplication->disbursed_at ? ' on '.$lenderApplication->disbursed_at->timezone('Asia/Kolkata')->format('d M Y') : '' }}</td></tr>
                </table>
                <table class="rows small"><tr><th>Date</th><th>Status</th><th>Note</th></tr>
                    @foreach ($lenderApplication->statusChanges->sortBy('id') as $change)
                        <tr><td>{{ $change->created_at?->timezone('Asia/Kolkata')->format('d M Y') }}</td><td>{{ \App\Modules\DebtConsolidation\Enums\LenderApplicationStatus::tryFrom($change->to_status)?->getLabel() }}</td><td>{{ $change->note }}</td></tr>
                    @endforeach
                </table>
            </div>
        @empty
            <p class="muted">No lender application has been made.</p>
        @endforelse
        <p class="note">Statuses record what each lender communicated. FynnEdge does not approve, sanction or disburse loans.</p>
    @endif

    @if ($show(T::ClosureReport))
        <h2>Closure of existing debts</h2>
        <table class="rows">
            <tr><th>Liability</th><th>Type</th><th class="num">Closure amount</th><th class="num">Confirmed paid</th><th>NOC received</th><th>Status</th><th>Verified</th></tr>
            @forelse ($application->closures as $closure)
                <tr class="keep">
                    <td>{{ $closure->liability?->lender_name }}{{ $closure->liability?->account_reference_masked ? ' ('.$closure->liability->account_reference_masked.')' : '' }}</td>
                    <td>{{ $closure->isFullClosure() ? 'Full' : 'Partial' }}</td>
                    <td class="num">{{ P::inr($closure->final_closure_amount, true) }}</td>
                    <td class="num">{{ P::inr($closure->payments->whereNotNull('confirmed_at')->sum('amount'), true) }}</td>
                    <td>{{ $closure->noc_received_at?->timezone('Asia/Kolkata')->format('d M Y') ?? ($closure->isFullClosure() ? '—' : 'n/a') }}</td>
                    <td>{{ $closure->status->getLabel() }}</td>
                    <td>{{ $closure->verified_at?->timezone('Asia/Kolkata')->format('d M Y') ?? '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="muted">No closures are being tracked.</td></tr>
            @endforelse
        </table>
        @php $closures = $application->closures; $verified = $closures->where('status', \App\Modules\DebtConsolidation\Enums\ClosureStatus::ClosureVerified)->count(); @endphp
        <p class="note">{{ $verified }} of {{ $closures->count() }} tracked liabilities have a verified closure (confirmed payment{{ $closures->contains(fn ($closure) => $closure->isFullClosure()) ? ' and a verified NOC / closure letter for full closures' : '' }}). Closure is confirmed by the lender concerned; check that your credit report shows each closed loan as closed.</p>
    @endif

    @if ($snapshot)
        <h2>Calculation assumptions</h2>
        <p class="small muted">Formula version {{ $snapshot->formula_version }}; scoring configuration {{ $snapshot->scoring_version ?? '—' }}; as of {{ $inputs['assumptions']['as_of'] ?? '—' }}; GST on charges {{ $inputs['assumptions']['gst_rate_percent'] ?? '—' }}%; card minimum payment for FOIR {{ $inputs['assumptions']['revolving_minimum_payment_percent'] ?? '—' }}% of balance; reducing-balance interest rounded half-up to the paisa every month. Figures entered by the customer are unverified unless stated. {{ $calculatorDisclaimer }}</p>
    @endif
    <p class="note">{{ $disclaimer }}</p>
</body>
</html>
