<x-filament-panels::page>
    <x-filament::section>
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Search filename/path</label>
                <x-filament::input.wrapper class="mt-1">
                    <x-filament::input type="text" wire:model.live.debounce.400ms="search" placeholder="e.g. banners/" />
                </x-filament::input.wrapper>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Source</label>
                <x-filament::input.wrapper class="mt-1">
                    <x-filament::input.select wire:model.live="model">
                        @foreach ($this->modelOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">File type</label>
                <x-filament::input.wrapper class="mt-1">
                    <x-filament::input.select wire:model.live="fileType">
                        @foreach ($this->fileTypeOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
            <div>
                <label class="text-sm font-medium text-gray-700 dark:text-gray-300">Status</label>
                <x-filament::input.wrapper class="mt-1">
                    <x-filament::input.select wire:model.live="status">
                        @foreach ($this->statusOptions() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </div>
        </div>
    </x-filament::section>

    <x-filament::section>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="border-b border-gray-100 text-left text-[11px] font-bold uppercase tracking-wider text-gray-500 dark:border-white/5 dark:text-gray-400">
                    <tr>
                        <th class="py-2.5 pe-4">Preview</th>
                        <th class="py-2.5 pe-4">File</th>
                        <th class="py-2.5 pe-4">Disk</th>
                        <th class="py-2.5 pe-4">Type</th>
                        <th class="py-2.5 pe-4">Size</th>
                        <th class="py-2.5 pe-4">Modified</th>
                        <th class="py-2.5 pe-4">Used by</th>
                        <th class="py-2.5 pe-4">Status</th>
                        <th class="py-2.5">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 text-gray-700 dark:divide-white/5 dark:text-gray-300">
                    @forelse ($this->filteredEntries() as $entry)
                        <tr class="align-top">
                            <td class="py-2.5 pe-4 font-medium text-gray-950 dark:text-white">
                                @if ($entry->existsOnDisk && $entry->isImage())
                                    <img src="{{ Illuminate\Support\Facades\Storage::disk($entry->disk)->url($entry->path) }}" class="h-12 w-12 rounded object-cover" loading="lazy" alt="">
                                @else
                                    <span class="flex h-12 w-12 items-center justify-center rounded bg-gray-100 font-mono text-[0.6rem] uppercase text-gray-400 dark:bg-white/5">{{ $entry->extension() ?: '?' }}</span>
                                @endif
                            </td>
                            <td class="py-2.5 pe-4">
                                <p class="font-medium text-gray-950 dark:text-white">{{ $entry->filename() }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $entry->path }}</p>
                            </td>
                            <td class="py-2.5 pe-4">{{ $entry->disk }}</td>
                            <td class="py-2.5 pe-4 uppercase">{{ $entry->extension() ?: '—' }}</td>
                            <td class="py-2.5 pe-4 whitespace-nowrap tabular-nums">{{ $entry->humanSize() ?? '—' }}</td>
                            <td class="py-2.5 pe-4 whitespace-nowrap tabular-nums">{{ $entry->lastModifiedAt?->format('d M Y, H:i') ?? '—' }}</td>
                            <td class="py-2.5 pe-4">
                                @forelse ($entry->references as $reference)
                                    <div class="mb-1 last:mb-0">
                                        <span class="font-medium text-gray-950 dark:text-white">{{ $reference->modelLabel }}</span> —
                                        @if ($reference->recordEditUrl)
                                            <a href="{{ $reference->recordEditUrl }}" class="text-primary-600 underline dark:text-primary-400" target="_blank">{{ $reference->recordLabel }}</a>
                                        @else
                                            {{ $reference->recordLabel }}
                                        @endif
                                        <span class="text-xs text-gray-400">({{ $reference->fieldLabel }})</span>
                                    </div>
                                @empty
                                    <span class="text-gray-400">Not referenced</span>
                                @endforelse
                                @if ($entry->usageCount() > 1)
                                    <p class="mt-1 text-xs font-semibold text-gray-500 dark:text-gray-400">{{ $entry->usageCount() }} usages</p>
                                @endif
                            </td>
                            <td class="py-2.5 pe-4">
                                <x-filament::badge :color="match ($entry->status()) {
                                    'used' => 'success',
                                    'unused' => 'warning',
                                    'missing' => 'danger',
                                }">
                                    {{ ucfirst($entry->status()) }}
                                </x-filament::badge>
                            </td>
                            <td class="py-2.5">
                                @if ($entry->status() === 'unused')
                                    <x-filament::button
                                        size="xs"
                                        color="danger"
                                        wire:click="deleteUnused('{{ $entry->disk }}', '{{ $entry->path }}')"
                                        wire:confirm="Delete this unused file permanently? This cannot be undone."
                                    >
                                        Delete
                                    </x-filament::button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="py-6 text-center text-gray-500 dark:text-gray-400">No media matches these filters.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">
            {{ $this->filteredEntries()->links() }}
        </div>
    </x-filament::section>
</x-filament-panels::page>
