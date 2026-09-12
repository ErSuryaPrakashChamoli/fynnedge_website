@props([
    'source' => 'website',
    'heading' => 'Get Started with a Quick Enquiry',
    'subheading' => 'Enter your mobile number and our team will get in touch with you.',
    'ctaLabel' => 'Submit Enquiry',
])

@php
    /*
        One field, one button, one request. Alpine intercepts the submit and
        posts JSON to quick-enquiry.store, swapping the form for a confirmation
        in place — a page reload here would cost the visitor the few seconds
        this form exists to save.

        It is still a real <form method="POST"> underneath, so with JavaScript
        off (or not yet booted) it submits normally and the controller answers
        with a redirect and a flash message instead of JSON. The AJAX path is
        the enhancement, not the only path.

        Built from the site's own tokens and x-ui.* components only, so it
        inherits both themes and the Settings appearance overrides like every
        other section. Behaviour lives in the `quickEnquiryForm` Alpine
        component in resources/js/app.js.
    */
    $fieldId = 'quick-enquiry-phone-'.$source;
    $serverError = $errors->first('phone');
    $serverStatus = session('quickEnquiryStatus');
@endphp

<div
    {{ $attributes->class('rounded-2xl border border-line bg-surface p-6 sm:p-8') }}
    {{-- `phone` seeds the field from old() so a no-JavaScript validation bounce
         does not have its number wiped the moment Alpine boots and binds x-model. --}}
    x-data="quickEnquiryForm({ endpoint: @js(route('quick-enquiry.store')), source: @js($source), phone: @js(old('phone', '')) })"
>
    <div x-show="!done">
        <p class="font-display text-xl font-semibold text-ink sm:text-2xl">{{ $heading }}</p>
        <p class="mt-2 text-sm text-ink-muted sm:text-base">{{ $subheading }}</p>

        @if ($serverStatus)
            <x-ui.alert class="mt-4" tone="pass" :title="session('quickEnquiryTitle')">{{ $serverStatus }}</x-ui.alert>
        @endif

        <form
            method="POST"
            action="{{ route('quick-enquiry.store') }}"
            novalidate
            @submit.prevent="submit"
            class="mt-5 flex flex-col gap-3 sm:flex-row sm:items-start"
        >
            @csrf
            <input type="hidden" name="source" value="{{ $source }}">

            {{-- Honeypot: hidden from people, irresistible to bots. Never remove without replacing. --}}
            <div class="hidden" aria-hidden="true">
                <label for="quick-enquiry-website-{{ $source }}">Website</label>
                <input type="text" name="website" id="quick-enquiry-website-{{ $source }}" x-ref="honeypot" tabindex="-1" autocomplete="off">
            </div>

            <div class="flex-1">
                <label for="{{ $fieldId }}" class="sr-only">Mobile number</label>
                <div
                    @class([
                        'flex items-stretch overflow-hidden rounded-lg border bg-surface transition-colors focus-within:ring-2',
                        'border-line-strong focus-within:border-accent focus-within:ring-accent/30' => ! $serverError,
                        'border-warn focus-within:ring-warn/30' => $serverError,
                    ])
                    :class="error ? 'border-warn focus-within:ring-warn/30' : 'border-line-strong focus-within:border-accent focus-within:ring-accent/30'"
                >
                    <span class="flex select-none items-center border-r border-line px-3 text-sm text-ink-muted">+91</span>
                    <input
                        type="tel"
                        id="{{ $fieldId }}"
                        name="phone"
                        inputmode="numeric"
                        autocomplete="tel-national"
                        maxlength="10"
                        placeholder="10-digit mobile number"
                        required
                        value="{{ old('phone') }}"
                        aria-describedby="{{ $fieldId }}-error"
                        @if ($serverError) aria-invalid="true" @endif
                        class="w-full bg-transparent px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none"
                        x-model="phone"
                        @input="onInput"
                        :aria-invalid="error ? 'true' : 'false'"
                    >
                </div>

                {{-- Server-rendered message for the no-JavaScript path; Alpine's own
                     inline message below replaces it once the component is live. --}}
                @if ($serverError)
                    <p class="mt-1 text-xs text-warn" x-show="!error" role="alert">{{ $serverError }}</p>
                @endif

                <p
                    id="{{ $fieldId }}-error"
                    role="alert"
                    class="mt-1 text-xs text-warn"
                    x-show="error"
                    x-text="error"
                    x-cloak
                ></p>
            </div>

            <x-ui.button type="submit" class="shrink-0" ::disabled="loading">
                <span x-text="loading ? 'Submitting...' : @js($ctaLabel)">{{ $ctaLabel }}</span>
            </x-ui.button>
        </form>

        <p class="mt-3 text-xs text-ink-faint">
            We only need your number to call you back. No forms and no documents at this stage.
        </p>
    </div>

    <div x-show="done" x-cloak>
        <x-ui.alert tone="pass">
            <p class="font-semibold" x-text="resultTitle"></p>
            <p class="mt-1 text-ink-muted" x-text="resultMessage"></p>
        </x-ui.alert>
    </div>
</div>
