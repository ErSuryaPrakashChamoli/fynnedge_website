<x-filament-panels::page>
    <form wire:submit="evaluate">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit">
                Evaluate
            </x-filament::button>
        </div>
    </form>

    @if ($results !== null)
        <div class="mt-8 flex flex-col gap-4">
            @forelse ($results as $row)
                @php
                    $color = match ($row['status']) {
                        'eligible' => 'success',
                        'conditional' => 'warning',
                        default => 'danger',
                    };
                @endphp
                <x-filament::section>
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-base font-semibold">{{ $row['lender_name'] }}</p>
                            @if ($row['foir'] !== null)
                                <p class="text-xs text-gray-500">FOIR: {{ $row['foir'] }}%</p>
                            @endif
                        </div>
                        <x-filament::badge :color="$color">
                            {{ $row['status_label'] }}
                        </x-filament::badge>
                    </div>

                    @if (empty($row['reasons']))
                        <p class="mt-3 text-sm text-gray-500">No eligibility rules are configured yet for this lender product.</p>
                    @else
                        <ul class="mt-3 flex flex-col gap-1.5">
                            @foreach ($row['reasons'] as $reason)
                                <li class="flex items-center gap-2 text-sm">
                                    @if ($reason['passed'])
                                        <x-filament::icon icon="heroicon-o-check-circle" class="h-4 w-4 text-success-500" />
                                    @else
                                        <x-filament::icon icon="heroicon-o-x-circle" class="h-4 w-4 text-danger-500" />
                                    @endif
                                    <span>{{ $reason['label'] }}</span>
                                    <x-filament::badge size="xs" :color="$reason['priority'] === 'mandatory' ? 'danger' : ($reason['priority'] === 'preferred' ? 'success' : 'warning')">
                                        {{ $reason['priority'] }}
                                    </x-filament::badge>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </x-filament::section>
            @empty
                <x-filament::section>
                    <p class="text-sm text-gray-500">No active lenders offer this loan product yet.</p>
                </x-filament::section>
            @endforelse
        </div>
    @endif
</x-filament-panels::page>
