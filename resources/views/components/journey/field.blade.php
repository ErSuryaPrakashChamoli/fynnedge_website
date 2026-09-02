@use('App\Modules\Journey\Enums\FieldType')

@props(['field', 'value' => null])

@php
    $span = $field->type === FieldType::Textarea ? 'sm:col-span-2' : '';
    $errorBag = $errors->has($field->key);

    // Native min/max on number inputs so the browser stops someone from typing an absurd
    // value (e.g. a 9-digit tenure) before server-side validation ever runs — parsed straight
    // from this field's own validation_rules so it stays correct per loan product without
    // hardcoding a value here that would drift from the rules that actually enforce it.
    $parseIntRule = function (?array $rules, string $prefix): ?int {
        foreach ($rules ?? [] as $rule) {
            if (is_string($rule) && str_starts_with($rule, $prefix)) {
                return (int) substr($rule, strlen($prefix));
            }
        }

        return null;
    };
    $numericMin = $parseIntRule($field->validation_rules, 'min:');
    $numericMax = $parseIntRule($field->validation_rules, 'max:');
    $numericMaxLength = $numericMax !== null ? strlen((string) $numericMax) : null;
@endphp

<div
    x-show="isVisible('{{ $field->key }}')"
    x-cloak
    class="{{ $span }}"
>
    @switch($field->type)
        @case(FieldType::Select)
            <div class="flex flex-col gap-1.5">
                <label for="{{ $field->key }}" class="text-sm font-medium text-ink">{{ $field->label }}</label>
                <select
                    id="{{ $field->key }}"
                    name="{{ $field->key }}"
                    x-model="values['{{ $field->key }}']"
                    class="rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink transition-colors focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30 {{ $errorBag ? 'border-warn' : 'border-line-strong' }}"
                >
                    <option value="">Select…</option>
                    @foreach ($field->options ?? [] as $option)
                        <option value="{{ $option['value'] }}" @selected(old($field->key, $value) == $option['value'])>{{ $option['label'] }}</option>
                    @endforeach
                </select>
                @if ($errorBag)
                    <p class="text-xs text-warn">{{ $errors->first($field->key) }}</p>
                @elseif ($field->help_text)
                    <p class="text-xs text-ink-faint">{{ $field->help_text }}</p>
                @endif
            </div>
            @break

        @case(FieldType::SearchableSelect)
            <div
                x-data="{
                    open: false,
                    options: @js(collect($field->options ?? [])->all()),
                    mode: 'select',
                    query: '',
                    init() {
                        const current = values['{{ $field->key }}'];
                        const match = this.options.find((o) => o.value === current);
                        if (match) {
                            this.query = match.label;
                        } else if (current) {
                            this.mode = 'custom';
                        }
                    },
                    get filtered() {
                        const q = this.query.trim().toLowerCase();
                        return q
                            ? this.options.filter((o) => o.label.toLowerCase().includes(q)).slice(0, 8)
                            : this.options.slice(0, 8);
                    },
                    choose(option) {
                        values['{{ $field->key }}'] = option.value;
                        this.query = option.label;
                        this.open = false;
                    },
                    chooseOther() {
                        this.mode = 'custom';
                        values['{{ $field->key }}'] = '';
                        this.open = false;
                        $nextTick(() => $refs.customInput?.focus());
                    },
                    backToSearch() {
                        this.mode = 'select';
                        this.query = '';
                        values['{{ $field->key }}'] = '';
                    },
                }"
                class="relative flex flex-col gap-1.5"
                @click.outside="open = false"
            >
                <label class="text-sm font-medium text-ink">{{ $field->label }}</label>

                <template x-if="mode === 'select'">
                    <div class="relative">
                        <input
                            type="text"
                            x-model="query"
                            @focus="open = true"
                            @input="open = true"
                            placeholder="Start typing to search…"
                            autocomplete="off"
                            class="w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint transition-colors focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30 {{ $errorBag ? 'border-warn' : 'border-line-strong' }}"
                        >
                        <input type="hidden" name="{{ $field->key }}" x-model="values['{{ $field->key }}']">

                        <div x-show="open" x-cloak class="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-line bg-surface py-1 shadow-lg">
                            <template x-for="option in filtered" :key="option.value">
                                <button type="button" @click="choose(option)" class="block w-full px-3.5 py-2 text-left text-sm text-ink hover:bg-surface-2" x-text="option.label"></button>
                            </template>
                            <template x-if="filtered.length === 0">
                                <p class="px-3.5 py-2 text-sm text-ink-faint">No matches — choose "Other" below</p>
                            </template>
                            <button type="button" @click="chooseOther()" class="block w-full border-t border-line px-3.5 py-2 text-left text-sm font-medium text-accent hover:bg-surface-2">Other — enter manually</button>
                        </div>
                    </div>
                </template>

                <template x-if="mode === 'custom'">
                    <div>
                        <input
                            type="text"
                            name="{{ $field->key }}"
                            x-ref="customInput"
                            x-model="values['{{ $field->key }}']"
                            placeholder="Enter {{ Str::lower($field->label) }}"
                            class="w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint transition-colors focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30 {{ $errorBag ? 'border-warn' : 'border-line-strong' }}"
                        >
                        <button type="button" @click="backToSearch()" class="mt-1.5 text-xs font-medium text-accent hover:underline">← Search from list instead</button>
                    </div>
                </template>

                @if ($errorBag)
                    <p class="text-xs text-warn">{{ $errors->first($field->key) }}</p>
                @elseif ($field->help_text)
                    <p class="text-xs text-ink-faint">{{ $field->help_text }}</p>
                @endif
            </div>
            @break

        @case(FieldType::Radio)
            <fieldset class="flex flex-col gap-2">
                <legend class="text-sm font-medium text-ink">{{ $field->label }}</legend>
                <div class="flex flex-wrap gap-4">
                    @foreach ($field->options ?? [] as $option)
                        <label class="flex items-center gap-2 text-sm text-ink-muted">
                            <input
                                type="radio"
                                name="{{ $field->key }}"
                                value="{{ $option['value'] }}"
                                x-model="values['{{ $field->key }}']"
                                @checked(old($field->key, $value) == $option['value'])
                                class="border-line-strong text-accent focus:ring-accent/30"
                            >
                            {{ $option['label'] }}
                        </label>
                    @endforeach
                </div>
                @if ($errorBag)
                    <p class="text-xs text-warn">{{ $errors->first($field->key) }}</p>
                @elseif ($field->help_text)
                    <p class="text-xs text-ink-faint">{{ $field->help_text }}</p>
                @endif
            </fieldset>
            @break

        @case(FieldType::Checkbox)
            <div class="flex flex-col gap-1.5">
                <label class="flex items-start gap-2.5 text-sm text-ink-muted">
                    <input
                        type="checkbox"
                        name="{{ $field->key }}"
                        value="1"
                        x-model="values['{{ $field->key }}']"
                        @checked(old($field->key, $value))
                        class="mt-0.5 rounded border-line-strong text-accent focus:ring-accent/30"
                    >
                    @if (in_array($field->key, ['credit_check_consent', 'platform_consent'], true))
                        {{-- $field->label carries the same sentence, escaped, as a fallback
                             wherever it's read verbatim elsewhere (e.g. admin previews) — this
                             is the only place it's rendered with real, safe (not user-supplied)
                             hyperlinks. See JourneySeeder::consentField() and
                             JourneySeeder::basicDetailsFields() ('platform_consent'). --}}
                        <span>
                            By submitting this form, you have read and agree to the
                            <a href="{{ route('credit-report-terms') }}" target="_blank" rel="noopener" class="text-accent underline">Credit Report Terms of Use</a>,
                            <a href="{{ route('terms') }}" target="_blank" rel="noopener" class="text-accent underline">Terms of Use</a>
                            &amp;
                            <a href="{{ route('privacy-policy') }}" target="_blank" rel="noopener" class="text-accent underline">Privacy Policy</a>.
                        </span>
                    @else
                        <span>{{ $field->label }}</span>
                    @endif
                </label>
                @if ($errorBag)
                    <p class="text-xs text-warn">{{ $errors->first($field->key) }}</p>
                @elseif ($field->help_text)
                    <p class="text-xs text-ink-faint">{{ $field->help_text }}</p>
                @endif
            </div>
            @break

        @case(FieldType::Textarea)
            <x-ui.textarea
                :name="$field->key"
                :label="$field->label"
                :hint="$field->help_text"
                :value="$value"
                x-model="values['{{ $field->key }}']"
            />
            @break

        @case(FieldType::Number)
            @if (str_contains($field->label, '₹'))
                <div
                    class="flex flex-col gap-1.5"
                    x-data="{ raw: (@js($value) ?? '').toString().replace(/[^0-9]/g, '') }"
                    x-init="$refs.input.value = raw ? formatIndianNumber(raw) : ''"
                >
                    <label for="{{ $field->key }}" class="text-sm font-medium text-ink">{{ $field->label }}</label>
                    <input
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        id="{{ $field->key }}"
                        name="{{ $field->key }}"
                        x-ref="input"
                        data-currency-input
                        x-on:input="raw = $el.value.replace(/[^0-9]/g, ''); values['{{ $field->key }}'] = raw === '' ? null : Number(raw)"
                        x-on:focus="$el.value = raw"
                        x-on:blur="$el.value = raw ? formatIndianNumber(raw) : ''"
                        class="rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint transition-colors focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent {{ $errorBag ? 'border-warn focus:ring-warn/30 focus:border-warn' : 'border-line-strong' }}"
                    >
                    <template x-if="raw">
                        <p class="text-xs text-ink-faint" x-text="numberToIndianWords(raw) + ' Rupees only'"></p>
                    </template>
                    @if ($errorBag)
                        <p class="text-xs text-warn">{{ $errors->first($field->key) }}</p>
                    @elseif ($field->help_text)
                        <p class="text-xs text-ink-faint">{{ $field->help_text }}</p>
                    @endif
                </div>
            @elseif ($field->key === 'employment_vintage_years')
                {{-- Same field/response key regardless of employment type (eligibility rules key
                     off it as a single 'employment_vintage_years' attribute) — only the label
                     changes to match "job" vs "business" wording. --}}
                <div class="flex flex-col gap-1.5">
                    <label for="{{ $field->key }}" class="text-sm font-medium text-ink" x-text="values['employment_type'] === 'self-employed' ? 'Years in current business' : 'Years in current job'">{{ $field->label }}</label>
                    <input
                        type="number"
                        name="{{ $field->key }}"
                        id="{{ $field->key }}"
                        value="{{ old($field->key, $value) }}"
                        x-model="values['{{ $field->key }}']"
                        @if ($numericMin !== null) min="{{ $numericMin }}" @endif
                        @if ($numericMax !== null) max="{{ $numericMax }}" maxlength="{{ $numericMaxLength }}" @endif
                        class="rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint transition-colors focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent {{ $errorBag ? 'border-warn focus:ring-warn/30 focus:border-warn' : 'border-line-strong' }}"
                    >
                    @if ($errorBag)
                        <p class="text-xs text-warn">{{ $errors->first($field->key) }}</p>
                    @elseif ($field->help_text)
                        <p class="text-xs text-ink-faint">{{ $field->help_text }}</p>
                    @endif
                </div>
            @else
                <x-ui.input
                    :name="$field->key"
                    :label="$field->label"
                    :type="$field->type->value"
                    :hint="$field->help_text"
                    :value="$value"
                    x-model="values['{{ $field->key }}']"
                    :min="$numericMin"
                    :max="$numericMax"
                    :maxlength="$numericMaxLength"
                />
            @endif
            @break

        @default
            @if ($field->key === 'phone')
                {{-- Mobile number gets a Send OTP / Verify OTP flow instead of a plain input —
                     journeyStep() in app.js owns the OTP state and hides every OTHER field on
                     this step (via isVisible()) until phoneVerified is true. Server-side,
                     JourneyController::update() refuses the submission unless the session's
                     phone_verified_at/phone_number actually match, so this can't be bypassed
                     by posting the form directly. Keyed on 'phone' (not FieldType::Tel) since
                     that's the one shared field key across every loan product's basic-details
                     step (JourneySeeder::basicDetailsFields()). --}}
                <div class="flex flex-col gap-1.5">
                    <label for="{{ $field->key }}" class="text-sm font-medium text-ink">{{ $field->label }}</label>
                    <div class="flex gap-2">
                        <input
                            type="tel"
                            inputmode="numeric"
                            maxlength="10"
                            id="{{ $field->key }}"
                            name="{{ $field->key }}"
                            x-model="values['{{ $field->key }}']"
                            x-bind:readonly="phoneVerified"
                            placeholder="10-digit mobile number"
                            class="flex-1 rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint transition-colors focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30 {{ $errorBag ? 'border-warn' : 'border-line-strong' }}"
                        >
                        <button
                            type="button"
                            x-show="! phoneVerified"
                            x-on:click="sendPhoneOtp()"
                            x-bind:disabled="phoneOtpLoading || ! values['{{ $field->key }}']"
                            class="shrink-0 cursor-pointer rounded-lg bg-accent-soft px-4 py-2.5 text-sm font-medium text-accent transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                            x-text="phoneOtpSent ? 'Resend OTP' : 'Send OTP'"
                        ></button>
                        <span x-show="phoneVerified" x-cloak class="flex shrink-0 items-center gap-1.5 rounded-lg bg-pass-soft px-3.5 py-2.5 text-sm font-medium text-pass">
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                            Verified
                        </span>
                    </div>

                    <template x-if="phoneOtpSent && ! phoneVerified">
                        <div class="mt-1 flex flex-col gap-2 rounded-lg border border-line bg-surface-2 p-3">
                            <template x-if="phoneDemoOtpCode">
                                <p class="text-xs text-ink-faint">
                                    Demo mode — no SMS gateway connected, so your code is shown here: <span class="font-mono font-semibold text-ink" x-text="phoneDemoOtpCode"></span>
                                </p>
                            </template>
                            <div class="flex gap-2">
                                <input
                                    type="text"
                                    inputmode="numeric"
                                    maxlength="6"
                                    placeholder="6-digit code"
                                    x-model="phoneOtpCode"
                                    class="w-32 rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm tracking-[0.3em] text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30"
                                >
                                <button
                                    type="button"
                                    x-on:click="verifyPhoneOtp()"
                                    x-bind:disabled="phoneOtpLoading || phoneOtpCode.length !== 6"
                                    class="cursor-pointer rounded-lg bg-accent px-4 py-2 text-sm font-medium text-white transition-colors disabled:cursor-not-allowed disabled:opacity-50"
                                >
                                    Verify
                                </button>
                            </div>
                        </div>
                    </template>

                    <template x-if="phoneOtpError">
                        <p class="text-xs text-warn" x-text="phoneOtpError"></p>
                    </template>
                    @if ($errorBag)
                        <p class="text-xs text-warn">{{ $errors->first($field->key) }}</p>
                    @elseif ($field->help_text)
                        <p class="text-xs text-ink-faint">{{ $field->help_text }}</p>
                    @endif
                </div>
            @elseif ($field->type === FieldType::Date && $field->key === 'date_of_birth')
                {{-- A masked dd/mm/yyyy text input rather than <input type="date"> — the native
                     picker's displayed format follows the browser/OS locale (e.g. MM/DD/YYYY on
                     a US-locale browser), which doesn't match what Indian applicants expect. The
                     visible input holds only the display digits; the hidden input carries the
                     real ISO value that values['date_of_birth'] (and the server's `date` /
                     `before:-21 years` rules) expect. --}}
                <div
                    class="flex flex-col gap-1.5"
                    x-data="{ raw: isoToDateInputDigits(@js($value)) }"
                    x-init="$refs.input.value = formatDateInput(raw); values['{{ $field->key }}'] = dateInputToIso(raw) || (values['{{ $field->key }}'] ?? '')"
                >
                    <label for="{{ $field->key }}" class="text-sm font-medium text-ink">{{ $field->label }}</label>
                    <input
                        type="text"
                        inputmode="numeric"
                        autocomplete="off"
                        placeholder="DD/MM/YYYY"
                        maxlength="10"
                        id="{{ $field->key }}"
                        x-ref="input"
                        x-on:input="raw = $el.value.replace(/[^0-9]/g, '').slice(0, 8); $el.value = formatDateInput(raw); values['{{ $field->key }}'] = dateInputToIso(raw)"
                        class="rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint transition-colors focus:outline-none focus:ring-2 focus:ring-accent/30 focus:border-accent {{ $errorBag ? 'border-warn focus:ring-warn/30 focus:border-warn' : 'border-line-strong' }}"
                    >
                    <input type="hidden" name="{{ $field->key }}" x-model="values['{{ $field->key }}']">
                    @if ($errorBag)
                        <p class="text-xs text-warn">{{ $errors->first($field->key) }}</p>
                    @elseif ($field->help_text)
                        <p class="text-xs text-ink-faint">{{ $field->help_text }}</p>
                    @endif
                </div>
            @else
                <x-ui.input
                    :name="$field->key"
                    :label="$field->label"
                    :type="$field->type->value"
                    :hint="$field->help_text"
                    :value="$value"
                    x-model="values['{{ $field->key }}']"
                />
            @endif
    @endswitch
</div>
