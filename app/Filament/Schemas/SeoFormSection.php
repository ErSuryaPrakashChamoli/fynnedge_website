<?php

namespace App\Filament\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class SeoFormSection
{
    public static function make(): Section
    {
        return Section::make('SEO')
            ->relationship('seoMeta')
            ->collapsed()
            ->collapsible()
            ->description('Overrides the defaults derived from the content above. Leave blank to use them.')
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->label('SEO title')
                    ->maxLength(60)
                    ->columnSpanFull(),
                TextInput::make('description')
                    ->label('Meta description')
                    ->maxLength(160)
                    ->columnSpanFull(),
                TextInput::make('canonical_url')
                    ->label('Canonical URL')
                    ->url(),
                Select::make('robots')
                    ->options([
                        'index, follow' => 'Index, follow',
                        'noindex, follow' => 'Noindex, follow',
                        'noindex, nofollow' => 'Noindex, nofollow',
                    ])
                    ->default('index, follow'),
                FileUpload::make('og_image_path')
                    ->label('Social share image')
                    ->image()
                    ->disk('public')
                    ->directory('seo')
                    ->maxSize(2048)
                    ->helperText('Recommended 1200×630px, up to 2MB. Falls back to the site default SEO image if left blank.')
                    ->columnSpanFull(),
            ]);
    }
}
