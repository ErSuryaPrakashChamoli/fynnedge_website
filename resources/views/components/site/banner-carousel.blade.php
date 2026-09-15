@props(['banners'])

{{--
    The hero banner's right-hand column ("crawler"): admin-managed promotional
    images that both advance on their own and can be driven by hand — arrows,
    dots, left/right arrow keys, and a pause toggle. Auto-play pauses while the
    pointer is over it or focus is inside, and resumes on leaving.

    Height is deliberately HARDCODED here and mirrored by the left column in
    home.blade.php. It is sized so the header, ticker, banner, lender marquee
    AND stats strip all fit a ~732px CSS viewport (a 1080p screen at 125% OS
    scaling) with nothing below the fold — that, not the raw pixel value, is
    the constraint. Raising it pushes the marquee and stats off-screen. Admins
    control the images and copy; they do not control the sizing. Uploads are
    cropped with object-cover against this box — BannerForm states the target
    dimensions.

    DIRECTION: slides travel left-to-right — each new slide enters from the
    left and pushes the previous one off to the right. That is why the track is
    `flex-row-reverse` with a POSITIVE translate: row-reverse lays the first
    slide against the right edge of the box and stacks the rest off-screen to
    its left, so increasing the translate walks rightwards through them while
    still showing slides in the admin's sort_order. Simply flipping the sign on
    a normal row would reverse the running order instead of the motion.

    SEAMLESS WRAP: a copy of the first slide is appended after the last one, so
    passing the end animates onto that copy in the same direction as every
    other step. Once that transition finishes the track jumps back to the real
    first slide with the transition suppressed — identical pixels, so the swap
    is invisible. Without it the track had to travel its whole width back to
    the start, visibly rewinding through every slide in the wrong direction.
--}}

@if ($banners->isNotEmpty())
    @php
        // The trailing duplicate only earns its keep when there is something to
        // loop between; a single banner never moves.
        $slides = $banners->count() > 1 ? $banners->concat([$banners->first()]) : $banners;
        $cloneIndex = $banners->count();
    @endphp

    {{--
        Per-banner button colours. Banner::contentStyle() sets the --slide-cta-*
        properties on each slide's content block; anything a banner leaves
        unset falls back to the site-wide banner Settings, then to the site
        accent. The extra .banner-content in the selector outranks the
        site-wide rule SiteThemeStyles emits into <head>. Nothing here is admin
        input — only the property values are, and those are hex-validated.
    --}}
    <style>
        #hero-banner .banner-content .banner-cta{background-color:var(--slide-cta-bg,var(--banner-button-bg,var(--color-accent)));color:var(--slide-cta-text,var(--banner-button-text,#ffffff));}
        #hero-banner .banner-content .banner-cta:hover{background-color:var(--slide-cta-hover,var(--slide-cta-bg,var(--banner-button-bg-hover,var(--color-accent-strong))));}
    </style>

    <div
        x-data="{
            current: 0,
            total: {{ $banners->count() }},
            timer: null,
            playing: true,
            hovering: false,
            focused: false,
            /* Suppresses the transition for the invisible jump off the clone. */
            animating: true,
            /*
                One interval for the life of the component that no-ops while
                paused, rather than a timer torn down and rebuilt on every
                hover/focus. A start/stop design has to get every teardown
                path exactly right; if any one of them leaves a flag set (a
                pointerleave that never fires, a focusout swallowed by a
                re-render) the slideshow is dead for the rest of the visit
                with no way back. Here a stuck flag only ever costs one tick.
            */
            init() {
                this.arm();
            },
            destroy() {
                clearInterval(this.timer);
            },
            arm() {
                clearInterval(this.timer);
                this.timer = setInterval(() => {
                    if (this.paused) {
                        return;
                    }
                    this.next();
                }, 6000);
            },
            get paused() {
                return this.total < 2 || ! this.playing || this.hovering || this.focused;
            },
            next() {
                if (this.total < 2 || this.current > this.total - 1) {
                    return;
                }
                this.current++;
            },
            /*
                Stepping back off the first slide would otherwise sweep the
                whole track forwards. Jump silently onto the trailing clone
                (identical pixels to slide one) and animate one step back from
                there, so the motion matches an ordinary step.
            */
            prev() {
                if (this.total < 2) {
                    return;
                }
                if (this.current === 0) {
                    this.animating = false;
                    this.current = this.total;
                    requestAnimationFrame(() => requestAnimationFrame(() => {
                        this.animating = true;
                        this.current = this.total - 1;
                    }));

                    return;
                }
                this.current--;
            },
            /*
                Fires once the slide onto the clone has finished. The clone and
                the real first slide render identically, so swapping between
                them with the transition off cannot be seen.
            */
            settle(event) {
                if (event.propertyName !== 'transform' || this.current !== this.total) {
                    return;
                }
                this.animating = false;
                this.current = 0;
                requestAnimationFrame(() => requestAnimationFrame(() => {
                    this.animating = true;
                }));
            },
            /* Manual moves restart the countdown so a click landing late in
               the interval is not overridden a moment later. */
            go(index) {
                this.current = (index + this.total) % this.total;
                this.arm();
            },
            step(direction) {
                direction > 0 ? this.next() : this.prev();
                this.arm();
            },
            toggle() {
                this.playing = ! this.playing;
            },
        }"
        {{--
            pointerenter/leave rather than mouseenter/leave, filtered on
            pointerType: a touch screen fires a synthetic mouseenter on tap and
            never a matching mouseleave, which would latch `hovering` true and
            leave auto-play dead for the rest of the visit after one tap.
        --}}
        @pointerenter="if ($event.pointerType !== 'touch') { hovering = true }"
        @pointerleave="if ($event.pointerType !== 'touch') { hovering = false }"
        @focusin="focused = true"
        @focusout="focused = false"
        @keydown.left.prevent="step(-1)"
        @keydown.right.prevent="step(1)"
        role="region"
        aria-roledescription="carousel"
        aria-label="Promotional banners"
        id="hero-banner"
        class="group relative h-[200px] w-full overflow-hidden rounded-3xl border border-line md:h-[300px] lg:h-[330px] xl:h-[350px]"
    >
        <div
            class="flex h-full flex-row-reverse transition-transform duration-700 ease-out motion-reduce:transition-none"
            :style="`transform: translateX(${current * 100}%)${animating ? '' : '; transition: none'}`"
            @transitionend="settle($event)"
        >
            @foreach ($slides as $index => $banner)
                @php
                    $isClone = $index === $cloneIndex;
                    $verticalClasses = match ($banner->content_vertical_align) {
                        \App\Enums\BannerVerticalAlignment::Top => 'justify-start',
                        \App\Enums\BannerVerticalAlignment::Center => 'justify-center',
                        default => 'justify-end',
                    };
                    $horizontalClasses = match ($banner->content_horizontal_align) {
                        \App\Enums\BannerHorizontalAlignment::Center => 'items-center text-center',
                        \App\Enums\BannerHorizontalAlignment::Right => 'items-end text-right',
                        default => 'items-start text-left',
                    };
                    $contentStyle = $banner->contentStyle();
                @endphp
                <div class="relative h-full w-full shrink-0 basis-full" @if ($isClone) aria-hidden="true" @endif>
                    <img
                        src="{{ $banner->imageUrl() }}"
                        alt="{{ $isClone ? '' : ($banner->image_alt ?: $banner->heading) }}"
                        class="h-full w-full object-cover"
                    >
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"></div>
                    {{--
                        With more than one banner the arrows (vertically centred
                        at each side), the dots and the pause button sit over the
                        slide, so the text is inset to clear them: 48px sides and
                        40px bottom on a phone, 64px sides from sm up. On a phone
                        that inset is !important, overriding an admin's side
                        spacing — a percentage of a 342px-wide box cannot clear a
                        44px arrow, and the heading or button ended up under it.
                        Headings and subtitles are clamped to two lines there so
                        they cannot push the button out of the 200px box.
                    --}}
                    <div
                        @class([
                            'banner-content absolute inset-0 flex flex-col',
                            $verticalClasses,
                            $horizontalClasses,
                            'max-sm:px-12! pb-10 pt-5 sm:px-16 sm:py-10' => $banners->count() > 1,
                            'p-6 sm:p-10' => $banners->count() === 1,
                        ])
                        @if ($contentStyle !== '') style="{{ $contentStyle }}" @endif
                    >
                        @if ($banner->heading)
                            <h3 class="banner-heading line-clamp-2 max-w-2xl font-display text-xl font-semibold text-white sm:line-clamp-none sm:text-3xl">
                                {{ $banner->heading }}
                            </h3>
                        @endif
                        @if ($banner->subtitle)
                            <p class="banner-subtitle mt-1.5 line-clamp-2 max-w-xl text-sm text-white/85 sm:mt-2 sm:line-clamp-none sm:text-base">
                                {{ $banner->subtitle }}
                            </p>
                        @endif
                        @if ($banner->cta_label && $banner->cta_url)
                            {{-- The clone is a visual duplicate: keep it out of the tab order and the a11y tree. --}}
                            <x-ui.button
                                tag="a"
                                :href="$banner->cta_url"
                                variant="primary"
                                size="md"
                                class="banner-cta mt-3 sm:mt-4"
                                :tabindex="$isClone ? '-1' : null"
                            >
                                {{ $banner->cta_label }}
                            </x-ui.button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if ($banners->count() > 1)
            {{--
                Controls are always visible, never revealed on hover: a
                hover-gated control simply does not exist on a touch screen,
                which left the carousel auto-only on phones and tablets.
                Their footprint (arrows 44px from each side on a phone, 52px
                from sm up) is what the slide text's inset above clears —
                resize one and re-check the other.
            --}}
            <button
                type="button"
                @click="step(-1)"
                aria-label="Previous banner"
                class="absolute left-2 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/80 text-ink shadow-md transition hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:left-3 sm:h-10 sm:w-10"
            >
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="m12.5 15-5-5 5-5" /></svg>
            </button>
            <button
                type="button"
                @click="step(1)"
                aria-label="Next banner"
                class="absolute right-2 top-1/2 flex h-9 w-9 -translate-y-1/2 items-center justify-center rounded-full bg-white/80 text-ink shadow-md transition hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:right-3 sm:h-10 sm:w-10"
            >
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="m7.5 15 5-5-5-5" /></svg>
            </button>

            <div class="absolute inset-x-0 bottom-3 flex items-center justify-center gap-1.5">
                @foreach ($banners as $index => $banner)
                    <button
                        type="button"
                        @click="go({{ $index }})"
                        :class="(current % total) === {{ $index }} ? 'w-6 bg-white' : 'w-1.5 bg-white/50 hover:bg-white/80'"
                        :aria-current="(current % total) === {{ $index }} ? 'true' : 'false'"
                        class="h-1.5 rounded-full transition-all"
                        aria-label="Go to banner {{ $index + 1 }}"
                    ></button>
                @endforeach
            </div>

            {{--
                Auto-advancing content needs a way to stop it (WCAG 2.2.2), so
                this is always present whenever there is more than one slide.
                Reduced motion deliberately does NOT disable auto-play here —
                it removes the sliding animation (motion-reduce:transition-none
                on the track) and leaves this control to stop the rotation,
                rather than silently killing the slideshow with no visible
                reason and no way to turn it back on.
            --}}
            <button
                type="button"
                @click="toggle()"
                :aria-label="playing ? 'Pause banner slideshow' : 'Play banner slideshow'"
                class="absolute bottom-2.5 right-3 flex h-7 w-7 items-center justify-center rounded-full bg-white/80 text-ink shadow-md transition hover:bg-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white"
            >
                <svg x-show="playing" viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true"><path d="M6 4h3v12H6zM11 4h3v12h-3z" /></svg>
                <svg x-show="! playing" x-cloak viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true"><path d="M6 4l10 6-10 6z" /></svg>
            </button>
        @endif
    </div>
@endif
