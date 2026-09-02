@props(['label' => null, 'name', 'options' => [], 'placeholder' => 'Start typing to search…', 'hint' => null])

@php $errorBag = $errors->has($name); @endphp

<div
    x-data="{
        open: false,
        options: @js(collect($options)->values()->all()),
        mode: 'select',
        query: '',
        init() {
            const current = $wire.{{ $name }};
            const match = this.options.find((o) => o.value === current);
            if (match) {
                this.query = match.label;
            } else if (current) {
                this.mode = 'custom';
            }
        },
        get filtered() {
            const q = this.query.trim().toLowerCase();
            return q
                ? this.options.filter((o) => o.label.toLowerCase().includes(q)).slice(0, 8)
                : this.options.slice(0, 8);
        },
        choose(option) {
            $wire.{{ $name }} = option.value;
            this.query = option.label;
            this.open = false;
        },
        chooseOther() {
            this.mode = 'custom';
            $wire.{{ $name }} = '';
            this.open = false;
            $nextTick(() => $refs.customInput?.focus());
        },
        backToSearch() {
            this.mode = 'select';
            this.query = '';
            $wire.{{ $name }} = '';
        },
    }"
    class="relative flex flex-col gap-1.5"
    @click.outside="open = false"
>
    @if ($label)
        <label for="{{ $name }}" class="text-sm font-medium text-ink">{{ $label }}</label>
    @endif

    <template x-if="mode === 'select'">
        <div class="relative">
            <input
                type="text"
                id="{{ $name }}"
                x-model="query"
                @focus="open = true"
                @input="open = true"
                placeholder="{{ $placeholder }}"
                autocomplete="off"
                class="w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint transition-colors focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30 {{ $errorBag ? 'border-warn' : 'border-line-strong' }}"
            >

            <div x-show="open" x-cloak class="absolute z-10 mt-1 max-h-56 w-full overflow-auto rounded-lg border border-line bg-surface py-1 shadow-lg">
                <template x-for="option in filtered" :key="option.value">
                    <button type="button" @click="choose(option)" class="block w-full px-3.5 py-2 text-left text-sm text-ink hover:bg-surface-2" x-text="option.label"></button>
                </template>
                <template x-if="filtered.length === 0">
                    <p class="px-3.5 py-2 text-sm text-ink-faint">No matches — choose "Other" below</p>
                </template>
                <button type="button" @click="chooseOther()" class="block w-full border-t border-line px-3.5 py-2 text-left text-sm font-medium text-accent hover:bg-surface-2">Other — enter manually</button>
            </div>
        </div>
    </template>

    <template x-if="mode === 'custom'">
        <div>
            <input
                type="text"
                id="{{ $name }}"
                wire:model="{{ $name }}"
                x-ref="customInput"
                placeholder="Enter {{ $label ? Str::lower(Str::before($label, ' (')) : 'a value' }}"
                class="w-full rounded-lg border bg-surface px-3.5 py-2.5 text-sm text-ink placeholder:text-ink-faint transition-colors focus:border-accent focus:outline-none focus:ring-2 focus:ring-accent/30 {{ $errorBag ? 'border-warn' : 'border-line-strong' }}"
            >
            <button type="button" @click="backToSearch()" class="mt-1.5 text-xs font-medium text-accent hover:underline">← Search from list instead</button>
        </div>
    </template>

    @if ($errorBag)
        <p class="text-xs text-warn">{{ $errors->first($name) }}</p>
    @elseif ($hint)
        <p class="text-xs text-ink-faint">{{ $hint }}</p>
    @endif
</div>
