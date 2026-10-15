{{--
    Videos from FynnEdge's own social media posts for the page being rendered,
    chosen by the pages an admin pinned each one to (Admin → Content → Social
    media videos). The eyebrow, heading, intro and optional button are the
    "social_videos" Marketing section; the follow icons are the profiles under
    Settings → Social media. See App\Support\SocialVideos\SocialVideos.

    x-layouts.app renders this as its own band after the page content. Views
    that want it in a specific spot render it themselves and pass
    `handles-social-videos` to the layout — the homepage puts it just above
    the customer video testimonials.

    Built like x-site.video-testimonials: one swipeable row of cards, five
    across on extra-large screens, with no limit on how many. Each card
    mirrors a video testimonial card row for row (16:10 video, "▶ title",
    short info across the avatar row, caption where the stars sit), so the two
    sections are the same height. Once the row
    overflows it also moves on its own, one card every 3.5s, looping back to
    the start; it holds still while hovered, focused or recently touched, in a
    background tab, and for visitors who prefer reduced motion. Each card only dispatches
    `open-video-testimonial`; the layout's single shared player modal plays
    it. The original post is linked to, never embedded, so no Instagram,
    Facebook or LinkedIn script or cookie ever loads here.
--}}
{{-- Block form, not @php(...): Blade mis-pairs an inline @php with a later @endphp in the same view. --}}
@php
    $socialVideos = \App\Support\SocialVideos\SocialVideos::forCurrentPage();
@endphp

@if ($socialVideos->isNotEmpty())
    @php
        $copy = \App\Support\SocialVideos\SocialVideos::sectionCopy();
        $followLinks = \App\Support\SocialVideos\SocialVideos::followLinks();
    @endphp

    <section class="border-t border-line bg-surface">
        <div
            class="mx-auto max-w-7xl px-6 py-6 lg:px-8 lg:py-8"
            x-data="{
                atStart: true,
                atEnd: true,
                hovered: false,
                focused: false,
                touchedAt: 0,
                timer: null,
                reducedMotion: window.matchMedia('(prefers-reduced-motion: reduce)').matches,
                init() {
                    this.measure();
                    window.addEventListener('resize', () => this.measure(), { passive: true });
                    this.restart();
                },
                measure() {
                    const track = this.$refs.track;
                    this.atStart = track.scrollLeft <= 1;
                    this.atEnd = track.scrollLeft + track.clientWidth >= track.scrollWidth - 1;
                },
                scroll(direction) {
                    this.$refs.track.scrollBy({ left: direction * this.$refs.track.clientWidth * 0.8, behavior: 'smooth' });
                    this.restart();
                },
                restart() {
                    if (this.reducedMotion) {
                        return;
                    }
                    clearInterval(this.timer);
                    this.timer = setInterval(() => this.advance(), 3500);
                },
                advance() {
                    this.measure();
                    const track = this.$refs.track;
                    const card = track.querySelector('[data-social-video-card]');
                    const recentlyTouched = Date.now() - this.touchedAt < 6000;
                    if (! card || this.hovered || this.focused || recentlyTouched || document.hidden || (this.atStart && this.atEnd)) {
                        return;
                    }
                    if (this.atEnd) {
                        track.scrollTo({ left: 0, behavior: 'smooth' });
                        return;
                    }
                    track.scrollBy({ left: card.offsetWidth + (parseFloat(getComputedStyle(track).columnGap) || 0), behavior: 'smooth' });
                },
            }"
            @pointerenter="if ($event.pointerType === 'mouse') { hovered = true; }"
            @pointerleave="hovered = false"
            @focusin="focused = true"
            @focusout="focused = $el.contains($event.relatedTarget)"
            @touchstart.passive="touchedAt = Date.now()"
            data-social-videos-autoplay
            data-ai-context="Social media videos"
        >
            <div data-reveal="up" class="relative flex flex-wrap items-end justify-between gap-4">
                <div class="min-w-0">
                    <x-ui.badge tone="accent" class="max-w-full">{{ $copy['eyebrow'] }}</x-ui.badge>
                    <h2 class="mt-4 max-w-lg text-balance font-display text-2xl font-semibold tracking-tight text-ink sm:text-3xl">{{ $copy['heading'] }}</h2>
                    <p class="mt-2 max-w-2xl text-ink-muted">{{ $copy['intro'] }}</p>
                </div>

                <div class="flex flex-wrap items-center gap-2 max-sm:contents">
                    {{-- Phones skip these (the footer has the same links), so the heading stays as short as the testimonials' heading. --}}
                    @if ($followLinks !== [])
                        <div class="hidden items-center gap-2 sm:flex">
                            @foreach ($followLinks as $link)
                                <a
                                    href="{{ $link['url'] }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="{{ __('Follow FynnEdge on :platform', ['platform' => $link['label']]) }}"
                                    data-social-follow
                                    class="flex h-10 w-10 items-center justify-center rounded-full border border-line bg-surface text-ink-muted shadow-sm transition hover:border-accent hover:text-accent"
                                >
                                    <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4" aria-hidden="true"><path d="{{ $link['icon'] }}" /></svg>
                                </a>
                            @endforeach
                        </div>
                    @endif

                    @if ($copy['ctaUrl'])
                        <x-ui.button tag="a" :href="$copy['ctaUrl']" variant="secondary" size="sm">{{ $copy['ctaLabel'] }}</x-ui.button>
                    @endif

                    <div x-show="! (atStart && atEnd)" x-cloak class="flex gap-2">
                        <button type="button" @click="scroll(-1)" :disabled="atStart" aria-label="{{ __('Previous social videos') }}" class="flex h-10 w-10 items-center justify-center rounded-full border border-line bg-surface text-ink shadow-sm transition hover:border-accent hover:text-accent disabled:pointer-events-none disabled:opacity-40">
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m12.5 15-5-5 5-5" /></svg>
                        </button>
                        <button type="button" @click="scroll(1)" :disabled="atEnd" aria-label="{{ __('More social videos') }}" class="flex h-10 w-10 items-center justify-center rounded-full border border-line bg-surface text-ink shadow-sm transition hover:border-accent hover:text-accent disabled:pointer-events-none disabled:opacity-40">
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m7.5 15 5-5-5-5" /></svg>
                        </button>
                    </div>
                </div>
            </div>

            <ul
                x-ref="track"
                @scroll.debounce.60ms="measure()"
                role="list"
                class="relative -mx-2 mt-4 flex snap-x snap-mandatory gap-4 overflow-x-auto px-2 pb-2 pt-2 [scrollbar-width:none] [&::-webkit-scrollbar]:hidden"
            >
                @foreach ($socialVideos as $video)
                    @php
                        $posterUrl = $video->posterUrl();
                        $videoUrl = $video->videoUrl();
                        $postUrl = $video->postUrl();
                    @endphp
                    <li
                        data-reveal="up stagger"
                        data-social-video-card
                        class="w-[70%] shrink-0 snap-start sm:w-[calc((100%-1rem)/2)] md:w-[calc((100%-2rem)/3)] lg:w-[calc((100%-3rem)/4)] xl:w-[calc((100%-4rem)/5)]"
                    >
                        <article class="group/card flex h-full flex-col overflow-hidden rounded-3xl border border-line bg-surface shadow-md transition duration-300 hover:-translate-y-1 hover:shadow-xl motion-reduce:hover:translate-y-0">
                            {{-- The video, across the top of the card. --}}
                            <button
                                type="button"
                                x-data="{
                                    previewing: false,
                                    startPreview() {
                                        const video = this.$refs.preview;
                                        if (! video) {
                                            return;
                                        }
                                        this.previewing = true;
                                        video.muted = false;
                                        video.play().catch(() => {
                                            video.muted = true;
                                            video.play().catch(() => {});
                                        });
                                    },
                                    stopPreview() {
                                        const video = this.$refs.preview;
                                        if (! video) {
                                            return;
                                        }
                                        this.previewing = false;
                                        video.pause();
                                        video.muted = true;
                                    },
                                }"
                                @click="stopPreview(); $dispatch('open-video-testimonial', @js($video->playerData()))"
                                @if ($videoUrl)
                                    @pointerenter="if ($event.pointerType === 'mouse') { startPreview(); }"
                                    @pointerleave="stopPreview()"
                                @endif
                                aria-label="{{ __('Play video: :title', ['title' => $video->title]) }}"
                                class="group relative block aspect-[16/10] w-full overflow-hidden bg-gradient-to-br from-accent to-accent-strong focus-visible:outline-2 focus-visible:-outline-offset-4 focus-visible:outline-white"
                            >
                                @if ($videoUrl)
                                    {{-- Without a cover image, the #t fragment makes the browser paint the frame the admin picked (or an early one) as the thumbnail. --}}
                                    <video
                                        x-ref="preview"
                                        src="{{ $videoUrl }}#t={{ $video->coverFrameTime() }}"
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
                                        alt="{{ $video->poster_alt ?? '' }}"
                                        loading="lazy"
                                        :class="previewing ? 'opacity-0' : ''"
                                        class="absolute inset-0 h-full w-full object-cover transition duration-500 group-hover/card:scale-105 motion-reduce:group-hover/card:scale-100"
                                    >
                                @endif

                                <span class="absolute inset-0 bg-gradient-to-t from-black/50 via-transparent to-black/10" aria-hidden="true"></span>

                                <span class="absolute left-3 top-3 inline-flex items-center gap-1.5 rounded-full bg-white/90 px-2.5 py-1 text-[0.7rem] font-semibold text-accent-strong shadow-sm backdrop-blur">
                                    <svg viewBox="0 0 24 24" fill="currentColor" class="h-3 w-3" aria-hidden="true"><path d="{{ $video->platform->iconPath() }}" /></svg>
                                    {{ $video->platform->getLabel() }}
                                </span>

                                <span class="absolute left-1/2 top-1/2 flex h-14 w-14 -translate-x-1/2 -translate-y-1/2 items-center justify-center" aria-hidden="true">
                                    <span class="absolute inset-0 rounded-full bg-white/40 group-hover:animate-ping motion-reduce:group-hover:animate-none"></span>
                                    <span class="relative flex h-12 w-12 items-center justify-center rounded-full bg-white/95 text-accent shadow-lg transition duration-300 group-hover:scale-110 motion-reduce:group-hover:scale-100">
                                        <svg viewBox="0 0 20 20" fill="currentColor" class="ml-0.5 h-5 w-5"><path d="M6 4l10 6-10 6z" /></svg>
                                    </span>
                                </span>
                            </button>

                            {{-- Same rows, sizes and spacing as a video testimonial card, so the two sections line up. --}}
                            <div class="flex flex-1 flex-col p-3">
                                <button
                                    type="button"
                                    @click="$dispatch('open-video-testimonial', @js($video->playerData()))"
                                    title="{{ $video->title }}"
                                    class="inline-flex w-full min-w-0 items-center gap-2 text-left text-sm font-semibold text-accent transition hover:text-accent-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                                >
                                    <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-accent text-white" aria-hidden="true">
                                        <svg viewBox="0 0 20 20" fill="currentColor" class="ml-0.5 h-3 w-3"><path d="M6 4l10 6-10 6z" /></svg>
                                    </span>
                                    <span class="truncate">{{ $video->title }}</span>
                                </button>

                                {{-- The short info gets the full width, in a 40px block like the testimonial's photo-and-name row: one line above the post link, or up to two lines when there is no link. --}}
                                <div class="mt-3 border-t border-line pt-3">
                                    <div class="flex min-h-10 min-w-0 flex-col justify-center">
                                        <p
                                            title="{{ $video->cardLabel() }}"
                                            @class([
                                                'font-display text-base font-semibold text-ink',
                                                'truncate' => $postUrl,
                                                'line-clamp-2 leading-5' => ! $postUrl,
                                            ])
                                        >{{ $video->cardLabel() }}</p>
                                        @if ($postUrl)
                                            <a
                                                href="{{ $postUrl }}"
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                class="flex w-fit items-center gap-1 text-xs leading-4 font-semibold text-accent transition hover:text-accent-strong focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
                                            >
                                                {{ __('View on :platform', ['platform' => $video->platform->getLabel()]) }}
                                                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3 w-3" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M8 4h8v8M16 4 5 15" /></svg>
                                            </a>
                                        @endif
                                    </div>
                                </div>

                                {{-- Where a testimonial card has its star rating: one line, kept even when empty so every card is the same height. --}}
                                <p class="mt-2.5 h-4 truncate text-xs leading-4 text-ink-muted">{{ $video->caption }}</p>
                            </div>
                        </article>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
@endif
