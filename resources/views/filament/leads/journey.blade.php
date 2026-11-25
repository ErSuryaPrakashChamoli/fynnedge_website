{{-- "View journey": every lead record with one exact mobile number, oldest first. --}}
<ol class="relative space-y-4 border-s border-gray-200 ps-6 dark:border-white/10">
    @forelse ($entries as $entry)
        <li class="relative">
            <span class="absolute -start-[1.95rem] top-1 flex h-3.5 w-3.5 items-center justify-center rounded-full bg-white ring-2 ring-primary-500 dark:bg-gray-900"></span>
            <div class="flex flex-wrap items-center gap-2">
                <x-filament::badge :color="$entry->source->getColor()" size="sm">{{ $entry->source->getLabel() }}</x-filament::badge>
                <span class="text-xs text-gray-500">{{ $entry->received_at?->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</span>
                @if ($entry->verified_at)
                    <x-filament::badge color="success" size="sm" icon="heroicon-m-check-badge">Verified</x-filament::badge>
                @endif
                <x-filament::badge :color="$entry->syncStatus()->getColor()" size="sm">LMS: {{ $entry->syncStatus()->getLabel() }}</x-filament::badge>
            </div>
            <p class="mt-1 text-sm text-gray-800 dark:text-gray-200">
                {{ $entry->detailLabel() ?? $entry->source->getLabel() }}
                @if ($entry->loanProduct) · {{ $entry->loanProduct->name }} @endif
                @if ($entry->statusLabel()) · <span class="text-gray-500">{{ $entry->statusLabel() }}</span> @endif
            </p>
            <p class="text-xs text-gray-500">
                Page: {{ $entry->sourcePath() ?? 'not recorded' }}
                @if ($entry->utm_campaign) · Campaign: {{ $entry->utm_campaign }} @endif
            </p>
            @if ($url = $entry->recordUrl())
                <a href="{{ $url }}" class="mt-1 inline-block text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">Open record →</a>
            @endif
        </li>
    @empty
        <li class="text-sm text-gray-500">No records.</li>
    @endforelse
</ol>
