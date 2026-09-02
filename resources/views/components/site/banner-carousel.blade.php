@props(['banners'])

@if ($banners->isNotEmpty())
    <div
        x-data="{
            current: 0,
            total: {{ $banners->count() }},
            timer: null,
            start() {
                if (this.total < 2 || window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                    return;
                }
                this.stop();
                this.timer = setInterval(() => this.next(), 6000);
            },
            stop() {
                clearInterval(this.timer);
            },
            next() {
                this.current = (this.current + 1) % this.total;
            },
            prev() {
                this.current = (this.current - 1 + this.total) % this.total;
            },
        }"
        x-init="start()"
        @mouseenter="stop()"
        @mouseleave="start()"
        class="group relative mt-10 overflow-hidden rounded-3xl border border-line"
    >
        <div class="flex transition-transform duration-700 ease-out" :style="`transform: translateX(-${current * 100}%)`">
            @foreach ($banners as $banner)
                <div class="relative w-full shrink-0 basis-full">
                    <img
                        src="{{ $banner->imageUrl() }}"
                        alt="{{ $banner->image_alt ?: $banner->heading }}"
                        class="h-64 w-full object-cover sm:h-80 lg:h-96"
                    >
                    <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-black/10 to-transparent"></div>
                    <div class="absolute inset-x-0 bottom-0 p-6 sm:p-10">
                        <h3 class="max-w-2xl font-display text-2xl font-semibold text-white sm:text-3xl">
                            {{ $banner->heading }}
                        </h3>
                        @if ($banner->subtitle)
                            <p class="mt-2 max-w-xl text-sm text-white/85 sm:text-base">
                                {{ $banner->subtitle }}
                            </p>
                        @endif
                        @if ($banner->cta_label && $banner->cta_url)
                            <x-ui.button tag="a" :href="$banner->cta_url" variant="inverse" size="md" class="mt-4">
                                {{ $banner->cta_label }}
                            </x-ui.button>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>

        @if ($banners->count() > 1)
            <button
                type="button"
                @click="prev()"
                aria-label="Previous banner"
                class="absolute left-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-ink opacity-0 shadow-md transition-opacity hover:bg-white focus-visible:opacity-100 group-hover:opacity-100"
            >
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="m12.5 15-5-5 5-5" /></svg>
            </button>
            <button
                type="button"
                @click="next()"
                aria-label="Next banner"
                class="absolute right-3 top-1/2 flex h-10 w-10 -translate-y-1/2 items-center justify-center rounded-full bg-white/90 text-ink opacity-0 shadow-md transition-opacity hover:bg-white focus-visible:opacity-100 group-hover:opacity-100"
            >
                <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4"><path stroke-linecap="round" stroke-linejoin="round" d="m7.5 15 5-5-5-5" /></svg>
            </button>

            <div class="absolute inset-x-0 bottom-3 flex justify-center gap-1.5">
                @foreach ($banners as $index => $banner)
                    <button
                        type="button"
                        @click="current = {{ $index }}"
                        :class="current === {{ $index }} ? 'w-6 bg-white' : 'w-1.5 bg-white/50'"
                        class="h-1.5 rounded-full transition-all"
                        aria-label="Go to banner {{ $index + 1 }}"
                    ></button>
                @endforeach
            </div>
        @endif
    </div>
@endif
