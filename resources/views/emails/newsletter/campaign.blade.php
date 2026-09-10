<x-emails.newsletter.layout
    :preview="$campaign->preview_text"
    :unsubscribe-url="$unsubscribeUrl"
    :preferences-url="$preferencesUrl"
>
    {{--
        Admin-authored campaign HTML, rendered unescaped: this is the body an
        editor composed in the rich-text field, and escaping it would print
        markup instead of formatting it. It is written by authenticated admin
        users with the newsletter permission, never by a visitor.
    --}}
    {!! $campaign->content !!}

    @if ($ctaUrl)
        <x-emails.newsletter.button :url="$ctaUrl">{{ $campaign->cta_label ?: 'Read more' }}</x-emails.newsletter.button>
    @endif

    {{--
        Open tracking: a 1x1 image on a per-recipient tokenised URL. Most clients
        block images by default, so opens are a floor, never an exact count — the
        admin UI labels it that way rather than implying precision.
    --}}
    <img src="{{ $openTrackingUrl }}" alt="" width="1" height="1" style="display:block;width:1px;height:1px;border:0;">
</x-emails.newsletter.layout>
