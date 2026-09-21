<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Calculators\CalculatorPagesContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The heading copy on /calculators and each calculator page, saved as one
 * Setting through CalculatorPagesContent — which is also where the defaults
 * live, so this form opens showing exactly what the pages currently say.
 *
 * The "About this calculator" body is edited elsewhere: Content → Calculator
 * Pages (FD, SIP, Daily SIP, GST) or each Loan Product's calculator
 * explanation (EMI, Eligibility, Prepayment).
 */
class CalculatorPagesSettings extends Page
{
    /**
     * Each section of the form: the page key in CalculatorPagesContent, its
     * heading, and the note shown under it.
     */
    private const SECTIONS = [
        'index' => ['All calculators page (/calculators)', 'The directory page listing every calculator.'],
        'emi' => ['Loan EMI calculators', 'Shared by every loan type — write {loan} wherever the loan name should appear, e.g. "{loan} EMI Calculator" shows as "Home Loan EMI Calculator".'],
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
        $this->form->fill(CalculatorPagesContent::resolve());
    }

    public function form(Schema $schema): Schema
    {
        $defaults = CalculatorPagesContent::defaults();

        return $schema
            ->statePath('data')
            ->components(collect(self::SECTIONS)
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
                ->all());
    }

    public function save(): void
    {
        Setting::set(CalculatorPagesContent::SETTING_KEY, $this->form->getState());

        Notification::make()->title('Calculator pages saved')->success()->send();
    }

    public function resetToDefaults(): void
    {
        Setting::set(CalculatorPagesContent::SETTING_KEY, null);

        $this->form->fill(CalculatorPagesContent::resolve());

        Notification::make()->title('Calculator pages reset to default wording')->success()->send();
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
                ->modalDescription('Every field on this page goes back to the built-in wording. This cannot be undone.')
                ->action('resetToDefaults'),
            Action::make('save')
                ->label('Save')
                ->action('save'),
        ];
    }
}
