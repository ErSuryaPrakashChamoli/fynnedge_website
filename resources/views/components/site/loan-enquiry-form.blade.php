@props([
    'loanProduct' => null,
    'loanProducts' => null,
    'selected' => null,
    'ctaLabel' => 'Submit Enquiry',
    'eyebrow' => null,
    // Shown when there is no loan ceiling to quote, e.g. before a loan type is picked.
    'headline' => 'Get the funds you need',
])

@php
    /*
        The reusable loan enquiry form, in one of two modes.

        On a page that has a LoanProduct in scope:

            <x-site.loan-enquiry-form :loan-product="$loanProduct" />

        Nothing about the product is hardcoded and nothing about it is
        submitted: the form posts to that product's own route, and the server
        resolves the product from the URL segment. There is no loan_type input
        to tamper with, which is what makes the reporting trustworthy.

        On the Quick Enquiry page, with a loan-type dropdown instead:

            <x-site.loan-enquiry-form :loan-products="$loanProducts" :selected="$slug" />

        The chosen slug posts to quick-enquiry.apply, which only accepts a
        currently published product. The headline, the amount range and its
        validation all follow the dropdown, from the same per-product figures the
        loan pages render — so a Home Loan never quotes a Personal Loan range.

        Behaviour lives in the `loanEnquiryForm` Alpine component in
        resources/js/app.js. It is a real <form method="POST"> underneath, so
        with JavaScript off it submits normally and the controller answers with
        a redirect and a flash message instead of JSON. The headline's inline
        `display:none` styles are what keep that path correct: x-show toggles
        exactly that inline style once Alpine boots.
    */
    use App\Support\Enquiries\LoanEnquiryAmount;
    use App\Support\Formatting\IndianNumberFormatter;

    $selectable = $loanProducts !== null;

    /*
        Rate and ceiling come from the product row, so an admin editing the
        product updates this headline without anyone touching Blade.
    */
    $productOptions = collect($selectable ? $loanProducts : [$loanProduct])
        ->mapWithKeys(function ($product): array {
            $range = LoanEnquiryAmount::rangeFor($product);

            return [$product->slug => [
                'name' => $product->name,
                'amount' => $product->max_amount ? '₹'.IndianNumberFormatter::compact($product->max_amount) : null,
                'rate' => $product->min_interest_rate
                    ? rtrim(rtrim(number_format((float) $product->min_interest_rate, 2), '0'), '.').'%'
                    : null,
                'min' => $range['min'],
                'max' => $range['max'],
                'rangeHint' => '₹'.IndianNumberFormatter::format($range['min']).' – ₹'.IndianNumberFormatter::format($range['max']),
                'rangeMessage' => LoanEnquiryAmount::rangeMessage($range),
            ]];
        })
        ->all();

    // old() first, so a no-JavaScript validation bounce keeps the visitor's choice.
    $requestedSlug = $selectable ? old('loan_product', $selected) : $loanProduct->slug;
    $selectedSlug = is_string($requestedSlug) && array_key_exists($requestedSlug, $productOptions) ? $requestedSlug : null;
    $current = $selectedSlug ? $productOptions[$selectedSlug] : null;

    $noProductHint = 'Select a loan type to see the amount range';
    $eyebrow = $eyebrow ?: 'Instant '.$loanProduct?->name;
    $fieldId = $selectable ? 'quick-enquiry-page' : 'loan-enquiry-'.$loanProduct->slug;
    $action = $selectable ? route('quick-enquiry.apply') : route('loans.enquiry.store', $loanProduct);
    $status = session('quickEnquiryStatus');
    $privacyUrl = \Illuminate\Support\Facades\Route::has('privacy-policy') ? route('privacy-policy') : null;
    $inputClasses = 'w-full bg-transparent px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none';
    $groupClasses = 'flex items-stretch overflow-hidden rounded-lg border bg-surface transition-colors focus-within:ring-2';
    $inputTone = 'border-line-strong focus:border-accent focus:ring-accent/30';
    $inputToneError = 'border-warn focus:border-warn focus:ring-warn/30';
@endphp

<div
    {{ $attributes->class('rounded-2xl border border-line bg-surface p-5 shadow-xl shadow-ink/5 sm:p-6') }}
    x-data="loanEnquiryForm({
        endpoint: @js($action),
        products: @js($productOptions),
        selected: @js($selectedSlug),
        selectable: @js($selectable),
    })"
>
    <div x-show="!done">
        <div class="flex items-center gap-3" aria-hidden="true">
            <span class="h-px flex-1 bg-line"></span>
            <span class="font-mono text-[0.65rem] font-semibold uppercase tracking-[0.16em] text-ink-faint">{{ $eyebrow }}</span>
            <span class="h-px flex-1 bg-line"></span>
        </div>

        <p class="mt-3 text-center font-display text-lg font-semibold leading-snug text-ink sm:text-xl" aria-live="polite">
            <span x-show="!selected?.amount" @style(['display:none' => $current['amount'] ?? null])>{{ $headline }}</span>
            <span x-show="selected?.amount" @style(['display:none' => ! ($current['amount'] ?? null)])>
                Get up to <span class="text-accent" x-text="selected?.amount">{{ $current['amount'] ?? '' }}</span>
            </span>
            <span x-show="selected?.rate" @style(['display:none' => ! ($current['rate'] ?? null)])>
                <br class="hidden sm:block">starting at <span class="text-accent" x-text="selected?.rate">{{ $current['rate'] ?? '' }}</span>
            </span>
        </p>

        @if ($status)
            <x-ui.alert class="mt-4" tone="pass" :title="session('quickEnquiryTitle')">{{ $status }}</x-ui.alert>
        @endif

        <form
            method="POST"
            action="{{ $action }}"
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

            @if ($selectable)
                <div>
                    <label for="{{ $fieldId }}-product" class="sr-only">Loan type</label>
                    <div
                        class="relative {{ $groupClasses }} {{ $errors->has('loan_product') ? $inputToneError : $inputTone }}"
                        :class="errors.loan_product ? @js($inputToneError) : @js($inputTone)"
                    >
                        <x-ui.input-affix>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-5 w-5" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0 1 15.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 0 1 3 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 0 0-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 0 1-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 0 0 3 15h-.75M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm3 0h.008v.008H18V10.5Zm-12 0h.008v.008H6V10.5Z" />
                            </svg>
                        </x-ui.input-affix>
                        {{-- `invalid:` greys the text while the placeholder option is
                             chosen: a required select with an empty value matches
                             :invalid, with or without JavaScript. --}}
                        <select
                            id="{{ $fieldId }}-product"
                            name="loan_product"
                            required
                            aria-describedby="{{ $fieldId }}-product-error"
                            class="{{ $inputClasses }} cursor-pointer appearance-none pr-10 invalid:text-ink-faint"
                            x-model="values.loan_product"
                            @change="onProductChange"
                            :aria-invalid="errors.loan_product ? 'true' : 'false'"
                        >
                            <option value="" @selected(! $selectedSlug) disabled>Select Loan Type</option>
                            @foreach ($productOptions as $slug => $option)
                                <option value="{{ $slug }}" @selected($slug === $selectedSlug) class="text-ink">
                                    {{ $option['name'] }}{{ $option['rate'] ? ' · from '.$option['rate'] : '' }}
                                </option>
                            @endforeach
                        </select>
                        <svg viewBox="0 0 20 20" fill="currentColor" class="pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-faint" aria-hidden="true">
                            <path fill-rule="evenodd" d="M5.22 8.22a.75.75 0 0 1 1.06 0L10 11.94l3.72-3.72a.75.75 0 1 1 1.06 1.06l-4.25 4.25a.75.75 0 0 1-1.06 0L5.22 9.28a.75.75 0 0 1 0-1.06Z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <x-site.field-error :id="$fieldId.'-product'" field="loan_product" :server="$errors->first('loan_product')" />
                </div>
            @endif

            <div>
                <label for="{{ $fieldId }}-name" class="sr-only">Full name</label>
                <div
                    class="{{ $groupClasses }} {{ $errors->has('name') ? $inputToneError : $inputTone }}"
                    :class="errors.name ? @js($inputToneError) : @js($inputTone)"
                >
                    <x-ui.input-affix>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" class="h-5 w-5" aria-hidden="true">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.5 20.25a7.5 7.5 0 0 1 15 0" />
                        </svg>
                    </x-ui.input-affix>
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
                        class="{{ $inputClasses }}"
                        x-model="values.name"
                        @input="onInput"
                        :aria-invalid="errors.name ? 'true' : 'false'"
                    >
                </div>
                <x-site.field-error :id="$fieldId.'-name'" field="name" :server="$errors->first('name')" />
            </div>

            <div>
                <label for="{{ $fieldId }}-phone" class="sr-only">Mobile number</label>
                <div
                    class="{{ $groupClasses }} {{ $errors->has('phone') ? $inputToneError : $inputTone }}"
                    :class="errors.phone ? @js($inputToneError) : @js($inputTone)"
                >
                    <x-ui.input-affix>+91</x-ui.input-affix>
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
                        class="{{ $inputClasses }}"
                        x-model="values.phone"
                        @input="onPhoneInput"
                        :aria-invalid="errors.phone ? 'true' : 'false'"
                    >
                </div>
                <x-site.field-error :id="$fieldId.'-phone'" field="phone" :server="$errors->first('phone')" />
            </div>

            <div>
                <label for="{{ $fieldId }}-email" class="sr-only">Email address</label>
                <div
                    class="{{ $groupClasses }} {{ $errors->has('email') ? $inputToneError : $inputTone }}"
                    :class="errors.email ? @js($inputToneError) : @js($inputTone)"
                >
                    <x-ui.input-affix><span class="text-base" aria-hidden="true">&#64;</span></x-ui.input-affix>
                    <input
                        type="email"
                        id="{{ $fieldId }}-email"
                        name="email"
                        autocomplete="email"
                        maxlength="190"
                        placeholder="Email Address (optional)"
                        value="{{ old('email') }}"
                        aria-describedby="{{ $fieldId }}-email-error"
                        class="{{ $inputClasses }}"
                        x-model="values.email"
                        @input="onInput"
                        :aria-invalid="errors.email ? 'true' : 'false'"
                    >
                </div>
                <x-site.field-error :id="$fieldId.'-email'" field="email" :server="$errors->first('email')" />
            </div>

            <div>
                <label for="{{ $fieldId }}-amount" class="sr-only">Loan amount required</label>
                <div
                    class="{{ $groupClasses }} {{ $errors->has('loan_amount') ? $inputToneError : $inputTone }}"
                    :class="errors.loan_amount ? @js($inputToneError) : @js($inputTone)"
                >
                    <x-ui.input-affix>₹</x-ui.input-affix>
                    <input
                        type="text"
                        id="{{ $fieldId }}-amount"
                        name="loan_amount"
                        inputmode="numeric"
                        placeholder="Loan Amount Required"
                        required
                        value="{{ old('loan_amount') }}"
                        aria-describedby="{{ $fieldId }}-amount-error {{ $fieldId }}-amount-hint"
                        class="{{ $inputClasses }}"
                        x-model="values.loan_amount"
                        @input="onAmountInput"
                        :aria-invalid="errors.loan_amount ? 'true' : 'false'"
                    >
                </div>
                <x-site.field-error :id="$fieldId.'-amount'" field="loan_amount" :server="$errors->first('loan_amount')" />
                <p
                    id="{{ $fieldId }}-amount-hint"
                    class="mt-1 text-xs text-ink-faint"
                    x-text="selected ? selected.rangeHint : @js($noProductHint)"
                >{{ $current['rangeHint'] ?? $noProductHint }}</p>
            </div>

            <x-ui.button type="submit" class="mt-1 w-full py-3" ::disabled="loading">
                <span x-text="loading ? 'Submitting...' : @js($ctaLabel)">{{ $ctaLabel }}</span>
                <span aria-hidden="true" x-show="!loading">&rarr;</span>
            </x-ui.button>
        </form>

        <p class="mt-2.5 text-[0.7rem] leading-relaxed text-ink-faint">
            By submitting this form you agree to be contacted by {{ \App\Models\Setting::get('site_name', 'FynnEdge') }}
            about your <span x-text="selected ? selected.name : 'loan'">{{ $current['name'] ?? 'loan' }}</span> enquiry.
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
