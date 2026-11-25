<?php

namespace App\Filament\Resources\Pages\Schemas;

use App\Enums\PublishStatus;
use App\Filament\Schemas\HindiTranslationSection;
use App\Filament\Schemas\SeoFormSection;
use App\Models\Page;
use App\Models\Redirect;
use App\Support\Seo\Sitemap;
use Closure;
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
                            ->label('Address (slug)')
                            ->required()
                            ->unique(Page::class, 'slug', ignoreRecord: true)
                            ->prefix(fn (): string => rtrim((string) config('app.url'), '/').'/')
                            ->disabled(fn (?Page $record): bool => $record !== null && in_array($record->slug, Sitemap::publicPageSlugs(), true))
                            /*
                             * A new general page is served at /{slug} by the catch-all
                             * route, which every other route outranks — so a slug some
                             * other page already answers (/contact, /loans…) or that a
                             * redirect sends elsewhere would never show this page.
                             */
                            ->rules([
                                fn (?Page $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                                    $slug = (string) $value;

                                    if ($record !== null && in_array($record->slug, Sitemap::publicPageSlugs(), true)) {
                                        return;
                                    }

                                    if (preg_match('/^'.Sitemap::GENERAL_PAGE_SLUG_PATTERN.'$/', $slug) !== 1) {
                                        $fail('Use lowercase letters, numbers and single hyphens only (e.g. home-loan-guide).');

                                        return;
                                    }

                                    if (! Sitemap::servesGeneralPage($slug)) {
                                        $fail("fynnedge.com/{$slug} is already used by another part of the website. Choose a different address.");

                                        return;
                                    }

                                    if (Redirect::query()->where('is_active', true)->get(['source_path'])->contains(fn (Redirect $redirect): bool => Redirect::normalizePath($redirect->source_path) === Redirect::normalizePath('/'.$slug))) {
                                        $fail("An active redirect sends /{$slug} to another address (SEO & Performance → Redirects). Remove it or choose a different address.");
                                    }
                                },
                            ])
                            ->helperText(fn (?Page $record): string => $record !== null && in_array($record->slug, Sitemap::publicPageSlugs(), true)
                                ? 'Locked: this page is served at /'.$record->slug.', and the website links to that address.'
                                : 'The page\'s web address. Changing it after publishing breaks existing links — add a redirect from the old address if you do.'),
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

                HindiTranslationSection::make([
                    TextInput::make('title_hi')
                        ->label('Title (Hindi)'),
                    TextInput::make('excerpt_hi')
                        ->label('Excerpt (Hindi)')
                        ->maxLength(240),
                    HindiTranslationSection::richEditor('body_hi', 'Body (Hindi)'),
                ]),
            ]);
    }
}
