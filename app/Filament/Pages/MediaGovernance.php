<?php

namespace App\Filament\Pages;

use App\Support\Media\MediaCatalog;
use App\Support\Media\MediaCatalogEntry;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * A read-only, computed view over the existing `*_path` columns (see
 * MediaRegistry) — not a new media table. Gives admins visibility into what's
 * uploaded, where it's used, and whether it's safe to remove, without
 * replacing the per-model storage architecture those columns already use.
 */
class MediaGovernance extends Page
{
    use WithPagination;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Media Library';

    protected static ?string $title = 'Media Library';

    protected string $view = 'filament.pages.media-governance';

    #[Url]
    public ?string $search = null;

    #[Url]
    public ?string $model = null;

    #[Url]
    public ?string $fileType = null;

    #[Url]
    public ?string $status = null;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:MediaGovernance');
    }

    /**
     * @return array<string, string>
     */
    public function modelOptions(): array
    {
        return ['' => 'All sources', ...$this->catalog()
            ->flatMap(fn (MediaCatalogEntry $entry) => $entry->references->pluck('modelLabel'))
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $label) => [$label => $label])
            ->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function fileTypeOptions(): array
    {
        return ['' => 'All file types', ...$this->catalog()
            ->map(fn (MediaCatalogEntry $entry) => $entry->extension())
            ->filter()
            ->unique()
            ->sort()
            ->mapWithKeys(fn (string $ext) => [$ext => strtoupper($ext)])
            ->all(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function statusOptions(): array
    {
        return ['' => 'All statuses', 'used' => 'Used', 'unused' => 'Unused', 'missing' => 'Missing'];
    }

    #[Computed]
    public function catalog()
    {
        return app(MediaCatalog::class)->all();
    }

    public function filteredEntries(): LengthAwarePaginator
    {
        $filtered = $this->catalog()
            ->when(filled($this->search), fn ($entries) => $entries->filter(
                fn (MediaCatalogEntry $entry) => str_contains(strtolower($entry->path), strtolower($this->search)),
            ))
            ->when(filled($this->model), fn ($entries) => $entries->filter(
                fn (MediaCatalogEntry $entry) => $entry->references->contains(fn ($ref) => $ref->modelLabel === $this->model),
            ))
            ->when(filled($this->fileType), fn ($entries) => $entries->filter(
                fn (MediaCatalogEntry $entry) => $entry->extension() === strtolower($this->fileType),
            ))
            ->when(filled($this->status), fn ($entries) => $entries->filter(
                fn (MediaCatalogEntry $entry) => $entry->status() === $this->status,
            ))
            ->values();

        $page = $this->getPage();
        $perPage = 25;

        return new LengthAwarePaginator(
            $filtered->forPage($page, $perPage),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page'],
        );
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'model', 'fileType', 'status'], true)) {
            $this->resetPage();
        }
    }

    public function deleteUnused(string $disk, string $path): void
    {
        if (! auth()->user()?->can('View:MediaGovernance')) {
            abort(403);
        }

        unset($this->catalog);

        $fresh = app(MediaCatalog::class)->all()->first(
            fn (MediaCatalogEntry $entry) => $entry->disk === $disk && $entry->path === $path,
        );

        if (! $fresh || $fresh->status() !== 'unused') {
            Notification::make()
                ->title('This file is still referenced — it was not deleted')
                ->danger()
                ->send();

            unset($this->catalog);

            return;
        }

        Storage::disk($disk)->delete($path);
        unset($this->catalog);

        Notification::make()
            ->title('Unused file deleted')
            ->success()
            ->send();
    }
}
