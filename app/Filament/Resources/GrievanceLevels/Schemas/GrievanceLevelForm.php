<?php

namespace App\Filament\Resources\GrievanceLevels\Schemas;

use App\Enums\PublishStatus;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GrievanceLevelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('level')
                    ->required()
                    ->helperText('e.g. "Initial Complaint", "Level 1", "Level 2".'),
                TextInput::make('turnaround_time')
                    ->required()
                    ->helperText('e.g. "7 Working Days".'),
                TextInput::make('contact_name')
                    ->label('Name')
                    ->required(),
                TextInput::make('designation')
                    ->required(),
                TextInput::make('address')
                    ->columnSpanFull(),
                TextInput::make('phone')
                    ->tel(),
                TextInput::make('email')
                    ->email(),
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
