<?php

namespace App\Filament\Resources\Achievements\Schemas;

use App\Enums\PublishStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * A homepage stat is text only — prefix + value + suffix, plus its caption.
 * The strip has no markup for an image, so no image field is offered here.
 */
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
                    ->default(0)
                    ->helperText('Lowest first. Publish as many stats as you want — the row spreads them evenly.'),
                Select::make('status')
                    ->options(PublishStatus::class)
                    ->default(PublishStatus::Draft)
                    ->required(),
            ]);
    }
}
