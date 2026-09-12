@props([
    'loanProduct',
    'ctaLabel' => 'Submit Enquiry',
    'eyebrow' => null,
])

@php
    /*
        The reusable loan enquiry form. Drop it on any page that has a
        LoanProduct in scope:

            <x-site.loan-enquiry-form :loan-product="$loanProduct" />

        Nothing about the product is hardcoded and nothing about it is
        submitted: the form posts to that product's own route, and the server
        resolves the product from the URL segment. There is no loan_type input
        to tamper with, which is what makes the reporting trustworthy.

        Behaviour lives in the `loanEnquiryForm` Alpine component in
        resources/js/app.js. It is a real <form method="POST"> underneath, so
        with JavaScript off it submits normally and the controller answers with
        a redirect and a flash message instead of JSON.
    */
    use App\Support\Enquiries\LoanEnquiryAmount;
    use App\Support\Formatting\IndianNumberFormatter;

    $range = LoanEnquiryAmount::rangeFor($loanProduct);
    $eyebrow = $eyebrow ?: 'Instant '.$loanProduct->name;
    $fieldId = 'loan-enquiry-'.$loanProduct->slug;
    $status = session('quickEnquiryStatus');
    $privacyUrl = \Illuminate\Support\Facades\Route::has('privacy-policy') ? route('privacy-policy') : null;
    $inputClasses = 'w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint transition-colors focus:outline-none focus:ring-2';
    $inputTone = 'border-line-strong focus:border-accent focus:ring-accent/30';
    $inputToneError = 'border-warn focus:border-warn focus:ring-warn/30';
@endphp

<div
    {{ $attributes->class('rounded-2xl border border-line bg-surface p-5 shadow-xl shadow-ink/5 sm:p-6') }}
    x-data="loanEnquiryForm({
        endpoint: @js(route('loans.enquiry.store', $loanProduct)),
        product: @js(str_replace('-', '_', $loanProduct->slug)),
        productName: @js($loanProduct->name),
    })"
>
    <div x-show="!done">
        {{-- Rate and ceiling come from the product row, so an admin editing the
             product updates this headline without anyone touching Blade. --}}
        <div class="flex items-center gap-3" aria-hidden="true">
            <span class="h-px flex-1 bg-line"></span>
            <span class="font-mono text-[0.65rem] font-semibold uppercase tracking-[0.16em] text-ink-faint">{{ $eyebrow }}</span>
            <span class="h-px flex-1 bg-line"></span>
        </div>

        <p class="mt-3 text-center font-display text-lg font-semibold leading-snug text-ink sm:text-xl">
            @if ($loanProduct->max_amount)
                Get up to <span class="text-accent">₹{{ IndianNumberFormatter::compact($loanProduct->max_amount) }}</span>
            @else
                Get the funds you need
            @endif
            @if ($loanProduct->min_interest_rate)
                <br class="hidden sm:block">starting at <span class="text-accent">{{ rtrim(rtrim(number_format((float) $loanProduct->min_interest_rate, 2), '0'), '.') }}%</span>
            @endif
        </p>

        @if ($status)
            <x-ui.alert class="mt-4" tone="pass" :title="session('quickEnquiryTitle')">{{ $status }}</x-ui.alert>
        @endif

        <form
            method="POST"
            action="{{ route('loans.enquiry.store', $loanProduct) }}"
            novalidate
            @submit.prevent="submit"
            class="mt-4 flex flex-col gap-2.5"
        >
            @csrf

            {{-- Honeypot: hidden from people, irresistible to bots. Never remove without replacing. --}}
            <div class="hidden" aria-hidden="true">
                <label for="{{ $fieldId }}-website">Website</label>
                <input type="text" name="website" id="{{ $fieldId }}-website" x-ref="honeypot" tabindex="-1" autocomplete="off">
            </div>

            <div>
                <label for="{{ $fieldId }}-name" class="sr-only">Full name</label>
                <input
                    type="text"
                    id="{{ $fieldId }}-name"
                    name="name"
                    autocomplete="name"
                    maxlength="120"
                    placeholder="Full Name"
                    required
                    value="{{ old('name') }}"
                    aria-describedby="{{ $fieldId }}-name-error"
                    class="{{ $inputClasses }} {{ $errors->has('name') ? $inputToneError : $inputTone }}"
                    x-model="values.name"
                    @input="onInput"
                    :class="errors.name ? @js($inputToneError) : @js($inputTone)"
                    :aria-invalid="errors.name ? 'true' : 'false'"
                >
                <x-site.field-error :id="$fieldId.'-name'" field="name" :server="$errors->first('name')" />
            </div>

            <div>
                <label for="{{ $fieldId }}-phone" class="sr-only">Mobile number</label>
                <div
                    class="flex items-stretch overflow-hidden rounded-lg border bg-surface transition-colors focus-within:ring-2 {{ $errors->has('phone') ? $inputToneError : $inputTone }}"
                    :class="errors.phone ? @js($inputToneError) : @js($inputTone)"
                >
                    <span class="flex select-none items-center border-r border-line px-3 text-sm text-ink-muted">+91</span>
                    <input
                        type="tel"
                        id="{{ $fieldId }}-phone"
                        name="phone"
                        inputmode="numeric"
                        autocomplete="tel-national"
                        maxlength="10"
                        placeholder="Mobile Number"
                        required
                        value="{{ old('phone') }}"
                        aria-describedby="{{ $fieldId }}-phone-error"
                        class="w-full bg-transparent px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none"
                        x-model="values.phone"
                        @input="onPhoneInput"
                        :aria-invalid="errors.phone ? 'true' : 'false'"
                    >
                </div>
                <x-site.field-error :id="$fieldId.'-phone'" field="phone" :server="$errors->first('phone')" />
            </div>

            <div>
                <label for="{{ $fieldId }}-email" class="sr-only">Email address</label>
                <input
                    type="email"
                    id="{{ $fieldId }}-email"
                    name="email"
                    autocomplete="email"
                    maxlength="190"
                    placeholder="Email Address (optional)"
                    value="{{ old('email') }}"
                    aria-describedby="{{ $fieldId }}-email-error"
                    class="{{ $inputClasses }} {{ $errors->has('email') ? $inputToneError : $inputTone }}"
                    x-model="values.email"
                    @input="onInput"
                    :class="errors.email ? @js($inputToneError) : @js($inputTone)"
                    :aria-invalid="errors.email ? 'true' : 'false'"
                >
                <x-site.field-error :id="$fieldId.'-email'" field="email" :server="$errors->first('email')" />
            </div>

            <div>
                <label for="{{ $fieldId }}-amount" class="sr-only">Loan amount required</label>
                <div
                    class="flex items-stretch overflow-hidden rounded-lg border bg-surface transition-colors focus-within:ring-2 {{ $errors->has('loan_amount') ? $inputToneError : $inputTone }}"
                    :class="errors.loan_amount ? @js($inputToneError) : @js($inputTone)"
                >
                    <span class="flex select-none items-center border-r border-line px-3 text-sm text-ink-muted">₹</span>
                    <input
                        type="text"
                        id="{{ $fieldId }}-amount"
                        name="loan_amount"
                        inputmode="numeric"
                        placeholder="Loan Amount Required"
                        required
                        value="{{ old('loan_amount') }}"
                        aria-describedby="{{ $fieldId }}-amount-error {{ $fieldId }}-amount-hint"
                        class="w-full bg-transparent px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none"
                        x-model="values.loan_amount"
                        @input="onAmountInput"
                        :aria-invalid="errors.loan_amount ? 'true' : 'false'"
                    >
                </div>
                <x-site.field-error :id="$fieldId.'-amount'" field="loan_amount" :server="$errors->first('loan_amount')" />
                <p id="{{ $fieldId }}-amount-hint" class="mt-1 text-xs text-ink-faint">
                    ₹{{ IndianNumberFormatter::format($range['min']) }} – ₹{{ IndianNumberFormatter::format($range['max']) }}
                </p>
            </div>

            <x-ui.button type="submit" class="mt-1 w-full py-3" ::disabled="loading">
                <span x-text="loading ? 'Submitting...' : @js($ctaLabel)">{{ $ctaLabel }}</span>
                <span aria-hidden="true" x-show="!loading">&rarr;</span>
            </x-ui.button>
        </form>

        <p class="mt-2.5 text-[0.7rem] leading-relaxed text-ink-faint">
            By submitting this form you agree to be contacted by {{ \App\Models\Setting::get('site_name', 'FynnEdge') }}
            about your {{ $loanProduct->name }} enquiry.
            @if ($privacyUrl)
                <a href="{{ $privacyUrl }}" class="underline transition-colors hover:text-ink">Privacy Policy</a>.
            @endif
        </p>
    </div>

    <div x-show="done" x-cloak class="py-6 text-center">
        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-pass-soft text-pass" aria-hidden="true">
            <svg class="h-6 w-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
            </svg>
        </div>
        <p class="mt-4 font-display text-xl font-semibold text-ink" x-text="resultTitle"></p>
        <p class="mx-auto mt-2 max-w-sm text-sm text-ink-muted" x-text="resultMessage"></p>
    </div>
</div>
