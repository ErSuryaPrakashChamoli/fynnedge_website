<?php

namespace App\Console\Commands;

use App\Models\Article;
use App\Models\Banner;
use App\Models\LoanProduct;
use App\Models\Setting;
use App\Support\Media\ResponsiveImage;
use Illuminate\Console\Command;

/**
 * Makes the WebP copies the site serves in a srcset, for every banner, loan
 * product and article image, the About page's founder photo and the site
 * logo. An image saved from the admin gets its copies on save; this catches
 * up the images uploaded before that existed, and any whose copies were lost.
 * Copies that already exist are left alone unless --force is passed. Safe to
 * run at any time.
 */
class GenerateResponsiveImages extends Command
{
    protected $signature = 'media:responsive-images
                            {--force : Rewrite copies that already exist}';

    protected $description = 'Generate the responsive WebP copies of every banner, loan product and article image, the founder photo and the logo';

    public function handle(): int
    {
        if (! ResponsiveImage::isSupported()) {
            $this->warn('This PHP build\'s GD extension cannot write WebP, so no copies can be made. The site keeps serving the original uploads.');

            return self::SUCCESS;
        }

        $images = 0;
        $written = 0;
        $force = (bool) $this->option('force');

        $generate = function (?string $path, array $widths) use (&$images, &$written, $force): void {
            if (blank($path)) {
                return;
            }

            $images++;
            $written += ResponsiveImage::generate($path, force: $force, widths: $widths);
        };

        collect([Banner::class, LoanProduct::class, Article::class])
            ->flatMap(fn (string $model) => $model::query()->whereNotNull('image_path')->distinct()->pluck('image_path'))
            ->push(Setting::get('founder_photo'))
            ->unique()
            ->each(fn (?string $path) => $generate($path, ResponsiveImage::WIDTHS));

        $generate(Setting::get('site_logo'), ResponsiveImage::LOGO_WIDTHS);

        $this->info("Checked {$images} image(s), wrote {$written} WebP cop".($written === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }
}
