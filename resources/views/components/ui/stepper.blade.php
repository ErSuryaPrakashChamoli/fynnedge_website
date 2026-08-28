@props(['steps', 'current'])

{{--
    Drives the customer journey progress indicator (see Phase 5 journey engine).
    $steps: array<string> of step titles. $current: 0-based index of the active step.
--}}
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
