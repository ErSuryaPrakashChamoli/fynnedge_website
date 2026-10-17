{{--
    The customer's downloadable copy of a saved report (CreditReportController::download),
    rendered by dompdf: plain HTML tables and CSS 2.1 only — no Tailwind, no flex or grid,
    nothing remote. Always rendered in English; dompdf cannot join Devanagari letters.
--}}
@php
    $bureau = $report->sourceBureau()->getLabel();
    $band = $check->score !== null ? \App\Modules\CreditScore\Enums\ScoreBand::fromScore($check->score) : null;
    $bandColour = match ($band) {
        \App\Modules\CreditScore\Enums\ScoreBand::Poor => '#c2412d',
        \App\Modules\CreditScore\Enums\ScoreBand::Average => '#b26b00',
        \App\Modules\CreditScore\Enums\ScoreBand::Good => '#4f8a1c',
        \App\Modules\CreditScore\Enums\ScoreBand::Excellent => '#1f7a4d',
        default => '#4c5a70',
    };
    $personal = $report->personalInfo();
    $identities = $report->identities();
    $addresses = $report->addresses();
    $accountsSummary = $report->accountsSummary();
    $accounts = $report->accounts();
    $enquirySummary = $report->enquirySummary();
    $enquiries = $report->enquiries();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ __(':bureau credit report', ['bureau' => $bureau]) }}</title>
    <style>
        @page { margin: 22mm 16mm 20mm; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9.5pt; color: #101828; line-height: 1.45; }
        h1, h2, p { margin: 0; }
        .masthead { position: fixed; top: -14mm; left: 0; right: 0; height: 9mm; border-bottom: 1.5pt solid #163a63; }
        .masthead td { vertical-align: bottom; padding-bottom: 2mm; }
        .brand { font-size: 13pt; font-weight: bold; color: #163a63; }
        .muted { color: #4c5a70; }
        .faint { color: #838fa1; }
        .footer { position: fixed; bottom: -12mm; left: 0; right: 0; height: 8mm; font-size: 7.5pt; color: #838fa1; border-top: 0.5pt solid #e2e5ec; padding-top: 1.5mm; }
        .page-number:after { content: counter(page); }
        table { width: 100%; border-collapse: collapse; }
        .summary { background: #eef1f6; border-radius: 6pt; }
        .summary td { padding: 10pt 12pt; vertical-align: top; }
        .score { font-size: 34pt; font-weight: bold; line-height: 1; }
        .band { display: inline-block; margin-top: 4pt; padding: 2pt 8pt; border-radius: 8pt; color: #ffffff; font-size: 8.5pt; font-weight: bold; }
        .facts td { padding: 2.5pt 0; }
        .facts td.label { width: 38%; color: #838fa1; }
        .note { margin-top: 10pt; padding: 7pt 10pt; border-left: 2.5pt solid #163a63; background: #e1e8f4; color: #0d2340; font-size: 8.5pt; }
        h2 { margin-top: 18pt; padding-bottom: 4pt; border-bottom: 0.75pt solid #c7cdd9; font-size: 11.5pt; color: #163a63; }
        .rows td { padding: 3.5pt 6pt 3.5pt 0; border-bottom: 0.5pt solid #e2e5ec; vertical-align: top; }
        .rows td.label { width: 34%; color: #4c5a70; }
        .account { margin-top: 10pt; page-break-inside: avoid; }
        .account-title { padding: 5pt 8pt; background: #eef1f6; font-weight: bold; }
        .account-title span { font-weight: normal; color: #4c5a70; }
        .history { margin-top: 5pt; }
        .history td { width: 8.33%; padding: 2pt 1pt; text-align: center; font-size: 6.5pt; border: 0.5pt solid #ffffff; }
        .history .ok { background: #e1f2e7; color: #1f7a4d; }
        .history .late { background: #f7ecd4; color: #b3791f; font-weight: bold; }
        .small { font-size: 8pt; }
    </style>
</head>
<body>
    <table class="masthead">
        <tr>
            <td class="brand">{{ config('app.name') }}</td>
            <td class="muted" style="text-align: right;">{{ __(':bureau credit report', ['bureau' => $bureau]) }}</td>
        </tr>
    </table>

    <div class="footer">
        <table>
            <tr>
                <td>{{ __('Prepared by :app from the :bureau report saved in your profile. A copy for your records, not the bureau\'s own document. Downloaded on :date.', ['app' => config('app.name'), 'bureau' => $bureau, 'date' => now()->translatedFormat('d M Y')]) }}</td>
                <td style="width: 14%; text-align: right;">{{ __('Page') }} <span class="page-number"></span></td>
            </tr>
        </table>
    </div>

    <table class="summary">
        <tr>
            <td style="width: 34%;">
                <p class="faint small">{{ __(':bureau score', ['bureau' => $bureau]) }}</p>
                @if ($check->score !== null)
                    <p class="score" style="color: {{ $bandColour }};">{{ $check->score }}</p>
                    <span class="band" style="background: {{ $bandColour }};">{{ $band->getLabel() }}</span>
                    <p class="faint small" style="margin-top: 6pt;">{{ __(':bureau scores range from 300 to 900.', ['bureau' => $bureau]) }}</p>
                @else
                    <p class="score muted">—</p>
                    <p class="muted small" style="margin-top: 6pt;">{{ __('No score yet: the bureau holds too little credit history to score.') }}</p>
                @endif
            </td>
            <td>
                <table class="facts">
                    <tr><td class="label">{{ __('Name checked') }}</td><td>{{ $check->full_name }}</td></tr>
                    <tr><td class="label">{{ __('PAN') }}</td><td>{{ \App\Modules\CreditBureau\Services\PanNumber::mask((string) $check->pan_number) }}</td></tr>
                    <tr><td class="label">{{ __('Report date') }}</td><td>{{ $report->reportDate()?->translatedFormat('d M Y') ?? '—' }}</td></tr>
                    <tr><td class="label">{{ __('Requested on') }}</td><td>{{ $check->requested_at?->translatedFormat('d M Y') ?? '—' }}</td></tr>
                    <tr><td class="label">{{ __('Bureau') }}</td><td>{{ $bureau }}</td></tr>
                </table>
            </td>
        </tr>
    </table>

    @if ($report->isStandIn())
        <p class="note">{{ __('This report is from :source, shown on the :bureau page because :bureau was not connected when you checked. Scores can differ a little between bureaus.', ['source' => $bureau, 'bureau' => $check->bureau->getLabel()]) }}</p>
    @endif

    @if (! $report->hasFullReport())
        <p class="note">{{ __('Only the score from this check was saved — the detailed report is not available to view.') }}</p>
    @else
        <p class="faint small" style="margin-top: 10pt;">{{ __('The details below are as reported by :bureau. They may differ from your profile with us; if something here is wrong, it has to be corrected with the bureau or the lender that reported it.', ['bureau' => $bureau]) }}</p>

        @if ($personal !== [] || $identities !== [] || $addresses !== [])
            <h2>{{ __('Personal information reported') }}</h2>
            <table class="rows">
                @foreach ($personal as $label => $value)
                    <tr><td class="label">{{ __($label) }}</td><td>{{ $value }}</td></tr>
                @endforeach
                @foreach ($identities as $type => $numbers)
                    <tr><td class="label">{{ __($type) }}</td><td>{{ implode(', ', $numbers) }}</td></tr>
                @endforeach
                @foreach ($addresses as $address)
                    <tr><td class="label">{{ $loop->first ? __('Addresses') : '' }}</td><td>{{ $address }}</td></tr>
                @endforeach
            </table>
        @endif

        @if ($accountsSummary !== [])
            <h2>{{ __('Account summary') }}</h2>
            <table class="rows">
                @foreach ($accountsSummary as $label => $value)
                    <tr><td class="label">{{ __($label) }}</td><td>{{ $value }}</td></tr>
                @endforeach
            </table>
        @endif

        <h2>{{ __('Credit accounts') }} <span class="faint" style="font-size: 9pt; font-weight: normal;">({{ count($accounts) }})</span></h2>
        @forelse ($accounts as $account)
            <div class="account">
                <p class="account-title">
                    {{ $account['fields']['Lender'] ?? $account['fields']['Member name'] ?? $account['fields']['Account type'] ?? __('Account :number', ['number' => $loop->iteration]) }}
                    <span>{{ $account['fields']['Account type'] ?? '' }}{{ isset($account['fields']['Open / closed']) ? ' · '.__($account['fields']['Open / closed']) : '' }}</span>
                </p>
                <table class="rows">
                    @foreach ($account['fields'] as $label => $value)
                        <tr><td class="label">{{ __($label) }}</td><td>{{ $value }}</td></tr>
                    @endforeach
                </table>
                @if ($account['history'] !== [])
                    <p class="muted small" style="margin-top: 5pt;">{{ __('Payment history') }}</p>
                    <table class="history">
                        @foreach (array_chunk($account['history'], 12) as $months)
                            <tr>
                                @foreach ($months as $month)
                                    <td class="{{ $month['late'] ? 'late' : 'ok' }}">{{ $month['month'] }}<br>{{ $month['status'] }}</td>
                                @endforeach
                                @for ($blank = count($months); $blank < 12; $blank++)
                                    <td></td>
                                @endfor
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>
        @empty
            <p class="muted" style="margin-top: 6pt;">{{ __('The bureau reported no credit accounts.') }}</p>
        @endforelse

        <h2>{{ __('Recent credit enquiries') }}</h2>
        @if ($enquirySummary !== [])
            <table class="rows">
                @foreach ($enquirySummary as $label => $value)
                    <tr><td class="label">{{ __($label) }}</td><td>{{ $value }}</td></tr>
                @endforeach
            </table>
        @endif
        @forelse ($enquiries as $enquiry)
            <table class="rows" style="margin-top: 6pt;">
                @foreach ($enquiry as $label => $value)
                    <tr><td class="label">{{ __($label) }}</td><td>{{ $value }}</td></tr>
                @endforeach
            </table>
        @empty
            <p class="muted" style="margin-top: 6pt;">{{ __('The bureau reported no recent enquiries.') }}</p>
        @endforelse
    @endif
</body>
</html>
