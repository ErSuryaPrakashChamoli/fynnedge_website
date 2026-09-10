<x-emails.newsletter.layout preview="Confirm your subscription to {{ \App\Models\Setting::get('site_name', 'FynnEdge') }} Insights.">
    <p style="margin:0 0 16px;font-size:20px;font-weight:600;color:#1c1917;">Please confirm your subscription</p>

    <p style="margin:0 0 16px;">
        @if ($subscriber->name)Hi {{ $subscriber->name }},@else Hi,@endif
        thanks for signing up for practical financial insights, loan tips and useful money guidance.
    </p>

    <p style="margin:0 0 8px;">Confirm your email address to start receiving them:</p>

    <x-emails.newsletter.button :url="$confirmUrl">Confirm subscription</x-emails.newsletter.button>

    <p style="margin:0 0 16px;font-size:13px;color:#78716c;">
        This link expires in {{ $expiryDays }} days. If the button does not work, copy this address into your browser:<br>
        <span style="word-break:break-all;color:#1E3A5F;">{{ $confirmUrl }}</span>
    </p>

    <p style="margin:0;font-size:13px;color:#78716c;">
        If you did not request this, you can simply ignore this email — no subscription is created until you confirm.
    </p>
</x-emails.newsletter.layout>
