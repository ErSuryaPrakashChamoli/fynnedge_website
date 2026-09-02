@props([
    'heading' => 'About this calculator',
    'body' => null,
    'eligibilityUrl' => null,
    'applyUrl' => null,
    'applyLabel' => 'Apply for this loan',
])

@if ($body || $eligibilityUrl || $applyUrl)
    <div data-reveal="fade" class="mt-14 border-t border-line pt-10">
        @if ($body)
            <h2 class="font-display text-2xl font-semibold text-ink">{{ $heading }}</h2>
            <div class="prose prose-neutral mt-4 max-w-2xl text-ink-muted [&_h3]:font-display [&_h3]:text-ink [&_p]:leading-relaxed">
                {!! $body !!}
            </div>
        @endif

        @if ($eligibilityUrl || $applyUrl)
            <div class="mt-8 flex flex-wrap gap-3">
                @if ($eligibilityUrl)
                    <x-ui.button tag="a" :href="$eligibilityUrl" size="lg">Check Your Eligibility</x-ui.button>
                @endif
                @if ($applyUrl)
                    <x-ui.button tag="a" :href="$applyUrl" variant="secondary" size="lg">{{ $applyLabel }}</x-ui.button>
                @endif
            </div>
        @endif
    </div>
@endif
