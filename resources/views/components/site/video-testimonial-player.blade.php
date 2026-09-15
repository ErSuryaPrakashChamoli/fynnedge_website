{{--
    The one video player on the page, shared by every video testimonial card
    and the floating bubble: they dispatch `open-video-testimonial` with
    VideoTestimonial::playerData(), and this plays it with sound.

    The <video>/<iframe> is created inside x-if only when opened and destroyed
    on close, so nothing downloads until a visitor asks for it and closing
    really stops playback. YouTube plays through youtube-nocookie.com, which
    SecurityHeaders allows in frame-src.
--}}
@if (\App\Support\Testimonials\VideoTestimonials::forCurrentPage()->isNotEmpty())
    <div
        x-data="{
            open: false,
            video: null,
            returnFocus: null,
            show(video) {
                this.returnFocus = document.activeElement;
                this.video = video;
                this.open = true;
                document.documentElement.style.overflow = 'hidden';
                this.$nextTick(() => this.$refs.close.focus());
            },
            hide() {
                if (! this.open) {
                    return;
                }
                this.open = false;
                this.video = null;
                document.documentElement.style.overflow = '';
                this.returnFocus?.focus();
            },
        }"
        @open-video-testimonial.window="show($event.detail)"
        @keydown.escape.window="hide()"
    >
        <div
            x-show="open"
            x-cloak
            x-transition.opacity.duration.200ms
            role="dialog"
            aria-modal="true"
            :aria-label="video ? video.title : 'Video testimonial'"
            @click.self="hide()"
            class="fixed inset-0 z-60 flex items-center justify-center bg-black/85 p-4 backdrop-blur-sm sm:p-10"
        >
            <div class="relative flex w-full max-w-4xl flex-col items-center" @click.self="hide()">
                <button
                    x-ref="close"
                    type="button"
                    @click="hide()"
                    aria-label="Close video"
                    class="mb-3 flex h-10 w-10 items-center justify-center self-end rounded-full bg-white/15 text-white transition hover:bg-white/25 focus-visible:outline-2 focus-visible:outline-white"
                >
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" class="h-5 w-5" aria-hidden="true"><path stroke-linecap="round" d="M5 5l10 10M15 5 5 15" /></svg>
                </button>

                <template x-if="video && video.type === 'upload'">
                    <video :src="video.src" controls autoplay playsinline class="max-h-[78vh] max-w-full rounded-2xl bg-black shadow-2xl"></video>
                </template>

                <template x-if="video && video.type === 'youtube'">
                    <div :class="video.portrait ? 'aspect-[9/16] h-[78vh] max-w-full' : 'aspect-video w-full'" class="overflow-hidden rounded-2xl bg-black shadow-2xl">
                        <iframe
                            :src="video.src"
                            :title="video.title"
                            class="h-full w-full"
                            allow="autoplay; encrypted-media; picture-in-picture; fullscreen"
                            allowfullscreen
                        ></iframe>
                    </div>
                </template>

                <p x-show="video" x-text="video ? video.title : ''" class="mt-4 text-center font-display text-lg text-white"></p>
            </div>
        </div>
    </div>
@endif
