<x-layouts.portal :title="__('Dashboard')" :heading="$customer->full_name ? __('Welcome back, :name', ['name' => $customer->full_name]) : __('Welcome back')" :customer="$customer">
    {{-- Status line: what the account is and what it has proved. --}}
    <div class="flex flex-wrap items-center gap-2 text-sm">
        <x-ui.badge tone="pass">{{ __('Mobile verified') }}</x-ui.badge>
        @if ($customer->isIdentityVerified())
            <x-ui.badge tone="pass">{{ __('Identity verified') }}</x-ui.badge>
        @else
            <x-ui.badge tone="muted">{{ __('Identity not yet verified') }}</x-ui.badge>
        @endif
        <span class="text-ink-faint">{{ __('Registered mobile +91 :number', ['number' => $customer->maskedMobile()]) }}</span>
    </div>

    {{-- Summary cards --}}
    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <x-ui.card data-summary="score">
            <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">{{ __('Latest credit score') }}</p>
            @if ($latestScore)
                <p class="mt-2 font-display text-3xl font-semibold text-ink">{{ $latestScore->score }}</p>
                <p class="mt-1 text-xs text-ink-muted">{{ __(':bureau · :date', ['bureau' => $latestScore->sourceBureau()->getLabel(), 'date' => $latestScore->completed_at->translatedFormat('d M Y')]) }}</p>
            @else
                <p class="mt-2 font-display text-3xl font-semibold text-ink-faint">—</p>
                <p class="mt-1 text-xs text-ink-muted">{{ __('No score saved yet') }}</p>
            @endif
        </x-ui.card>
        <x-ui.card data-summary="profile">
            <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">{{ __('Profile completion') }}</p>
            <p class="mt-2 font-display text-3xl font-semibold text-ink">{{ $customer->profileCompletion() }}%</p>
            <div class="mt-2 h-1.5 w-full overflow-hidden rounded-full bg-surface-2" aria-hidden="true">
                <div class="h-full rounded-full bg-accent" style="width: {{ $customer->profileCompletion() }}%"></div>
            </div>
            @if ($customer->profileCompletion() < 100)
                <a href="{{ route('portal.profile') }}" class="mt-2 inline-block text-xs font-medium text-accent underline">{{ __('Complete your profile') }}</a>
            @endif
        </x-ui.card>
        <x-ui.card data-summary="applications">
            <p class="font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-ink-faint">{{ __('Active loan applications') }}</p>
            <p class="mt-2 font-display text-3xl font-semibold text-ink">{{ $activeApplicationsCount }}</p>
            <a href="{{ route('portal.applications') }}" class="mt-1 inline-block text-xs font-medium text-accent underline">{{ __('View applications') }}</a>
        </x-ui.card>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        {{-- Credit score card --}}
        <x-ui.card>
            <div class="flex items-start justify-between gap-3">
                <h2 class="font-display text-lg font-semibold text-ink">{{ __('My credit score') }}</h2>
                <a href="{{ route('portal.credit-score') }}" class="text-sm font-medium text-accent underline">{{ __('All bureaus') }}</a>
            </div>
            @if ($latestScore)
                <div class="mt-4 flex flex-col items-center text-center">
                    <x-site.credit-score-meter :score="$latestScore->score" />
                    <p class="mt-3 text-sm text-ink-muted">{{ __('Your :bureau score', ['bureau' => $latestScore->sourceBureau()->getLabel()]) }}</p>
                </div>
                <dl class="mt-4 grid grid-cols-2 gap-3 text-sm">
                    <div>
                        <dt class="text-ink-faint">{{ __('Report date') }}</dt>
                        <dd class="text-ink">{{ $latestScore->completed_at->translatedFormat('d M Y') }}</dd>
                    </div>
                    <div>
                        <dt class="text-ink-faint">{{ __('Last refresh attempt') }}</dt>
                        <dd class="text-ink">{{ $refresh[$latestScore->bureau->value]->latestAttempt?->requested_at?->translatedFormat('d M Y') ?? '—' }}</dd>
                    </div>
                </dl>
                <div class="mt-4 flex flex-col gap-3">
                    <x-ui.button tag="a" :href="route('portal.reports.show', $latestScore->public_id)" class="justify-center">{{ __('View report') }}</x-ui.button>
                    <x-portal.refresh-status :eligibility="$refresh[$latestScore->bureau->value]" />
                </div>
            @else
                <x-portal.empty-state class="mt-4" :title="__('No credit score saved yet')" :body="__('Check your free credit score — it is saved here so you can see it again without another check.')">
                    <x-ui.button tag="a" :href="route('portal.credit-score')" size="sm">{{ __('Check my credit score') }}</x-ui.button>
                </x-portal.empty-state>
            @endif
        </x-ui.card>

        {{-- Loan application card --}}
        <x-ui.card>
            <div class="flex items-start justify-between gap-3">
                <h2 class="font-display text-lg font-semibold text-ink">{{ __('Apply for a loan') }}</h2>
                <a href="{{ route('portal.apply') }}" class="text-sm font-medium text-accent underline">{{ __('All loan types') }}</a>
            </div>

            @if ($drafts->isNotEmpty())
                <div class="mt-4 flex flex-col gap-2">
                    <p class="text-sm font-medium text-ink">{{ __('Continue where you left off') }}</p>
                    @foreach ($drafts->take(3) as $draft)
                        <a href="{{ route('journey.show', $draft) }}" class="flex items-center justify-between gap-3 rounded-lg border border-line px-3.5 py-2.5 text-sm hover:bg-surface-2">
                            <span class="text-ink">{{ $draft->loanProduct->name }}</span>
                            <span class="text-xs text-ink-faint">{{ __('Last updated :date', ['date' => $draft->updated_at->translatedFormat('d M Y')]) }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($loanProducts->isNotEmpty())
                <div class="mt-4 grid gap-2 sm:grid-cols-2">
                    @foreach ($loanProducts as $loanProduct)
                        <form method="POST" action="{{ route('portal.apply.start', $loanProduct) }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center justify-between gap-2 rounded-lg border border-line-strong px-3.5 py-2.5 text-left text-sm font-medium text-ink hover:border-accent hover:text-accent">
                                {{ $loanProduct->name }}
                                <svg viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" class="h-3.5 w-3.5 shrink-0" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 8h10m0 0-4-4m4 4-4 4" /></svg>
                            </button>
                        </form>
                    @endforeach
                </div>
            @else
                <x-portal.empty-state class="mt-4" :title="__('No loan applications are open right now')" :body="__('Please check back soon, or contact us for help.')" />
            @endif
        </x-ui.card>
    </div>

    {{-- Application summary --}}
    <x-ui.card class="mt-6">
        <div class="flex items-start justify-between gap-3">
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('My loan applications') }}</h2>
            <a href="{{ route('portal.applications') }}" class="text-sm font-medium text-accent underline">{{ __('View all') }}</a>
        </div>
        @if ($recentApplications->isEmpty())
            <x-portal.empty-state class="mt-4" :title="__('No loan applications yet')" :body="__('When you apply for a loan, you can follow its progress here.')" />
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[34rem] text-left text-sm">
                    <thead class="text-xs text-ink-faint">
                        <tr>
                            <th class="py-2 pr-3 font-medium">{{ __('Reference') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('Loan type') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('Status') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('Last updated') }}</th>
                            <th class="py-2 font-medium"><span class="sr-only">{{ __('Action') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($recentApplications as $application)
                            <tr>
                                <td class="py-2.5 pr-3 font-mono text-xs text-ink-muted">{{ strtoupper(substr($application->public_id, 0, 8)) }}</td>
                                <td class="py-2.5 pr-3 text-ink">{{ $application->lenderProduct->loanProduct->name }}</td>
                                <td class="py-2.5 pr-3"><x-ui.badge tone="accent">{{ $application->status->getLabel() }}</x-ui.badge></td>
                                <td class="py-2.5 pr-3 text-ink-muted">{{ $application->updated_at->translatedFormat('d M Y') }}</td>
                                <td class="py-2.5 text-right"><a href="{{ route('applications.show', $application) }}" class="font-medium text-accent underline">{{ ($missingDocuments[$application->id] ?? 0) > 0 ? __('Continue') : __('View') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    {{-- Recent credit reports --}}
    <x-ui.card class="mt-6">
        <div class="flex items-start justify-between gap-3">
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Recent credit reports') }}</h2>
            <a href="{{ route('portal.reports.index') }}" class="text-sm font-medium text-accent underline">{{ __('View all') }}</a>
        </div>
        @include('portal.credit-reports.partials.table', ['reports' => $recentReports])
    </x-ui.card>
</x-layouts.portal>
