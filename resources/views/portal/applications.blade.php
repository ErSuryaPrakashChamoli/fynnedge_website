<x-layouts.portal :title="__('My Loan Applications')" :customer="$customer">
    @if ($drafts->isEmpty() && $awaitingLender->isEmpty() && $applications->isEmpty())
        <x-portal.empty-state :title="__('No loan applications yet')" :body="__('Applications you start — here or on our website with this mobile number — appear here.')">
            <x-ui.button tag="a" :href="route('portal.apply')" size="sm">{{ __('Apply for a loan') }}</x-ui.button>
        </x-portal.empty-state>
    @endif

    @if ($drafts->isNotEmpty())
        <x-ui.card data-section="drafts">
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('In progress') }}</h2>
            <ul class="mt-4 divide-y divide-line">
                @foreach ($drafts as $draft)
                    <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-sm">
                            <p class="font-medium text-ink">{{ $draft->loanProduct->name }}</p>
                            <p class="text-ink-muted">
                                {{ __('Next step: :step', ['step' => $draft->currentStep?->title ?? __('Review')]) }} ·
                                {{ __('Last updated :date', ['date' => $draft->updated_at->translatedFormat('d M Y')]) }}
                            </p>
                        </div>
                        <x-ui.button tag="a" :href="route('journey.show', $draft)" size="sm" class="justify-center">{{ __('Resume') }}</x-ui.button>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    @if ($awaitingLender->isNotEmpty())
        <x-ui.card class="mt-6" data-section="awaiting-lender">
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Eligibility ready — choose a lender') }}</h2>
            <ul class="mt-4 divide-y divide-line">
                @foreach ($awaitingLender as $session)
                    <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="text-sm">
                            <p class="font-medium text-ink">{{ $session->loanProduct->name }}</p>
                            <p class="text-ink-muted">{{ __('Completed :date', ['date' => $session->completed_at?->translatedFormat('d M Y') ?? '—']) }}</p>
                        </div>
                        <x-ui.button tag="a" :href="route('journey.show', $session)" size="sm" variant="secondary" class="justify-center">{{ __('See my options') }}</x-ui.button>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    @if ($applications->isNotEmpty())
        <x-ui.card class="mt-6" data-section="applications">
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Applications') }}</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead class="text-xs text-ink-faint">
                        <tr>
                            <th class="py-2 pr-3 font-medium">{{ __('Reference') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('Loan type') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('Lender') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('Submitted') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('Status') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('Pending') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('Last updated') }}</th>
                            <th class="py-2 font-medium"><span class="sr-only">{{ __('Action') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($applications as $application)
                            @php $missing = $missingDocuments[$application->id] ?? 0; @endphp
                            <tr>
                                <td class="py-2.5 pr-3 font-mono text-xs text-ink-muted">{{ strtoupper(substr($application->public_id, 0, 8)) }}</td>
                                <td class="py-2.5 pr-3 text-ink">{{ $application->lenderProduct->loanProduct->name }}</td>
                                <td class="py-2.5 pr-3 text-ink">{{ $application->lenderProduct->lender->name }}</td>
                                <td class="py-2.5 pr-3 text-ink-muted">{{ $application->submitted_at?->translatedFormat('d M Y') ?? __('Not yet') }}</td>
                                <td class="py-2.5 pr-3"><x-ui.badge tone="accent">{{ $application->status->getLabel() }}</x-ui.badge></td>
                                <td class="py-2.5 pr-3 text-ink-muted">{{ $missing > 0 ? trans_choice(':count document|:count documents', $missing, ['count' => $missing]) : '—' }}</td>
                                <td class="py-2.5 pr-3 text-ink-muted">{{ $application->updated_at->translatedFormat('d M Y') }}</td>
                                <td class="py-2.5 text-right"><a href="{{ route('applications.show', $application) }}" class="font-medium text-accent underline">{{ $application->submitted_at === null ? __('Continue') : __('View details') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>
    @endif
</x-layouts.portal>
