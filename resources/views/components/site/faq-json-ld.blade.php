@props(['faqs'])

@if ($faqs->isNotEmpty())
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            '@id' => url()->current().'#faq',
            'mainEntity' => $faqs->map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq->question,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text' => \App\Support\Rates\RateText::render($faq->answer),
                ],
            ])->all(),
        ]) !!}
    </script>
@endif
