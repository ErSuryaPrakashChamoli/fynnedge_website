@props(['testimonials', 'heading' => 'What our customers say'])

@if ($testimonials->isNotEmpty())
    <div
        {{ $attributes->class('mt-12') }}
        data-reveal="zoom"
        x-data="{
            atStart: true,
            atEnd: true,
            init() {
                this.measure();
                window.addEventListener('resize', () => this.measure(), { passive: true });
            },
            measure() {
                const track = this.$refs.track;
                this.atStart = track.scrollLeft <= 1;
                this.atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 1;
            },
            scroll(direction) {
                this.$refs.track.scrollBy({ left: direction * this.$refs.track.clientWidth * 0.8, behavior: 'smooth' });
            },
        }"
    >
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h2 class="font-display text-xl font-semibold text-ink">{{ $heading }}</h2>

            <div x-show="! (atStart && atEnd)" x-cloak class="flex gap-2">
                <button type="button" @click="scroll(-1)" :disabled="atStart" aria-label="Previous testimonials" class="flex h-10 w-10 items-center justify-center rounded-full border border-line bg-surface text-ink shadow-sm transition hover:border-accent hover:text-accent disabled:pointer-events-none disabled:opacity-40">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12.5 15-5-5 5-5" /></svg>
                </button>
                <button type="button" @click="scroll(1)" :disabled="atEnd" aria-label="More testimonials" class="flex h-10 w-10 items-center justify-center rounded-full border border-line bg-surface text-ink shadow-sm transition hover:border-accent hover:text-accent disabled:pointer-events-none disabled:opacity-40">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m7.5 15 5-5-5-5" /></svg>
                </button>
            </div>
        </div>

        <ul
            x-ref="track"
            @scroll.debounce.60ms="measure()"
            role="list"
            class="relative -mx-2 mt-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-2 pb-2 pt-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
            @foreach ($testimonials as $testimonial)
                <li data-reveal="up stagger" data-testimonial-card class="flex w-[85%] shrink-0 snap-start sm:w-[calc((100%-1rem)/2)] lg:w-[calc((100%-2rem)/3)]">
                    <x-ui.card class="card-lift flex h-full w-full flex-col">
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
                </li>
            @endforeach
        </ul>
    </div>
@endif
