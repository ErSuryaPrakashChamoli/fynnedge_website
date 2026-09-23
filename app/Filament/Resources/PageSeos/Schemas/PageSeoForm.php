<?php

namespace App\Filament\Resources\PageSeos\Schemas;

use App\Filament\Schemas\SeoFormSection;
use App\Models\PageSeo;
use App\Support\Seo\PublicPagePaths;
use Closure;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PageSeoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Page')
                    ->description('Pick the page whose meta tags you want to set. Anything left blank in the SEO section below keeps whatever that page shows today.')
                    ->columns(2)
                    ->components([
                        TextInput::make('url_path')
                            ->label('Page URL')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('/contact')
                            ->datalist(PublicPagePaths::all())
                            ->helperText('Start typing to pick a page, or enter any path on this site starting with / ("/" is the home page). Trailing slashes and query strings are ignored when matching.')
                            ->dehydrateStateUsing(fn (string $state): string => PageSeo::normalizePath($state))
                            /*
                             * Uniqueness is checked against the NORMALISED value for the
                             * same reason RedirectForm does it: "/a", "a/" and "/A" are one
                             * row as far as matching is concerned, so a plain unique() rule
                             * would let an admin save a second row that shadows the first
                             * and fail on the database index instead.
                             */
                            ->rules([
                                fn (?PageSeo $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    $exists = PageSeo::query()
                                        ->where('url_path', PageSeo::normalizePath((string) $value))
                                        ->when($record, fn ($query) => $query->whereKeyNot($record->getKey()))
                                        ->exists();

                                    if ($exists) {
                                        $fail('This page already has an SEO entry — edit that one instead.');
                                    }
                                },
                            ]),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->helperText('Turn off to keep the entry without applying it.'),
                    ]),
                SeoFormSection::make(defaultRobots: null)
                    ->collapsed(false)
                    ->description('These win over whatever the page sets for itself. Pages that already have their own SEO section (loan products, articles, CMS pages) are better edited there.'),
            ]);
    }
}
