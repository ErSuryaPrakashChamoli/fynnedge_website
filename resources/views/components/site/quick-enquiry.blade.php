@props([
    'source' => 'website',
    'heading' => null,
    'subheading' => null,
    'ctaLabel' => null,
])

@php
    /*
        Two steps, one box: the number, then the 6-digit code sent to it. Alpine
        intercepts both submits and posts JSON to quick-enquiry.otp and
        quick-enquiry.store, swapping the panel in place — a page reload here
        would cost the visitor the few seconds this form exists to save.

        They are still two real <form method="POST"> elements underneath, so with
        JavaScript off (or not yet booted) each submits normally and the
        controller answers with a redirect and a flash message instead of JSON.
        Which step shows is then decided server-side, by the inline
        `display: none` below — x-show manipulates exactly that inline style, so
        Alpine takes the same switch over the moment it boots. The AJAX path is
        the enhancement, not the only path.

        Built from the site's own tokens and x-ui.* components only, so it
        inherits both themes and the Settings appearance overrides like every
        other section. Behaviour lives in the `quickEnquiryForm` Alpine
        component in resources/js/app.js.
    */
    use App\Support\Enquiries\EnquiryFormContent;

    /*
        Copy is admin-editable through Marketing Sections → "Homepage — Quick
        Enquiry box"; an explicit prop still wins, and with neither the built-in
        wording renders exactly as before.
    */
    $content = EnquiryFormContent::forQuickEnquiry();
    $heading = $heading ?: $content['heading'];
    $subheading = $subheading ?: $content['description'];
    $ctaLabel = $ctaLabel ?: $content['ctaLabel'];

    $fieldId = 'quick-enquiry-phone-'.$source;
    $otpFieldId = 'quick-enquiry-otp-'.$source;
    $serverError = $errors->first('phone');
    $serverOtpError = $errors->first('otp_code');
    $serverStatus = session('quickEnquiryStatus');

    /*
        A challenge id survives the no-JavaScript round trip two ways: flashed by
        requestOtp() when the code has just been sent, or as old input when the
        code was wrong and the verify step bounced back. Either way it is what
        keeps the visitor on step two instead of being sent back to retype the
        number they already gave us.
    */
    $serverChallengeId = session('quickEnquiryChallenge') ?: old('otp_challenge_id');
    $serverDemoOtp = session('quickEnquiryDemoOtp');
    $onOtpStep = (bool) $serverChallengeId;
@endphp

<div
    {{ $attributes->class('rounded-2xl border border-line bg-surface p-6 sm:p-8') }}
    {{-- `phone` seeds the field from old() so a no-JavaScript validation bounce
         does not have its number wiped the moment Alpine boots and binds x-model. --}}
    x-data="quickEnquiryForm({
        otpEndpoint: @js(route('quick-enquiry.otp')),
        endpoint: @js(route('quick-enquiry.store')),
        source: @js($source),
        phone: @js(old('phone', '')),
        challengeId: @js($serverChallengeId),
        demoCode: @js($serverDemoOtp),
        step: @js($onOtpStep ? 'otp' : 'phone'),
    })"
>
    <div x-show="!done">
        <p class="font-display text-xl font-semibold text-ink sm:text-2xl" x-text="step === 'otp' ? 'Verify your mobile number' : @js($heading)">{{ $onOtpStep ? 'Verify your mobile number' : $heading }}</p>

        <p class="mt-2 text-sm text-ink-muted sm:text-base" style="{{ $onOtpStep ? 'display:none' : '' }}" x-show="step !== 'otp'">{{ $subheading }}</p>
        <p class="mt-2 text-sm text-ink-muted sm:text-base" style="{{ $onOtpStep ? '' : 'display:none' }}" x-show="step === 'otp'">
            Enter the 6-digit code we sent to <span class="font-medium text-ink">+91 <span x-text="phone">{{ old('phone') }}</span></span>.
        </p>

        @if ($serverStatus)
            <x-ui.alert class="mt-4" tone="pass" :title="session('quickEnquiryTitle')">{{ $serverStatus }}</x-ui.alert>
        @endif

        {{-- Step one: the number. --}}
        <form
            method="POST"
            action="{{ route('quick-enquiry.otp') }}"
            novalidate
            @submit.prevent="sendOtp"
            style="{{ $onOtpStep ? 'display:none' : '' }}"
            x-show="step !== 'otp'"
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
                    <x-ui.input-affix>+91</x-ui.input-affix>
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
                <span x-text="loading ? 'Sending code...' : 'Send OTP'">Send OTP</span>
            </x-ui.button>
        </form>

        {{-- Step two: the code. --}}
        <form
            method="POST"
            action="{{ route('quick-enquiry.store') }}"
            novalidate
            @submit.prevent="submit"
            style="{{ $onOtpStep ? '' : 'display:none' }}"
            x-show="step === 'otp'"
            class="mt-5"
        >
            @csrf
            <input type="hidden" name="source" value="{{ $source }}">
            <input type="hidden" name="phone" value="{{ old('phone') }}" :value="phone">
            <input type="hidden" name="otp_challenge_id" value="{{ $serverChallengeId }}" :value="challengeId">

            {{-- There is no SMS gateway connected yet, so the code is shown here
                 rather than pretending a message went out. Same demo-mode wording
                 as the credit-score check and the journey's OTP step. --}}
            <div style="{{ $serverDemoOtp ? '' : 'display:none' }}" x-show="demoCode">
                <x-ui.alert tone="accent" title="Demo mode">
                    There's no SMS gateway connected yet, so your one-time code is shown right here instead of being texted to you:
                    <span class="font-mono text-base font-semibold text-ink" x-text="demoCode">{{ $serverDemoOtp }}</span>
                </x-ui.alert>
            </div>

            <div class="mt-3 flex flex-col gap-3 sm:flex-row sm:items-start">
                <div class="flex-1">
                    <label for="{{ $otpFieldId }}" class="sr-only">6-digit verification code</label>
                    <input
                        type="text"
                        id="{{ $otpFieldId }}"
                        name="otp_code"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        maxlength="6"
                        placeholder="6-digit code"
                        required
                        aria-describedby="{{ $otpFieldId }}-error"
                        @if ($serverOtpError) aria-invalid="true" @endif
                        @class([
                            'w-full rounded-lg border bg-surface px-3.5 py-2.5 font-mono text-sm tracking-[0.3em] text-ink transition-colors placeholder:tracking-normal placeholder:font-sans placeholder:text-ink-faint focus:outline-none focus:ring-2',
                            'border-line-strong focus:border-accent focus:ring-accent/30' => ! $serverOtpError,
                            'border-warn focus:ring-warn/30' => $serverOtpError,
                        ])
                        :class="otpError ? 'border-warn focus:ring-warn/30' : 'border-line-strong focus:border-accent focus:ring-accent/30'"
                        x-model="otpCode"
                        @input="onOtpInput"
                        :aria-invalid="otpError ? 'true' : 'false'"
                    >

                    @if ($serverOtpError)
                        <p class="mt-1 text-xs text-warn" x-show="!otpError" role="alert">{{ $serverOtpError }}</p>
                    @endif

                    <p
                        id="{{ $otpFieldId }}-error"
                        role="alert"
                        class="mt-1 text-xs text-warn"
                        x-show="otpError"
                        x-text="otpError"
                        x-cloak
                    ></p>
                </div>

                <x-ui.button type="submit" class="shrink-0" ::disabled="loading">
                    <span x-text="loading ? 'Verifying...' : @js($ctaLabel)">{{ $ctaLabel }}</span>
                </x-ui.button>
            </div>

            {{-- With JavaScript off, "Resend code" resubmits this form's number to
                 the OTP endpoint for a fresh code, and "Change number" is a plain
                 link back to the page, which drops the flashed challenge and
                 renders step one again. Neither strands the visitor on a code
                 that will not arrive. --}}
            <p class="mt-3 flex flex-wrap gap-x-4 gap-y-1 text-xs text-ink-faint">
                <button
                    type="submit"
                    formaction="{{ route('quick-enquiry.otp') }}"
                    class="font-medium text-accent underline-offset-2 hover:underline disabled:opacity-50"
                    @click.prevent="resendOtp"
                    ::disabled="loading"
                >Resend code</button>
                <a
                    href="{{ url()->current() }}"
                    class="font-medium text-accent underline-offset-2 hover:underline"
                    @click.prevent="editPhone"
                >Change number</a>
            </p>
        </form>

        <p class="mt-3 text-xs text-ink-faint" style="{{ $onOtpStep ? 'display:none' : '' }}" x-show="step !== 'otp'">
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
