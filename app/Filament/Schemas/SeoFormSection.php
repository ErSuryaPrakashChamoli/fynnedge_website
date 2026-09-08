<?php

namespace App\Filament\Schemas;

use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                Textarea::make('structured_data')
                    ->label('Custom JSON-LD structured data')
                    ->rows(10)
                    ->helperText('Optional escape hatch for schema.org types this app does not generate itself (HowTo, Service, Event…). Paste the JSON object only — no surrounding <script> tag. FAQPage schema is generated automatically from the FAQs tab, so it does not belong here.')
                    ->formatStateUsing(fn (mixed $state): ?string => match (true) {
                        is_string($state) => $state,
                        filled($state) => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                        default => null,
                    })
                    ->dehydrateStateUsing(fn (?string $state): ?array => filled($state) ? json_decode($state, true) : null)
                    ->rules([
                        fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                            if (blank($value)) {
                                return;
                            }

                            if (! is_array(json_decode((string) $value, true))) {
                                $fail('The custom JSON-LD must be a valid JSON object or array.');
                            }
                        },
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
