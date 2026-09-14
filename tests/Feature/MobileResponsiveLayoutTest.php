<?php

use App\Enums\LoanCategory;
use App\Models\LenderProduct;
use App\Models\LoanProduct;
use Filament\Facades\Filament;

/*
 * Guards the three fixes for page-level horizontal scrolling on mobile. Each
 * assertion below stands in for something that was measured overflowing and is
 * easy to undo by accident, because in every case the class looks decorative.
 */

it('keeps the lender comparison table scroll container positioned', function () {
    $product = LoanProduct::factory()->published()->create([
        'slug' => 'responsive-compare-test',
        'category' => LoanCategory::PersonalLoan,
    ]);
    LenderProduct::factory()->for($product, 'loanProduct')->create();

    $html = $this->get("/loans/{$product->slug}")->assertOk()->getContent();

    /*
     * The table is min-w-[720px] inside an overflow-x-auto wrapper, and the
     * "Apply" header cell holds a position:absolute .sr-only span. Without
     * `relative` on the wrapper that span's containing block is the page, so it
     * escapes the scroll container and stretches the document to the table's
     * full width (measured: +396px at a 375px viewport).
     */
    expect($html)->toContain('relative mt-4 overflow-x-auto');
});

it('lets the journey phone input shrink beside its Send OTP button', function () {
    /*
     * A flex item defaults to min-width:auto, so `flex-1` alone could not shrink
     * the input below an <input>'s intrinsic width and pushed the button off
     * screen (measured: +31px at 320px).
     */
    expect(file_get_contents(resource_path('views/components/journey/field.blade.php')))
        ->toContain('min-w-0 flex-1');
});

it('labels each figure in the calculator year rows for mobile', function (string $component) {
    /*
     * The breakdown's header row is hidden below sm, so without these per-figure
     * labels a phone showed three unexplained rupee amounts crammed two-per-line.
     * col-start-2 stacks them under the period; sm:col-start-auto hands them back
     * to the desktop grid untouched.
     */
    $blade = file_get_contents(resource_path("views/components/{$component}.blade.php"));

    expect($blade)
        ->toContain('grid-cols-[1.25rem_minmax(0,1fr)]')
        ->toContain('col-start-2')
        ->toContain('sm:col-start-auto')
        ->toContain('>Principal paid</span>')
        ->toContain('>Interest paid</span>')
        ->toContain('>Total paid</span>');
})->with(['⚡emi-calculator', '⚡flexi-hybrid-calculator']);

it('keeps amortisation cells on one line so the table scrolls instead of squeezing', function (string $component) {
    // Squeezed into a phone, every one of these money columns wrapped.
    expect(file_get_contents(resource_path("views/components/{$component}.blade.php")))
        ->toContain('w-full whitespace-nowrap text-xs');
})->with(['⚡emi-calculator', '⚡flexi-hybrid-calculator']);

it('gives the grievance matrix room to breathe without scrolling on desktop', function () {
    /*
     * 42rem is the widest this can be while still fitting the ~702px desktop
     * container; 44rem produced a pointless 2px scrollbar there. Below sm it
     * scrolls inside its own container instead of wrapping "Turn-around Time"
     * onto three lines.
     */
    expect(file_get_contents(resource_path('views/pages/show.blade.php')))
        ->toContain('min-w-[42rem]');
});

it('registers a custom Filament theme so Tailwind classes in admin views compile', function () {
    /*
     * Filament's default stylesheet contains only its own component CSS. Without
     * a compiled theme every Tailwind utility written in resources/views/filament
     * is inert — which silently turned the five `overflow-x-auto` table wrappers
     * on the custom admin pages into `overflow-x: visible`, clipping those tables
     * off screen with no way to scroll to them.
     */
    expect(Filament::getPanel('admin')->getViteTheme())
        ->not->toBeNull();

    expect(resource_path('css/filament/admin/theme.css'))->toBeReadableFile();
});
