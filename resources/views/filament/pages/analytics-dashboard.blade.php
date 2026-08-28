<x-filament-panels::page>
    <x-filament::section>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end">
            <div class="flex-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Loan product</label>
                <x-filament::input.wrapper class="mt-1">
                    <x-filament::input.select wire:model.live="loanProductId">
                        @foreach ($this->loanProductOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div class="flex-1">
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Date range</label>
                <x-filament::input.wrapper class="mt-1">
                    <x-filament::input.select wire:model.live="range">
                        @foreach ($this->rangeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <x-slot name="heading">Conversion funnel</x-slot>

        <div class="flex flex-col gap-3">
            @foreach ($this->funnel as $stage)
                <div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium">{{ $stage['label'] }}</span>
                        <span class="text-gray-500">{{ $stage['count'] }} &middot; {{ $stage['conversion'] }}%</span>
                    </div>
                    <div class="mt-1 h-2.5 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                        <div class="h-2.5 rounded-full bg-primary-500" style="width: {{ max($stage['conversion'], 2) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    @if ($this->stepBreakdown !== null)
        <x-filament::section class="mt-6">
            <x-slot name="heading">Step-by-step drop-off</x-slot>

            <div class="flex flex-col gap-3">
                @php $maxStepCount = max(1, collect($this->stepBreakdown)->max('count')); @endphp
                @foreach ($this->stepBreakdown as $step)
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium">{{ $step['title'] }}</span>
                            <span class="text-gray-500">{{ $step['count'] }}</span>
                        </div>
                        <div class="mt-1 h-2.5 w-full rounded-full bg-gray-100 dark:bg-gray-800">
                            <div class="h-2.5 rounded-full bg-warning-500" style="width: {{ max($step['count'] / $maxStepCount * 100, 2) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @else
        <x-filament::section class="mt-6">
            <x-slot name="heading">By product</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-gray-700">
                            <th class="py-2 pr-4 font-medium">Product</th>
                            <th class="py-2 pr-4 font-medium">Started</th>
                            <th class="py-2 pr-4 font-medium">Submitted</th>
                            <th class="py-2 font-medium">Conversion</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->productBreakdown as $row)
                            <tr class="border-b border-gray-100 dark:border-gray-800">
                                <td class="py-2 pr-4">{{ $row['name'] }}</td>
                                <td class="py-2 pr-4">{{ $row['started'] }}</td>
                                <td class="py-2 pr-4">{{ $row['submitted'] }}</td>
                                <td class="py-2">{{ $row['conversion'] }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-4 text-center text-gray-500">No published loan products yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
