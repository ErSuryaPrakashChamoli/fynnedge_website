<?php

use App\Models\Lender;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

function storeLenderLogo(string $name, int $width = 600, int $height = 200): string
{
    Storage::disk('public')->putFileAs('lenders', UploadedFile::fake()->image($name, $width, $height), $name);

    return "lenders/{$name}";
}

it('makes the WebP copies of a lender logo when it is saved, and replaces them with the logo', function () {
    Storage::fake('public');
    $lender = Lender::factory()->create(['logo_path' => storeLenderLogo('old.png')]);

    $lender->update(['logo_path' => storeLenderLogo('new.png')]);

    expect(Storage::disk('public')->allFiles('responsive/lenders'))->toEqualCanonicalizing([
        'responsive/lenders/new-128.webp',
        'responsive/lenders/new-320.webp',
    ]);
});

it('keeps a trashed lender\'s logo copies, since restoring it shows the logo again, and removes them on a force delete', function () {
    Storage::fake('public');
    $lender = Lender::factory()->create(['logo_path' => storeLenderLogo('bank.png')]);

    $lender->delete();
    $afterTrash = Storage::disk('public')->allFiles('responsive');
    $lender->forceDelete();

    expect($afterTrash)->toContain('responsive/lenders/bank-320.webp')
        ->and(Storage::disk('public')->allFiles('responsive'))->toBeEmpty();
});

it('serves a lender logo lazily, as WebP sized to its disc, once the copies exist', function () {
    Storage::fake('public');
    $lender = Lender::factory()->create(['logo_path' => storeLenderLogo('bank.png')]);

    $view = $this->blade('<x-ui.lender-logo :lender="$lender" size="md" />', ['lender' => $lender]);

    $view->assertSee('<source type="image/webp" srcset="/storage/responsive/lenders/bank-128.webp 128w, /storage/responsive/lenders/bank-320.webp 320w" sizes="44px">', false);
    expect((string) $view)->toMatch('#src="/storage/lenders/bank\.png"\s+alt="[^"]*"\s+loading="lazy"#');
});

it('serves only the upload for a lender logo whose copies do not exist', function () {
    $lender = Lender::factory()->create(['logo_path' => 'lenders/missing.png']);

    $view = $this->blade('<x-ui.lender-logo :lender="$lender" />', ['lender' => $lender]);

    $view->assertSee('src="/storage/lenders/missing.png"', false)
        ->assertDontSee('<source', false);
});

it('offers the WebP copies of each lender logo in the homepage marquee', function () {
    Storage::fake('public');
    Lender::factory()->create(['logo_path' => storeLenderLogo('bank.png')]);

    $this->get('/')->assertSee('<source type="image/webp" srcset="/storage/responsive/lenders/bank-128.webp 128w, /storage/responsive/lenders/bank-320.webp 320w" sizes="(min-width: 640px) 152px, 120px">', false);
});

it('backfills the copies of lender logos uploaded before copies were made', function () {
    Storage::fake('public');
    Lender::factory()->create(['logo_path' => storeLenderLogo('bank.png', 200, 80)]);
    Storage::disk('public')->deleteDirectory('responsive');

    Artisan::call('media:responsive-images');

    expect(Artisan::output())->toContain('Checked 1 image(s), wrote 2 WebP copies.');
    Storage::disk('public')->assertExists('responsive/lenders/bank-320.webp');
});
