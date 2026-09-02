<?php

namespace App\Filament\Resources\HowItWorksSteps\Schemas;

use App\Enums\PublishStatus;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class HowItWorksStepForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(80)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->rows(2)
                    ->columnSpanFull(),
                FileUpload::make('icon_path')
                    ->label('Icon (optional)')
                    ->image()
                    ->disk('public')
                    ->directory('how-it-works')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                    ->maxSize(1024)
                    ->helperText('Optional. A small square icon or illustration, up to 1MB.'),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0),
                Select::make('status')
                    ->options(PublishStatus::class)
                    ->default(PublishStatus::Draft)
                    ->required(),
            ]);
    }
}
