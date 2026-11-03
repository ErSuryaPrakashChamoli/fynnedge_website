<?php

namespace App\Models\Concerns;

use App\Support\Media\ResponsiveImage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Keeps the WebP copies of a model's upload (see ResponsiveImage) in step with
 * it: made for a new upload, and removed with the image they were cut from
 * unless another row of the same table still shows that file (a replicated
 * record shares its path). A soft-deleted row keeps its copies, because
 * restoring it shows the image again; they go with a force delete.
 *
 * The upload is `image_path` at ResponsiveImage::WIDTHS unless the model
 * overrides responsiveImageColumn() / responsiveImageWidths() (Lender: its
 * logo, at the logo widths).
 *
 * `media:responsive-images` backfills the copies of images uploaded before a
 * model used this, so add any new model here to that command too.
 */
trait HasResponsiveImage
{
    public static function bootHasResponsiveImage(): void
    {
        static::saved(function (Model $model): void {
            $column = $model->responsiveImageColumn();

            if (! $model->wasRecentlyCreated && ! $model->wasChanged($column)) {
                return;
            }

            $model->forgetResponsiveImage($model->getPrevious()[$column] ?? null);

            if ($model->{$column}) {
                ResponsiveImage::generate($model->{$column}, widths: $model->responsiveImageWidths());
            }
        });

        static::deleted(function (Model $model): void {
            if (in_array(SoftDeletes::class, class_uses_recursive($model), true) && ! $model->isForceDeleting()) {
                return;
            }

            $model->forgetResponsiveImage($model->{$model->responsiveImageColumn()});
        });
    }

    /**
     * The column holding the upload the copies are cut from.
     */
    public function responsiveImageColumn(): string
    {
        return 'image_path';
    }

    /**
     * @return list<int>
     */
    public function responsiveImageWidths(): array
    {
        return ResponsiveImage::WIDTHS;
    }

    /**
     * The WebP srcset for this record's image, or null until its copies exist
     * (see ResponsiveImage), in which case only the upload itself is served.
     */
    public function imageSrcset(): ?string
    {
        return ResponsiveImage::srcset($this->{$this->responsiveImageColumn()}, widths: $this->responsiveImageWidths());
    }

    /**
     * @return array{0: int, 1: int}|null
     */
    public function imageDimensions(): ?array
    {
        return ResponsiveImage::dimensions($this->{$this->responsiveImageColumn()});
    }

    private function forgetResponsiveImage(?string $path): void
    {
        if ($path && ! static::query()->withoutGlobalScopes()->where($this->responsiveImageColumn(), $path)->exists()) {
            ResponsiveImage::delete($path, widths: $this->responsiveImageWidths());
        }
    }
}
