<x-layouts.app title="Application Received" robots="noindex, nofollow">
    <section class="mx-auto max-w-2xl px-6 py-20 text-center lg:px-8">
        <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-pass-soft">
            <svg viewBox="0 0 20 20" fill="currentColor" class="h-7 w-7 text-pass" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
        </div>

        <h1 class="mt-6 text-balance font-display text-2xl font-semibold text-ink sm:text-3xl">
            Thanks — we've got your details
        </h1>
        <p class="mt-3 text-ink-muted">
            We've received your profile for <strong class="text-ink">{{ $session->loanProduct->name }}</strong>.
            Our eligibility engine will match you against suitable lenders — we'll be in touch with the results.
        </p>

        <x-ui.alert tone="accent" class="mt-8 text-left">
            Eligibility results are indicative and subject to each lender's own verification, documentation and
            underwriting.
        </x-ui.alert>

        <x-ui.button tag="a" :href="route('home')" variant="secondary" class="mt-8">
            Back to home
        </x-ui.button>
    </section>
</x-layouts.app>
