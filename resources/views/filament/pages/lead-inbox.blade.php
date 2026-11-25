<x-filament-panels::page>
    <x-admin.stat-band
        :columns="5"
        :items="collect($this->summary())->map(fn (array $item): array => [
            'label' => $item['label'],
            'badge' => true,
            'tone' => $item['color'],
            'value' => number_format($item['week']),
            'description' => 'last 7 days · '.number_format($item['today']).' today',
        ])->all()"
    />

    {{ $this->table }}
</x-filament-panels::page>
