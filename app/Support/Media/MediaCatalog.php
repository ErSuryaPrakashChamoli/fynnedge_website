<?php

namespace App\Support\Media;

use App\Filament\Pages\Settings;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\Banners\BannerResource;
use App\Filament\Resources\CompanyPhotos\CompanyPhotoResource;
use App\Filament\Resources\HowItWorksSteps\HowItWorksStepResource;
use App\Filament\Resources\Lenders\LenderResource;
use App\Filament\Resources\LoanLandingPages\LoanLandingPageResource;
use App\Filament\Resources\LoanProducts\LoanProductResource;
use App\Filament\Resources\MarketingSections\MarketingSectionResource;
use App\Filament\Resources\Pages\PageResource;
use App\Filament\Resources\Testimonials\TestimonialResource;
use App\Models\Article;
use App\Models\Banner;
use App\Models\CompanyPhoto;
use App\Models\HowItWorksStep;
use App\Models\Lender;
use App\Models\LoanLandingPage;
use App\Models\LoanProduct;
use App\Models\MarketingSection;
use App\Models\Page;
use App\Models\SeoMeta;
use App\Models\Setting;
use App\Models\Testimonial;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Builds a read-only catalog of every file referenced by MediaRegistry's
 * fields, cross-referenced against what's actually on disk. This is a
 * computed view, not a stored table — the existing *_path columns remain
 * the single source of truth, per 5.1's "prefer a lightweight index/
 * reference layer over replacing the existing media storage architecture".
 */
class MediaCatalog
{
    /**
     * @var array<class-string, class-string>
     */
    private const RESOURCE_MAP = [
        Banner::class => BannerResource::class,
        CompanyPhoto::class => CompanyPhotoResource::class,
        Testimonial::class => TestimonialResource::class,
        LoanProduct::class => LoanProductResource::class,
        MarketingSection::class => MarketingSectionResource::class,
        HowItWorksStep::class => HowItWorksStepResource::class,
        Lender::class => LenderResource::class,
        Article::class => ArticleResource::class,
        Page::class => PageResource::class,
        LoanLandingPage::class => LoanLandingPageResource::class,
    ];

    /**
     * @return Collection<int, MediaCatalogEntry>
     */
    public function all(): Collection
    {
        /** @var Collection<string, Collection<int, MediaReference>> $referencesByKey */
        $referencesByKey = collect();

        /** @var Collection<string, array{disk: string, directory: string}> $directoriesByDisk */
        $directories = collect();

        foreach (MediaRegistry::all() as $definition) {
            $directories->push(['disk' => $definition->disk, 'directory' => $definition->directory]);

            $references = $definition->source === 'setting'
                ? $this->referencesFromSetting($definition)
                : $this->referencesFromModel($definition);

            foreach ($references as ['key' => $key, 'reference' => $reference]) {
                $referencesByKey->put($key, $referencesByKey->get($key, collect())->push($reference));
            }
        }

        $knownPaths = $referencesByKey->keys();

        $diskPaths = $directories
            ->unique(fn (array $entry) => $entry['disk'].'|'.$entry['directory'])
            ->flatMap(function (array $entry) {
                $disk = Storage::disk($entry['disk']);

                if (! $disk->exists($entry['directory'])) {
                    return collect();
                }

                return collect($disk->allFiles($entry['directory']))
                    ->map(fn (string $path) => $entry['disk'].'::'.$path);
            });

        $allKeys = $knownPaths->merge($diskPaths)->unique();

        return $allKeys
            ->map(function (string $key) use ($referencesByKey) {
                [$disk, $path] = explode('::', $key, 2);
                $exists = Storage::disk($disk)->exists($path);

                return new MediaCatalogEntry(
                    disk: $disk,
                    path: $path,
                    existsOnDisk: $exists,
                    references: $referencesByKey->get($key, collect()),
                    sizeBytes: $exists ? Storage::disk($disk)->size($path) : null,
                    lastModifiedAt: $exists ? Carbon::createFromTimestamp(Storage::disk($disk)->lastModified($path)) : null,
                );
            })
            ->sortBy(fn (MediaCatalogEntry $entry) => $entry->path)
            ->values();
    }

    /**
     * @return Collection<int, array{key: string, reference: MediaReference}>
     */
    private function referencesFromModel(MediaFieldDefinition $definition): Collection
    {
        $query = ($definition->modelClass)::query()->whereNotNull($definition->pathField);

        if ($definition->withTrashed) {
            $query->withTrashed();
        }

        if ($definition->modelClass === SeoMeta::class) {
            // Avoids one lazy-loaded query per SeoMeta row for its polymorphic
            // owner — recordLabel()/recordEditUrl() both read $record->seoable.
            $query->with('seoable');
        }

        return $query->get()
            ->map(function (Model $record) use ($definition) {
                $path = $record->getAttribute($definition->pathField);

                if (blank($path)) {
                    return null;
                }

                $reference = new MediaReference(
                    modelLabel: $definition->label,
                    recordLabel: $this->recordLabel($record, $definition),
                    recordEditUrl: $this->recordEditUrl($record),
                    fieldLabel: $definition->pathField,
                    alt: $definition->altField ? $record->getAttribute($definition->altField) : null,
                );

                return ['key' => $definition->disk.'::'.$path, 'reference' => $reference];
            })
            ->filter()
            ->values();
    }

    /**
     * @return Collection<int, array{key: string, reference: MediaReference}>
     */
    private function referencesFromSetting(MediaFieldDefinition $definition): Collection
    {
        $path = Setting::get($definition->settingKey);

        if (blank($path)) {
            return collect();
        }

        $reference = new MediaReference(
            modelLabel: 'Site Settings',
            recordLabel: $definition->label,
            recordEditUrl: Settings::getUrl(),
            fieldLabel: $definition->settingKey,
            alt: null,
        );

        return collect([['key' => $definition->disk.'::'.$path, 'reference' => $reference]]);
    }

    private function recordLabel(Model $record, MediaFieldDefinition $definition): string
    {
        if ($definition->modelClass === SeoMeta::class) {
            $owner = $record->getAttribute('seoable');

            if (! $owner) {
                $owner = $record->seoable()->withTrashed()->first();
            }

            if ($owner) {
                $ownerLabel = $owner->name ?? $owner->title ?? ('#'.$owner->getKey());

                return class_basename($owner).': '.$ownerLabel;
            }

            return 'SeoMeta #'.$record->getKey().' (owner deleted)';
        }

        $label = $definition->recordLabelField ? $record->getAttribute($definition->recordLabelField) : null;

        return filled($label) ? $label : ($definition->label.' #'.$record->getKey());
    }

    private function recordEditUrl(Model $record): ?string
    {
        if ($record instanceof SeoMeta) {
            $owner = $record->seoable ?? $record->seoable()->withTrashed()->first();

            return $owner ? $this->recordEditUrl($owner) : null;
        }

        $resourceClass = self::RESOURCE_MAP[get_class($record)] ?? null;

        if (! $resourceClass || ! method_exists($record, 'getRouteKey')) {
            return null;
        }

        try {
            return $resourceClass::getUrl('edit', ['record' => $record]);
        } catch (\Throwable) {
            return null;
        }
    }
}
