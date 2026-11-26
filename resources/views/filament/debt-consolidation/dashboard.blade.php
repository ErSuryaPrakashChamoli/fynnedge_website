@use('App\Filament\Resources\DebtApplications\DebtApplicationResource')
<x-filament-panels::page>
    @unless ($this->serviceLive())
        <x-filament::callout color="warning" icon="heroicon-o-exclamation-triangle" heading="The service is switched off on the website.">
            <x-slot name="description">Visitors see 404 on the Debt Consolidation pages until it is switched on in Website Settings → Debt Consolidation Settings and the pages are published in Website Pages → General Pages.</x-slot>
        </x-filament::callout>
    @endunless

    <x-admin.stat-band :columns="4" :items="collect($this->kpis())->map(fn ($kpi, $label) => ['label' => $label, 'value' => $kpi['value'], 'description' => $kpi['description'], 'tone' => $kpi['tone']])->values()->all()" />

    <div class="grid gap-6 lg:grid-cols-3">
        <x-filament::section heading="Case follow-ups due">
            @forelse ($this->caseFollowUps() as $case)
                <a href="{{ DebtApplicationResource::getUrl('view', ['record' => $case]) }}" class="flex justify-between py-1.5 text-sm hover:underline"><span>{{ $case->reference }} — {{ $case->status->getLabel() }}</span><span class="text-danger-600">{{ $case->follow_up_at->timezone('Asia/Kolkata')->format('j M, g:i a') }}</span></a>
            @empty
                <p class="text-sm text-gray-500">Nothing due.</p>
            @endforelse
        </x-filament::section>
        <x-filament::section heading="Communication follow-ups due">
            @forelse ($this->followUps() as $note)
                <a href="{{ DebtApplicationResource::getUrl('view', ['record' => $note->application]) }}?relation=communication" class="flex justify-between py-1.5 text-sm hover:underline"><span>{{ $note->application?->reference }} — {{ $note->subject ?? $note->type->getLabel() }}</span><span class="text-danger-600">{{ $note->follow_up_at->timezone('Asia/Kolkata')->format('j M, g:i a') }}</span></a>
            @empty
                <p class="text-sm text-gray-500">Nothing due.</p>
            @endforelse
        </x-filament::section>
        <x-filament::section heading="Foreclosure quotations expiring">
            @forelse ($this->expiringQuotations() as $quotation)
                <a href="{{ DebtApplicationResource::getUrl('view', ['record' => $quotation->liability->application]) }}?relation=closure" class="flex justify-between py-1.5 text-sm hover:underline"><span>{{ $quotation->liability->application->reference }} — {{ $quotation->liability->lender_name }}</span><span @class(['text-danger-600' => $quotation->isExpired()])>{{ $quotation->valid_until->format('j M') }}</span></a>
            @empty
                <p class="text-sm text-gray-500">None in the next 3 days.</p>
            @endforelse
            <p class="mt-3 text-xs text-gray-500">{{ $this->unverifiedQuotationCount() }} quotation(s) still unverified.</p>
        </x-filament::section>
    </div>
</x-filament-panels::page>
