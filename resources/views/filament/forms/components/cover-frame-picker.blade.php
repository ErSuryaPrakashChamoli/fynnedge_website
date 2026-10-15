@php
    $previewUrl = $field->getVideoPreviewUrl();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
>
    <div
        x-data="{
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            useCurrentFrame() {
                this.state = Math.round(this.$refs.video.currentTime * 10) / 10;
            },
            useFirstFrame() {
                this.state = null;
                this.$refs.video.currentTime = 0;
            },
        }"
        {{ $getExtraAttributeBag()->class(['grid gap-3']) }}
    >
        @if ($previewUrl)
            {{-- 16:10 and object-cover, exactly like the card on the website, so what is seen here is what visitors see. --}}
            <video
                x-ref="video"
                wire:key="cover-frame-{{ md5($previewUrl) }}"
                src="{{ $previewUrl }}"
                controls
                muted
                playsinline
                preload="metadata"
                x-on:loadedmetadata="if (state) { $el.currentTime = state }"
                class="aspect-[16/10] w-full max-w-md rounded-xl bg-black object-cover"
            ></video>

            <div class="flex flex-wrap items-center gap-3">
                <x-filament::button size="sm" x-on:click="useCurrentFrame()">
                    Use this frame
                </x-filament::button>
                <x-filament::button size="sm" color="gray" outlined x-show="state" x-cloak x-on:click="useFirstFrame()">
                    Use the first frame
                </x-filament::button>
                <span
                    class="text-sm text-gray-600 dark:text-gray-400"
                    x-text="state ? `Cover: the frame at ${state} seconds` : 'Cover: the first frame'"
                ></span>
            </div>
        @else
            <p class="text-sm text-gray-500 dark:text-gray-400">
                Upload the video above and it will appear here. Then pause it on the moment you want and press "Use this frame". (WebM files: save first, then come back to pick the frame.)
            </p>
        @endif
    </div>
</x-dynamic-component>
