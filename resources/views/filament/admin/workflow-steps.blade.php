{{--
    A workflow as a numbered process diagram: a grid of step cards (left-to-right on wide
    screens), or — with $layout = 'vertical', for narrow columns — a top-to-bottom timeline.
    Status per step: done (green), next (highlighted), todo, info (no live check).
    Data: App\Support\Admin\AdminWorkflows::resolve().
--}}
@php
    $layout ??= 'grid';
    $columns = match (min(count($workflow['steps']), 4)) {
        1 => 'xl:grid-cols-1',
        2 => 'xl:grid-cols-2',
        3 => 'xl:grid-cols-3',
        default => 'xl:grid-cols-4',
    };
@endphp
<ol @class([
    'fe-workflow',
    'grid gap-3 sm:grid-cols-2 '.$columns => $layout === 'grid',
    'relative space-y-3 before:absolute before:start-[1.875rem] before:top-4 before:bottom-4 before:w-px before:bg-gray-200 dark:before:bg-white/10' => $layout === 'vertical',
])>
    @foreach ($workflow['steps'] as $index => $step)
        @php
            $tone = match ($step['status']) {
                'done' => 'border-success-200 bg-success-50/60 dark:border-success-500/30 dark:bg-success-500/10',
                'next' => 'border-primary-300 bg-primary-50/70 ring-2 ring-primary-500/20 dark:border-primary-500/40 dark:bg-primary-500/10',
                default => 'border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900',
            };
            $badge = match ($step['status']) {
                'done' => 'bg-success-600 text-white',
                'next' => 'bg-primary-600 text-white',
                default => 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300',
            };
        @endphp
        <li class="fe-workflow-step relative flex flex-col rounded-xl border p-4 {{ $tone }}">
            <div class="flex items-start gap-3">
                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $badge }}">
                    @if ($step['status'] === 'done')
                        <x-filament::icon icon="heroicon-m-check" class="h-4 w-4" />
                    @else
                        {{ $index + 1 }}
                    @endif
                </span>
                <div class="min-w-0 flex-1">
                    <p class="text-sm font-semibold text-gray-950 dark:text-white">
                        {{ $step['label'] }}
                        @if ($step['optional'])
                            <span class="ms-1 rounded bg-gray-100 px-1.5 py-0.5 text-[10px] font-medium uppercase tracking-wide text-gray-500 dark:bg-white/10">Optional</span>
                        @endif
                    </p>
                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">{{ $step['detail'] }}</p>
                    <p class="mt-2 text-xs">
                        @if ($step['status'] === 'done')
                            <span class="font-medium text-success-700 dark:text-success-400">Done ({{ $step['count'] }})</span>
                        @elseif ($step['status'] === 'next')
                            <span class="font-medium text-primary-700 dark:text-primary-400">Next step</span>
                        @elseif ($step['status'] === 'todo')
                            <span class="font-medium text-gray-500">To do</span>
                        @endif
                    </p>
                </div>
            </div>
            @if ($step['url'])
                <a href="{{ $step['url'] }}" class="mt-3 inline-flex items-center gap-1 self-start text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">
                    Open {{ $step['screenLabel'] }}
                    <x-filament::icon icon="heroicon-m-arrow-right" class="h-3.5 w-3.5" />
                </a>
            @endif
        </li>
    @endforeach
</ol>
