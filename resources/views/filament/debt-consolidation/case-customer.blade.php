@php
    use App\Modules\DebtConsolidation\Services\DebtPresenter as P;
    $profile = $record->financialProfile;
    $business = $record->applicant_type->value === 'business';
    $rows = [
        'Name' => $record->full_name,
        'Mobile' => '+91 '.$record->mobile.($record->mobile_verified_at ? ' (OTP-verified '.$record->mobile_verified_at->timezone('Asia/Kolkata')->format('j M Y').')' : ' (not verified)'),
        'Email' => $record->email,
        'City / PIN' => trim($record->city.' '.$record->pincode),
        'Portal account' => $record->customer ? 'Yes — '.$record->customer->public_id : 'Not linked (the customer has not verified this number on the website yet)',
    ];
    $finance = $business ? [
        'Business name' => $profile?->business_name,
        'Constitution' => $profile?->business_constitution?->getLabel(),
        'Industry' => $profile?->industry,
        'In business for' => P::months($profile?->business_vintage_months),
        'GST registered' => $profile?->gst_registered === null ? null : ($profile->gst_registered ? 'Yes' : 'No'),
        'Average monthly revenue' => P::inr($profile?->average_monthly_revenue),
        'Monthly operating expenses' => P::inr($profile?->monthly_operating_expenses),
        'Annual turnover' => P::inr($profile?->annual_turnover),
        'Annual net profit' => P::inr($profile?->annual_net_profit),
        'Other monthly commitments' => P::inr($profile?->other_monthly_obligations),
    ] : [
        'Employment' => $profile?->employment_type?->getLabel(),
        'Employer' => $profile?->employer_name,
        'Age' => $profile?->ageInYears(),
        'Home' => $profile?->residence_type,
        'Net monthly income' => P::inr($profile?->net_monthly_income),
        'Other monthly income' => P::inr($profile?->other_monthly_income),
        'Living expenses' => P::inr($profile?->monthly_living_expenses),
        'Other monthly commitments' => P::inr($profile?->other_monthly_obligations),
        'Declared credit score' => $profile?->declared_credit_score ? $profile->declared_credit_score.' (self-declared)' : null,
    ];
@endphp
<div class="grid gap-6 lg:grid-cols-2">
    <x-filament::section heading="Contact">
        <dl class="grid grid-cols-3 gap-x-4 gap-y-2 text-sm">
            @foreach ($rows as $label => $value)
                <dt class="text-gray-500">{{ $label }}</dt><dd class="col-span-2 text-gray-950 dark:text-white">{{ filled($value) ? $value : '—' }}</dd>
            @endforeach
        </dl>
    </x-filament::section>
    <x-filament::section :heading="$business ? 'Business and cash flow' : 'Employment and income'">
        <dl class="grid grid-cols-3 gap-x-4 gap-y-2 text-sm">
            @foreach ($finance as $label => $value)
                <dt class="text-gray-500">{{ $label }}</dt><dd class="col-span-2 text-gray-950 dark:text-white">{{ filled($value) && $value !== '—' ? $value : '—' }}</dd>
            @endforeach
        </dl>
        <p class="mt-3 text-xs text-gray-500">Entered by the customer (or an advisor) and not verified unless a document says otherwise. Correct figures in the Existing liabilities tab or ask the customer to update them; every change is in the audit history.</p>
    </x-filament::section>
</div>
