{{-- One bureau's refresh availability, from RefreshEligibility. --}}
@props(['eligibility'])

@php
    $state = $eligibility->state;
@endphp

<div {{ $attributes->class('flex flex-col gap-3 rounded-xl border border-line p-4 sm:flex-row sm:items-center sm:justify-between') }} data-refresh-state="{{ $state }}">
    <div class="min-w-0 text-sm">
        <p class="font-medium text-ink">{{ $eligibility->bureau->getLabel() }}</p>
        <p class="mt-0.5 text-ink-muted">
            @if ($state === \App\Modules\Customers\DataTransferObjects\RefreshEligibility::AVAILABLE)
                {{ $eligibility->latestSuccess ? __('A refresh is available now.') : __('You can check this score now.') }}
            @elseif ($state === \App\Modules\Customers\DataTransferObjects\RefreshEligibility::TOO_SOON)
                {{ __('Next refresh available on :date.', ['date' => $eligibility->nextEligibleAt->translatedFormat('d M Y')]) }}
            @elseif ($state === \App\Modules\Customers\DataTransferObjects\RefreshEligibility::IN_PROGRESS)
                {{ __('Your last check is still waiting for the bureau. Please try again in an hour.') }}
            @else
                {{ __('Live scores from this bureau are not available right now.') }}
            @endif
        </p>
    </div>
    @if ($state === \App\Modules\Customers\DataTransferObjects\RefreshEligibility::AVAILABLE)
        <x-ui.button tag="a" :href="route('portal.credit-score.refresh', $eligibility->bureau)" size="sm" variant="secondary" class="shrink-0 justify-center">
            {{ $eligibility->latestSuccess ? __('Refresh score') : __('Check score') }}
        </x-ui.button>
    @endif
</div>
