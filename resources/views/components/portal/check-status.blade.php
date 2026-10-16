{{-- What happened to one credit check, in the customer's words. --}}
@props(['check'])

@php
    [$label, $tone] = match (true) {
        $check->status === \App\Modules\CreditBureau\Enums\CreditCheckStatus::Completed && $check->score !== null => [__('Score received'), 'pass'],
        $check->status === \App\Modules\CreditBureau\Enums\CreditCheckStatus::Completed => [__('No score yet'), 'muted'],
        $check->status === \App\Modules\CreditBureau\Enums\CreditCheckStatus::Pending => [__('Awaiting bureau'), 'warn'],
        default => [__('Not available'), 'warn'],
    };
@endphp

<x-ui.badge :tone="$tone" {{ $attributes }}>{{ $label }}</x-ui.badge>
