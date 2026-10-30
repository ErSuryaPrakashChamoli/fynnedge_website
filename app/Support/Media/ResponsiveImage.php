<?php

namespace App\Support\Media;

use GdImage;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Smaller WebP copies of an uploaded image, for an <img srcset>.
 *
 * Banners are uploaded at 1440px or wider, up to 5MB and often as PNG, but a
 * phone shows that box about 360px wide. Serving the upload made the
 * homepage's first banner (the page's Largest Contentful Paint) a 600KB–2MB
 * download where a 768px WebP is about 40KB. The About page's founder photo
 * (a 280px box that showed a 1.8MB PNG) is served from the same copies.
 *
 * The copies sit under responsive/ on the upload's own disk, named after the
 * upload, so they need no column. Media Governance scans only the registered
 * upload directories, so it never lists them as unused files. Nothing is
 * generated when GD cannot write WebP, and a page with no copies simply keeps
 * serving the upload.
 */
final class ResponsiveImage
{
    /**
     * The srcset widths, in pixels. A width wider than the upload is not
     * generated, except the first one at or above it, which holds a copy at
     * the upload's own width. A small upload therefore still has a
     * full-resolution candidate, listed under a descriptor slightly larger
     * than its real width.
     *
     * @var list<int>
     */
    public const WIDTHS = [480, 768, 1080, 1440];

    public const QUALITY = 80;

    public const DIRECTORY = 'responsive';

    public static function isSupported(): bool
    {
        return function_exists('imagewebp') && (imagetypes() & IMG_WEBP) !== 0;
    }

    /**
     * Writes every missing copy of the upload at $path, or every copy when
     * $force is set, and returns how many were written. An unreadable or
     * missing upload writes nothing and never throws, because this runs
     * inside the admin's save.
     */
    public static function generate(string $path, string $disk = 'public', bool $force = false): int
    {
        $storage = Storage::disk($disk);

        if (! self::isSupported() || ! $storage->exists($path)) {
            return 0;
        }

        try {
            $contents = $storage->get($path);
            $source = imagecreatefromstring($contents);
        } catch (Throwable $exception) {
            report($exception);

            return 0;
        }

        if ($source === false) {
            return 0;
        }

        $source = self::upright($source, $contents);
        imagepalettetotruecolor($source);

        $sourceWidth = imagesx($source);
        $written = 0;

        foreach (self::widthsFor($sourceWidth) as $width) {
            $variantPath = self::variantPath($path, $width);

            if (! $force && $storage->exists($variantPath)) {
                continue;
            }

            $resized = imagescale($source, min($width, $sourceWidth), -1, IMG_BICUBIC);
            imagealphablending($resized, false);
            imagesavealpha($resized, true);

            ob_start();
            imagewebp($resized, null, self::QUALITY);
            $storage->put($variantPath, (string) ob_get_clean());

            $written++;
        }

        return $written;
    }

    /**
     * The srcset for the copies of $path that exist, or null when there are
     * none, so the caller falls back to the upload alone.
     */
    public static function srcset(?string $path, string $disk = 'public'): ?string
    {
        if (blank($path)) {
            return null;
        }

        $storage = Storage::disk($disk);

        $candidates = collect(self::WIDTHS)
            ->filter(fn (int $width): bool => $storage->exists(self::variantPath($path, $width)))
            ->map(fn (int $width): string => $storage->url(self::variantPath($path, $width))." {$width}w");

        return $candidates->isEmpty() ? null : $candidates->implode(', ');
    }

    public static function delete(?string $path, string $disk = 'public'): void
    {
        if (blank($path)) {
            return;
        }

        Storage::disk($disk)->delete(array_map(fn (int $width): string => self::variantPath($path, $width), self::WIDTHS));
    }

    public static function variantPath(string $path, int $width): string
    {
        $directory = pathinfo($path, PATHINFO_DIRNAME);
        $directory = $directory === '.' ? '' : $directory.'/';

        return self::DIRECTORY.'/'.$directory.pathinfo($path, PATHINFO_FILENAME)."-{$width}.webp";
    }

    /**
     * @return list<int>
     */
    private static function widthsFor(int $sourceWidth): array
    {
        $widths = [];

        foreach (self::WIDTHS as $width) {
            $widths[] = $width;

            if ($width >= $sourceWidth) {
                break;
            }
        }

        return $widths;
    }

    /**
     * Browsers turn a JPEG upright from its EXIF Orientation tag, but GD
     * ignores the tag, so a phone photo's copies would otherwise come out on
     * their side next to an upright original.
     */
    private static function upright(GdImage $image, string $contents): GdImage
    {
        if (! function_exists('exif_read_data') || (getimagesizefromstring($contents)[2] ?? null) !== IMAGETYPE_JPEG) {
            return $image;
        }

        try {
            $orientation = (int) (exif_read_data('data://image/jpeg;base64,'.base64_encode($contents))['Orientation'] ?? 1);
        } catch (Throwable) {
            return $image;
        }

        return match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => $image,
        };
    }
}
