<x-layouts.portal :title="__('Debt Consolidation')" :customer="$customer">
    @if (session('status'))
        <x-ui.alert tone="pass" class="mb-6">{{ session('status') }}</x-ui.alert>
    @endif

    @forelse ($applications as $application)
        <a href="{{ route('portal.debt.show', $application) }}" class="mb-4 flex flex-col gap-2 rounded-2xl border border-line bg-surface p-5 shadow-sm transition-colors hover:border-accent sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-display text-lg font-semibold text-ink">{{ $application->reference }}</p>
                <p class="text-sm text-ink-muted">{{ $application->applicant_type->value === 'business' ? __('Business debt consolidation') : __('Personal debt consolidation') }} · {{ __('Started :date', ['date' => $application->created_at->timezone('Asia/Kolkata')->translatedFormat('j M Y')]) }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                @if ($application->latestSnapshot)
                    <span class="text-sm text-ink-muted">{{ __('Total debt :amount', ['amount' => \App\Modules\DebtConsolidation\Services\DebtPresenter::inr($application->latestSnapshot->total_outstanding)]) }}</span>
                @endif
                <x-ui.badge :tone="$application->status->isOpen() ? 'accent' : 'muted'">{{ $application->status->getLabel() }}</x-ui.badge>
            </div>
        </a>
    @empty
        <x-portal.empty-state :title="__('No debt consolidation request yet')" :body="__('See whether combining your loans and cards could lower your total cost, and let an advisor help you apply.')">
            @if ($assessmentUrl)
                <x-ui.button tag="a" :href="$assessmentUrl">{{ __('Start a free assessment') }}</x-ui.button>
            @endif
        </x-portal.empty-state>
    @endforelse

    @if ($applications->isNotEmpty() && $assessmentUrl && $applications->every(fn ($application) => $application->isSubmitted()))
        <p class="mt-6 text-sm text-ink-muted"><a href="{{ $assessmentUrl }}" class="font-medium text-accent hover:underline">{{ __('Start another assessment') }}</a></p>
    @endif
</x-layouts.portal>
