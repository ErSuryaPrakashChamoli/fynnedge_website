{{--
    Renders the campaign through the real email layout, so what an admin
    previews is the same HTML a subscriber receives — not an approximation.
    Sandboxed in an iframe because email HTML carries its own inline styles and
    would otherwise inherit (and fight with) the panel's CSS.
--}}
@php
    $html = view('emails.newsletter.campaign', [
        'campaign' => $campaign,
        'subscriber' => null,
        'unsubscribeUrl' => '#',
        'preferencesUrl' => '#',
        'openTrackingUrl' => '',
        'ctaUrl' => $campaign->cta_url,
    ])->render();
@endphp

<div class="space-y-3">
    <div class="text-sm text-gray-500 dark:text-gray-400">
        <p><span class="font-medium">Subject:</span> {{ $campaign->subject }}</p>
        @if ($campaign->preview_text)
            <p><span class="font-medium">Preview text:</span> {{ $campaign->preview_text }}</p>
        @endif
    </div>

    <iframe
        srcdoc="{{ $html }}"
        class="h-[60vh] w-full rounded-lg border border-gray-200 bg-white dark:border-gray-700"
        title="Campaign preview"
        sandbox
    ></iframe>
</div>
