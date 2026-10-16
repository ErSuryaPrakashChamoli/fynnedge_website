<x-layouts.portal :title="__('My Profile')" :customer="$customer">
    @php
        $locked = $customer->isIdentityVerified();
        $inputClass = 'mt-1.5 w-full rounded-lg border border-line-strong bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30 disabled:bg-surface-2 disabled:text-ink-muted';
    @endphp

    <div class="flex flex-wrap items-center gap-3">
        <div class="h-2 w-40 overflow-hidden rounded-full bg-surface-2" aria-hidden="true">
            <div class="h-full rounded-full bg-accent" style="width: {{ $customer->profileCompletion() }}%"></div>
        </div>
        <p class="text-sm text-ink-muted">{{ __('Profile :percent% complete', ['percent' => $customer->profileCompletion()]) }}</p>
    </div>

    <x-ui.alert class="mt-4" :title="__('Where your details come from')">
        {{ __('Details marked "Verified" have been checked — your mobile by a one-time code, your identity by our team. Everything else is as you gave it to us. Your credit reports never change your profile.') }}
    </x-ui.alert>

    <form method="POST" action="{{ route('portal.profile.update') }}" class="mt-6 flex flex-col gap-6">
        @csrf
        @method('PUT')

        <x-ui.card>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-display text-lg font-semibold text-ink">{{ __('Personal information') }}</h2>
                @if ($locked)
                    <x-ui.badge tone="pass">{{ __('Verified') }}</x-ui.badge>
                @else
                    <x-ui.badge tone="muted">{{ __('Not verified') }}</x-ui.badge>
                @endif
            </div>
            @if ($locked)
                <p class="mt-2 text-sm text-ink-muted">{{ __('Your identity has been verified, so these details can only be changed by our team. Please contact support if something is wrong.') }}</p>
            @endif
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="full_name" class="text-sm font-medium text-ink">{{ __('Full name (as per PAN)') }}</label>
                    <input id="full_name" name="full_name" type="text" maxlength="150" autocomplete="name" value="{{ old('full_name', $customer->full_name) }}" @disabled($locked) class="{{ $inputClass }}">
                    @error('full_name') <p class="mt-1 text-xs text-warn" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="date_of_birth" class="text-sm font-medium text-ink">{{ __('Date of birth') }}</label>
                    <input id="date_of_birth" name="date_of_birth" type="date" value="{{ old('date_of_birth', $customer->date_of_birth?->toDateString()) }}" @disabled($locked) class="{{ $inputClass }}">
                    @error('date_of_birth') <p class="mt-1 text-xs text-warn" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="gender" class="text-sm font-medium text-ink">{{ __('Gender') }}</label>
                    <select id="gender" name="gender" @disabled($locked) class="{{ $inputClass }}">
                        <option value="">{{ __('Select') }}</option>
                        @foreach ($genders as $gender)
                            <option value="{{ $gender->value }}" @selected(old('gender', $customer->gender?->value) === $gender->value)>{{ __($gender->getLabel()) }}</option>
                        @endforeach
                    </select>
                    @error('gender') <p class="mt-1 text-xs text-warn" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-ui.card>

        <x-ui.card>
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Contact information') }}</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div>
                    <p class="text-sm font-medium text-ink">{{ __('Mobile number') }}</p>
                    <p class="mt-1.5 flex items-center justify-between gap-2 rounded-lg border border-line bg-surface-2 px-3.5 py-2.5 font-mono text-sm text-ink-muted">
                        +91 {{ $customer->maskedMobile() }}
                        <x-ui.badge tone="pass">{{ __('Verified') }}</x-ui.badge>
                    </p>
                    <p class="mt-1.5 text-xs text-ink-faint">{{ __('This is how you sign in. To change it, contact support.') }}</p>
                </div>
                <div>
                    <label for="email" class="text-sm font-medium text-ink">{{ __('Email address') }}</label>
                    <input id="email" name="email" type="email" maxlength="190" autocomplete="email" value="{{ old('email', $customer->email) }}" class="{{ $inputClass }}">
                    @error('email') <p class="mt-1 text-xs text-warn" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-ui.card>

        <x-ui.card>
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Address') }}</h2>
            <div class="mt-4 grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label for="address_line1" class="text-sm font-medium text-ink">{{ __('Address line 1') }}</label>
                    <input id="address_line1" name="address_line1" type="text" maxlength="255" autocomplete="address-line1" value="{{ old('address_line1', $customer->address_line1) }}" class="{{ $inputClass }}">
                    @error('address_line1') <p class="mt-1 text-xs text-warn" role="alert">{{ $message }}</p> @enderror
                </div>
                <div class="sm:col-span-2">
                    <label for="address_line2" class="text-sm font-medium text-ink">{{ __('Address line 2') }} <span class="font-normal text-ink-faint">{{ __('(optional)') }}</span></label>
                    <input id="address_line2" name="address_line2" type="text" maxlength="255" autocomplete="address-line2" value="{{ old('address_line2', $customer->address_line2) }}" class="{{ $inputClass }}">
                    @error('address_line2') <p class="mt-1 text-xs text-warn" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="city" class="text-sm font-medium text-ink">{{ __('City') }}</label>
                    <input id="city" name="city" type="text" maxlength="100" autocomplete="address-level2" value="{{ old('city', $customer->city) }}" class="{{ $inputClass }}">
                    @error('city') <p class="mt-1 text-xs text-warn" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="state" class="text-sm font-medium text-ink">{{ __('State') }}</label>
                    <input id="state" name="state" type="text" maxlength="100" autocomplete="address-level1" value="{{ old('state', $customer->state) }}" class="{{ $inputClass }}">
                    @error('state') <p class="mt-1 text-xs text-warn" role="alert">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="pincode" class="text-sm font-medium text-ink">{{ __('PIN code') }}</label>
                    <input id="pincode" name="pincode" type="text" inputmode="numeric" maxlength="6" autocomplete="postal-code" value="{{ old('pincode', $customer->pincode) }}" class="{{ $inputClass }}">
                    @error('pincode') <p class="mt-1 text-xs text-warn" role="alert">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-ui.card>

        <x-ui.card>
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 class="font-display text-lg font-semibold text-ink">{{ __('Identity') }}</h2>
                @if ($customer->pan_masked)
                    <x-ui.badge :tone="$locked ? 'pass' : 'muted'">{{ $locked ? __('Verified') : __('Not verified') }}</x-ui.badge>
                @endif
            </div>
            <div class="mt-4 max-w-sm">
                @if ($customer->pan_masked)
                    <p class="text-sm font-medium text-ink">{{ __('PAN') }}</p>
                    <p class="mt-1.5 rounded-lg border border-line bg-surface-2 px-3.5 py-2.5 font-mono text-sm tracking-widest text-ink-muted">{{ $customer->pan_masked }}</p>
                    <p class="mt-1.5 text-xs text-ink-faint">{{ __('Shown masked for your security. To change your PAN, contact support.') }}</p>
                @else
                    <label for="pan_number" class="text-sm font-medium text-ink">{{ __('PAN') }} <span class="font-normal text-ink-faint">{{ __('(optional)') }}</span></label>
                    <input id="pan_number" name="pan_number" type="text" maxlength="10" placeholder="ABCDE1234F" value="{{ old('pan_number') }}" style="text-transform: uppercase" class="{{ $inputClass }} tracking-widest">
                    @error('pan_number') <p class="mt-1 text-xs text-warn" role="alert">{{ $message }}</p> @enderror
                    <p class="mt-1.5 text-xs text-ink-faint">{{ __('Once saved, your PAN can only be changed by our team.') }}</p>
                @endif
            </div>
        </x-ui.card>

        <div>
            <x-ui.button type="submit">{{ __('Save changes') }}</x-ui.button>
        </div>
    </form>
</x-layouts.portal>
