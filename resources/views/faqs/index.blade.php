<x-layouts.app :title="'FAQs'" description="Answers to common questions about how FynnEdge works, eligibility checks, and applying for a loan." handles-faqs>
    <section class="mx-auto max-w-3xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="['FAQs' => null]" />

        <h1 data-reveal="up" class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            Frequently asked questions
        </h1>
        <p data-reveal="up" class="mt-3 text-lg text-ink-muted">
            Answers to common questions about how FynnEdge works. Have a question about a specific loan
            product? Check that product's page, or <a href="{{ route('contact') }}" class="text-accent underline underline-offset-2">get in touch</a>.
        </p>

        @if ($faqs->isEmpty())
            <x-ui.alert tone="accent" class="mt-10">
                FAQs are being added. Check back shortly.
            </x-ui.alert>
        @else
            <div class="mt-10 flex flex-col divide-y divide-line border-y border-line">
                @foreach ($faqs as $faq)
                    <details data-reveal="fade stagger" class="group py-4">
                        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 text-sm font-medium text-ink">
                            {{ $faq->question }}
                            <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" class="h-4 w-4 shrink-0 transition-transform group-open:rotate-45"><path stroke-linecap="round" d="M10 4v12M4 10h12" /></svg>
                        </summary>
                        <p class="mt-3 text-sm text-ink-muted">{{ $faq->answer }}</p>
                    </details>
                @endforeach
            </div>

            <x-site.faq-json-ld :faqs="$faqs" />
        @endif
    </section>
</x-layouts.app>
