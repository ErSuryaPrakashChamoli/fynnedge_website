<?php

namespace App\Filament\Resources\CompanyPhotos\Schemas;

use App\Enums\PublishStatus;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class CompanyPhotoForm
{
    /**
     * Used for editing a single, already-uploaded photo.
     */
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('photo_path')
                    ->label('Photo')
                    ->image()
                    ->disk('public')
                    ->directory('company-photos')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(5120)
                    ->helperText('JPG or PNG, up to 5MB.')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('photo_alt')
                    ->label('Image alt text')
                    ->columnSpanFull(),
                TextInput::make('caption')
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Select::make('status')
                    ->options(PublishStatus::class)
                    ->default(PublishStatus::Draft)
                    ->required(),
            ]);
    }

    /**
     * Used on creation only — lets an admin select several images at once (up
     * to 8) and have each become its own CompanyPhoto record. See
     * CreateCompanyPhoto::handleRecordCreation(), which turns the resulting
     * photo_paths array into one record per path.
     */
    public static function configureBulkCreate(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('photo_paths')
                    ->label('Photos')
                    ->image()
                    ->multiple()
                    ->maxFiles(8)
                    ->reorderable()
                    ->disk('public')
                    ->directory('company-photos')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(5120)
                    ->required()
                    ->helperText('Select up to 8 photos to add at once. JPG or PNG, up to 5MB each.')
                    ->columnSpanFull(),
                TextInput::make('caption')
                    ->helperText('Applied to every photo in this batch — edit a photo afterwards to give it its own caption.')
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->label('Starting order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Each photo after the first gets the next order number.'),
                Select::make('status')
                    ->options(PublishStatus::class)
                    ->default(PublishStatus::Draft)
                    ->required(),
            ]);
    }
}
