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

    <x-filament::section>
        <x-slot name="heading">Conversion funnel</x-slot>

        <div class="flex flex-col gap-3">
            @foreach ($this->funnel as $stage)
                <div>
                    <div class="flex items-center justify-between text-sm">
                        <span class="font-medium text-gray-950 dark:text-white">{{ $stage['label'] }}</span>
                        <span class="tabular-nums text-gray-500 dark:text-gray-400">{{ $stage['count'] }} &middot; {{ $stage['conversion'] }}%</span>
                    </div>
                    <div class="mt-1 h-2.5 w-full rounded-full bg-gray-100 dark:bg-white/10">
                        <div class="h-2.5 rounded-full bg-primary-500" style="width: {{ max($stage['conversion'], 2) }}%"></div>
                    </div>
                </div>
            @endforeach
        </div>
    </x-filament::section>

    @if ($this->stepBreakdown !== null)
        <x-filament::section>
            <x-slot name="heading">Step-by-step drop-off</x-slot>

            <div class="flex flex-col gap-3">
                @php $maxStepCount = max(1, collect($this->stepBreakdown)->max('count')); @endphp
                @foreach ($this->stepBreakdown as $step)
                    <div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="font-medium text-gray-950 dark:text-white">{{ $step['title'] }}</span>
                            <span class="tabular-nums text-gray-500 dark:text-gray-400">{{ $step['count'] }}</span>
                        </div>
                        <div class="mt-1 h-2.5 w-full rounded-full bg-gray-100 dark:bg-white/10">
                            <div class="h-2.5 rounded-full bg-warning-500" style="width: {{ max($step['count'] / $maxStepCount * 100, 2) }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-filament::section>
    @else
        <x-filament::section>
            <x-slot name="heading">By product</x-slot>

            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="border-b border-gray-100 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-white/5 dark:text-gray-400">
                        <tr>
                            <th class="py-2.5 pe-4">Product</th>
                            <th class="py-2.5 pe-4 text-right">Started</th>
                            <th class="py-2.5 pe-4 text-right">Submitted</th>
                            <th class="py-2.5 text-right">Conversion</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-white/5 dark:text-gray-300">
                        @forelse ($this->productBreakdown as $row)
                            <tr>
                                <td class="py-2.5 pe-4 font-medium text-gray-950 dark:text-white">{{ $row['name'] }}</td>
                                <td class="py-2.5 pe-4 text-right tabular-nums">{{ $row['started'] }}</td>
                                <td class="py-2.5 pe-4 text-right tabular-nums">{{ $row['submitted'] }}</td>
                                <td class="py-2.5 text-right tabular-nums">{{ $row['conversion'] }}%</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-6 text-center text-gray-500 dark:text-gray-400">No published loan products yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
