<x-layouts.portal :title="__('My Documents')" :customer="$customer">
    <p class="text-sm text-ink-muted">{{ __('Documents you upload to a loan application are stored privately and shown only to you and the team processing your application. To add or replace a document, open the application.') }}</p>

    @php $applicationsMissing = $applications->filter(fn ($application) => ($missingDocuments[$application->id] ?? 0) > 0); @endphp
    @if ($applicationsMissing->isNotEmpty())
        <x-ui.card class="mt-6" data-section="missing">
            <h2 class="font-display text-lg font-semibold text-ink">{{ __('Still needed') }}</h2>
            <ul class="mt-4 divide-y divide-line">
                @foreach ($applicationsMissing as $application)
                    <li class="flex flex-col gap-2 py-3 sm:flex-row sm:items-center sm:justify-between">
                        <p class="text-sm text-ink">
                            {{ $application->lenderProduct->loanProduct->name }} · {{ $application->lenderProduct->lender->name }}
                            <span class="block text-ink-muted">{{ trans_choice(':count document|:count documents', $missingDocuments[$application->id], ['count' => $missingDocuments[$application->id]]) }}</span>
                        </p>
                        <x-ui.button tag="a" :href="route('applications.show', $application)" size="sm" class="justify-center">{{ __('Upload') }}</x-ui.button>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    <x-ui.card class="mt-6" data-section="uploaded">
        <h2 class="font-display text-lg font-semibold text-ink">{{ __('Uploaded documents') }}</h2>
        @if ($documents->isEmpty())
            <x-portal.empty-state class="mt-4" :title="__('No documents uploaded yet')" :body="__('Documents are requested once you choose a lender for your application.')" />
        @else
            <div class="mt-4 overflow-x-auto">
                <table class="w-full min-w-[34rem] text-left text-sm">
                    <thead class="text-xs text-ink-faint">
                        <tr>
                            <th class="py-2 pr-3 font-medium">{{ __('Document') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('Application') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('Uploaded') }}</th>
                            <th class="py-2 pr-3 font-medium">{{ __('Status') }}</th>
                            <th class="py-2 font-medium"><span class="sr-only">{{ __('Action') }}</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($documents as $document)
                            <tr>
                                <td class="py-2.5 pr-3 text-ink">{{ $document->custom_label ?: $document->documentType->label }}</td>
                                <td class="py-2.5 pr-3 text-ink-muted">{{ $document->application->lenderProduct->loanProduct->name }}</td>
                                <td class="py-2.5 pr-3 text-ink-muted">{{ $document->uploaded_at->translatedFormat('d M Y') }}</td>
                                <td class="py-2.5 pr-3">
                                    <x-ui.badge :tone="match ($document->status) { \App\Modules\Applications\Enums\DocumentStatus::Verified => 'pass', \App\Modules\Applications\Enums\DocumentStatus::Rejected => 'warn', default => 'muted' }">{{ $document->status->getLabel() }}</x-ui.badge>
                                    @if ($document->rejection_reason)
                                        <span class="mt-1 block text-xs text-warn">{{ $document->rejection_reason }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 text-right"><a href="{{ route('portal.documents.show', $document->public_id) }}" class="font-medium text-accent underline">{{ __('Download') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>
</x-layouts.portal>
