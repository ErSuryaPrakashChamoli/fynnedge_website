<?php

namespace App\Filament\Pages;

use App\Enums\LenderStatus;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The copy on the public /quick-enquiry page, saved as one Setting through
 * QuickEnquiryPageContent — which is also where the defaults live, so this form
 * opens showing exactly what the page currently says.
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
        $this->form->fill(QuickEnquiryPageContent::resolve());
    }

    public function form(Schema $schema): Schema
    {
        $defaults = QuickEnquiryPageContent::defaults();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Homepage button')
                    ->description('The button beside "Check Your Eligibility" in the homepage hero that opens this page.')
                    ->components([
                        TextInput::make('home_button_label')
                            ->label('Button label')
                            ->maxLength(30)
                            ->placeholder($defaults['home_button_label']),
                    ]),

                Section::make('Page heading')
                    ->description('The top of the page, beside the enquiry form. Leave a field blank to use its default wording.')
                    ->columns(2)
                    ->components([
                        TextInput::make('badge')
                            ->label('Badge')
                            ->maxLength(40)
                            ->placeholder($defaults['badge'])
                            ->columnSpanFull(),
                        TextInput::make('heading')
                            ->label('Headline')
                            ->maxLength(80)
                            ->placeholder($defaults['heading']),
                        TextInput::make('heading_accent')
                            ->label('Headline (highlighted part)')
                            ->maxLength(80)
                            ->placeholder($defaults['heading_accent'])
                            ->helperText('Shown in the accent colour on the line after the headline.'),
                        Textarea::make('description')
                            ->label('Introduction')
                            ->rows(2)
                            ->maxLength(300)
                            ->placeholder($defaults['description'])
                            ->columnSpanFull(),
                    ]),

                Section::make('Highlights')
                    ->description('The short ticked points under the introduction. Remove them all to hide this row.')
                    ->components([
                        Repeater::make('assurances')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('text')
                                    ->label('Point')
                                    ->required()
                                    ->maxLength(60),
                            ])
                            ->maxItems(4)
                            ->defaultItems(0)
                            ->addActionLabel('Add point'),
                    ]),

                Section::make('How it works')
                    ->description('The numbered step cards. Up to four; remove them all to hide the row.')
                    ->components([
                        Repeater::make('steps')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(40),
                                Textarea::make('body')
                                    ->label('Description')
                                    ->rows(2)
                                    ->maxLength(140),
                            ])
                            ->columns(2)
                            ->maxItems(4)
                            ->defaultItems(0)
                            ->addActionLabel('Add step'),
                    ]),

                Section::make('Partner lenders')
                    ->description('Logos of active lenders (Catalog → Lenders — upload each logo there). The "+N more" link opens the full partner list at /partners.')
                    ->columns(2)
                    ->components([
                        Toggle::make('show_lenders')
                            ->label('Show partner lenders')
                            ->inline(false),
                        TextInput::make('lenders_label')
                            ->label('Label above the logos')
                            ->maxLength(60)
                            ->placeholder($defaults['lenders_label'])
                            ->helperText('Also the heading of the full partner list.'),
                        Select::make('featured_lender_ids')
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
                        TextInput::make('lenders_limit')
                            ->label('How many logos to show')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(QuickEnquiryPageContent::MAX_VISIBLE_LENDERS)
                            ->placeholder((string) $defaults['lenders_limit'])
                            ->helperText('The rest are counted in the "+N more" link.'),
                        Textarea::make('partners_description')
                            ->label('Introduction on the full partner list')
                            ->rows(2)
                            ->maxLength(300)
                            ->placeholder($defaults['partners_description'])
                            ->columnSpanFull(),
                    ]),

                Section::make('Enquiry form')
                    ->description('The loan type list, rates, amounts and amount ranges come from each loan product and are not edited here.')
                    ->columns(3)
                    ->components([
                        TextInput::make('form_eyebrow')
                            ->label('Small label above the form')
                            ->maxLength(40)
                            ->placeholder($defaults['form_eyebrow']),
                        TextInput::make('form_headline')
                            ->label('Headline before a loan type is chosen')
                            ->maxLength(60)
                            ->placeholder($defaults['form_headline'])
                            ->helperText('Replaced by "Get up to ₹X starting at Y%" once a loan type is picked.'),
                        TextInput::make('form_cta_label')
                            ->label('Submit button')
                            ->maxLength(30)
                            ->placeholder($defaults['form_cta_label']),
                    ]),

                Section::make('Explore links')
                    ->description('The cards at the bottom of the page. Remove them all to hide this section.')
                    ->columns(2)
                    ->components([
                        TextInput::make('explore_heading')
                            ->label('Heading')
                            ->maxLength(80)
                            ->placeholder($defaults['explore_heading']),
                        TextInput::make('explore_description')
                            ->label('Description')
                            ->maxLength(160)
                            ->placeholder($defaults['explore_description']),
                        Repeater::make('explore_links')
                            ->label('Cards')
                            ->schema([
                                TextInput::make('label')
                                    ->label('Title')
                                    ->required()
                                    ->maxLength(40),
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
                                    ->maxLength(140),
                                TextInput::make('link_text')
                                    ->label('Link text')
                                    ->maxLength(20)
                                    ->placeholder('Go'),
                            ])
                            ->columns(2)
                            ->maxItems(6)
                            ->defaultItems(0)
                            ->addActionLabel('Add card')
                            ->columnSpanFull(),
                    ]),

                Section::make('Search engines')
                    ->description('The browser tab title and the description shown in search results.')
                    ->columns(2)
                    ->components([
                        TextInput::make('meta_title')
                            ->label('Page title')
                            ->maxLength(70)
                            ->placeholder($defaults['meta_title']),
                        Textarea::make('meta_description')
                            ->label('Meta description')
                            ->rows(2)
                            ->maxLength(160)
                            ->placeholder($defaults['meta_description']),
                    ]),
            ]);
    }

    public function save(): void
    {
        // Repeater state is keyed by item UUIDs; store plain lists.
        Setting::set(QuickEnquiryPageContent::SETTING_KEY, array_map(
            fn (mixed $value): mixed => is_array($value) ? array_values($value) : $value,
            $this->form->getState(),
        ));

        Notification::make()->title('Quick Enquiry page saved')->success()->send();
    }

    public function resetToDefaults(): void
    {
        Setting::set(QuickEnquiryPageContent::SETTING_KEY, null);

        $this->form->fill(QuickEnquiryPageContent::resolve());

        Notification::make()->title('Quick Enquiry page reset to default wording')->success()->send();
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
                ->modalDescription('Every field on this page goes back to the built-in wording. This cannot be undone.')
                ->action('resetToDefaults'),
            Action::make('save')
                ->label('Save')
                ->action('save'),
        ];
    }
}
