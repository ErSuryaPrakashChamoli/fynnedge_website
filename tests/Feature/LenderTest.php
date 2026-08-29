<?php

use App\Models\Lender;
use Illuminate\Support\Facades\Storage;

it('has no logo url when no logo has been uploaded', function () {
    $lender = Lender::factory()->create(['logo_path' => null]);

    expect($lender->logoUrl())->toBeNull();
});

it('resolves a logo url from the public disk when a logo is set', function () {
    Storage::fake('public');
    Storage::disk('public')->put('lenders/demo-logo.png', 'fake-image-content');

    $lender = Lender::factory()->create(['logo_path' => 'lenders/demo-logo.png']);

    expect($lender->logoUrl())->toBe(Storage::disk('public')->url('lenders/demo-logo.png'));
    expect($lender->logoUrl())->toContain('/storage/lenders/demo-logo.png');
});
