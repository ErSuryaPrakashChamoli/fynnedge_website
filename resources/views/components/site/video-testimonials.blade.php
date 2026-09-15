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
        <section class="border-t border-line bg-surface-2">
            <div class="mx-auto max-w-7xl px-6 py-16 lg:px-8">
    @endunless

    <div
        {{ $attributes->class(['relative']) }}
        x-data="{
            scroll(direction) {
                this.$refs.track.scrollBy({ left: direction * this.$refs.track.clientWidth * 0.8, behavior: 'smooth' });
            },
        }"
        data-ai-context="Customer video testimonials"
    >
        <div data-reveal="up" class="flex flex-wrap items-end justify-between gap-4">
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

            @if ($videoTestimonials->count() > 1)
                <div class="hidden gap-2 sm:flex">
                    <button type="button" @click="scroll(-1)" aria-label="Previous videos" class="flex h-10 w-10 items-center justify-center rounded-full border border-line bg-surface text-ink shadow-sm transition hover:border-accent hover:text-accent">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12.5 15-5-5 5-5" /></svg>
                    </button>
                    <button type="button" @click="scroll(1)" aria-label="More videos" class="flex h-10 w-10 items-center justify-center rounded-full border border-line bg-surface text-ink shadow-sm transition hover:border-accent hover:text-accent">
                        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m7.5 15 5-5-5-5" /></svg>
                    </button>
                </div>
            @endif
        </div>

        <ul
            x-ref="track"
            role="list"
            class="mt-8 flex snap-x snap-mandatory gap-4 overflow-x-auto pb-4 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
        >
            @foreach ($videoTestimonials as $testimonial)
                @php
                    $posterUrl = $testimonial->posterUrl();
                    $videoUrl = $testimonial->videoUrl();
                @endphp
                <li
                    data-reveal="up stagger"
                    class="shrink-0 snap-start {{ $embedded ? 'w-[72%] sm:w-[42%] lg:w-[31%]' : 'w-[72%] sm:w-[42%] md:w-[31%] lg:w-[23.5%]' }}"
                >
                    <button
                        type="button"
                        x-data="{ previewing: false }"
                        @click="$dispatch('open-video-testimonial', @js($testimonial->playerData()))"
                        @if ($videoUrl)
                            @pointerenter="if ($event.pointerType === 'mouse') { previewing = true; $refs.preview.play().catch(() => {}); }"
                            @pointerleave="previewing = false; $refs.preview.pause();"
                        @endif
                        aria-label="Play {{ $testimonial->customer_name }}'s video testimonial"
                        class="group relative block aspect-[9/16] w-full overflow-hidden rounded-3xl bg-gradient-to-br from-accent to-accent-strong text-left shadow-md ring-1 ring-line transition duration-300 hover:-translate-y-1 hover:shadow-xl focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-accent motion-reduce:hover:translate-y-0"
                    >
                        <span class="absolute inset-0 flex items-center justify-center font-display text-7xl font-semibold text-white/20" aria-hidden="true">
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
                                class="absolute inset-0 h-full w-full object-cover"
                            ></video>
                        @endif

                        {{-- Above the video, and faded out while it previews. --}}
                        @if ($posterUrl)
                            <img
                                src="{{ $posterUrl }}"
                                alt="{{ $testimonial->poster_alt ?? '' }}"
                                loading="lazy"
                                :class="previewing ? 'opacity-0' : ''"
                                class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover:scale-105 motion-reduce:group-hover:scale-100"
                            >
                        @endif

                        <span class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/15 to-black/10" aria-hidden="true"></span>

                        @if ($testimonial->loan_category)
                            <span class="absolute left-3 top-3 rounded-full bg-white/90 px-2.5 py-1 text-xs font-semibold text-accent-strong shadow-sm backdrop-blur">
                                {{ $testimonial->loan_category->getLabel() }}
                            </span>
                        @endif

                        <span class="absolute left-1/2 top-1/2 flex h-16 w-16 -translate-x-1/2 -translate-y-1/2 items-center justify-center" aria-hidden="true">
                            <span class="absolute inset-0 rounded-full bg-white/40 group-hover:animate-ping motion-reduce:group-hover:animate-none"></span>
                            <span class="relative flex h-14 w-14 items-center justify-center rounded-full bg-white text-accent shadow-lg transition duration-300 group-hover:scale-110 motion-reduce:group-hover:scale-100">
                                <svg viewBox="0 0 20 20" fill="currentColor" class="ml-1 h-6 w-6"><path d="M6 4l10 6-10 6z" /></svg>
                            </span>
                        </span>

                        <span class="absolute inset-x-0 bottom-0 flex flex-col gap-1 p-4 text-white">
                            @if ($testimonial->rating)
                                <span class="flex gap-0.5 text-warn" aria-label="Rated {{ $testimonial->rating }} out of 5">
                                    @for ($i = 1; $i <= 5; $i++)
                                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5 {{ $i > $testimonial->rating ? 'opacity-30' : '' }}" aria-hidden="true">
                                            <path d="M10 1.5l2.6 5.4 5.9.8-4.3 4.2 1 5.9L10 15l-5.2 2.8 1-5.9L1.5 7.7l5.9-.8L10 1.5Z" />
                                        </svg>
                                    @endfor
                                </span>
                            @endif
                            @if ($testimonial->headline)
                                <span class="font-display text-base font-semibold leading-snug">&ldquo;{{ $testimonial->headline }}&rdquo;</span>
                            @endif
                            <span class="mt-1 text-sm font-semibold">{{ $testimonial->customer_name }}</span>
                            @if ($testimonial->role_location)
                                <span class="text-xs text-white/75">{{ $testimonial->role_location }}</span>
                            @endif
                        </span>
                    </button>

                    @if ($testimonial->quote)
                        <p class="mt-3 line-clamp-3 px-1 text-sm italic text-ink-muted">&ldquo;{{ $testimonial->quote }}&rdquo;</p>
                    @endif
                </li>
            @endforeach
        </ul>
    </div>

    @unless ($embedded)
            </div>
        </section>
    @endunless
@endif
