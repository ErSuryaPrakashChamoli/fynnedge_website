{{-- One plain-English line under every admin screen's title (App\Support\Admin\AdminGuide). --}}
@if ($guide)
    <p class="fe-screen-summary mt-1 max-w-4xl text-sm text-gray-500 dark:text-gray-400">
        {{ $guide['summary'] }}
    </p>
@endif
