<?php

namespace App\Models\Concerns;

use App\Support\Media\ResponsiveImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Keeps the WebP copies of a model's `image_path` upload (see ResponsiveImage)
 * in step with it: made for a new upload, and removed with the image they were
 * cut from unless another row of the same table still shows that file (a
 * replicated record shares its image_path). A soft-deleted row keeps its
 * copies, because restoring it shows the image again; they go with a force
 * delete.
 *
 * `media:responsive-images` backfills the copies of images uploaded before a
 * model used this, so add any new model here to that command too.
 */
trait HasResponsiveImage
{
    public static function bootHasResponsiveImage(): void
    {
        static::saved(function (Model $model): void {
            if (! $model->wasRecentlyCreated && ! $model->wasChanged('image_path')) {
                return;
            }

            $model->forgetResponsiveImage($model->getPrevious()['image_path'] ?? null);

            if ($model->image_path) {
                ResponsiveImage::generate($model->image_path);
            }
        });

        static::deleted(function (Model $model): void {
            if (in_array(SoftDeletes::class, class_uses_recursive($model), true) && ! $model->isForceDeleting()) {
                return;
            }

            $model->forgetResponsiveImage($model->image_path);
        });
    }

    /**
     * The WebP srcset for this record's image, or null until its copies exist
     * (see ResponsiveImage), in which case only the upload itself is served.
     */
    public function imageSrcset(): ?string
    {
        return ResponsiveImage::srcset($this->image_path);
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    public function imageDimensions(): ?array
    {
        return ResponsiveImage::dimensions($this->image_path);
    }

    private function forgetResponsiveImage(?string $path): void
    {
        if ($path && ! static::query()->withoutGlobalScopes()->where('image_path', $path)->exists()) {
            ResponsiveImage::delete($path);
        }
    }
}
