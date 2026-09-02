@props(['faqs', 'heading' => 'Frequently asked questions'])

@if ($faqs->isNotEmpty())
    <div {{ $attributes->class('mt-12') }} data-reveal="fade">
        <h2 class="font-display text-xl font-semibold text-ink">{{ $heading }}</h2>
        <div class="mt-4 flex flex-col divide-y divide-line border-y border-line">
            @foreach ($faqs as $faq)
                <details data-reveal="up stagger" class="group py-4">
                    <summary class="flex cursor-pointer list-none items-center justify-between text-sm font-medium text-ink">
                        {{ $faq->question }}
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4 shrink-0 transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                    </summary>
                    <p class="mt-3 text-sm text-ink-muted">{{ $faq->answer }}</p>
                </details>
            @endforeach
        </div>
    </div>
@endif
