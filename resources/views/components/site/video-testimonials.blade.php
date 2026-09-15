@props([
    'embedded' => false,
    'eyebrow' => 'Video testimonials',
    'heading' => 'Real customers. Real stories.',
    'intro' => 'Hear it straight from borrowers who found the right loan with FynnEdge.',
])

{{--
    Customer video testimonials for the page being rendered, chosen by the
    pages an admin pinned each video to (Admin → Catalog → Video testimonials).

    x-layouts.app renders this as its own band after the page content, so a
    new page picks it up with no view change. Views that want it in a specific
    spot — next to their written testimonials — render it themselves with
    `embedded` and pass `handles-video-testimonials` to the layout, so it never
    appears twice on one page.

    A rounded panel across the full content width, with one row of cards —
    five across on extra-large screens, four on large, three on tablets —
    that swipes once it overflows. Each card stacks, kept short: the video
    across the top, "Watch X's story", the customer's photo and name, then
    their rating.

    Cards only dispatch `open-video-testimonial`; the single player modal
    (x-site.video-testimonial-player, rendered by the layout) plays it. With a
    mouse, an uploaded video previews silently while hovered.
--}}
{{-- Block form, not @php(...): Blade mis-pairs an inline @php with a later @endphp in the same view. --}}
@php
    $videoTestimonials = \App\Support\Testimonials\VideoTestimonials::forCurrentPage();
@endphp

@if ($videoTestimonials->isNotEmpty())
    @unless ($embedded)
        <section class="mx-auto max-w-7xl px-6 py-10 lg:px-8">
    @endunless

    <div
        {{ $attributes->class(['relative w-full overflow-hidden rounded-[2rem] border border-line bg-surface-2 p-5 shadow-sm sm:p-6']) }}
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
        data-ai-context="Customer video testimonials"
    >
        <div class="pointer-events-none absolute -right-24 -top-24 h-64 w-64 rounded-full bg-accent/10 blur-3xl" aria-hidden="true"></div>
        <div class="pointer-events-none absolute -bottom-24 -left-24 h-64 w-64 rounded-full bg-warn/10 blur-3xl" aria-hidden="true"></div>

        <div data-reveal="up" class="relative flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="inline-flex items-center gap-2 rounded-full bg-accent-soft px-3 py-1 font-mono text-xs font-semibold uppercase tracking-wider text-accent">
                    <span class="relative flex h-2 w-2" aria-hidden="true">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-accent opacity-60 motion-reduce:animate-none"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-accent"></span>
                    </span>
                    {{ $eyebrow }}
                </p>
                <h2 class="mt-3 text-balance font-display text-2xl font-semibold text-ink sm:text-3xl">{{ $heading }}</h2>
                <p class="mt-2 max-w-xl text-sm text-ink-muted">{{ $intro }}</p>
            </div>

            <div x-show="! (atStart && atEnd)" x-cloak class="flex gap-2">
                <button type="button" @click="scroll(-1)" :disabled="atStart" aria-label="Previous videos" class="flex h-10 w-10 items-center justify-center rounded-full border border-line bg-surface text-ink shadow-sm transition hover:border-accent hover:text-accent disabled:pointer-events-none disabled:opacity-40">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12.5 15-5-5 5-5" /></svg>
                </button>
                <button type="button" @click="scroll(1)" :disabled="atEnd" aria-label="More videos" class="flex h-10 w-10 items-center justify-center rounded-full border border-line bg-surface text-ink shadow-sm transition hover:border-accent hover:text-accent disabled:pointer-events-none disabled:opacity-40">
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m7.5 15 5-5-5-5" /></svg>
                </button>
            </div>
        </div>

        <ul
            x-ref="track"
            @scroll.debounce.60ms="measure()"
            role="list"
            class="relative -mx-2 mt-6 flex snap-x snap-mandatory gap-4 overflow-x-auto px-2 pb-2 pt-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
            @foreach ($videoTestimonials as $testimonial)
                @php
                    $photoUrl = $testimonial->customerPhotoUrl();
                    $posterUrl = $testimonial->posterUrl();
                    $videoUrl = $testimonial->videoUrl();
                @endphp
                <li
                    data-reveal="up stagger"
                    data-video-card
                    class="w-[70%] shrink-0 snap-start sm:w-[calc((100%-1rem)/2)] md:w-[calc((100%-2rem)/3)] lg:w-[calc((100%-3rem)/4)] xl:w-[calc((100%-4rem)/5)]"
                >
                    <article class="group/card flex h-full flex-col overflow-hidden rounded-3xl border border-line bg-surface shadow-md transition duration-300 hover:-translate-y-1 hover:shadow-xl motion-reduce:hover:translate-y-0">
                        {{-- The video, across the top of the card. --}}
                        <button
                            type="button"
                            x-data="{ previewing: false }"
                            @click="$dispatch('open-video-testimonial', @js($testimonial->playerData()))"
                            @if ($videoUrl)
                                @pointerenter="if ($event.pointerType === 'mouse') { previewing = true; $refs.preview.play().catch(() => {}); }"
                                @pointerleave="previewing = false; $refs.preview.pause();"
                            @endif
                            aria-label="Play {{ $testimonial->customer_name }}'s video testimonial"
                            class="group relative block aspect-[16/10] w-full overflow-hidden bg-gradient-to-br from-accent to-accent-strong focus-visible:outline-2 focus-visible:-outline-offset-4 focus-visible:outline-white"
                        >
                            <span class="absolute inset-0 flex items-center justify-center font-display text-6xl font-semibold text-white/20" aria-hidden="true">
                                {{ Str::substr($testimonial->customer_name, 0, 1) }}
                            </span>

                            @if ($videoUrl)
                                {{-- Without a cover image, the #t fragment makes the browser paint an early frame as the thumbnail. --}}
                                <video
                                    x-ref="preview"
                                    src="{{ $videoUrl }}#t=0.1"
                                    muted
                                    loop
                                    playsinline
                                    preload="metadata"
                                    aria-hidden="true"
                                    tabindex="-1"
                                    class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover/card:scale-105 motion-reduce:group-hover/card:scale-100"
                                ></video>
                            @endif

                            {{-- Above the video, and faded out while it previews. --}}
                            @if ($posterUrl)
                                <img
                                    src="{{ $posterUrl }}"
                                    alt="{{ $testimonial->poster_alt ?? '' }}"
                                    loading="lazy"
                                    :class="previewing ? 'opacity-0' : ''"
                                    class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover/card:scale-105 motion-reduce:group-hover/card:scale-100"
                                >
                            @endif

                            <span class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-black/10" aria-hidden="true"></span>

                            @if ($testimonial->loan_category)
                                <span class="absolute left-3 top-3 rounded-full bg-white/90 px-2.5 py-1 text-[0.7rem] font-semibold text-accent-strong shadow-sm backdrop-blur">
                                    {{ $testimonial->loan_category->getLabel() }}
                                </span>
                            @endif

                            <span class="absolute left-1/2 top-1/2 flex h-14 w-14 -translate-x-1/2 -translate-y-1/2 items-center justify-center" aria-hidden="true">
                                <span class="absolute inset-0 rounded-full bg-white/40 group-hover:animate-ping motion-reduce:group-hover:animate-none"></span>
                                <span class="relative flex h-12 w-12 items-center justify-center rounded-full bg-white/95 text-accent shadow-lg transition duration-300 group-hover:scale-110 motion-reduce:group-hover:scale-100">
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="ml-0.5 h-5 w-5"><path d="M6 4l10 6-10 6z" /></svg>
                                </span>
                            </span>
                        </button>

                        <div class="flex flex-1 flex-col p-3">
                            <button
                                type="button"
                                @click="$dispatch('open-video-testimonial', @js($testimonial->playerData()))"
                                class="inline-flex w-fit items-center gap-2 text-sm font-semibold text-accent transition hover:text-accent-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                            >
                                <span class="flex h-6 w-6 items-center justify-center rounded-full bg-accent text-white" aria-hidden="true">
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="ml-0.5 h-3 w-3"><path d="M6 4l10 6-10 6z" /></svg>
                                </span>
                                Watch {{ Str::before($testimonial->customer_name, ' ') }}&rsquo;s story
                            </button>

                            <div class="mt-3 flex items-center gap-3 border-t border-line pt-3">
                                <span class="relative shrink-0">
                                    @if ($photoUrl)
                                        <img
                                            src="{{ $photoUrl }}"
                                            alt="{{ $testimonial->customer_photo_alt ?: $testimonial->customer_name }}"
                                            loading="lazy"
                                            class="h-10 w-10 rounded-full object-cover ring-2 ring-accent-soft"
                                        >
                                    @else
                                        <span class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-br from-accent to-accent-strong font-display text-base font-semibold text-white ring-2 ring-accent-soft" aria-hidden="true">
                                            {{ Str::upper(Str::substr($testimonial->customer_name, 0, 1)) }}
                                        </span>
                                    @endif
                                    <span class="absolute -bottom-0.5 -right-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-pass text-white ring-2 ring-surface" title="Verified customer">
                                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="3" class="h-2.5 w-2.5" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 10.5 3 3 7-7" /></svg>
                                    </span>
                                </span>
                                <div class="min-w-0">
                                    <p class="truncate font-display text-base font-semibold text-ink">{{ $testimonial->customer_name }}</p>
                                    @if ($testimonial->role_location)
                                        <p class="truncate text-xs text-ink-muted">{{ $testimonial->role_location }}</p>
                                    @endif
                                </div>
                            </div>

                            @if ($testimonial->rating)
                                <div class="mt-2.5 flex gap-0.5 text-warn" role="img" aria-label="Rated {{ $testimonial->rating }} out of 5">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-4 w-4 {{ $i > $testimonial->rating ? 'text-line-strong' : '' }}" aria-hidden="true">
                                            <path d="M10 1.5l2.6 5.4 5.9.8-4.3 4.2 1 5.9L10 15l-5.2 2.8 1-5.9L1.5 7.7l5.9-.8L10 1.5Z" />
                                        </svg>
                                    @endfor
                                </div>
                            @endif

                            @if ($testimonial->headline)
                                <p class="mt-2.5 line-clamp-2 font-display text-sm font-semibold leading-snug text-ink">&ldquo;{{ $testimonial->headline }}&rdquo;</p>
                            @endif
                            @if ($testimonial->quote)
                                <p class="mt-1 line-clamp-3 text-xs leading-relaxed text-ink-muted">{{ $testimonial->quote }}</p>
                            @endif
                        </div>
                    </article>
                </li>
            @endforeach
        </ul>
    </div>

    @unless ($embedded)
        </section>
    @endunless
@endif
