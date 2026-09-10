@props(['url'])

{{-- A bulletproof-ish email button: padding on the anchor, no flexbox, no pseudo-elements. --}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:24px 0;">
    <tr>
        <td style="background-color:#1E3A5F;border-radius:9999px;">
            <a href="{{ $url }}" style="display:inline-block;padding:12px 28px;color:#ffffff;font-size:15px;font-weight:600;text-decoration:none;">{{ $slot }}</a>
        </td>
    </tr>
</table>
