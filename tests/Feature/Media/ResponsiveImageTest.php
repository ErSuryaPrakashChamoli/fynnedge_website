<?php

use App\Support\Media\ResponsiveImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Storage;

/**
 * A JPEG carrying only an EXIF Orientation tag, the way a phone writes a
 * photo it took sideways. GD cannot write EXIF, so the APP1 segment is
 * spliced in after the start-of-image marker by hand.
 */
function jpegWithOrientation(int $width, int $height, int $orientation): string
{
    ob_start();
    imagejpeg(imagecreatetruecolor($width, $height));
    $jpeg = (string) ob_get_clean();

    $tiff = 'MM'.pack('n', 42).pack('N', 8).pack('n', 1).pack('nnNnn', 0x0112, 3, 1, $orientation, 0).pack('N', 0);
    $app1 = "Exif\0\0".$tiff;

    return substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($app1) + 2).$app1.substr($jpeg, 2);
}

function storedImageSize(string $path): array
{
    $size = getimagesizefromstring(Storage::disk('public')->get($path));

    return [$size[0], $size[1], $size['mime']];
}

it('writes a WebP copy at each srcset width, capping the first width past the upload at its own size', function () {
    Storage::fake('public');
    Storage::disk('public')->putFileAs('banners', UploadedFile::fake()->image('promo.png', 1000, 500), 'promo.png');

    $written = ResponsiveImage::generate('banners/promo.png');

    expect($written)->toBe(3);
    expect(storedImageSize('responsive/banners/promo-480.webp'))->toBe([480, 240, 'image/webp']);
    expect(storedImageSize('responsive/banners/promo-768.webp'))->toBe([768, 384, 'image/webp']);
    expect(storedImageSize('responsive/banners/promo-1080.webp'))->toBe([1000, 500, 'image/webp']);
    Storage::disk('public')->assertMissing('responsive/banners/promo-1440.webp');
});

it('keeps the transparent parts of a PNG upload transparent', function () {
    Storage::fake('public');
    $image = imagecreatetruecolor(600, 300);
    imagealphablending($image, false);
    imagesavealpha($image, true);
    imagefill($image, 0, 0, imagecolorallocatealpha($image, 0, 0, 0, 127));
    ob_start();
    imagepng($image);
    Storage::disk('public')->put('banners/cutout.png', (string) ob_get_clean());

    ResponsiveImage::generate('banners/cutout.png');

    $copy = imagecreatefromstring(Storage::disk('public')->get('responsive/banners/cutout-480.webp'));
    expect(imagecolorsforindex($copy, imagecolorat($copy, 10, 10))['alpha'])->toBe(127);
});

it('turns a sideways phone photo upright, as browsers show the original', function () {
    Storage::fake('public');
    Storage::disk('public')->put('banners/phone.jpg', jpegWithOrientation(1600, 900, 6));

    ResponsiveImage::generate('banners/phone.jpg');

    expect(storedImageSize('responsive/banners/phone-480.webp'))->toBe([480, 853, 'image/webp']);
    expect(storedImageSize('responsive/banners/phone-1080.webp'))->toBe([900, 1600, 'image/webp']);
});

it('writes nothing, and reports the failure, for an upload that is not a readable image', function () {
    Exceptions::fake();
    Storage::fake('public');
    Storage::disk('public')->put('banners/broken.png', 'not an image');

    $written = ResponsiveImage::generate('banners/broken.png');

    expect($written)->toBe(0);
    expect(Storage::disk('public')->allFiles('responsive'))->toBeEmpty();
    Exceptions::assertReported(ErrorException::class);
});

it('lists only the copies that exist in the srcset, narrowest first', function () {
    Storage::fake('public');
    Storage::disk('public')->putFileAs('banners', UploadedFile::fake()->image('promo.jpg', 900, 600), 'promo.jpg');
    ResponsiveImage::generate('banners/promo.jpg');

    $srcset = ResponsiveImage::srcset('banners/promo.jpg');

    expect($srcset)->toBe('/storage/responsive/banners/promo-480.webp 480w, /storage/responsive/banners/promo-768.webp 768w, /storage/responsive/banners/promo-1080.webp 1080w');
});

it('returns no srcset for an upload whose copies have not been made', function () {
    Storage::fake('public');
    Storage::disk('public')->putFileAs('banners', UploadedFile::fake()->image('promo.jpg', 900, 600), 'promo.jpg');

    expect(ResponsiveImage::srcset('banners/promo.jpg'))->toBeNull();
});
