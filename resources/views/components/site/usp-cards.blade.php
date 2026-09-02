@props(['cards', 'heading' => null])

{{--
    Generic numbered-card grid, styled like <x-site.why-fynnedge>'s card grid
    but driven by a passed $cards array (['title' => ..., 'body' => ...])
    instead of a hardcoded list, so it can be reused for any product's own
    USP set without copying the card markup.
--}}
<div {{ $attributes->class('mt-12') }} data-reveal="fade">
    @if ($heading)
        <h2 class="font-display text-xl font-semibold text-ink">{{ $heading }}</h2>
    @endif

    <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @foreach ($cards as $index => $card)
            <x-ui.card data-reveal="zoom stagger" class="card-lift transition-colors transition-shadow hover:bg-accent-soft hover:shadow-md hover:animate-card-swing">
                <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-accent-soft font-mono text-xs font-semibold text-accent">
                    {{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }}
                </span>
                <p class="mt-4 font-display text-base font-semibold text-ink">{{ $card['title'] }}</p>
                <p class="mt-2 text-sm text-ink-muted">{{ $card['body'] }}</p>
            </x-ui.card>
        @endforeach
    </div>
</div>
