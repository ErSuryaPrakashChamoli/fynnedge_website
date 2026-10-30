<?php

namespace App\Console\Commands;

use App\Models\Banner;
use App\Models\Setting;
use App\Support\Media\ResponsiveImage;
use Illuminate\Console\Command;

/**
 * Makes the WebP copies the site serves in a srcset, for every banner image
 * and the About page's founder photo. A banner or founder photo saved from
 * the admin gets its copies on save; this catches up the images uploaded
 * before that existed, and any whose copies were lost. Copies that already
 * exist are left alone unless --force is passed. Safe to run at any time.
 */
class GenerateResponsiveImages extends Command
{
    protected $signature = 'media:responsive-images
                            {--force : Rewrite copies that already exist}';

    protected $description = 'Generate the responsive WebP copies of every banner image and the founder photo';

    public function handle(): int
    {
        if (! ResponsiveImage::isSupported()) {
            $this->warn('This PHP build\'s GD extension cannot write WebP, so no copies can be made. The site keeps serving the original uploads.');

            return self::SUCCESS;
        }

        $images = 0;
        $written = 0;

        Banner::query()
            ->whereNotNull('image_path')
            ->distinct()
            ->pluck('image_path')
            ->push(Setting::get('founder_photo'))
            ->filter()
            ->unique()
            ->each(function (string $path) use (&$images, &$written): void {
                $images++;
                $written += ResponsiveImage::generate($path, force: (bool) $this->option('force'));
            });

        $this->info("Checked {$images} image(s), wrote {$written} WebP cop".($written === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }
}
