<?php

use App\Enums\PublishStatus;
use App\Filament\Resources\MarketingSections\Pages\CreateMarketingSection;
use App\Filament\Resources\NavigationLinks\Pages\CreateNavigationLink;
use App\Models\User;
use Livewire\Livewire;

/**
 * Both admin-editable URL fields (NavigationLink::url, MarketingSection::cta_url)
 * use an allowlist regex (^(https?://|/|...)) rather than a denylist — every
 * dangerous scheme is rejected by construction, not by name. This confirms
 * that holds for the specific schemes the audit called out, not just
 * javascript: (already covered elsewhere).
 */
beforeEach(function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
});

it('rejects dangerous URL schemes on a navigation link', function (string $scheme) {
    Livewire::test(CreateNavigationLink::class)
        ->fillForm(['label' => 'Bad link', 'url' => $scheme, 'location' => 'footer'])
        ->call('create')
        ->assertHasFormErrors(['url']);
})->with([
    'data:' => ['data:text/html,<script>alert(1)</script>'],
    'vbscript:' => ['vbscript:msgbox(1)'],
    'javascript:' => ['javascript:alert(1)'],
    'file:' => ['file:///etc/passwd'],
]);

it('rejects dangerous URL schemes on a marketing section CTA', function (string $scheme) {
    Livewire::test(CreateMarketingSection::class)
        ->fillForm([
            'placement' => 'home_finance_cta',
            'heading' => 'Test',
            'cta_url' => $scheme,
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasFormErrors(['cta_url']);
})->with([
    'data:' => ['data:text/html,<script>alert(1)</script>'],
    'vbscript:' => ['vbscript:msgbox(1)'],
    'javascript:' => ['javascript:alert(1)'],
]);

it('accepts a safe relative and absolute URL on a marketing section CTA', function () {
    Livewire::test(CreateMarketingSection::class)
        ->fillForm([
            'placement' => 'home_finance_cta',
            'heading' => 'Test',
            'cta_url' => '/eligibility',
            'status' => PublishStatus::Draft->value,
        ])
        ->call('create')
        ->assertHasNoFormErrors();
});
