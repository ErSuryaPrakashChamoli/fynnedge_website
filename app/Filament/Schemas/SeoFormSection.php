<?php

namespace App\Filament\Schemas;

use App\Enums\SchemaPageType;
use App\Support\Seo\SchemaGraph;
use App\Support\Seo\SchemaTemplateRenderer;
use App\Support\Seo\SearchEngineIndexing;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class SeoFormSection
{
    /**
     * The only ceiling on any SEO text field is the width of the seo_metas
     * columns that store it.
     *
     * The old 60/160/70/200 caps were search-engine DISPLAY guidance enforced
     * as validation, which is the wrong layer for it: a title Google truncates
     * in its results page is still a perfectly valid title, still read in full
     * by other crawlers, share previews and AI assistants, and an admin who
     * genuinely needs a longer one had no way to save it. The guidance now
     * lives in the helper text, where it informs without blocking.
     */
    private const MAX_LENGTH = 255;

    /**
     * The structured-data controls are gated on the same permission as the
     * Structured Data settings page, so an admin decides in one place who may
     * shape schema output. The pre-existing title/description/canonical/robots/
     * image fields are deliberately NOT gated — they were already available to
     * every role that can edit a record's SEO, and moving them behind a new,
     * unassigned permission would silently revoke access the SEO role has today.
     */
    private static function canEditStructuredData(): bool
    {
        return (bool) auth()->user()?->can('View:StructuredData');
    }

    /**
     * A record's own SEO section defaults robots to "index, follow". An
     * OVERRIDE layer (Page SEO) passes null instead: a filled robots value there
     * wins over the page's own, so a default would silently re-index a page
     * that set itself noindex whenever an admin added a row just to change
     * its title. Blank means "don't override".
     */
    public static function make(?string $defaultRobots = SearchEngineIndexing::DEFAULT_ROBOTS): Section
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
                    ->maxLength(self::MAX_LENGTH)
                    ->helperText('Google usually shows about the first 60 characters — longer is allowed, it just gets truncated in results.')
                    ->columnSpanFull(),
                TextInput::make('description')
                    ->label('Meta description')
                    ->maxLength(self::MAX_LENGTH)
                    ->helperText('Google usually shows about the first 160 characters. Write the full sentence if you want to — nothing is cut off before it reaches the page.')
                    ->columnSpanFull(),
                TextInput::make('canonical_url')
                    ->label('Canonical URL')
                    ->url(),
                Select::make('robots')
                    ->label('SEO indexing')
                    ->helperText('Sets this page\'s <meta name="robots"> tag. Noindex pages are also left out of the sitemap.')
                    ->options([
                        'index, follow' => 'Index, follow',
                        'noindex, follow' => 'Noindex, follow',
                        'noindex, nofollow' => 'Noindex, nofollow',
                    ])
                    ->default($defaultRobots)
                    ->placeholder($defaultRobots === null ? "Don't override — keep the page's own setting" : null),
                FileUpload::make('og_image_path')
                    ->label('Social share image')
                    ->image()
                    ->disk('public')
                    ->directory('seo')
                    ->maxSize(2048)
                    ->helperText('Recommended 1200×630px, up to 2MB. Falls back to the site default SEO image if left blank.')
                    ->columnSpanFull(),
                TextInput::make('og_title')
                    ->label('Social share title')
                    ->maxLength(self::MAX_LENGTH)
                    ->placeholder('Falls back to the SEO title above')
                    ->helperText('Used for Facebook, LinkedIn, WhatsApp and X previews.'),
                TextInput::make('twitter_title')
                    ->label('X (Twitter) title')
                    ->maxLength(self::MAX_LENGTH)
                    ->placeholder('Falls back to the social share title'),
                TextInput::make('og_description')
                    ->label('Social share description')
                    ->maxLength(self::MAX_LENGTH)
                    ->placeholder('Falls back to the meta description above'),
                TextInput::make('twitter_description')
                    ->label('X (Twitter) description')
                    ->maxLength(self::MAX_LENGTH)
                    ->placeholder('Falls back to the social share description'),
                FileUpload::make('twitter_image_path')
                    ->label('X (Twitter) image')
                    ->image()
                    ->disk('public')
                    ->directory('seo')
                    ->maxSize(2048)
                    ->helperText('Optional. Only needed when X should show a different image from the social share image above.')
                    ->columnSpanFull(),
                Select::make('page_type')
                    ->label('Page type (schema.org)')
                    ->options(SchemaPageType::options())
                    ->searchable()
                    ->native(false)
                    ->visible(self::canEditStructuredData(...))
                    ->placeholder('Use the sitewide default ('.SchemaGraph::defaultPageType().')')
                    ->helperText('Sets the @type on this page\'s WebPage node in the sitewide graph. Leave blank to inherit the default.')
                    ->columnSpanFull(),
                Select::make('schema_template_id')
                    ->label('Schema template')
                    ->relationship('schemaTemplate', 'name', fn ($query) => $query->active())
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->visible(self::canEditStructuredData(...))
                    ->helperText('Optional. Renders a reusable JSON-LD blueprint into this page\'s graph, with '.implode(', ', array_map(fn (string $token): string => '{{ '.$token.' }}', SchemaTemplateRenderer::availableTokens())).' filled in from this record. Manage blueprints under Content → Schema Templates.')
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
