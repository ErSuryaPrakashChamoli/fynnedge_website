<?php

namespace App\Filament\Pages;

use App\Enums\SchemaPageType;
use App\Models\Setting;
use App\Support\Seo\SchemaGraph;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Admin control over the SHAPE of the sitewide JSON-LD `@graph` — the `@type`
 * of each of its three standing nodes, and any extra properties to merge into
 * them — as opposed to the Settings page's Business profile section, which
 * supplies the Organization node's VALUES (address, phone, areas served).
 *
 * Split onto its own page rather than added as another Settings section so it
 * carries its own Shield permission (`View:StructuredData`), which an admin can
 * grant to Marketing, SEO or any other role independently of the site settings
 * an editor already has. The same permission gates the structured-data controls
 * in the shared SEO form section and, as on AdminActivity, it governs editing
 * too — the page is the edit surface, so a separate write permission would name
 * a distinction the panel doesn't have.
 *
 * `@id` and `@context` are unavailable here on purpose: the graph's nodes
 * reference each other by `@id`, so an override would disconnect them. See
 * SchemaGraph::applyOverrides().
 */
class StructuredData extends Page
{
    protected string $view = 'filament.pages.structured-data';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCodeBracket;

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Structured Data';

    protected static ?string $title = 'Structured Data (JSON-LD)';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:StructuredData');
    }

    public function mount(): void
    {
        $this->form->fill([
            'schema_organization_types' => Setting::get(
                'schema_organization_types',
                implode("\n", SchemaGraph::DEFAULT_ORGANIZATION_TYPES),
            ),
            'schema_organization_extra' => Setting::get('schema_organization_extra'),
            'schema_website_type' => Setting::get('schema_website_type', SchemaGraph::DEFAULT_WEBSITE_TYPE),
            'schema_website_extra' => Setting::get('schema_website_extra'),
            'schema_default_page_type' => Setting::get('schema_default_page_type', SchemaGraph::DEFAULT_PAGE_TYPE),
            'schema_webpage_extra' => Setting::get('schema_webpage_extra'),
            'schema_language' => Setting::get('schema_language', SchemaGraph::DEFAULT_LANGUAGE),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Organization node')
                    ->description('The business itself, referenced by every other node in the graph as #organization. Its address, phone, description and areas served are edited under Settings → Business profile — this section controls how it is typed.')
                    ->columns(2)
                    ->components([
                        Textarea::make('schema_organization_types')
                            ->label('Types')
                            ->rows(3)
                            ->helperText('One schema.org type per line, e.g. Organization, FinancialService, LocalBusiness. "Organization" is always kept — the rest of the graph identifies the business by it.')
                            ->columnSpanFull(),
                        self::extraPropertiesField(
                            'schema_organization_extra',
                            'Any further schema.org properties for the Organization node, e.g. {"foundingDate": "2023-04-01", "numberOfEmployees": 12}. These override the generated values on a name clash.',
                        ),
                    ]),

                Section::make('WebSite node')
                    ->description('The site as a work, referenced as #website by every page.')
                    ->columns(2)
                    ->components([
                        Textarea::make('schema_website_type')
                            ->label('Type')
                            ->rows(1)
                            ->helperText('Usually WebSite.')
                            ->columnSpanFull(),
                        self::extraPropertiesField(
                            'schema_website_extra',
                            'Any further schema.org properties for the WebSite node, e.g. a {"potentialAction": {...}} sitelinks search box.',
                        ),
                    ]),

                Section::make('WebPage node')
                    ->description('The page being viewed. A single page can override its type on its own SEO section; this is the default every page starts from.')
                    ->columns(2)
                    ->components([
                        Select::make('schema_default_page_type')
                            ->label('Default page type')
                            ->options(SchemaPageType::options())
                            ->native(false)
                            ->searchable()
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('schema_language')
                            ->label('Content language')
                            ->rows(1)
                            ->helperText('BCP 47 language tag used as inLanguage on the WebSite and WebPage nodes, e.g. en or en-IN.')
                            ->columnSpanFull(),
                        self::extraPropertiesField(
                            'schema_webpage_extra',
                            'Any further schema.org properties added to every page\'s WebPage node. Use sparingly — a value that is not true of every page belongs on that page\'s own SEO section instead.',
                        ),
                    ]),
            ]);
    }

    /**
     * Free-form JSON merged into a generated node. Stored decoded, the same
     * rule seo_metas.structured_data and schema_templates.body follow, so the
     * app can only ever re-encode valid JSON.
     */
    private static function extraPropertiesField(string $key, string $helperText): Textarea
    {
        return Textarea::make($key)
            ->label('Extra properties (JSON)')
            ->rows(6)
            ->helperText($helperText)
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
                        $fail('Extra properties must be a valid JSON object.');
                    }
                },
            ])
            ->columnSpanFull();
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()
            ->title('Structured data settings saved')
            ->success()
            ->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->action('save'),
        ];
    }
}
