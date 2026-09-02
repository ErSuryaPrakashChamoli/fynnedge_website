<x-layouts.app
    :title="'Free '.$bureau->getLabel().' Score'"
    :description="'Check your free '.$bureau->getLabel().' credit score — verify your mobile number, add a few details, and see where you stand.'"
>
    <section class="mx-auto max-w-6xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Credit Score' => null, $bureau->getLabel() => null]" />

        <div class="mt-6 grid gap-12 lg:grid-cols-[1.1fr_1fr] lg:items-start">
            <div data-reveal="up">
                <x-ui.badge tone="accent">Free {{ $bureau->getLabel() }} score check</x-ui.badge>

                <h1 class="mt-4 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
                    Check your free {{ $bureau->getLabel() }} score &amp; report
                </h1>
                <p class="mt-3 max-w-lg text-ink-muted">
                    Verify your mobile number, add a few details, and see an instant {{ $bureau->getLabel() }} score —
                    it's free, and checking it here never affects your real credit history.
                </p>

                <div class="mt-8">
                    <p class="font-display text-lg font-semibold text-ink">Why check with FynnEdge?</p>
                    <ul class="mt-4 flex flex-col gap-3">
                        @foreach ([
                            'Instant result — no paperwork, no waiting on hold',
                            'Verified by mobile OTP, so it\'s really you checking',
                            '100% free, and it never impacts your real credit score',
                        ] as $benefit)
                            <li class="flex items-start gap-3 text-sm text-ink-muted">
                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-pass-soft text-pass">
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                                </span>
                                {{ $benefit }}
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="mt-10 grid grid-cols-3 gap-4 border-t border-line pt-6">
                    <div>
                        <p class="font-display text-lg font-semibold text-ink">Secure</p>
                        <p class="mt-1 text-xs text-ink-faint">OTP-verified, no passwords stored</p>
                    </div>
                    <div>
                        <p class="font-display text-lg font-semibold text-ink">Free</p>
                        <p class="mt-1 text-xs text-ink-faint">No cost, no hidden charges</p>
                    </div>
                    <div>
                        <p class="font-display text-lg font-semibold text-ink">~2 min</p>
                        <p class="mt-1 text-xs text-ink-faint">Mobile, OTP, a few details</p>
                    </div>
                </div>
            </div>

            <div data-reveal="right" class="lg:sticky lg:top-24">
                <livewire:credit-score-check :bureau="$bureau->value" />
            </div>
        </div>
    </section>
</x-layouts.app>
