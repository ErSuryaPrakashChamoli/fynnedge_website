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

    /**
     * The widths for the site logo, which the header shows in a 36px box and
     * the footer in a 32px one: 1x to 3x screens. The logo upload was a
     * 3000px, 111KB PNG on every page (Lighthouse, 2026-10-31).
     *
     * @var list<int>
     */
    public const LOGO_WIDTHS = [64, 128];

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
     *
     * @param  list<int>  $widths
     */
    public static function generate(string $path, string $disk = 'public', bool $force = false, array $widths = self::WIDTHS): int
    {
        $storage = Storage::disk($disk);

        // An SVG (the logo field accepts one) stays vector: it gets no copies,
        // and GD could only fail to read it, reported as if the upload were broken.
        if (! self::isSupported() || strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'svg' || ! $storage->exists($path)) {
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

        foreach (self::widthsFor($sourceWidth, $widths) as $width) {
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
     *
     * @param  list<int>  $widths
     */
    public static function srcset(?string $path, string $disk = 'public', array $widths = self::WIDTHS): ?string
    {
        if (blank($path)) {
            return null;
        }

        $storage = Storage::disk($disk);

        $candidates = collect($widths)
            ->filter(fn (int $width): bool => $storage->exists(self::variantPath($path, $width)))
            ->map(fn (int $width): string => $storage->url(self::variantPath($path, $width))." {$width}w");

        return $candidates->isEmpty() ? null : $candidates->implode(', ');
    }

    /**
     * The upload's width and height in pixels, for an <img>'s width and height
     * attributes, so the browser reserves its box before the file arrives.
     * Null when the file is missing or is not an image getimagesize()
     * recognises. It reads only the file's header, so it is cheap per request.
     *
     * @return array{0: int, 1: int}|null
     */
    public static function dimensions(?string $path, string $disk = 'public'): ?array
    {
        if (blank($path)) {
            return null;
        }

        try {
            $file = Storage::disk($disk)->path($path);
            $size = is_file($file) ? getimagesize($file) : false;
        } catch (Throwable) {
            return null;
        }

        return $size && $size[0] > 0 && $size[1] > 0 ? [$size[0], $size[1]] : null;
    }

    /**
     * @param  list<int>  $widths
     */
    public static function delete(?string $path, string $disk = 'public', array $widths = self::WIDTHS): void
    {
        if (blank($path)) {
            return;
        }

        Storage::disk($disk)->delete(array_map(fn (int $width): string => self::variantPath($path, $width), $widths));
    }

    public static function variantPath(string $path, int $width): string
    {
        $directory = pathinfo($path, PATHINFO_DIRNAME);
        $directory = $directory === '.' ? '' : $directory.'/';

        return self::DIRECTORY.'/'.$directory.pathinfo($path, PATHINFO_FILENAME)."-{$width}.webp";
    }

    /**
     * @param  list<int>  $widths
     * @return list<int>
     */
    private static function widthsFor(int $sourceWidth, array $widths): array
    {
        $generated = [];

        foreach ($widths as $width) {
            $generated[] = $width;

            if ($width >= $sourceWidth) {
                break;
            }
        }

        return $generated;
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
