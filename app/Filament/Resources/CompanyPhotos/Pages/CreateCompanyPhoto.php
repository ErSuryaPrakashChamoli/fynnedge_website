<?php

namespace App\Filament\Resources\CompanyPhotos\Pages;

use App\Filament\Resources\CompanyPhotos\CompanyPhotoResource;
use App\Filament\Resources\CompanyPhotos\Schemas\CompanyPhotoForm;
use App\Models\CompanyPhoto;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

class CreateCompanyPhoto extends CreateRecord
{
    protected static string $resource = CompanyPhotoResource::class;

    public function form(Schema $schema): Schema
    {
        return CompanyPhotoForm::configureBulkCreate($schema);
    }

    /**
     * The create form collects an array of up to 8 uploaded photo paths
     * (photo_paths) rather than the single photo_path column the model
     * actually has — one CompanyPhoto row is created per uploaded photo,
     * sharing the caption/status entered and incrementing sort_order from
     * the given starting value.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $paths = $data['photo_paths'] ?? [];
        $baseSortOrder = (int) ($data['sort_order'] ?? 0);

        $records = collect($paths)->values()->map(fn (string $path, int $index) => CompanyPhoto::create([
            'photo_path' => $path,
            'caption' => $data['caption'] ?? null,
            'sort_order' => $baseSortOrder + $index,
            'status' => $data['status'],
        ]));

        return $records->first() ?? new CompanyPhoto;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('index');
    }
}
