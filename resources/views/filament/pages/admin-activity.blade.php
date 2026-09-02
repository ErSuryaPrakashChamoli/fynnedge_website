<x-filament-panels::page>
    <x-filament::section>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Admin</label>
                <x-filament::input.wrapper class="mt-1">
                    <x-filament::input.select wire:model.live="userId">
                        @foreach ($this->userOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Action</label>
                <x-filament::input.wrapper class="mt-1">
                    <x-filament::input.select wire:model.live="action">
                        @foreach ($this->actionOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Entity</label>
                <x-filament::input.wrapper class="mt-1">
                    <x-filament::input.select wire:model.live="model">
                        @foreach ($this->modelOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">From</label>
                <x-filament::input.wrapper class="mt-1">
                    <x-filament::input type="date" wire:model.live="dateFrom" />
                </x-filament::input.wrapper>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">To</label>
                <x-filament::input.wrapper class="mt-1">
                    <x-filament::input type="date" wire:model.live="dateTo" />
                </x-filament::input.wrapper>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section class="mt-6">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-200 text-left text-gray-500 dark:border-gray-700">
                        <th class="py-2 pr-4 font-medium">Admin</th>
                        <th class="py-2 pr-4 font-medium">Action</th>
                        <th class="py-2 pr-4 font-medium">Entity</th>
                        <th class="py-2 pr-4 font-medium">Record</th>
                        <th class="py-2 pr-4 font-medium">Changes</th>
                        <th class="py-2 font-medium">Date</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($this->logs as $log)
                        <tr class="border-b border-gray-100 align-top dark:border-gray-800">
                            <td class="py-3 pr-4">{{ $log->user->name ?? 'System' }}</td>
                            <td class="py-3 pr-4">
                                <x-filament::badge :color="match ($log->action) {
                                    'created' => 'success',
                                    'deleted' => 'danger',
                                    default => 'gray',
                                }">
                                    {{ ucfirst($log->action) }}
                                </x-filament::badge>
                            </td>
                            <td class="py-3 pr-4">{{ \Illuminate\Support\Str::headline(class_basename($log->auditable_type)) }}</td>
                            <td class="py-3 pr-4">{{ static::recordLabel($log) }}</td>
                            <td class="py-3 pr-4">{!! static::formatChanges($log->changes) !!}</td>
                            <td class="py-3 whitespace-nowrap">{{ $log->created_at->format('d M Y, H:i') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-gray-500">No matching activity yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->logs->links() }}
        </div>
    </x-filament::section>
</x-filament-panels::page>
