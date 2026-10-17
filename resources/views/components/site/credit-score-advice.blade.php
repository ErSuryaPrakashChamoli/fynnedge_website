@props(['advice', 'bureau'])

{{--
    Below the dial on the credit-score result: what in the visitor's report is
    shaping the score (only when a live report was read), then the pointers to
    improve or keep it. All wording comes from CreditScoreAdvice.
--}}
@php
    $factors = $advice->factors();
    $figures = $advice->figures();
    $tips = $advice->tips();

    // Per rating: [status pill, card border on hover, value colour on hover].
    $ratings = [
        'good' => ['bg-pass-soft text-pass', 'hover:border-pass/40', 'group-hover:text-pass'],
        'fair' => ['bg-warn-soft text-warn', 'hover:border-warn/40', 'group-hover:text-warn'],
        'poor' => ['bg-score-poor/10 text-score-poor', 'hover:border-score-poor/40', 'group-hover:text-score-poor'],
    ];
    // Per tone: [card, icon, card border on hover].
    $tipTones = [
        'urgent' => ['border-score-poor/25 bg-score-poor/5', 'bg-score-poor text-surface', 'hover:border-score-poor/50'],
        'improve' => ['border-warn/25 bg-warn-soft/60', 'bg-warn text-surface', 'hover:border-warn/50'],
        'keep' => ['border-pass/25 bg-pass-soft/60', 'bg-pass text-surface', 'hover:border-pass/50'],
        'info' => ['border-line bg-surface-2/60', 'bg-accent text-surface', 'hover:border-line-strong'],
    ];
@endphp

<div {{ $attributes->class('flex flex-col gap-6 text-left') }}>
    @if ($factors !== [] || $figures !== [])
        <section aria-labelledby="score-factors-heading">
            <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                <h3 id="score-factors-heading" class="font-display text-lg font-semibold text-ink">{{ __('What is shaping your score') }}</h3>
                <p class="text-xs text-ink-faint">{{ __('From your :bureau report', ['bureau' => $bureau]) }}</p>
            </div>

            @if ($factors !== [])
                <div class="mt-3 grid gap-3 sm:grid-cols-2">
                    @foreach ($factors as $factor)
                        <div class="group flex flex-col rounded-xl border border-line bg-surface p-4 transition duration-300 ease-out hover:shadow-lg motion-safe:hover:-translate-y-1 motion-safe:hover:animate-card-swing {{ $ratings[$factor['rating']][1] }}">
                            <div class="flex items-start justify-between gap-2">
                                <p class="text-sm font-medium text-ink">{{ $factor['label'] }}</p>
                                <span class="shrink-0 rounded-full px-2 py-0.5 text-[0.7rem] font-semibold transition-transform duration-300 ease-out motion-safe:group-hover:scale-110 {{ $ratings[$factor['rating']][0] }}">{{ $factor['status'] }}</span>
                            </div>
                            <p class="mt-2 origin-left font-display text-2xl font-semibold leading-tight text-ink transition duration-300 ease-out motion-safe:group-hover:scale-105 {{ $ratings[$factor['rating']][2] }}">{{ $factor['value'] }}</p>
                            <p class="mt-0.5 text-xs text-ink-muted">{{ $factor['caption'] }}</p>
                            <p class="mt-auto pt-3 font-mono text-[0.62rem] font-semibold uppercase tracking-wider text-ink-faint">{{ $factor['impact'] }}</p>
                        </div>
                    @endforeach
                </div>
            @endif

            @if ($figures !== [])
                <dl @class([
                    'mt-3 grid divide-x divide-line overflow-hidden rounded-xl border border-line bg-surface-2/50 transition-shadow duration-300 hover:shadow-md',
                    'grid-cols-1' => count($figures) === 1,
                    'grid-cols-2' => count($figures) === 2,
                    'grid-cols-3' => count($figures) >= 3,
                ])>
                    @foreach ($figures as $figure)
                        <div class="group px-3 py-3 text-center transition-colors duration-200 hover:bg-surface">
                            <dt class="text-[0.7rem] text-ink-faint">{{ $figure['label'] }}</dt>
                            <dd @class(['mt-0.5 text-sm font-semibold tabular-nums transition-transform duration-200 ease-out motion-safe:group-hover:scale-110', 'text-score-poor' => $figure['alert'], 'text-ink' => ! $figure['alert']])>{{ $figure['value'] }}</dd>
                        </div>
                    @endforeach
                </dl>
            @endif
        </section>
    @endif

    @if ($tips !== [])
        <section aria-labelledby="score-tips-heading">
            <h3 id="score-tips-heading" class="font-display text-lg font-semibold text-ink">{{ $advice->tipsHeading() }}</h3>
            <p class="mt-0.5 text-xs text-ink-faint">
                {{ $advice->hasReport() ? __('Personalised from your :bureau report', ['bureau' => $bureau]) : __('Pointers for your score range') }}
            </p>

            <ol class="mt-3 flex flex-col gap-2.5">
                @foreach ($tips as $tip)
                    <li class="group flex gap-3 rounded-xl border p-3.5 transition duration-300 ease-out hover:shadow-md motion-safe:hover:translate-x-1 {{ $tipTones[$tip['tone']][0] }} {{ $tipTones[$tip['tone']][2] }}">
                        <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full transition-transform duration-300 ease-out motion-safe:group-hover:scale-115 motion-safe:group-hover:-rotate-12 {{ $tipTones[$tip['tone']][1] }}" aria-hidden="true">
                            @switch($tip['tone'])
                                @case('urgent')
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5"><path d="M10 3.5a1.25 1.25 0 0 1 1.25 1.25v5.5a1.25 1.25 0 1 1-2.5 0v-5.5A1.25 1.25 0 0 1 10 3.5Zm0 9.75a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3Z" /></svg>
                                    @break
                                @case('improve')
                                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="2" class="h-3.5 w-3.5"><path stroke-linecap="round" stroke-linejoin="round" d="M3.5 13.5 8 9l3 3 5.5-5.5M12 6.5h4.5V11" /></svg>
                                    @break
                                @case('keep')
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5"><path fill-rule="evenodd" d="M16.7 5.3a1 1 0 0 1 0 1.4l-7.5 7.5a1 1 0 0 1-1.4 0L3.3 9.7a1 1 0 1 1 1.4-1.4L8 11.6l6.8-6.8a1 1 0 0 1 1.4 0Z" clip-rule="evenodd" /></svg>
                                    @break
                                @default
                                    <svg viewBox="0 0 20 20" fill="currentColor" class="h-3.5 w-3.5"><path d="M10 2.5a5.5 5.5 0 0 0-3.3 9.9c.5.4.8.9.8 1.5v.35h5v-.35c0-.6.3-1.1.8-1.5A5.5 5.5 0 0 0 10 2.5ZM7.75 15.75h4.5a.75.75 0 0 1 0 1.5h-4.5a.75.75 0 0 1 0-1.5Z" /></svg>
                            @endswitch
                        </span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-ink">{{ $tip['title'] }}</p>
                            <p class="mt-0.5 text-sm text-ink-muted">{{ $tip['detail'] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </section>
    @endif
</div>
