<?php

namespace App\Filament\Resources\PageFaqs\Schemas;

use App\Enums\FaqPlacement;
use App\Enums\PublishStatus;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PageFaqForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('question')
                    ->required()
                    ->columnSpanFull(),
                Textarea::make('answer')
                    ->required()
                    ->rows(4)
                    ->columnSpanFull()
                    ->helperText('Shown to visitors on the page and published as FAQ structured data, so write a direct, self-contained answer.'),
                Select::make('placements')
                    ->label('Show on these pages')
                    ->multiple()
                    ->required()
                    ->searchable()
                    ->options(FaqPlacement::groupedOptions())
                    ->columnSpanFull()
                    ->helperText('Pick one or more pages. Options covering "every ... page" apply to all URLs of that type — e.g. "Every loan product page" shows on all of them, on top of each product\'s own FAQs tab.'),
                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->helperText('Lower shows first. The same order applies on every page this FAQ appears on.'),
                Select::make('status')
                    ->options(PublishStatus::class)
                    ->default(PublishStatus::Draft)
                    ->required()
                    ->live(),
                DateTimePicker::make('published_at')
                    ->helperText('Leave blank to publish immediately once status is Published.')
                    ->visible(fn (callable $get) => $get('status') === PublishStatus::Published->value),
                DateTimePicker::make('expires_at')
                    ->helperText('Optional. The FAQ stops appearing publicly after this time.'),
            ]);
    }
}
