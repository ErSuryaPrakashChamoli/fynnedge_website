@props(['score' => null, 'fetching' => false])

{{--
    The credit score dial on the free credit-score check: a 300–900 half-circle
    in the four ScoreBand colours, with a knob and a filled arc up to the score.
    `fetching` sweeps the knob back and forth while the bureau is asked (CSS,
    see .score-meter-sweep in app.css); with a score, creditScoreMeter (app.js)
    counts it up from 300 as the knob travels. It is rendered at the final
    value, so without JavaScript or with reduced motion it simply shows the
    score. No score and not fetching: the empty dial.
--}}
@php
    $band = $score === null ? null : \App\Modules\CreditScore\Enums\ScoreBand::fromScore($score);
    $lowest = \App\Modules\CreditScore\Enums\ScoreBand::LOWEST_SCORE;
    $span = \App\Modules\CreditScore\Enums\ScoreBand::HIGHEST_SCORE - $lowest;
    $percent = $score === null ? 0 : round((max($lowest, min($lowest + $span, $score)) - $lowest) / $span * 100, 2);

    // Dial geometry (viewBox 240 × 150): centre (120, 120), arc radius 100.
    // A fraction of the dial, 0 (left end, 300) → 1 (right end, 900), as a point on a circle.
    $point = fn (float $fraction, float $radius = 100): array => [round(120 - $radius * cos(M_PI * $fraction), 2), round(120 - $radius * sin(M_PI * $fraction), 2)];
    $arc = fn (float $from, float $to, float $radius = 100): string => 'M '.implode(' ', $point($from, $radius)).' A '.$radius.' '.$radius.' 0 0 1 '.implode(' ', $point($to, $radius));
    $gap = 0.006;

    $segments = collect(\App\Modules\CreditScore\Enums\ScoreBand::cases())->map(function ($segmentBand) use ($lowest, $span, $gap, $arc) {
        $from = ($segmentBand->min() - $lowest) / $span;
        $to = $segmentBand->next() === null ? 1 : ($segmentBand->next()->min() - $lowest) / $span;

        return [
            'band' => $segmentBand,
            'path' => $arc($from <= 0 ? 0 : $from + $gap, $to >= 1 ? 1 : $to - $gap),
        ];
    });

    // A tick every 30 points; longer ones where a band begins.
    $boundaries = collect(\App\Modules\CreditScore\Enums\ScoreBand::cases())->map(fn ($tickBand) => $tickBand->min())->all();
    $ticks = collect(range($lowest + 30, $lowest + $span - 30, 30))->map(fn (int $value) => [
        'from' => $point(($value - $lowest) / $span, in_array($value, $boundaries, true) ? 78 : 82),
        'to' => $point(($value - $lowest) / $span, 86),
        'major' => in_array($value, $boundaries, true),
    ]);

    $tones = [
        'poor' => 'text-score-poor',
        'average' => 'text-score-average',
        'good' => 'text-score-good',
        'excellent' => 'text-score-excellent',
    ];
    $toneClass = $band === null ? 'text-accent' : $tones[$band->value];
@endphp

<div
    {{ $attributes->class(['relative mx-auto w-full max-w-[21rem]', $toneClass]) }}
    @if ($score !== null && ! $fetching) x-data="creditScoreMeter(@js($score), @js($percent))" @endif
>
    <svg viewBox="0 0 240 150" class="block w-full overflow-visible" aria-hidden="true">
        @foreach ($segments as $segment)
            <path
                d="{{ $segment['path'] }}"
                fill="none"
                stroke="var(--color-score-{{ $segment['band']->value }})"
                stroke-width="16"
                @class(['opacity-25' => $score !== null || $fetching, 'opacity-40' => $score === null && ! $fetching])
            />
        @endforeach

        @foreach ($ticks as $tick)
            <line
                x1="{{ $tick['from'][0] }}" y1="{{ $tick['from'][1] }}"
                x2="{{ $tick['to'][0] }}" y2="{{ $tick['to'][1] }}"
                stroke="var(--color-line-strong)"
                stroke-width="{{ $tick['major'] ? 1.6 : 1 }}"
                stroke-linecap="round"
            />
        @endforeach

        @if ($fetching)
            <path class="score-meter-sweep-arc" d="{{ $arc(0, 1) }}" fill="none" stroke="currentColor" stroke-width="16" stroke-linecap="round" pathLength="100" stroke-dasharray="100 100" stroke-dashoffset="90" />
            <g class="score-meter-sweep">
                <circle cx="20" cy="120" r="11" fill="var(--color-surface)" stroke="currentColor" stroke-width="4" />
            </g>
        @elseif ($score !== null)
            <path
                d="{{ $arc(0, 1) }}"
                fill="none"
                stroke="currentColor"
                stroke-width="16"
                stroke-linecap="round"
                pathLength="100"
                stroke-dasharray="100 100"
                stroke-dashoffset="{{ 100 - $percent }}"
                x-bind:stroke-dashoffset="100 - percent"
            />
            <g transform="rotate({{ $percent * 1.8 }} 120 120)" x-bind:transform="`rotate(${percent * 1.8} 120 120)`">
                <circle cx="20" cy="120" r="13" fill="currentColor" opacity="0.18" />
                <circle cx="20" cy="120" r="10" fill="var(--color-surface)" stroke="currentColor" stroke-width="4" />
            </g>
        @endif

        <text x="20" y="145" text-anchor="middle" font-size="10" fill="var(--color-ink-faint)" font-family="var(--font-mono)">{{ $lowest }}</text>
        <text x="220" y="145" text-anchor="middle" font-size="10" fill="var(--color-ink-faint)" font-family="var(--font-mono)">{{ $lowest + $span }}</text>
    </svg>

    <div class="absolute inset-x-0 bottom-[18%] flex flex-col items-center">
        @if ($fetching)
            <span class="flex h-12 items-center gap-1.5" aria-hidden="true">
                @foreach ([0, 150, 300] as $delay)
                    <span class="h-2.5 w-2.5 animate-bounce rounded-full bg-current" style="animation-delay: {{ $delay }}ms"></span>
                @endforeach
            </span>
        @elseif ($score !== null)
            <span class="font-display text-5xl font-semibold leading-none tracking-tight tabular-nums text-ink" x-text="shown">{{ $score }}</span>
            <span class="mt-2 inline-flex items-center rounded-full bg-current/10 px-2.5 py-0.5 font-mono text-[0.68rem] font-semibold uppercase tracking-wider">{{ $band->getLabel() }}</span>
        @else
            <span class="font-display text-4xl font-semibold leading-none text-ink-faint">—</span>
        @endif
    </div>
</div>
