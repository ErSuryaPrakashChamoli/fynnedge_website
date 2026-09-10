<x-layouts.app handles-faqs :title="$page->seoTitle()" :description="$page->seoDescription()" :canonical="$page->seoCanonicalUrl()" :og-image="$page->seoOgImageUrl()" :social="$page->seoSocial()" :robots="$page->seoRobots()" :structured-data="$page->seoStructuredData()" :page-type="$page->seoPageType()" :schema-template="$page->seoSchemaTemplate()">
    <section class="mx-auto max-w-3xl px-6 py-14 lg:px-8">
        <x-ui.breadcrumbs :trail="[$page->title => null]" />

        <h1 data-reveal="up" class="mt-5 text-balance font-display text-3xl font-semibold tracking-tight text-ink sm:text-4xl">
            {{ $page->title }}
        </h1>
        @if ($page->excerpt)
            <p data-reveal="up" class="mt-3 text-lg text-ink-muted">{{ $page->excerpt }}</p>
        @endif

        @if ($page->body)
            <div data-reveal="fade" class="prose prose-neutral mt-10 max-w-none text-ink-muted [&_h2]:font-display [&_h2]:text-ink [&_h2]:mt-8 [&_p]:leading-relaxed">
                {!! $page->body !!}
            </div>
        @endif

        @if (($grievanceLevels ?? collect())->isNotEmpty())
            <div data-reveal="zoom" class="mt-10 overflow-x-auto rounded-2xl border border-line">
                <table class="w-full text-left text-sm">
                    <thead class="bg-surface-2 text-xs font-semibold uppercase tracking-wide text-ink-faint">
                        <tr>
                            <th class="px-5 py-3">Level</th>
                            <th class="px-5 py-3">Turn-around Time</th>
                            <th class="px-5 py-3">Name &amp; Designation</th>
                            <th class="px-5 py-3">Contact</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($grievanceLevels as $level)
                            <tr>
                                <td class="px-5 py-4 align-top font-medium text-ink">{{ $level->level }}</td>
                                <td class="px-5 py-4 align-top text-ink-muted">{{ $level->turnaround_time }}</td>
                                <td class="px-5 py-4 align-top">
                                    <p class="text-ink">Name: <span class="font-semibold">{{ $level->contact_name }}</span></p>
                                    <p class="mt-1 text-ink-muted">Designation: {{ $level->designation }}</p>
                                </td>
                                <td class="px-5 py-4 align-top text-ink-muted">
                                    @if ($level->address)
                                        <p>Address: {{ $level->address }}</p>
                                    @endif
                                    @if ($level->phone)
                                        <p class="mt-1">Phone: <span class="font-semibold text-ink">{{ $level->phone }}</span></p>
                                    @endif
                                    @if ($level->email)
                                        <p class="mt-1">Email: <a href="mailto:{{ $level->email }}" class="text-accent">{{ $level->email }}</a></p>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <x-site.faq-accordion :faqs="$faqs" />
        <x-site.faq-json-ld :faqs="$faqs" />
    </section>
</x-layouts.app>
