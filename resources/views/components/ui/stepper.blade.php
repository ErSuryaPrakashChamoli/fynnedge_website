@props(['steps', 'current', 'variant' => 'list'])

{{--
    Drives the customer journey progress indicator (see Phase 5 journey engine).
    $steps: array<string> of step titles. $current: 0-based index of the active step.
    variant="arrows" (the credit-score check) draws a horizontal chevron chart instead:
    one row when its container has room, two rows of two below that. The chevron
    shape and colours live in app.css (.step-arrow).
--}}
@if ($variant === 'arrows')
    <div {{ $attributes->class('@container overflow-hidden rounded-lg') }}>
        <ol class="grid grid-cols-2 gap-y-1.5 @min-[30rem]:grid-cols-[repeat(var(--step-count),minmax(0,1fr))]" style="--step-count: {{ count($steps) }}">
            @foreach ($steps as $index => $step)
                @php
                    $state = $index < $current ? 'done' : ($index === $current ? 'active' : 'upcoming');
                @endphp
                <li
                    data-step-state="{{ $state }}"
                    @if ($state === 'active') aria-current="step" @endif
                    @class([
                        'step-arrow flex min-h-12 items-center gap-1.5',
                        'step-arrow-done' => $state === 'done',
                        'step-arrow-active' => $state === 'active',
                        'step-arrow-upcoming' => $state === 'upcoming',
                        '-ml-2' => $index % 2 === 1,
                        '@min-[30rem]:-ml-2' => $index > 0 && $index % 2 === 0,
                    ])
                >
                    <span class="step-arrow-icon flex h-5 w-5 shrink-0 items-center justify-center rounded-full">
                        @if ($state === 'done')
                            <svg viewBox="0 0 20 20" fill="currentColor" class="h-3 w-3" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                        @elseif ($state === 'active')
                            <span class="relative flex h-2 w-2" aria-hidden="true">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-current opacity-75 motion-reduce:animate-none"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-current"></span>
                            </span>
                        @endif
                    </span>
                    <span class="min-w-0 text-xs leading-tight @2xl:text-sm">{{ $step }}</span>
                </li>
            @endforeach
        </ol>
    </div>
@else
    <ol {{ $attributes->class('flex flex-col gap-1') }}>
        @foreach ($steps as $index => $step)
            @php
                $state = $index < $current ? 'done' : ($index === $current ? 'active' : 'upcoming');
            @endphp
            <li class="flex items-center gap-3 py-1.5">
                <span @class([
                    'flex h-6 w-6 shrink-0 items-center justify-center rounded-full font-mono text-[0.65rem] font-semibold',
                    'bg-pass text-white' => $state === 'done',
                    'bg-accent text-white ring-4 ring-accent-soft' => $state === 'active',
                    'bg-surface-2 text-ink-faint' => $state === 'upcoming',
                ])>
                    @if ($state === 'done')
                        <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>
                <span @class([
                    'text-sm',
                    'text-ink font-medium' => $state === 'active',
                    'text-ink-muted' => $state !== 'active',
                ])>{{ $step }}</span>
            </li>
        @endforeach
    </ol>
@endif
