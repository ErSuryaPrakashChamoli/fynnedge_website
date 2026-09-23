<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Enums\PublishStatus;
use App\Filament\Schemas\SeoFormSection;
use App\Models\Page;
use App\Support\Seo\Sitemap;
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
                        /*
                         * The slug IS the public URL, so it is only derived from the
                         * title while creating. Re-deriving it on edit turned a copy
                         * tweak to a page title into a 404 at /terms (and its footer
                         * link and sitemap entry with it).
                         */
                        TextInput::make('title')
                            ->required()
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (string $operation, ?string $state, callable $set): void {
                                if ($operation === 'create') {
                                    $set('slug', Str::slug((string) $state));
                                }
                            }),
                        /*
                         * Pages whose slug a route depends on (/terms, /about, …) are
                         * locked. A disabled field is not dehydrated, so saving the
                         * form can never write a different slug for them.
                         */
                        TextInput::make('slug')
                            ->required()
                            ->unique(Page::class, 'slug', ignoreRecord: true)
                            ->disabled(fn (?Page $record): bool => $record !== null && in_array($record->slug, Sitemap::publicPageSlugs(), true))
                            ->helperText(fn (?Page $record): ?string => $record !== null && in_array($record->slug, Sitemap::publicPageSlugs(), true)
                                ? 'Locked: this page is served at /'.$record->slug.', and the website links to that address.'
                                : null),
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
