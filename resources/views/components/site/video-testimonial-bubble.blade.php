{{--
    The floating corner preview: the first video on this page an admin marked
    "Also pop it up in the corner". It slides in a few seconds after load with
    a soft pulse, and tapping it opens the shared player with sound.

    The preview video is only fetched on wider screens with motion allowed —
    a looping clip on every page would otherwise eat a phone's data allowance.
    Closing it hides that same video for the rest of the browser session.
--}}
@php
    $floatingTestimonial = \App\Support\Testimonials\VideoTestimonials::floatingForCurrentPage();
@endphp

@if ($floatingTestimonial)
    @php
        $posterUrl = $floatingTestimonial->posterUrl();
        $videoUrl = $floatingTestimonial->videoUrl();
        $firstName = Str::before(trim($floatingTestimonial->customer_name), ' ');
    @endphp

    <div
        x-data="{
            visible: false,
            storageKey: 'fynnedge.video-testimonial-dismissed',
            testimonialId: @js($floatingTestimonial->public_id),
            init() {
                try {
                    if (sessionStorage.getItem(this.storageKey) === this.testimonialId) {
                        return;
                    }
                } catch (error) {}

                setTimeout(() => {
                    this.visible = true;
                    const preview = this.$refs.preview;
                    if (preview && window.matchMedia('(min-width: 640px) and (prefers-reduced-motion: no-preference)').matches) {
                        preview.src = preview.dataset.src;
                        preview.play().catch(() => {});
                    }
                }, 3000);
            },
            dismiss() {
                this.visible = false;
                this.$refs.preview?.pause();
                try {
                    sessionStorage.setItem(this.storageKey, this.testimonialId);
                } catch (error) {}
            },
        }"
        x-show="visible"
        x-cloak
        x-transition:enter="transition duration-500 ease-out"
        x-transition:enter-start="translate-y-10 scale-75 opacity-0"
        x-transition:enter-end="translate-y-0 scale-100 opacity-100"
        x-transition:leave="transition duration-200 ease-in"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="translate-y-4 opacity-0"
        role="complementary"
        aria-label="Customer video testimonial"
        {{-- Lifted clear of the promo bar while it is showing; x-site.promo-bar sets the offset. --}}
        style="margin-bottom: var(--promo-bar-offset, 0px)"
        class="fixed bottom-4 left-4 z-40 transition-[margin-bottom] duration-300 sm:bottom-6 sm:left-6"
        data-video-testimonial-bubble
    >
        <div class="relative">
            <button
                type="button"
                @click="$dispatch('open-video-testimonial', @js($floatingTestimonial->playerData()))"
                aria-label="Watch {{ $floatingTestimonial->customer_name }}'s video testimonial"
                class="group relative block h-40 w-26 animate-soft-pulse overflow-hidden rounded-2xl border-2 border-white bg-gradient-to-br from-accent to-accent-strong shadow-xl transition duration-300 hover:scale-105 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-accent motion-reduce:animate-none motion-reduce:hover:scale-100 sm:h-48 sm:w-30"
            >
                <span class="absolute inset-0 flex items-center justify-center font-display text-5xl font-semibold text-white/25" aria-hidden="true">
                    {{ Str::substr($floatingTestimonial->customer_name, 0, 1) }}
                </span>

                @if ($posterUrl)
                    <img src="{{ $posterUrl }}" alt="" class="absolute inset-0 h-full w-full object-cover">
                @endif

                @if ($videoUrl)
                    <video
                        x-ref="preview"
                        data-src="{{ $videoUrl }}"
                        muted
                        loop
                        playsinline
                        preload="none"
                        aria-hidden="true"
                        tabindex="-1"
                        @playing="$el.classList.remove('opacity-0')"
                        class="absolute inset-0 h-full w-full object-cover opacity-0 transition-opacity duration-500"
                    ></video>
                @endif

                <span class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-black/20" aria-hidden="true"></span>

                <span class="absolute left-1.5 top-1.5 flex items-center gap-1 rounded-full bg-black/45 px-1.5 py-0.5 text-[0.625rem] font-semibold uppercase tracking-wide text-white" aria-hidden="true">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-pass motion-reduce:animate-none"></span>
                    Customer
                </span>

                <span class="absolute left-1/2 top-1/2 flex h-10 w-10 -translate-x-1/2 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-accent shadow-md transition group-hover:scale-110" aria-hidden="true">
                    <svg viewBox="0 0 20 20" fill="currentColor" class="ml-0.5 h-4 w-4"><path d="M6 4l10 6-10 6z" /></svg>
                </span>

                <span class="absolute inset-x-0 bottom-0 p-2 text-center text-[0.6875rem] font-semibold leading-tight text-white">
                    Watch {{ $firstName }}'s story
                </span>
            </button>

            <button
                type="button"
                @click="dismiss()"
                aria-label="Close video testimonial"
                class="absolute -right-2.5 -top-2.5 flex h-7 w-7 items-center justify-center rounded-full bg-surface text-ink shadow-md ring-1 ring-line transition hover:bg-surface-2"
            >
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-3.5 w-3.5" aria-hidden="true"><path stroke-linecap="round" d="M5 5l10 10M15 5 5 15" /></svg>
            </button>
        </div>
    </div>
@endif
