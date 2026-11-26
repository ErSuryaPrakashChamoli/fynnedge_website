@props([
    'rows' => [],
    'types' => [],
    'model' => 'liabilities',
    'detailed' => true,
    'live' => true,
])

{{--
    The loan rows of the Debt Consolidation calculator and application form.
    Rendered inside a Livewire component that owns $model (an array of
    LiabilityRows::blank() rows) and the addLiability/removeLiability actions.
--}}
@php
    $binding = $live ? 'wire:model.live.debounce.500ms' : 'wire:model.blur';
    $input = 'mt-1 w-full rounded-lg border border-line-strong bg-surface px-3 py-2 text-sm text-ink placeholder:text-ink-faint focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30';
    $label = 'text-xs font-medium text-ink-muted';
    $statuses = \App\Modules\DebtConsolidation\Enums\LiabilityAccountStatus::cases();
    $modes = \App\Modules\DebtConsolidation\Enums\ConsolidationMode::cases();
@endphp

<div class="flex flex-col gap-4">
    @foreach ($rows as $index => $row)
        <fieldset wire:key="liability-{{ $row['key'] }}" class="rounded-2xl border border-line bg-surface p-4 shadow-sm sm:p-5">
            <div class="flex items-center justify-between gap-3">
                <legend class="font-display text-sm font-semibold text-ink">{{ __('Loan :number', ['number' => $index + 1]) }}</legend>
                @if (count($rows) > 1)
                    <button type="button" wire:click="removeLiability({{ $index }})" class="text-xs font-medium text-warn hover:underline">{{ __('Remove') }}</button>
                @endif
            </div>

            <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                <label class="block">
                    <span class="{{ $label }}">{{ __('Type of debt') }}</span>
                    <select {{ $binding }}="{{ $model }}.{{ $index }}.type" class="{{ $input }}">
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}">{{ $type->getLabel() }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="block">
                    <span class="{{ $label }}">{{ __('Lender') }}</span>
                    <input type="text" {{ $binding }}="{{ $model }}.{{ $index }}.lender_name" maxlength="150" placeholder="{{ __('e.g. HDFC Bank') }}" class="{{ $input }}">
                    @error("{$model}.{$index}.lender_name") <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="{{ $label }}">{{ __('Outstanding principal (₹)') }}</span>
                    <input type="text" inputmode="decimal" {{ $binding }}="{{ $model }}.{{ $index }}.outstanding" placeholder="500000" class="{{ $input }}" @if ($index === 0) data-first-outstanding @endif>
                    @error("{$model}.{$index}.outstanding") <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="{{ $label }}">{{ __('Interest rate (% a year)') }}</span>
                    <input type="text" inputmode="decimal" {{ $binding }}="{{ $model }}.{{ $index }}.rate" placeholder="18" class="{{ $input }}">
                    @error("{$model}.{$index}.rate") <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="{{ $label }}">{{ in_array($row['type'] ?? '', ['credit_card_outstanding', 'overdraft', 'cash_credit'], true) ? __('Monthly payment you make (₹)') : __('Current EMI (₹)') }}</span>
                    <input type="text" inputmode="decimal" {{ $binding }}="{{ $model }}.{{ $index }}.emi" placeholder="18076" class="{{ $input }}">
                    @error("{$model}.{$index}.emi") <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror
                </label>
                <label class="block">
                    <span class="{{ $label }}">{{ __('Remaining months') }}</span>
                    <input type="text" inputmode="numeric" {{ $binding }}="{{ $model }}.{{ $index }}.months" placeholder="36" class="{{ $input }}">
                    @error("{$model}.{$index}.months") <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror
                </label>
            </div>

            @if ($detailed)
                <details class="mt-3 group" @if (filled($row['overdue'] ?? null) || filled($row['charge_percent'] ?? null) || ($row['mode'] ?? 'full') !== 'full') open @endif>
                    <summary class="cursor-pointer text-xs font-semibold text-accent">{{ __('Foreclosure, overdue and consolidation options') }}</summary>
                    <div class="mt-3 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        <label class="block">
                            <span class="{{ $label }}">{{ __('Foreclosure charge (% of amount closed)') }}</span>
                            <input type="text" inputmode="decimal" {{ $binding }}="{{ $model }}.{{ $index }}.charge_percent" placeholder="4" class="{{ $input }}">
                            @error("{$model}.{$index}.charge_percent") <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror
                        </label>
                        <label class="block">
                            <span class="{{ $label }}">{{ __('Fixed foreclosure fee (₹)') }}</span>
                            <input type="text" inputmode="decimal" {{ $binding }}="{{ $model }}.{{ $index }}.charge_fixed" class="{{ $input }}">
                        </label>
                        <label class="block">
                            <span class="{{ $label }}">{{ __('Other lender charges (₹)') }}</span>
                            <input type="text" inputmode="decimal" {{ $binding }}="{{ $model }}.{{ $index }}.other_charges" class="{{ $input }}">
                        </label>
                        <label class="block">
                            <span class="{{ $label }}">{{ __('Overdue amount (₹)') }}</span>
                            <input type="text" inputmode="decimal" {{ $binding }}="{{ $model }}.{{ $index }}.overdue" class="{{ $input }}">
                        </label>
                        <label class="block">
                            <span class="{{ $label }}">{{ __('Days overdue') }}</span>
                            <input type="text" inputmode="numeric" {{ $binding }}="{{ $model }}.{{ $index }}.days_past_due" class="{{ $input }}">
                        </label>
                        <label class="block">
                            <span class="{{ $label }}">{{ __('Instalment due before closure (₹)') }}</span>
                            <input type="text" inputmode="decimal" {{ $binding }}="{{ $model }}.{{ $index }}.pending" class="{{ $input }}">
                        </label>
                        <label class="block">
                            <span class="{{ $label }}">{{ __('Repayment status') }}</span>
                            <select {{ $binding }}="{{ $model }}.{{ $index }}.account_status" class="{{ $input }}">
                                @foreach ($statuses as $status)
                                    <option value="{{ $status->value }}">{{ $status->getLabel() }}</option>
                                @endforeach
                            </select>
                        </label>
                        <label class="block">
                            <span class="{{ $label }}">{{ __('In the consolidation') }}</span>
                            <select {{ $binding }}="{{ $model }}.{{ $index }}.mode" class="{{ $input }}">
                                @foreach ($modes as $mode)
                                    <option value="{{ $mode->value }}">{{ $mode->getLabel() }}</option>
                                @endforeach
                            </select>
                        </label>
                        @if (($row['mode'] ?? 'full') === 'partial')
                            <label class="block">
                                <span class="{{ $label }}">{{ __('Amount to pay down (₹)') }}</span>
                                <input type="text" inputmode="decimal" {{ $binding }}="{{ $model }}.{{ $index }}.partial_amount" class="{{ $input }}">
                                @error("{$model}.{$index}.partial_amount") <span class="mt-1 block text-xs text-warn">{{ $message }}</span> @enderror
                            </label>
                        @endif
                    </div>
                </details>
            @endif
        </fieldset>
    @endforeach

    @if (count($rows) < \App\Modules\DebtConsolidation\Services\LiabilityRows::MAX_ROWS)
        <button type="button" wire:click="addLiability" class="self-start rounded-full border border-dashed border-accent px-4 py-2 text-sm font-semibold text-accent hover:bg-accent-soft">+ {{ __('Add another loan or card') }}</button>
    @endif
</div>
