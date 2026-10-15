<?php

namespace App\Filament\Resources\JobOpenings\Schemas;

use App\Enums\PublishStatus;
use App\Filament\Schemas\HindiTranslationSection;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class JobOpeningForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required()
                    ->columnSpanFull(),
                TextInput::make('sort_order')
                    ->label('Order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower numbers show first on the Careers page.'),
                Select::make('status')
                    ->options(PublishStatus::class)
                    ->default(PublishStatus::Draft)
                    ->required(),
                HindiTranslationSection::make([
                    TextInput::make('title_hi')
                        ->label('Title (Hindi)')
                        ->columnSpanFull(),
                ]),
            ]);
    }
}
