<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Calculators\CalculatorIndexing;
use App\Support\Calculators\CalculatorPagesContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

/**
 * The heading copy on /calculators and each calculator page, saved as one
 * Setting through CalculatorPagesContent — which is also where the defaults
 * live, so this form opens showing exactly what the pages currently say.
 *
 * The "About this calculator" body is edited elsewhere: Content → Calculator
 * Pages (FD, SIP, Daily SIP, GST) or each Loan Product's calculator
 * explanation (EMI, Eligibility, Prepayment).
 *
 * The "Search engine indexing" section is saved separately through
 * CalculatorIndexing, so resetting the wording never changes which pages
 * search engines may index.
 */
class CalculatorPagesSettings extends Page
{
    /**
     * Each section of the form: the page key in CalculatorPagesContent, its
     * heading, and the note shown under it.
     */
    private const SECTIONS = [
        'index' => ['All calculators page (/calculators)', 'The directory page listing every calculator.'],
        'emi' => ['Loan EMI calculators', 'Shared by every loan type — write {loan} wherever the loan name should appear, e.g. "{loan} EMI Calculator" shows as "Home Loan EMI Calculator". To give one page its own headline, edit it under Content → Calculator Pages.'],
        'eligibility' => ['Loan eligibility calculators', 'Shared by the Personal Loan and Home Loan pages — {loan} becomes the loan name.'],
        'prepayment' => ['Loan prepayment calculators', 'Shared by every loan type — {loan} becomes the loan name.'],
        'fixed_deposit' => ['Fixed Deposit Calculator', 'The "About this calculator" text is under Content → Calculator Pages.'],
        'sip' => ['SIP Calculator', 'The "About this calculator" text is under Content → Calculator Pages.'],
        'daily_sip' => ['Daily SIP Calculator', 'The "About this calculator" text is under Content → Calculator Pages.'],
        'gst' => ['GST Calculator', 'The "About this calculator" text is under Content → Calculator Pages.'],
    ];

    protected string $view = 'filament.pages.calculator-pages-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static string|\UnitEnum|null $navigationGroup = 'Website Settings';

    protected static ?string $navigationLabel = 'Calculators Page';

    protected static ?string $title = 'Calculators Page';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:CalculatorPagesSettings');
    }

    public function mount(): void
    {
        $this->fillForm();
    }

    public function form(Schema $schema): Schema
    {
        $defaults = CalculatorPagesContent::defaults();

        return $schema
            ->statePath('data')
            ->components([
                $this->indexingSection(),
                ...$this->wordingSections($defaults),
            ]);
    }

    private function indexingSection(): Section
    {
        return Section::make('Search engine indexing')
            ->description('Choose whether Google and other search engines may list the calculator pages. Hidden pages get a "noindex" tag and are left out of the sitemap. The sitewide switch under Website Settings → SEO & Analytics still overrides this.')
            ->collapsible()
            ->components([
                Toggle::make('indexing.enabled')
                    ->label('Allow search engines to index the calculator pages')
                    ->helperText('Turn off to hide /calculators and every calculator page from search at once.'),
                CheckboxList::make('indexing.noindex')
                    ->label('Hide these pages from search engines')
                    ->helperText('Only applies while the switch above is on — when it is off, every calculator page is hidden.')
                    ->options(CalculatorIndexing::pages())
                    ->bulkToggleable()
                    ->columns(2),
            ]);
    }

    /**
     * @param  array<string, array<string, string>>  $defaults
     * @return array<int, Section>
     */
    private function wordingSections(array $defaults): array
    {
        return collect(self::SECTIONS)
            ->map(fn (array $section, string $page): Section => Section::make($section[0])
                ->description($section[1].' Leave a field blank to use its default wording.')
                ->collapsible()
                ->collapsed($page !== 'index')
                ->columns(2)
                ->components(array_values(array_filter([
                    TextInput::make("{$page}.heading")
                        ->label('Headline')
                        ->maxLength(100)
                        ->placeholder($defaults[$page]['heading']),
                    isset($defaults[$page]['about_heading'])
                        ? TextInput::make("{$page}.about_heading")
                            ->label('"About" section heading')
                            ->maxLength(100)
                            ->placeholder($defaults[$page]['about_heading'])
                            ->helperText('Above the loan explanation lower down the page.')
                        : null,
                    Textarea::make("{$page}.description")
                        ->label('Introduction')
                        ->rows(2)
                        ->maxLength(300)
                        ->placeholder($defaults[$page]['description'])
                        ->columnSpanFull(),
                    TextInput::make("{$page}.meta_title")
                        ->label('Page title (browser tab & search results)')
                        ->maxLength(70)
                        ->placeholder($defaults[$page]['meta_title']),
                    Textarea::make("{$page}.meta_description")
                        ->label('Meta description')
                        ->rows(2)
                        ->maxLength(160)
                        ->placeholder($defaults[$page]['meta_description']),
                ]))))
            ->values()
            ->all();
    }

    public function save(): void
    {
        $state = $this->form->getState();

        CalculatorIndexing::save(Arr::pull($state, 'indexing', []));
        Setting::set(CalculatorPagesContent::SETTING_KEY, $state);

        Notification::make()->title('Calculator pages saved')->success()->send();
    }

    public function resetToDefaults(): void
    {
        Setting::set(CalculatorPagesContent::SETTING_KEY, null);

        $this->fillForm();

        Notification::make()->title('Calculator pages reset to default wording')->success()->send();
    }

    private function fillForm(): void
    {
        $this->form->fill([
            ...CalculatorPagesContent::resolve(),
            'indexing' => CalculatorIndexing::formState(),
        ]);
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
                ->url(route('calculators.index'), shouldOpenInNewTab: true),
            Action::make('reset')
                ->label('Reset to defaults')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Every wording field on this page goes back to the built-in text. Search engine indexing is not changed. This cannot be undone.')
                ->action('resetToDefaults'),
            Action::make('save')
                ->label('Save')
                ->action('save'),
        ];
    }
}
