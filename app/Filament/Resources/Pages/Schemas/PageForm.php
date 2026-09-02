<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Enums\PublishStatus;
use App\Filament\Schemas\SeoFormSection;
use App\Models\Page;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class PageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Overview')
                    ->columns(2)
                    ->components([
                        TextInput::make('title')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn (string $state, callable $set) => $set('slug', Str::slug($state))),
                        TextInput::make('slug')
                            ->required()
                            ->unique(Page::class, 'slug', ignoreRecord: true),
                        TextInput::make('excerpt')
                            ->maxLength(160)
                            ->columnSpanFull(),
                        Select::make('status')
                            ->options(PublishStatus::class)
                            ->default(PublishStatus::Draft)
                            ->required()
                            ->live(),
                        DateTimePicker::make('published_at')
                            ->visible(fn (callable $get) => $get('status') === PublishStatus::Published->value),
                        DateTimePicker::make('expires_at')
                            ->helperText('Optional. The page stops appearing publicly after this time.'),
                    ]),

                RichEditor::make('body'),

                SeoFormSection::make(),
            ]);
    }
}
