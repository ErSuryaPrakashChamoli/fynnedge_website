<?php

namespace App\Filament\Resources\Lenders\Schemas;

use App\Enums\LenderStatus;
use App\Enums\LenderType;
use App\Models\Lender;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class LenderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', Str::slug($state))),
                TextInput::make('slug')
                    ->required()
                    ->unique(Lender::class, 'slug', ignoreRecord: true),
                FileUpload::make('logo_path')
                    ->label('Logo')
                    ->image()
                    ->disk('public')
                    ->directory('lenders')
                    ->acceptedFileTypes(['image/png', 'image/svg+xml', 'image/webp'])
                    ->maxSize(2048),
                Textarea::make('description')
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(LenderStatus::class)
                    ->default(LenderStatus::Active)
                    ->required(),
                Select::make('type')
                    ->options(LenderType::class)
                    ->native(false),
                TagsInput::make('serviceable_locations')
                    ->helperText('Cities or regions this lender services. Leave empty if nationwide.'),
            ]);
    }
}
