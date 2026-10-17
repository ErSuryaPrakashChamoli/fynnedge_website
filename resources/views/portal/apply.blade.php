<x-layouts.portal :title="__('Apply for a Loan')" :customer="$customer">
    <p class="text-sm text-ink-muted">{{ __('Choose a loan type. Your application follows our usual steps — your details, eligibility across lenders, then documents — and is saved as you go, so you can stop and come back any time.') }}</p>

    @if ($prefill !== [])
        <x-ui.alert class="mt-4" :title="__('We will fill in what we already know')">
            {{ __('Your name, verified mobile and other saved details are filled in for you to check. We always ask for loan details such as income and amount — we never work them out from your credit score.') }}
        </x-ui.alert>
    @endif

    @if ($loanProducts->isEmpty())
        <x-portal.empty-state class="mt-6" :title="__('No loan applications are open right now')" :body="__('Please check back soon, or contact us for help.')" />
    @else
        <div class="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            @foreach ($loanProducts as $loanProduct)
                @php $draft = $draftsByProduct->get($loanProduct->id); @endphp
                <x-ui.card class="flex flex-col" :data-product="$loanProduct->getRawOriginal('slug')">
                    <h2 class="font-display text-lg font-semibold text-ink">{{ $loanProduct->name }}</h2>
                    @if (filled($loanProduct->summary))
                        <p class="mt-2 text-sm text-ink-muted">{{ \Illuminate\Support\Str::limit(strip_tags((string) $loanProduct->summary), 140) }}</p>
                    @endif
                    <form method="POST" action="{{ route('portal.apply.start', $loanProduct) }}" class="mt-auto pt-4">
                        @csrf
                        <x-ui.button type="submit" :variant="$draft ? 'primary' : 'secondary'" class="w-full justify-center">
                            {{ $draft ? __('Continue application') : __('Apply now') }}
                        </x-ui.button>
                        @if ($draft)
                            <p class="mt-2 text-center text-xs text-ink-faint">{{ __('Last updated :date', ['date' => $draft->updated_at->translatedFormat('d M Y')]) }}</p>
                        @endif
                    </form>
                </x-ui.card>
            @endforeach
        </div>
    @endif
</x-layouts.portal>
