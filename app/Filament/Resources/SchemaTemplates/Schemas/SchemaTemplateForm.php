<?php

namespace App\Filament\Resources\SchemaTemplates\Schemas;

use App\Support\Seo\SchemaTemplateRenderer;
use Closure;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SchemaTemplateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(120)
                    ->unique(ignoreRecord: true)
                    ->helperText('How this blueprint appears in the Schema template picker on a page\'s SEO section.')
                    ->columnSpanFull(),
                TextInput::make('schema_type')
                    ->label('schema.org @type')
                    ->required()
                    ->maxLength(60)
                    ->rules(['regex:/^[A-Za-z][A-Za-z0-9]*$/'])
                    ->placeholder('HowTo')
                    ->helperText('Any schema.org type, e.g. HowTo, Event, Course, VideoObject, BreadcrumbList. Used when the JSON below omits its own "@type".'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(true)
                    ->helperText('Turning this off stops the blueprint rendering on every page it is attached to, without detaching it.'),
                Textarea::make('notes')
                    ->label('Notes')
                    ->rows(2)
                    ->maxLength(500)
                    ->helperText('Optional. What this blueprint is for and which pages should use it.')
                    ->columnSpanFull(),
                Textarea::make('body')
                    ->label('JSON-LD body')
                    ->required()
                    ->rows(14)
                    ->helperText(self::bodyHelperText())
                    /**
                     * Stored decoded, exactly as seo_metas.structured_data is:
                     * the Textarea pretty-prints the array to edit and decodes
                     * it back on save, so the app can only ever re-encode valid
                     * JSON. Do not switch this to a plain text column.
                     */
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
                                $fail('The JSON-LD body must be a valid JSON object or array.');
                            }
                        },
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function bodyHelperText(): string
    {
        $tokens = implode(', ', array_map(
            fn (string $token): string => '{{ '.$token.' }}',
            SchemaTemplateRenderer::availableTokens(),
        ));

        return 'The JSON object only — no surrounding <script> tag. These placeholders are replaced per page: '
            .$tokens
            .'. A placeholder with nothing to fill it removes its property rather than leaving it blank, and "@id" is added automatically if you omit it.';
    }
}
