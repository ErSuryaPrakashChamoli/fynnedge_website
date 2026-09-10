<x-emails.newsletter.layout preview="You have been unsubscribed.">
    <p style="margin:0 0 16px;font-size:20px;font-weight:600;color:#1c1917;">You have been unsubscribed</p>

    <p style="margin:0 0 16px;">
        {{ $subscriber->email }} has been removed from {{ \App\Models\Setting::get('site_name', 'FynnEdge') }} Insights.
        You will not receive any further newsletters from us.
    </p>

    <p style="margin:0 0 16px;">If this was a mistake, you can subscribe again at any time:</p>

    <x-emails.newsletter.button :url="url('/')">Back to the website</x-emails.newsletter.button>

    <p style="margin:0;font-size:13px;color:#78716c;">
        This is the last email you will receive from this list.
    </p>
</x-emails.newsletter.layout>
