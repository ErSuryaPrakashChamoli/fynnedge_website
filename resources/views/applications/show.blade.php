<x-layouts.app title="Your Application" robots="noindex, nofollow">
    <section class="mx-auto max-w-3xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['Application' => null]" />

        <h1 class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            {{ $application->lenderProduct->lender->name }}
        </h1>
        <p class="mt-3 text-ink-muted">
            {{ $application->lenderProduct->loanProduct->name }} application —
            <x-ui.badge tone="accent">{{ $application->status->getLabel() }}</x-ui.badge>
        </p>

        @if (session('status'))
            <x-ui.alert tone="accent" class="mt-6">{{ session('status') }}</x-ui.alert>
        @endif

        @if ($application->status->value === 'submitted')
            <x-ui.card class="mt-8">
                <p class="font-display text-lg font-semibold text-ink">Application submitted</p>
                <p class="mt-2 text-sm text-ink-muted">
                    We've received your documents and forwarded your application to {{ $application->lenderProduct->lender->name }}.
                    We'll be in touch with next steps.
                </p>
            </x-ui.card>
        @else
            <div class="mt-10">
                <h2 class="font-display text-xl font-semibold text-ink">Required documents</h2>
                <p class="mt-1 text-sm text-ink-muted">Accepted formats: PDF, JPG, PNG — up to 5MB each.</p>

                <form method="POST" action="{{ route('applications.documents.upload', $application) }}" enctype="multipart/form-data" class="mt-6 flex flex-col gap-5">
                    @csrf

                    @foreach ($requirements as $requirement)
                        @php $uploaded = $uploadedByType->get($requirement->document_type_id); @endphp
                        <x-ui.card>
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <p class="font-medium text-ink">{{ $requirement->documentType->label }}</p>
                                    @if ($requirement->notes)
                                        <p class="mt-1 text-xs text-ink-faint">{{ $requirement->notes }}</p>
                                    @endif
                                </div>
                                @if ($uploaded)
                                    <x-ui.badge :tone="$uploaded->status->value === 'rejected' ? 'warn' : 'pass'">
                                        {{ $uploaded->status->getLabel() }}
                                    </x-ui.badge>
                                @endif
                            </div>

                            @if ($uploaded)
                                <p class="mt-3 text-sm text-ink-muted">Uploaded: {{ $uploaded->original_filename }}</p>
                                @if ($uploaded->status->value === 'rejected' && $uploaded->rejection_reason)
                                    <p class="mt-1 text-sm text-warn">{{ $uploaded->rejection_reason }}</p>
                                @endif
                                <label class="mt-3 block text-xs font-medium text-ink-muted">Replace file</label>
                            @endif

                            <input
                                type="file"
                                name="documents[{{ $requirement->document_type_id }}]"
                                class="mt-2 block w-full text-sm text-ink-muted file:mr-4 file:rounded-full file:border-0 file:bg-accent-soft file:px-4 file:py-2 file:text-sm file:font-medium file:text-accent"
                            >
                            @error("documents.{$requirement->document_type_id}")
                                <p class="mt-1 text-xs text-warn">{{ $message }}</p>
                            @enderror
                        </x-ui.card>
                    @endforeach

                    <x-ui.button type="submit">Upload documents</x-ui.button>
                </form>

                <form method="POST" action="{{ route('applications.submit', $application) }}" class="mt-6">
                    @csrf
                    <x-ui.button type="submit" :disabled="! $allUploaded">
                        Submit application
                    </x-ui.button>
                    @unless ($allUploaded)
                        <p class="mt-2 text-xs text-ink-faint">Upload every required document above to submit.</p>
                    @endunless
                </form>
            </div>
        @endif
    </section>
</x-layouts.app>
