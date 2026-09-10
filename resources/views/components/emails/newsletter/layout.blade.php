{{--
    The branded shell every newsletter email renders inside.

    Email-safe by construction: table layout, inline styles, no JavaScript, no
    external CSS and no web fonts (a mail client would drop all four). Colours
    are hardcoded hex rather than the site's CSS custom properties for the same
    reason — var() is unsupported in most clients — but they are the same values
    as the light theme in resources/css/app.css.

    The footer here is the ONLY place the unsubscribe link is rendered, so no
    template or campaign body can ship an email without one.
--}}
@props([
    'preview' => null,
    'unsubscribeUrl' => null,
    'preferencesUrl' => null,
])
@php
    $siteName = \App\Models\Setting::get('site_name', 'FynnEdge');
    $postalAddress = \App\Modules\Newsletter\Services\NewsletterSettings::postalAddress();
@endphp
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $siteName }}</title>
</head>
<body style="margin:0;padding:0;background-color:#f5f4f1;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Helvetica,Arial,sans-serif;color:#1c1917;">
    @if ($preview)
        {{-- Preheader: shown in the inbox list next to the subject, hidden in the body. --}}
        <div style="display:none;max-height:0;overflow:hidden;opacity:0;">{{ $preview }}</div>
    @endif

    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color:#f5f4f1;padding:24px 12px;">
        <tr>
            <td align="center">
                <table role="presentation" width="600" cellpadding="0" cellspacing="0" border="0" style="width:100%;max-width:600px;background-color:#ffffff;border:1px solid #e7e5e4;border-radius:12px;overflow:hidden;">
                    <tr>
                        <td style="padding:24px 32px;border-bottom:1px solid #e7e5e4;">
                            <a href="{{ url('/') }}" style="color:#1E3A5F;font-size:18px;font-weight:600;text-decoration:none;">{{ $siteName }}</a>
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:32px;font-size:15px;line-height:1.6;color:#44403c;">
                            {{ $slot }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding:20px 32px;border-top:1px solid #e7e5e4;background-color:#fafaf9;font-size:12px;line-height:1.6;color:#78716c;">
                            @if ($postalAddress)
                                <p style="margin:0 0 8px;">{{ $postalAddress }}</p>
                            @endif
                            @if ($unsubscribeUrl)
                                <p style="margin:0;">
                                    You are receiving this because you subscribed to {{ $siteName }} Insights.
                                    <a href="{{ $unsubscribeUrl }}" style="color:#1E3A5F;">Unsubscribe</a>@if ($preferencesUrl) &nbsp;·&nbsp;
                                    <a href="{{ $preferencesUrl }}" style="color:#1E3A5F;">Email preferences</a>@endif
                                </p>
                            @endif
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
