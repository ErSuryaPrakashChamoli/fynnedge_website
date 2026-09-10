@php $siteName = \App\Models\Setting::get('site_name', 'FynnEdge'); @endphp

<x-emails.newsletter.layout
    preview="You're subscribed to {{ $siteName }} Insights."
    :unsubscribe-url="$unsubscribeUrl"
>
    <p style="margin:0 0 16px;font-size:20px;font-weight:600;color:#1c1917;">Welcome to {{ $siteName }} Insights</p>

    {{--
        The greeting is composed in PHP rather than with inline @if/@else:
        Blade does not compile a directive that is glued to a word character
        (a directive written straight after a letter), which silently leaves the if unterminated and the
        template unrenderable. Keep directives separated by whitespace.
    --}}
    @php
        $greeting = $subscriber->name ? "Hi {$subscriber->name}, you're all set." : "You're all set.";
    @endphp

    <p style="margin:0 0 16px;">
        {{ $greeting }} You'll receive practical financial insights, loan education, credit tips and useful
        updates — written to help you make better borrowing decisions, not to sell you something.
    </p>

    <p style="margin:0 0 16px;">Typically that means:</p>

    <ul style="margin:0 0 16px;padding-left:20px;">
        <li style="margin-bottom:6px;">How credit scores actually work, and what moves them</li>
        <li style="margin-bottom:6px;">What lenders look for before they approve a loan</li>
        <li style="margin-bottom:6px;">Plain explanations of interest, tenure, EMIs and eligibility</li>
    </ul>

    <x-emails.newsletter.button :url="route('resources.index')">Read the latest articles</x-emails.newsletter.button>

    <p style="margin:0;font-size:13px;color:#78716c;">
        You can change what you receive, or unsubscribe entirely, from any email we send.
    </p>
</x-emails.newsletter.layout>
