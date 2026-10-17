<x-layouts.portal :title="__('My Credit Reports')" :customer="$customer">
    <x-ui.card>
        <p class="text-sm text-ink-muted">{{ __('Every report is kept — a refresh adds a new one and never replaces an earlier report. Each bureau scores you in its own way, so compare a report only with earlier reports from the same bureau.') }}</p>
        @include('portal.credit-reports.partials.table', ['reports' => $reports])
        <div class="mt-4">{{ $reports->links() }}</div>
    </x-ui.card>
</x-layouts.portal>
