<?php

namespace App\Filament\Schemas;

use App\Enums\SchemaPageType;
use App\Support\Seo\SchemaGraph;
use App\Support\Seo\SchemaTemplateRenderer;
use Closure;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;

class SeoFormSection
{
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
                TextInput::make('og_title')
                    ->label('Social share title')
                    ->maxLength(70)
                    ->placeholder('Falls back to the SEO title above')
                    ->helperText('Used for Facebook, LinkedIn, WhatsApp and X previews.'),
                TextInput::make('twitter_title')
                    ->label('X (Twitter) title')
                    ->maxLength(70)
                    ->placeholder('Falls back to the social share title'),
                TextInput::make('og_description')
                    ->label('Social share description')
                    ->maxLength(200)
                    ->placeholder('Falls back to the meta description above'),
                TextInput::make('twitter_description')
                    ->label('X (Twitter) description')
                    ->maxLength(200)
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
