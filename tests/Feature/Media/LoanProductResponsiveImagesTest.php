<?php

use App\Enums\LoanCategory;
use App\Models\LoanProduct;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

function storeLoanProductImage(string $name, int $width = 1600, int $height = 900): string
{
    Storage::disk('public')->putFileAs('loan-products', UploadedFile::fake()->image($name, $width, $height), $name);

    return "loan-products/{$name}";
}

it('makes the WebP copies of a loan product image when it is saved, and replaces them with the image', function () {
    Storage::fake('public');
    $product = LoanProduct::factory()->create(['image_path' => storeLoanProductImage('old.jpg')]);

    $product->update(['image_path' => storeLoanProductImage('new.jpg')]);

    expect(Storage::disk('public')->allFiles('responsive/loan-products'))->toEqualCanonicalizing([
        'responsive/loan-products/new-480.webp',
        'responsive/loan-products/new-768.webp',
        'responsive/loan-products/new-1080.webp',
        'responsive/loan-products/new-1440.webp',
    ]);
});

it('keeps a trashed product\'s copies, since restoring it shows the image again, and removes them on a force delete', function () {
    Storage::fake('public');
    $product = LoanProduct::factory()->create(['image_path' => storeLoanProductImage('promo.jpg')]);

    $product->delete();
    $afterTrash = Storage::disk('public')->allFiles('responsive');
    $product->forceDelete();

    expect($afterTrash)->toContain('responsive/loan-products/promo-768.webp')
        ->and(Storage::disk('public')->allFiles('responsive'))->toBeEmpty();
});

it('serves the loan page image lazily, as WebP, in a box sized from the upload', function () {
    Storage::fake('public');
    LoanProduct::factory()->published()->create([
        'slug' => 'personal-loan',
        'category' => LoanCategory::PersonalLoan,
        'image_path' => storeLoanProductImage('personal.jpg', 1600, 900),
    ]);

    $html = $this->get('/loans/personal-loan')->assertOk()->getContent();

    expect($html)->toContain('<source type="image/webp" srcset="/storage/responsive/loan-products/personal-480.webp 480w, /storage/responsive/loan-products/personal-768.webp 768w, /storage/responsive/loan-products/personal-1080.webp 1080w, /storage/responsive/loan-products/personal-1440.webp 1440w" sizes="(min-width: 1280px) 1216px, (min-width: 1024px) calc(100vw - 64px), calc(100vw - 48px)">')
        ->toMatch('#src="/storage/loan-products/personal\.jpg"\s+alt="[^"]*"\s+width="1600" height="900"\s+loading="lazy"#');
});

it('serves only the upload, without a size, for a loan product image whose file is missing', function () {
    LoanProduct::factory()->published()->create([
        'slug' => 'personal-loan',
        'category' => LoanCategory::PersonalLoan,
        'image_path' => 'loan-products/missing.jpg',
    ]);

    $html = $this->get('/loans/personal-loan')->assertOk()->getContent();

    expect($html)->toMatch('#src="/storage/loan-products/missing\.jpg"\s+alt="[^"]*"\s+loading="lazy"#')
        ->not->toContain('responsive/loan-products/missing');
});

it('backfills the copies of loan product images uploaded before copies were made', function () {
    Storage::fake('public');
    LoanProduct::factory()->create(['image_path' => storeLoanProductImage('promo.jpg', 900, 600)]);
    Storage::disk('public')->deleteDirectory('responsive');

    Artisan::call('media:responsive-images');

    expect(Artisan::output())->toContain('Checked 1 image(s), wrote 3 WebP copies.');
    Storage::disk('public')->assertExists('responsive/loan-products/promo-1080.webp');
});
