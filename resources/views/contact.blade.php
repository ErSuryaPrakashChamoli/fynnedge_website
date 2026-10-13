<x-layouts.app title="Contact" description="Get in touch with FynnEdge Advisory." page-type="ContactPage">
    <section class="mx-auto max-w-7xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Contact' => null]" />

        <h1 data-reveal="up" class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            Talk to us
        </h1>
        <p data-reveal="up" class="mt-3 text-ink-muted text-justify hyphens-auto">
            Questions about a loan product, your eligibility, or an existing application — send us a message
            and we'll get back to you.
        </p>

        @if (session('status'))
            <x-ui.alert tone="pass" :title="session('statusTitle')" class="mt-8">{{ session('status') }}</x-ui.alert>
        @endif

        @if ($contactPhone || $contactEmail || $contactWhatsapp || $contactAddress)
            <div class="mt-8 flex flex-wrap gap-4 text-sm text-ink-muted">
                @if ($contactPhone)<span>📞 {{ $contactPhone }}</span>@endif
                @if ($contactEmail)<span>✉️ {{ $contactEmail }}</span>@endif
                @if ($contactWhatsapp)<span>💬 {{ $contactWhatsapp }}</span>@endif
                @if ($contactAddress)<span>📍 {{ $contactAddress }}</span>@endif
            </div>
        @endif

        {{-- The phone is optional; when it is filled in, contactPhoneOtp (app.js) verifies
             it through the public OTP API before the form may be sent, and
             ContactController::store() checks and spends that verification again —
             the browser-side gate is a convenience, never the guard. --}}
        <form
            method="POST"
            action="{{ route('contact.store') }}"
            data-reveal="up"
            class="mt-10 grid gap-5 sm:grid-cols-2"
            x-data="contactPhoneOtp({
                sendUrl: @js(route('api.v1.mobile-otp.send')),
                verifyUrl: @js(route('api.v1.mobile-otp.verify')),
                phone: @js(old('phone', '')),
                otpLength: @js($otpLength),
            })"
            @submit="guardSubmit($event)"
        >
            @csrf
            <x-ui.input name="name" label="Name" required class="sm:col-span-1" />
            <x-ui.input name="email" type="email" label="Email" required class="sm:col-span-1" />

            @php($phoneError = $errors->first('phone'))
            <div class="flex flex-col gap-1.5 sm:col-span-2">
                <label for="phone" class="text-sm font-medium text-ink">Phone (optional)</label>
                <div class="flex flex-col gap-2 sm:flex-row">
                    <div
                        @class([
                            'flex flex-1 items-stretch overflow-hidden rounded-lg border bg-surface transition-colors focus-within:ring-2',
                            'border-line-strong focus-within:border-accent focus-within:ring-accent/30' => ! $phoneError,
                            'border-warn focus-within:ring-warn/30' => $phoneError,
                        ])
                    >
                        <x-ui.input-affix>+91</x-ui.input-affix>
                        <input
                            type="tel"
                            id="phone"
                            name="phone"
                            inputmode="numeric"
                            autocomplete="tel-national"
                            maxlength="16"
                            placeholder="10-digit mobile number"
                            value="{{ old('phone') }}"
                            aria-describedby="phone-help"
                            class="w-full min-w-0 bg-transparent px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:outline-none"
                            x-model="phone"
                            @input="onPhoneInput"
                            :readonly="verified"
                        >
                    </div>
                    <x-ui.button
                        type="button"
                        variant="secondary"
                        class="shrink-0"
                        x-show="phone !== '' && ! verified"
                        x-cloak
                        x-on:click="sendCode"
                        ::disabled="loading || resendIn > 0"
                    >
                        <span x-text="loading && ! sent ? 'Sending…' : (resendIn > 0 ? `Resend in ${resendIn}s` : (sent ? 'Resend code' : 'Send code'))">Send code</span>
                    </x-ui.button>
                    <span x-show="verified" x-cloak class="flex shrink-0 items-center gap-1.5 rounded-full bg-pass-soft px-4 py-2.5 text-sm font-medium text-pass">
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                        Verified
                    </span>
                </div>

                {{-- Only a verified challenge id is ever posted; the server re-checks it. --}}
                <input type="hidden" name="otp_challenge_id" :value="verified ? challengeId : ''">

                <div x-show="sent && ! verified" x-cloak class="mt-1 flex flex-col gap-2 rounded-lg border border-line bg-surface-2 p-3">
                    <p class="text-xs text-ink-muted">
                        Enter the {{ $otpLength }}-digit code we sent by SMS to <span class="font-medium text-ink">+91 <span x-text="maskedPhone"></span></span>. It may take a moment to arrive.
                    </p>
                    <div class="flex flex-wrap gap-2">
                        <label for="contact-otp" class="sr-only">{{ $otpLength }}-digit verification code</label>
                        <input
                            type="text"
                            id="contact-otp"
                            inputmode="numeric"
                            autocomplete="one-time-code"
                            maxlength="{{ $otpLength }}"
                            placeholder="{{ $otpLength }}-digit code"
                            class="w-36 rounded-lg border border-line-strong bg-surface px-3 py-2 font-mono text-sm tracking-[0.3em] text-ink placeholder:font-sans placeholder:tracking-normal placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30"
                            x-model="otpCode"
                            @input="onOtpInput"
                            @keydown.enter.prevent="verifyCode"
                        >
                        <x-ui.button type="button" size="sm" x-on:click="verifyCode" ::disabled="loading || otpCode.length !== otpLength">Verify</x-ui.button>
                        <x-ui.button type="button" size="sm" variant="ghost" x-on:click="changeNumber">Change number</x-ui.button>
                    </div>
                </div>

                <p id="phone-help" class="text-xs" :class="error ? 'text-warn' : 'text-ink-faint'" role="status">
                    @if ($phoneError)
                        <span class="text-warn" x-show="! error">{{ $phoneError }}</span>
                    @endif
                    <span x-show="error" x-text="error" x-cloak></span>
                    <span x-show="! error" @if ($phoneError) x-cloak @endif>
                        <span x-show="! verified">Add a mobile number and we'll text you a code to confirm it, or leave it blank and we'll reply by email.</span>
                        <span x-show="verified" x-cloak>Number confirmed. We'll call you on it.</span>
                    </span>
                </p>
            </div>

            <x-ui.textarea name="message" label="Message" rows="5" required class="sm:col-span-2" :value="$prefillMessage" />

            <div class="sm:col-span-2">
                <x-ui.button type="submit">Send message</x-ui.button>
            </div>
        </form>

        @if ($contactMapUrl)
            <x-site.location-map
                data-reveal="zoom"
                class="mt-12 h-80 rounded-2xl border border-line sm:h-96"
                :src="$contactMapUrl"
                :view-url="$contactMapViewUrl"
                label="FynnEdge Advisory"
            />
        @endif
    </section>
</x-layouts.app>
