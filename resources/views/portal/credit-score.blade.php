<x-layouts.portal :title="__('My Credit Score')" :customer="$customer">
    <div class="grid gap-6 md:grid-cols-2">
        @foreach ($refresh as $eligibility)
            @php $latest = $eligibility->latestSuccess; @endphp
            <x-ui.card :data-bureau="$eligibility->bureau->value">
                <div class="flex items-start justify-between gap-3">
                    <h2 class="font-display text-lg font-semibold text-ink">{{ $eligibility->bureau->getLabel() }}</h2>
                    @if ($latest)
                        <x-portal.check-status :check="$latest" />
                    @endif
                </div>

                @if ($latest)
                    <div class="mt-4 flex flex-col items-center text-center">
                        @if ($latest->score !== null)
                            <x-site.credit-score-meter :score="$latest->score" />
                        @else
                            <x-site.credit-score-meter />
                            <p class="mt-2 max-w-xs text-sm text-ink-muted">{{ __(':bureau found your record but has no score for you yet.', ['bureau' => $latest->sourceBureau()->getLabel()]) }}</p>
                        @endif
                        @if ($latest->sourceBureau() !== $latest->bureau)
                            <p class="mt-2 max-w-xs text-xs text-ink-faint">{{ __('This is your :source score from the :bureau page — not a :bureau score.', ['source' => $latest->sourceBureau()->getLabel(), 'bureau' => $latest->bureau->getLabel()]) }}</p>
                        @endif
                    </div>
                    <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                        <div>
                            <dt class="text-ink-faint">{{ __('Latest report') }}</dt>
                            <dd class="text-ink">{{ $latest->completed_at->translatedFormat('d M Y') }}</dd>
                        </div>
                        <div>
                            <dt class="text-ink-faint">{{ __('Last refresh attempt') }}</dt>
                            <dd class="text-ink">
                                {{ $eligibility->latestAttempt?->requested_at?->translatedFormat('d M Y') ?? '—' }}
                                @if ($eligibility->latestAttempt && ! $eligibility->latestAttempt->is($latest))
                                    <x-portal.check-status :check="$eligibility->latestAttempt" class="mt-1" />
                                @endif
                            </dd>
                        </div>
                    </dl>
                    <x-ui.button tag="a" :href="route('portal.reports.show', $latest->public_id)" variant="secondary" size="sm" class="mt-4 w-full justify-center">{{ __('View report') }}</x-ui.button>
                @else
                    <p class="mt-3 text-sm text-ink-muted">{{ __('No :bureau score saved yet.', ['bureau' => $eligibility->bureau->getLabel()]) }}</p>
                @endif

                <x-portal.refresh-status :eligibility="$eligibility" class="mt-4" />
            </x-ui.card>
        @endforeach
    </div>

    <p class="mt-6 text-xs text-ink-faint">{{ __('Checking your credit score is free. You can generate a new credit report once every :months months, up to :max times a year, across all bureaus; in between, you see your saved score. A failed refresh never removes your last saved report, and checking your own score does not lower it.', ['months' => \App\Modules\CreditScore\Services\CreditReportRegenerationLimit::everyMonths(), 'max' => \App\Modules\CreditScore\Services\CreditReportRegenerationLimit::maxPerYear()]) }}</p>
</x-layouts.portal>
