@props(['at' => null])

{{--
    When a page's content last changed, in small muted type: "Updated 2 Nov
    2026, 11:30 AM", in India time (the app runs in UTC). Each content page
    places it last in its own content, after the FAQs. The <time> carries the
    exact moment for browsers and crawlers. Renders nothing without a date.
--}}
@if ($at)
    <p {{ $attributes->class('inline-flex items-center gap-1.5 font-mono text-xs text-ink-faint') }}>
        <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5 shrink-0" aria-hidden="true"><circle cx="10" cy="10" r="7.25" /><path stroke-linecap="round" stroke-linejoin="round" d="M10 6.5V10l2.5 1.5" /></svg>
        <span>{{ __('Updated') }} <time datetime="{{ $at->toIso8601String() }}">{{ $at->copy()->timezone('Asia/Kolkata')->translatedFormat('j M Y, g:i A') }}</time></span>
    </p>
@endif
