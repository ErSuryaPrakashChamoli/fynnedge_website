<?php

namespace App\Filament\Pages;

use App\Enums\LenderStatus;
use App\Filament\Schemas\HindiTranslationSection;
use App\Models\Lender;
use App\Models\Setting;
use App\Support\Enquiries\QuickEnquiryPageContent;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

/**
 * The copy on the public /quick-enquiry page, saved as one Setting through
 * QuickEnquiryPageContent — which is also where the defaults live, so this form
 * opens showing exactly what the page currently says.
 *
 * The Hindi tab edits the same wording for Hindi visitors, saved beside it as
 * `quick_enquiry_page_hi`. A blank Hindi field shows the built-in Hindi (the
 * English looked up in lang/hi.json), which its placeholder previews. The
 * partner lender settings are shared by both languages.
 *
 * The enquiry form's rate/amount headline and amount range are not here: they
 * come from each loan product's own fields (Catalog → Loan Products).
 */
class QuickEnquiryPageSettings extends Page
{
    protected string $view = 'filament.pages.quick-enquiry-page-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static string|\UnitEnum|null $navigationGroup = 'Website Settings';

    protected static ?string $navigationLabel = 'Quick Enquiry Page';

    protected static ?string $title = 'Quick Enquiry Page';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:QuickEnquiryPageSettings');
    }

    public function mount(): void
    {
        $this->fillForm();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Language')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('English')
                            ->components($this->wordingSections(hindi: false)),
                        Tab::make('हिन्दी (Hindi)')
                            ->statePath('hi')
                            ->components([
                                Callout::make('What Hindi visitors read')
                                    ->description('Leave a field blank to use the built-in Hindi shown in grey. A list left empty shows the Hindi of the English list, and a list removed on the English tab stays hidden in Hindi too. Which partner lenders show is set on the English tab for both languages.'.HindiTranslationSection::settingsNote())
                                    ->info(),
                                ...$this->wordingSections(hindi: true),
                            ]),
                    ]),
            ]);
    }

    /**
     * The page's fields, once per language. The Hindi copy previews the
     * built-in Hindi of what the page says now and allows a little more length
     * (Hindi needs more characters than English); the partner lender switch,
     * picks and count are shared, so only the English tab has them.
     *
     * @return array<int, Section>
     */
    private function wordingSections(bool $hindi): array
    {
        $defaults = QuickEnquiryPageContent::defaults();
        $english = QuickEnquiryPageContent::resolve();
        $placeholder = fn (string $key): string => $hindi ? self::builtInHindi($english[$key]) : $defaults[$key];
        $length = fn (int $english): int => $hindi ? (int) ceil($english * 1.5) : $english;
        $listHelp = fn (string $english): string => $hindi ? 'Leave empty to use the Hindi of the English list.' : $english;

        return [
            Section::make('Homepage button')
                ->description($hindi ? null : 'The button beside "Check Your Eligibility" in the homepage hero that opens this page.')
                ->components([
                    TextInput::make('home_button_label')
                        ->label('Button label')
                        ->maxLength($length(30))
                        ->placeholder($placeholder('home_button_label')),
                ]),

            Section::make('Page heading')
                ->description($hindi ? null : 'The top of the page, beside the enquiry form. Leave a field blank to use its default wording.')
                ->columns(2)
                ->components([
                    TextInput::make('badge')
                        ->label('Badge')
                        ->maxLength($length(40))
                        ->placeholder($placeholder('badge'))
                        ->columnSpanFull(),
                    TextInput::make('heading')
                        ->label('Headline')
                        ->maxLength($length(80))
                        ->placeholder($placeholder('heading')),
                    TextInput::make('heading_accent')
                        ->label('Headline (highlighted part)')
                        ->maxLength($length(80))
                        ->placeholder($placeholder('heading_accent'))
                        ->helperText('Shown in the accent colour on the line after the headline.'),
                    Textarea::make('description')
                        ->label('Introduction')
                        ->rows(2)
                        ->maxLength($length(300))
                        ->placeholder($placeholder('description'))
                        ->columnSpanFull(),
                ]),

            Section::make('Highlights')
                ->description($listHelp('The short ticked points under the introduction. Remove them all to hide this row.'))
                ->components([
                    Repeater::make('assurances')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('text')
                                ->label('Point')
                                ->required()
                                ->maxLength($length(60)),
                        ])
                        ->maxItems(4)
                        ->defaultItems(0)
                        ->addActionLabel('Add point'),
                ]),

            Section::make('How it works')
                ->description($listHelp('The numbered step cards. Up to four; remove them all to hide the row.'))
                ->components([
                    Repeater::make('steps')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('title')
                                ->required()
                                ->maxLength($length(40)),
                            Textarea::make('body')
                                ->label('Description')
                                ->rows(2)
                                ->maxLength($length(140)),
                        ])
                        ->columns(2)
                        ->maxItems(4)
                        ->defaultItems(0)
                        ->addActionLabel('Add step'),
                ]),

            Section::make('Partner lenders')
                ->description($hindi ? null : 'Logos of active lenders (Catalog → Lenders — upload each logo there). The "+N more" link opens the full partner list at /partners.')
                ->columns(2)
                ->components(array_values(array_filter([
                    $hindi ? null : Toggle::make('show_lenders')
                        ->label('Show partner lenders')
                        ->inline(false),
                    TextInput::make('lenders_label')
                        ->label('Label above the logos')
                        ->maxLength($length(60))
                        ->placeholder($placeholder('lenders_label'))
                        ->helperText('Also the heading of the full partner list.'),
                    $hindi ? null : Select::make('featured_lender_ids')
                        ->label('Lenders to show')
                        ->multiple()
                        ->searchable()
                        ->reorderable()
                        ->options(fn (): array => Lender::query()
                            ->where('status', LenderStatus::Active)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->helperText('Shown in this order. Leave empty to show lenders with an uploaded logo first, then A–Z.'),
                    $hindi ? null : TextInput::make('lenders_limit')
                        ->label('How many logos to show')
                        ->integer()
                        ->minValue(1)
                        ->maxValue(QuickEnquiryPageContent::MAX_VISIBLE_LENDERS)
                        ->placeholder((string) $defaults['lenders_limit'])
                        ->helperText('The rest are counted in the "+N more" link.'),
                    Textarea::make('partners_description')
                        ->label('Introduction on the full partner list')
                        ->rows(2)
                        ->maxLength($length(300))
                        ->placeholder($placeholder('partners_description'))
                        ->columnSpanFull(),
                ]))),

            Section::make('Enquiry form')
                ->description($hindi ? null : 'The loan type list, rates, amounts and amount ranges come from each loan product and are not edited here.')
                ->columns(3)
                ->components([
                    TextInput::make('form_eyebrow')
                        ->label('Small label above the form')
                        ->maxLength($length(40))
                        ->placeholder($placeholder('form_eyebrow')),
                    TextInput::make('form_headline')
                        ->label('Headline before a loan type is chosen')
                        ->maxLength($length(60))
                        ->placeholder($placeholder('form_headline'))
                        ->helperText('Replaced by "Get up to ₹X starting at Y%" once a loan type is picked.'),
                    TextInput::make('form_cta_label')
                        ->label('Submit button')
                        ->maxLength($length(30))
                        ->placeholder($placeholder('form_cta_label')),
                ]),

            Section::make('Explore links')
                ->description($listHelp('The cards at the bottom of the page. Remove them all to hide this section.'))
                ->columns(2)
                ->components([
                    TextInput::make('explore_heading')
                        ->label('Heading')
                        ->maxLength($length(80))
                        ->placeholder($placeholder('explore_heading')),
                    TextInput::make('explore_description')
                        ->label('Description')
                        ->maxLength($length(160))
                        ->placeholder($placeholder('explore_description')),
                    Repeater::make('explore_links')
                        ->label('Cards')
                        ->schema([
                            TextInput::make('label')
                                ->label('Title')
                                ->required()
                                ->maxLength($length(40)),
                            TextInput::make('url')
                                ->label('Link')
                                ->required()
                                ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                                    if ($value && ! preg_match(QuickEnquiryPageContent::SAFE_URL_PATTERN, (string) $value)) {
                                        $fail('The link must start with http://, https:// or /.');
                                    }
                                })
                                ->helperText('A site path like /eligibility, or a full URL.'),
                            Textarea::make('body')
                                ->label('Description')
                                ->rows(2)
                                ->maxLength($length(140)),
                            TextInput::make('link_text')
                                ->label('Link text')
                                ->maxLength($length(20))
                                ->placeholder($hindi ? self::builtInHindi('Go') : 'Go'),
                        ])
                        ->columns(2)
                        ->maxItems(6)
                        ->defaultItems(0)
                        ->addActionLabel('Add card')
                        ->columnSpanFull(),
                ]),

            Section::make('Search engines')
                ->description($hindi ? null : 'The browser tab title and the description shown in search results.')
                ->columns(2)
                ->components([
                    TextInput::make('meta_title')
                        ->label('Page title')
                        ->maxLength($length(70))
                        ->placeholder($placeholder('meta_title')),
                    Textarea::make('meta_description')
                        ->label('Meta description')
                        ->rows(2)
                        ->maxLength($length(160))
                        ->placeholder($placeholder('meta_description')),
                ]),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $hindi = Arr::pull($state, 'hi', []);

        Setting::set(QuickEnquiryPageContent::SETTING_KEY, self::plainLists($state));
        Setting::set(QuickEnquiryPageContent::HINDI_SETTING_KEY, self::plainLists($hindi));

        Notification::make()->title('Quick Enquiry page saved')->success()->send();
    }

    public function resetToDefaults(): void
    {
        Setting::set(QuickEnquiryPageContent::SETTING_KEY, null);
        Setting::set(QuickEnquiryPageContent::HINDI_SETTING_KEY, null);

        $this->fillForm();

        Notification::make()->title('Quick Enquiry page reset to default wording')->success()->send();
    }

    private function fillForm(): void
    {
        $this->form->fill([
            ...QuickEnquiryPageContent::resolve(),
            'hi' => QuickEnquiryPageContent::hindi(),
        ]);
    }

    /**
     * Repeater state is keyed by item UUIDs; store plain lists.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private static function plainLists(array $state): array
    {
        return array_map(fn (mixed $value): mixed => is_array($value) ? array_values($value) : $value, $state);
    }

    /**
     * What a Hindi visitor reads for this English text while the Hindi field
     * is blank.
     */
    private static function builtInHindi(string $english): string
    {
        $hindi = __($english, [], 'hi');

        return is_string($hindi) ? $hindi : $english;
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('view')
                ->label('View page')
                ->color('gray')
                ->url(route('quick-enquiry.show'), shouldOpenInNewTab: true),
            Action::make('reset')
                ->label('Reset to defaults')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Every field on this page, English and Hindi, goes back to the built-in wording. This cannot be undone.')
                ->action('resetToDefaults'),
            Action::make('save')
                ->label('Save')
                ->action('save'),
        ];
    }
}
