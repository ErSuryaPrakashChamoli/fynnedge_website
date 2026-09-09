{{--
    The FAQ block for pages that don't render one of their own — driven purely
    by which page an admin pinned each FAQ to (Admin → Page FAQs). Rendered by
    the app layout, so a new page picks this up with no view changes at all.

    Pages that DO own FAQs (loan products, CMS pages, the homepage, /faqs) opt
    out with `handles-faqs` on <x-layouts.app> and merge the pinned FAQs into
    their own list via PageFaqs::merge() instead — one accordion and one
    FAQPage entity per URL, never two.
--}}
@php($placementFaqs = \App\Support\Faqs\PageFaqs::forCurrentRoute())

@if ($placementFaqs->isNotEmpty())
    <section class="border-t border-line bg-surface">
        <div class="mx-auto max-w-3xl px-6 py-14 lg:px-8">
            <x-site.faq-accordion :faqs="$placementFaqs" class="!mt-0" data-ai-context="Frequently Asked Questions" />
            <x-site.faq-json-ld :faqs="$placementFaqs" />
        </div>
    </section>
@endif
