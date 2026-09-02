@props(['testimonials', 'heading' => 'What our customers say'])

@if ($testimonials->isNotEmpty())
    <div {{ $attributes->class('mt-12') }} data-reveal="zoom">
        <h2 class="font-display text-xl font-semibold text-ink">{{ $heading }}</h2>
        <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($testimonials as $testimonial)
                <x-ui.card data-reveal="up stagger" class="card-lift flex h-full flex-col">
                    @if ($testimonial->rating)
                        <div class="flex gap-0.5 text-accent" aria-hidden="true">
                            @for ($i = 1; $i <= 5; $i++)
                                <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 {{ $i > $testimonial->rating ? 'text-line-strong' : '' }}">
                                    <path d="M10 1.5l2.6 5.4 5.9.8-4.3 4.2 1 5.9L10 15l-5.2 2.8 1-5.9L1.5 7.7l5.9-.8L10 1.5Z" />
                                </svg>
                            @endfor
                        </div>
                    @endif
                    <p class="mt-3 flex-1 text-sm italic text-ink-muted">&ldquo;{{ $testimonial->quote }}&rdquo;</p>
                    <div class="mt-4 flex items-center gap-3">
                        @if ($testimonial->avatarUrl())
                            <img src="{{ $testimonial->avatarUrl() }}" alt="{{ $testimonial->avatar_alt ?: $testimonial->customer_name }}" class="h-9 w-9 rounded-full object-cover" loading="lazy">
                        @else
                            <span class="flex h-9 w-9 items-center justify-center rounded-full bg-accent-soft font-mono text-xs font-semibold text-accent">
                                {{ Str::substr($testimonial->customer_name, 0, 1) }}
                            </span>
                        @endif
                        <div>
                            <p class="font-display text-sm font-semibold text-ink">{{ $testimonial->customer_name }}</p>
                            @if ($testimonial->role_location)
                                <p class="text-xs text-ink-faint">{{ $testimonial->role_location }}</p>
                            @endif
                        </div>
                    </div>
                </x-ui.card>
            @endforeach
        </div>
    </div>
@endif
