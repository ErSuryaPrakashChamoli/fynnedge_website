<footer class="border-t border-line bg-surface">
    <div class="mx-auto max-w-7xl px-6 py-10 lg:px-8">
        <div @class(['grid grid-cols-2 gap-x-10 gap-y-8' => true, 'sm:grid-cols-5' => ($navigationLinks ?? collect())->isNotEmpty(), 'sm:grid-cols-4' => ($navigationLinks ?? collect())->isEmpty()])>
            <div class="col-span-2 sm:col-span-1">
                <div class="flex items-center gap-2">
                    <img src="{{ $siteBranding['logoUrl'] }}" alt="" class="h-8 w-8" width="32" height="32">
                    <p class="font-display text-lg font-semibold text-ink">{{ $siteBranding['name'] }}</p>
                </div>
                <p class="mt-2 font-display text-sm italic text-accent">{{ $siteBranding['tagline'] }}</p>
                <p class="mt-4 text-xs text-ink-faint">{{ $footerLegal['name'] }}</p>

                @if (collect($contactDetails ?? [])->filter()->isNotEmpty())
                    <ul class="mt-4 flex flex-col gap-2 text-sm text-ink-muted">
                        @if ($contactDetails['address'])
                            <li class="flex items-start gap-2">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="mt-0.5 h-4 w-4 shrink-0 text-ink-faint" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                                </svg>
                                <span>{{ $contactDetails['address'] }}</span>
                            </li>
                        @endif
                        @if ($contactDetails['phone'])
                            <li class="flex items-center gap-2">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4 w-4 shrink-0 text-ink-faint" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.362-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z" />
                                </svg>
                                <a href="tel:{{ $contactDetails['phone'] }}" class="transition-colors hover:text-ink">{{ $contactDetails['phone'] }}</a>
                            </li>
                        @endif
                        @if ($contactDetails['email'])
                            <li class="flex items-center gap-2">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="h-4 w-4 shrink-0 text-ink-faint" aria-hidden="true">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                </svg>
                                <a href="mailto:{{ $contactDetails['email'] }}" class="transition-colors hover:text-ink">{{ $contactDetails['email'] }}</a>
                            </li>
                        @endif
                    </ul>
                @endif

                @php
                    $socialIcons = [
                        'Instagram' => 'M12 2.16c3.2 0 3.58.01 4.85.07 1.17.05 1.8.25 2.23.42.56.22.96.48 1.38.9.42.42.68.82.9 1.38.17.42.37 1.05.42 2.22.06 1.27.07 1.65.07 4.85s-.01 3.58-.07 4.85c-.05 1.17-.25 1.8-.42 2.22-.22.56-.48.96-.9 1.38-.42.42-.82.68-1.38.9-.42.17-1.06.37-2.23.42-1.27.06-1.65.07-4.85.07s-3.58-.01-4.85-.07c-1.17-.05-1.8-.25-2.23-.42a3.7 3.7 0 0 1-1.38-.9 3.7 3.7 0 0 1-.9-1.38c-.17-.42-.37-1.05-.42-2.22-.06-1.27-.07-1.65-.07-4.85s.01-3.58.07-4.85c.05-1.17.25-1.8.42-2.22.22-.56.48-.96.9-1.38.42-.42.82-.68 1.38-.9.42-.17 1.06-.37 2.23-.42 1.27-.06 1.65-.07 4.85-.07Zm0 3.68a6.16 6.16 0 1 0 0 12.32 6.16 6.16 0 0 0 0-12.32Zm0 10.16a4 4 0 1 1 0-8 4 4 0 0 1 0 8Zm7.85-10.4a1.44 1.44 0 1 1-2.88 0 1.44 1.44 0 0 1 2.88 0Z',
                        'Facebook' => 'M22.68 0H1.32C.59 0 0 .59 0 1.32v21.36C0 23.41.59 24 1.32 24h11.5v-9.3H9.7v-3.62h3.12V8.4c0-3.1 1.9-4.79 4.66-4.79 1.32 0 2.46.1 2.8.14v3.24h-1.92c-1.5 0-1.8.72-1.8 1.77v2.32h3.6l-.47 3.63h-3.13V24h6.14c.73 0 1.32-.59 1.32-1.32V1.32C24 .59 23.41 0 22.68 0Z',
                        'WhatsApp' => 'M12.04 2C6.58 2 2.13 6.44 2.13 11.9c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38c1.45.79 3.08 1.21 4.79 1.21h.01c5.46 0 9.9-4.44 9.9-9.9 0-2.64-1.03-5.12-2.9-6.99A9.82 9.82 0 0 0 12.04 2Zm4.42 12.29c-.2.55-1.13 1.04-1.57 1.11-.4.06-.91.08-1.46-.09-.34-.1-.77-.24-1.32-.48-2.36-1-3.85-3.35-3.96-3.5-.12-.16-.94-1.24-.94-2.36s.6-1.66.8-1.9c.2-.24.45-.3.6-.3s.3 0 .43.01c.14.01.32-.05.5.4.2.48.67 1.66.73 1.78.06.11.1.25.02.4-.08.16-.12.26-.24.4-.12.14-.25.31-.36.42-.11.12-.24.24-.1.48.14.23.62 1.01 1.32 1.63.9.8 1.66 1.05 1.9 1.17.24.11.37.09.51-.06.14-.15.59-.69.74-.91.16-.22.32-.19.53-.11.22.08 1.38.65 1.6.76.24.12.39.18.45.27.06.09.06.56-.14 1.11Z',
                        'LinkedIn' => 'M20.45 20.45h-3.56v-5.57c0-1.33-.02-3.04-1.85-3.04-1.85 0-2.14 1.45-2.14 2.94v5.67H9.34V9h3.42v1.56h.05c.48-.9 1.64-1.85 3.38-1.85 3.6 0 4.27 2.37 4.27 5.46v6.28ZM5.34 7.43a2.07 2.07 0 1 1 0-4.13 2.07 2.07 0 0 1 0 4.13ZM7.12 20.45H3.56V9h3.56v11.45Z',
                        'X' => 'm18.24 2.25 3.31.001-7.23 8.26 8.5 11.24H16.17l-5.21-6.82-5.96 6.82H1.68l7.73-8.83-7.16-9.44h6.83l4.71 6.23zm-1.16 17.52h1.83L7.08 4.13H5.12z',
                    ];
                @endphp
                @if (collect($socialLinks ?? [])->filter()->isNotEmpty())
                    <div class="mt-4 flex items-center gap-3">
                        @foreach ($socialLinks as $platform => $url)
                            @if ($url)
                                <a
                                    href="{{ $url }}"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    aria-label="FynnEdge on {{ $platform }}"
                                    class="flex h-8 w-8 items-center justify-center rounded-full bg-surface-2 text-ink-muted transition-colors hover:bg-accent-soft hover:text-accent"
                                >
                                    <svg viewBox="0 0 24 24" fill="currentColor" class="h-4 w-4" aria-hidden="true"><path d="{{ $socialIcons[$platform] }}" /></svg>
                                </a>
                            @endif
                        @endforeach
                    </div>
                @endif
            </div>

            <div>
                <p class="border-b border-line pb-2 font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-accent">Loans</p>
                <ul class="mt-3 flex flex-col gap-2">
                    @forelse ($loanProducts ?? [] as $product)
                        <li><a href="{{ route('loans.show', $product) }}" class="text-sm font-medium text-ink-muted transition-colors hover:text-ink">{{ $product->name }}</a></li>
                    @empty
                        <li><x-site.nav-link route="loans.index" label="Browse loans" /></li>
                    @endforelse
                </ul>
            </div>

            <div>
                <p class="border-b border-line pb-2 font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-accent">Company</p>
                <ul class="mt-3 flex flex-col gap-2">
                    <li><x-site.nav-link route="about" label="About" /></li>
                    <li><x-site.nav-link route="careers" label="Careers" /></li>
                    <li><x-site.nav-link route="contact" label="Contact" /></li>
                    <li><x-site.nav-link route="grievance" label="Grievance" /></li>
                </ul>
            </div>

            <div>
                <p class="border-b border-line pb-2 font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-accent">Legal</p>
                <ul class="mt-3 flex flex-col gap-2">
                    <li><x-site.nav-link route="privacy-policy" label="Privacy Policy" /></li>
                    <li><x-site.nav-link route="terms" label="Terms" /></li>
                    <li><x-site.nav-link route="disclaimer" label="Disclaimer" /></li>
                    <li><x-site.nav-link route="credit-report-terms" label="Credit Report Terms of Use" /></li>
                </ul>
            </div>

            @if (($navigationLinks ?? collect())->isNotEmpty())
                <div>
                    <p class="border-b border-line pb-2 font-mono text-[0.65rem] font-semibold uppercase tracking-wider text-accent">Quick Links</p>
                    <ul class="mt-3 flex flex-col gap-2">
                        @foreach ($navigationLinks as $link)
                            @continue (! $link->resolvedUrl())
                            <li>
                                <a
                                    href="{{ $link->resolvedUrl() }}"
                                    @if ($link->is_external) target="_blank" rel="noopener noreferrer" @endif
                                    class="text-sm font-medium text-ink-muted transition-colors hover:text-ink"
                                >
                                    {{ $link->label }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>

        <div class="mt-8 flex flex-col gap-3 border-t border-line pt-5 text-xs text-ink-faint sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} {{ $footerLegal['name'] }}. All rights reserved.</p>
            <p class="max-w-2xl">{{ $footerLegal['disclaimer'] }}</p>
        </div>
    </div>
</footer>
