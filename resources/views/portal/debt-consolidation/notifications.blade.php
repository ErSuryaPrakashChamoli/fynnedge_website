<x-layouts.portal :title="__('Notifications')" :customer="$customer">
    @forelse ($notifications as $notification)
        @php
            $data = $notification->data;
            $parameters = collect($data['parameters'] ?? [])->map(fn ($value) => __((string) $value))->all();
        @endphp
        <div @class(['mb-3 rounded-xl border p-4', 'border-accent bg-accent-soft' => $notification->read_at === null, 'border-line bg-surface' => $notification->read_at !== null])>
            <div class="flex flex-wrap items-baseline justify-between gap-2">
                <p class="font-semibold text-ink">{{ __($data['title'] ?? '', $parameters) }}</p>
                <p class="text-xs text-ink-faint">{{ $notification->created_at->timezone('Asia/Kolkata')->translatedFormat('j M Y, g:i a') }}</p>
            </div>
            <p class="mt-1 text-sm text-ink-muted">{{ __($data['message'] ?? '', $parameters) }}</p>
            @if (filled($data['url'] ?? null))
                <a href="{{ $data['url'] }}" class="mt-2 inline-block text-sm font-medium text-accent hover:underline">{{ __('View :reference', ['reference' => $data['reference'] ?? '']) }}</a>
            @endif
        </div>
    @empty
        <x-portal.empty-state :title="__('No notifications')" :body="__('Updates about your requests will appear here.')" />
    @endforelse

    <div class="mt-6">{{ $notifications->links() }}</div>
</x-layouts.portal>
