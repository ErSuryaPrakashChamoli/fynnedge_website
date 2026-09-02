@props(['steps'])

{{--
    Deliberately separate from <x-ui.stepper> (used by the real loan-journey
    and credit-score-check progress UI) — this is static marketing
    illustration of the process, not a live progress indicator, and must
    never share a contract with business-critical flows.
--}}
<ol {{ $attributes->class('grid gap-6 sm:grid-cols-2') }}>
    @foreach ($steps as $index => $step)
        <li class="flex items-start gap-3">
            @if ($step->iconUrl())
                <img src="{{ $step->iconUrl() }}" alt="" class="h-9 w-9 shrink-0 rounded-full object-cover" loading="lazy">
            @else
                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-accent-soft font-mono text-sm font-semibold text-accent">
                    {{ $index + 1 }}
                </span>
            @endif
            <div>
                <p class="font-display text-sm font-semibold text-ink">{{ $step->title }}</p>
                @if ($step->description)
                    <p class="mt-1 text-sm text-ink-muted">{{ $step->description }}</p>
                @endif
            </div>
        </li>
    @endforeach
</ol>
