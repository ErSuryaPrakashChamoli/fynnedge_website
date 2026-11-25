{{--
    "Before you start": shown at the top of a list/create screen only while something it
    depends on is missing, with the order to fix it in and a link to each step.
--}}
@if ($missing !== [])
    <div class="fe-prerequisites mb-6 overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-warning-200 dark:bg-gray-900 dark:ring-warning-500/30" role="note">
        <div class="flex items-start gap-3.5 border-b border-warning-100 bg-gradient-to-r from-warning-50 to-white px-5 py-4 dark:border-warning-500/20 dark:from-warning-500/10 dark:to-transparent">
            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-warning-500 text-white shadow-sm shadow-warning-500/30">
                <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-5 w-5" />
            </span>
            <div class="min-w-0">
                <p class="text-[0.9375rem] font-bold text-gray-950 dark:text-white">Before you start</p>
                <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-300">This screen depends on something that does not exist yet. Complete these steps first, in this order:</p>
            </div>
        </div>
        <ol class="divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($missing as $index => $requirement)
                <li class="flex flex-col gap-3 px-5 py-3.5 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-start gap-3">
                        <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-warning-100 text-xs font-bold text-warning-700 dark:bg-warning-500/15 dark:text-warning-300">{{ $index + 1 }}</span>
                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $requirement['label'] }}</p>
                            <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">{{ $requirement['why'] }}</p>
                        </div>
                    </div>
                    @if ($requirement['url'])
                        <x-filament::button tag="a" :href="$requirement['url']" size="sm" color="warning" icon="heroicon-m-arrow-right" icon-position="after" class="shrink-0 self-start sm:self-auto">
                            {{ $requirement['action'] }}
                        </x-filament::button>
                    @else
                        <span class="shrink-0 text-xs text-gray-500">Ask an administrator — your role cannot open that screen.</span>
                    @endif
                </li>
            @endforeach
        </ol>
    </div>
@endif
