@php
    $events = collect()
        ->concat($record->statusChanges()->with('user')->get()->map(fn ($change) => ['at' => $change->created_at, 'kind' => 'Status', 'text' => ($change->from_status ? str_replace('_', ' ', $change->from_status).' → ' : '').str_replace('_', ' ', $change->to_status).($change->note ? ' — '.$change->note : ''), 'who' => $change->user?->name ?? $change->actor_type->getLabel()]))
        ->concat($record->lenderApplications()->with('statusChanges.user')->get()->flatMap(fn ($application) => $application->statusChanges->map(fn ($change) => ['at' => $change->created_at, 'kind' => 'Lender: '.$application->lender_name, 'text' => str_replace('_', ' ', $change->to_status).($change->note ? ' — '.$change->note : ''), 'who' => $change->user?->name ?? $change->actor_type->getLabel()])))
        ->concat($record->closures()->with(['statusChanges.user', 'liability'])->get()->flatMap(fn ($closure) => $closure->statusChanges->map(fn ($change) => ['at' => $change->created_at, 'kind' => 'Closure: '.$closure->liability?->lender_name, 'text' => str_replace('_', ' ', $change->to_status).($change->note ? ' — '.$change->note : ''), 'who' => $change->user?->name ?? $change->actor_type->getLabel()])))
        ->concat((auth()->user()?->can('ViewAny:DebtNote') ? $record->notes()->with('author')->get() : collect())->map(fn ($note) => ['at' => $note->created_at, 'kind' => $note->type->getLabel(), 'text' => trim(($note->subject ? $note->subject.': ' : '').\Illuminate\Support\Str::limit($note->body, 160)), 'who' => $note->author?->name ?? 'System']))
        ->concat($record->auditLogs()->whereNotIn('action', ['created', 'updated', 'deleted'])->with('user')->get()->map(fn ($log) => ['at' => $log->created_at, 'kind' => 'Activity', 'text' => str_replace('_', ' ', $log->action), 'who' => $log->user?->name ?? 'Customer / system']))
        ->sortByDesc('at')
        ->values();
@endphp
<x-filament::section heading="Everything that happened on this case">
    <ol class="relative flex flex-col gap-4 border-s border-gray-200 ps-5 dark:border-white/10">
        @forelse ($events as $event)
            <li>
                <span class="absolute -start-1.5 mt-1.5 h-3 w-3 rounded-full bg-primary-500"></span>
                <p class="text-xs text-gray-500">{{ $event['at']?->timezone('Asia/Kolkata')->format('j M Y, g:i a') }} · {{ $event['kind'] }} · {{ $event['who'] }}</p>
                <p class="text-sm text-gray-950 dark:text-white">{{ ucfirst($event['text']) }}</p>
            </li>
        @empty
            <li class="text-sm text-gray-500">Nothing yet.</li>
        @endforelse
    </ol>
</x-filament::section>
