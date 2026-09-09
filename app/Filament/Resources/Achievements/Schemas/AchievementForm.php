<?php

namespace App\Filament\Resources\Achievements\Schemas;

use App\Enums\PublishStatus;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AchievementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('label')
                    ->label('Metric name')
                    ->required()
                    ->maxLength(60)
                    ->placeholder('Cities served')
                    ->helperText('The caption shown under the number.')
                    ->columnSpanFull(),
                TextInput::make('value')
                    ->label('Value')
                    ->required()
                    ->maxLength(20)
                    ->placeholder('550')
                    ->helperText('Publish only figures the business has verified — this appears as a public claim.'),
                TextInput::make('prefix')
                    ->label('Prefix (optional)')
                    ->maxLength(10)
                    ->placeholder('₹'),
                TextInput::make('suffix')
                    ->label('Suffix (optional)')
                    ->maxLength(10)
                    ->placeholder('+'),
                TextInput::make('sort_order')
                    ->label('Display order')
                    ->numeric()
                    ->default(0),
                FileUpload::make('icon_path')
                    ->label('Icon (optional)')
                    ->image()
                    ->disk('public')
                    ->directory('achievements')
                    ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp', 'image/svg+xml'])
                    ->maxSize(1024)
                    ->helperText('Optional. A small square icon, up to 1MB.'),
                TextInput::make('icon_alt')
                    ->label('Icon alt text')
                    ->maxLength(120)
                    ->helperText('Describes the icon for screen readers. Leave blank if the icon is purely decorative.'),
                Select::make('status')
                    ->options(PublishStatus::class)
                    ->default(PublishStatus::Draft)
                    ->required(),
            ]);
    }
}
