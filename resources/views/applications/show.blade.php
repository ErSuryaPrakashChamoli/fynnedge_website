<x-layouts.app :title="__('Your Application')" robots="noindex, nofollow">
    <section class="mx-auto max-w-7xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="[__('Application') => null]" />

        <div class="mt-5 flex items-center gap-4">
            <x-ui.lender-logo :lender="$application->lenderProduct->lender" size="lg" />
            <div>
                <h1 class="text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
                    {{ $application->lenderProduct->lender->name }}
                </h1>
                <p class="mt-2 text-ink-muted">
                    {{ __(':product application', ['product' => $application->lenderProduct->loanProduct->name]) }} —
                    <x-ui.badge tone="accent">{{ $application->status->getLabel() }}</x-ui.badge>
                </p>
            </div>
        </div>

        @if (session('status'))
            <x-ui.alert tone="accent" class="mt-6">{{ session('status') }}</x-ui.alert>
        @endif

        @if ($application->status->value === 'submitted')
            <x-ui.card class="mt-8">
                <p class="font-display text-lg font-semibold text-ink">{{ __('Application Submitted. Journey Started. 🚀') }}</p>
                <p class="mt-2 text-sm text-ink-muted">
                    {{ __('Your documents are safely received and your application has been forwarded to :lender.', ['lender' => $application->lenderProduct->lender->name]) }}
                </p>
                <p class="mt-2 text-sm text-ink-muted">
                    {{ __('FynnEdge will keep you informed at every stage.') }}
                </p>
                <p class="mt-4 text-xs text-ink-faint">{{ __('Powered by FynnEdge.') }}</p>
            </x-ui.card>
        @elseif ($application->assistance_preference === null)
            <div class="mt-10">
                <h2 class="font-display text-xl font-semibold text-ink">{{ __('How would you like to continue?') }}</h2>
                <p class="mt-1 text-sm text-ink-muted">
                    {{ __('Choose how you\'d like to move forward with your :lender application.', ['lender' => $application->lenderProduct->lender->name]) }}
                </p>

                <div class="mt-6 grid gap-5 sm:grid-cols-2">
                    <x-ui.card class="flex flex-col">
                        <p class="font-display text-lg font-semibold text-ink">{{ __('Upload documents myself') }}</p>
                        <p class="mt-2 flex-1 text-sm text-ink-muted">
                            {{ __('Submit your KYC, income and other required documents online and track your application yourself.') }}
                        </p>
                        <form method="POST" action="{{ route('applications.assistance', $application) }}" class="mt-5">
                            @csrf
                            <input type="hidden" name="assistance_preference" value="self_service">
                            <x-ui.button type="submit" size="sm" class="w-full">{{ __('Continue with documents') }}</x-ui.button>
                        </form>
                    </x-ui.card>

                    <x-ui.card class="flex flex-col">
                        <p class="font-display text-lg font-semibold text-ink">{{ __('Connect with a FynnEdge Loan Expert') }}</p>
                        <p class="mt-2 flex-1 text-sm text-ink-muted">
                            {{ __('Let one of our loan experts collect your documents and guide you through the rest of the process.') }}
                        </p>
                        <form method="POST" action="{{ route('applications.assistance', $application) }}" class="mt-5">
                            @csrf
                            <input type="hidden" name="assistance_preference" value="expert_assisted">
                            <x-ui.button type="submit" size="sm" variant="secondary" class="w-full">{{ __('Connect with a Loan Expert') }}</x-ui.button>
                        </form>
                    </x-ui.card>
                </div>
            </div>
        @elseif ($application->assistance_preference->value === 'expert_assisted')
            <x-ui.card class="mt-8">
                <p class="font-display text-lg font-semibold text-ink">{{ __('Your Loan Journey Just Got Easier. ✨') }}</p>
                <p class="mt-2 text-sm text-ink-muted">
                    {{ __('Your request has been received. Our FynnEdge Loan Expert will connect with you shortly and guide you through the next steps.') }}
                </p>
            </x-ui.card>
        @else
            <div class="mt-10">
                <h2 class="font-display text-xl font-semibold text-ink">{{ __('Required documents') }}</h2>
                <p class="mt-1 text-sm text-ink-muted">{{ __('Accepted formats: PDF, JPG, PNG — up to 5MB each.') }}</p>

                <form method="POST" action="{{ route('applications.documents.upload', $application) }}" enctype="multipart/form-data" class="mt-6 flex flex-col gap-5" data-require-file-upload>
                    @csrf

                    @foreach ($requirements as $requirement)
                        @php
                            $documentType = $requirement->documentType;
                            $typeId = $requirement->document_type_id;
                            $uploadedSlots = $uploadedByType->get($typeId, collect())->keyBy('slot');
                            $slotCount = max($requirement->min_slots, ($uploadedSlots->keys()->max() ?? -1) + 1, 1);
                        @endphp
                        <x-ui.card>
                            <div>
                                <p class="font-medium text-ink">
                                    {{ $documentType->label }}
                                    @unless ($requirement->is_required)
                                        <span class="font-normal text-ink-faint">{{ __('(optional)') }}</span>
                                    @endunless
                                </p>
                                @if ($requirement->notes)
                                    <p class="mt-1 text-xs text-ink-faint">{{ $requirement->notes }}</p>
                                @endif
                            </div>

                            <div class="mt-3 flex flex-col gap-4" data-document-slots="{{ $typeId }}" data-next-slot="{{ $slotCount }}">
                                @for ($slot = 0; $slot < $slotCount; $slot++)
                                    @php $uploaded = $uploadedSlots->get($slot); @endphp
                                    <div class="{{ $slot > 0 ? 'border-t border-line pt-4' : '' }}" data-document-slot>
                                        @if ($uploaded)
                                            <div class="mb-2 flex items-start justify-between gap-4">
                                                <p class="text-sm text-ink-muted">
                                                    {{ __('Uploaded: :file', ['file' => ($uploaded->custom_label ? "{$uploaded->custom_label} — " : '').$uploaded->original_filename]) }}
                                                </p>
                                                <x-ui.badge :tone="$uploaded->status->value === 'rejected' ? 'warn' : 'pass'">
                                                    {{ $uploaded->status->getLabel() }}
                                                </x-ui.badge>
                                            </div>
                                            @if ($uploaded->status->value === 'rejected' && $uploaded->rejection_reason)
                                                <p class="mb-2 text-sm text-warn">{{ $uploaded->rejection_reason }}</p>
                                            @endif
                                            @if ($documentType->allow_multiple)
                                                <form method="POST" action="{{ route('applications.documents.delete', [$application, $uploaded]) }}" class="mb-2">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="cursor-pointer text-xs font-medium text-warn hover:underline">{{ __('Remove this document') }}</button>
                                                </form>
                                            @endif
                                            <label class="mb-1 block text-xs font-medium text-ink-muted">{{ __('Replace file') }}</label>
                                        @endif

                                        @if ($documentType->allow_custom_label)
                                            <input
                                                type="text"
                                                name="document_labels[{{ $typeId }}][{{ $slot }}]"
                                                value="{{ old("document_labels.{$typeId}.{$slot}", $uploaded->custom_label ?? '') }}"
                                                placeholder="{{ __('What is this document? e.g. Rent Agreement') }}"
                                                class="mb-2 block w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink"
                                            >
                                        @endif

                                        <input
                                            type="file"
                                            name="documents[{{ $typeId }}][{{ $slot }}]"
                                            class="block w-full text-sm text-ink-muted file:mr-4 file:rounded-full file:border-0 file:bg-accent-soft file:px-4 file:py-2 file:text-sm file:font-medium file:text-accent"
                                        >
                                        @error("documents.{$typeId}.{$slot}")
                                            <p class="mt-1 text-xs text-warn">{{ $message }}</p>
                                        @enderror
                                    </div>
                                @endfor
                            </div>

                            @if ($documentType->allow_multiple)
                                <template data-document-slot-template="{{ $typeId }}">
                                    <div class="border-t border-line pt-4" data-document-slot>
                                        @if ($documentType->allow_custom_label)
                                            <input
                                                type="text"
                                                name="document_labels[{{ $typeId }}][__SLOT__]"
                                                placeholder="{{ __('What is this document? e.g. Rent Agreement') }}"
                                                class="mb-2 block w-full rounded-lg border border-line bg-surface px-3 py-2 text-sm text-ink"
                                            >
                                        @endif
                                        <input
                                            type="file"
                                            name="documents[{{ $typeId }}][__SLOT__]"
                                            class="block w-full text-sm text-ink-muted file:mr-4 file:rounded-full file:border-0 file:bg-accent-soft file:px-4 file:py-2 file:text-sm file:font-medium file:text-accent"
                                        >
                                        <button type="button" data-remove-document-slot class="mt-2 cursor-pointer text-xs font-medium text-warn hover:underline">{{ __('Remove') }}</button>
                                    </div>
                                </template>
                                <button
                                    type="button"
                                    data-add-document-slot="{{ $typeId }}"
                                    class="mt-3 cursor-pointer text-sm font-semibold text-accent hover:underline"
                                >
                                    {{ __('+ Add another document') }}
                                </button>
                            @endif
                        </x-ui.card>
                    @endforeach

                    <div>
                        <x-ui.button type="submit">{{ __('Upload documents') }}</x-ui.button>
                        @error('documents')
                            <p class="mt-2 text-xs text-warn">{{ $message }}</p>
                        @enderror
                        <p data-upload-empty-error hidden class="mt-2 text-xs text-warn">{{ __('Choose at least one file before uploading.') }}</p>
                    </div>
                </form>

                <form method="POST" action="{{ route('applications.submit', $application) }}" class="mt-6">
                    @csrf
                    <x-ui.button type="submit" :disabled="! $allUploaded">
                        {{ __('Submit application') }}
                    </x-ui.button>
                    @unless ($allUploaded)
                        <p class="mt-2 text-xs text-ink-faint">{{ __('Upload every required document above to submit.') }}</p>
                    @endunless
                </form>
            </div>
        @endif
    </section>
</x-layouts.app>
