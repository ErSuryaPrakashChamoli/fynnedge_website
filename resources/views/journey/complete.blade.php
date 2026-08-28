@php
    $eligibleCount = $results->filter(fn ($result) => $result->status->value === 'eligible')->count();
@endphp

<x-layouts.app title="Your Eligibility Results" robots="noindex, nofollow">
    <section class="mx-auto max-w-3xl px-6 py-16 lg:px-8">
        <div class="text-center">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-pass-soft">
                <svg viewBox="0 0 20 20" fill="currentColor" class="h-7 w-7 text-pass" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
            </div>

            <h1 class="mt-6 text-balance font-display text-2xl font-semibold text-ink sm:text-3xl">
                @if ($results->isEmpty())
                    Thanks — we've got your details
                @elseif ($eligibleCount > 0)
                    You may be eligible with {{ $eligibleCount }} {{ Str::plural('lender', $eligibleCount) }}
                @else
                    Here's how you matched up
                @endif
            </h1>
            <p class="mt-3 text-ink-muted">
                For your <strong class="text-ink">{{ $session->loanProduct->name }}</strong> application.
            </p>
        </div>

        <x-ui.alert tone="accent" class="mt-8">
            Eligibility results are indicative and subject to each lender's own verification,
            documentation and final underwriting.
        </x-ui.alert>

        @if ($results->isEmpty())
            <p class="mt-8 text-center text-sm text-ink-muted">
                No lenders are configured for this product yet — our team will follow up once we've matched you.
            </p>
        @else
            <div class="mt-10 flex flex-col gap-5">
                @foreach ($results as $result)
                    <x-ui.card>
                        <div class="flex items-center justify-between gap-4">
                            <p class="font-display text-lg font-semibold text-ink">{{ $result->lenderProduct->lender->name }}</p>
                            <x-ui.badge :tone="$result->status->value === 'eligible' ? 'pass' : 'warn'">
                                {{ $result->status->getLabel() }}
                            </x-ui.badge>
                        </div>

                        @php
                            // Mandatory/preferred rules state a requirement, so passed=true is good.
                            // Warning rules instead flag a caveat condition (e.g. "has existing EMIs") —
                            // passed=true means the caveat was found and is worth showing; passed=false
                            // means it wasn't, which isn't noteworthy and shouldn't render as a warning icon.
                            $visibleReasons = $result->reasons->filter(
                                fn ($reason) => $reason->priority->value !== 'warning' || $reason->passed,
                            );
                        @endphp
                        @if ($visibleReasons->isNotEmpty())
                            <ul class="mt-4 flex flex-col gap-2">
                                @foreach ($visibleReasons as $reason)
                                    <li class="flex items-start gap-2.5 text-sm">
                                        @if ($reason->priority->value === 'warning')
                                            <svg viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-4 w-4 shrink-0 text-warn" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-11.25a.75.75 0 0 0-1.5 0v4a.75.75 0 0 0 1.5 0v-4ZM10 15a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" /></svg>
                                        @elseif ($reason->passed)
                                            <svg viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-4 w-4 shrink-0 text-pass" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                                        @else
                                            <svg viewBox="0 0 20 20" fill="currentColor" class="mt-0.5 h-4 w-4 shrink-0 text-warn" aria-hidden="true"><path fill-rule="evenodd" d="M10 18a8 8 0 1 0 0-16 8 8 0 0 0 0 16Zm.75-11.25a.75.75 0 0 0-1.5 0v4a.75.75 0 0 0 1.5 0v-4ZM10 15a1 1 0 1 0 0-2 1 1 0 0 0 0 2Z" clip-rule="evenodd" /></svg>
                                        @endif
                                        <span class="text-ink-muted">{{ $reason->customer_message ?? $reason->label }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </x-ui.card>
                @endforeach
            </div>
        @endif

        <div class="mt-10 text-center">
            <x-ui.button tag="a" :href="route('home')" variant="secondary">
                Back to home
            </x-ui.button>
        </div>
    </section>
</x-layouts.app>
