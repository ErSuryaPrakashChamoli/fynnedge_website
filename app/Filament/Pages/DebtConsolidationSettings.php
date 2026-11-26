<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Models\Setting;
use App\Modules\DebtConsolidation\Enums\FinancingRoute;
use App\Modules\DebtConsolidation\Services\DebtConsolidationSettings as Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

/**
 * Website Settings → Debt Consolidation Settings: whether the service is live
 * and for whom, which calculators show, engine assumptions, standard
 * comparison scenarios, notifications, disclaimers and consent wording.
 * Formulas and scoring thresholds are not here (code / Scoring
 * Configuration). Changing any consent wording bumps the consent version, so
 * every consent record shows which wording was agreed to.
 */
class DebtConsolidationSettings extends Page
{
    use BelongsToAdminModule;

    protected string $view = 'filament.pages.newsletter-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static ?string $navigationLabel = 'Debt Consolidation Settings';

    protected static ?string $title = 'Debt Consolidation Settings';

    protected static ?string $slug = 'debt-consolidation-settings';

    private const CONSENT_KEYS = ['consent_assessment', 'consent_contact', 'consent_lender_sharing', 'consent_external_analysis'];

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:DebtConsolidationSettings');
    }

    public function mount(): void
    {
        $hindi = Setting::get(Settings::KEY.'_hi', []);

        $this->form->fill([...Settings::all(), 'hi' => is_array($hindi) ? $hindi : []]);
    }

    public function form(Schema $schema): Schema
    {
        $text = fn (string $key, string $label, int $rows = 3) => Textarea::make($key)->label($label)->rows($rows)->required()->maxLength(2000);
        $hindiText = fn (string $key, string $label) => Textarea::make('hi.'.$key)->label($label.' (हिन्दी)')->rows(3)->maxLength(3000)->helperText('Leave blank to use the automatic translation.');

        return $schema->statePath('data')->components([
            Tabs::make()->columnSpanFull()->tabs([
                Tab::make('Service')->schema([
                    Section::make('Availability')->columns(3)->components([
                        Toggle::make('enabled')->label('Debt Consolidation is live on the website')->helperText('Off: the pages answer 404 and the forms are hidden. Pages must also be published in Website Pages → General Pages.'),
                        Toggle::make('individual_enabled')->label('Accept individuals'),
                        Toggle::make('business_enabled')->label('Accept businesses'),
                        CheckboxList::make('calculators')->label('Calculators shown')->columnSpanFull()->columns(3)->options([
                            'consolidation' => 'Consolidation', 'foreclosure' => 'Foreclosure cost', 'comparison' => 'EMI & savings comparison',
                            'affordability' => 'Affordability', 'break_even' => 'Break-even', 'portfolio' => 'Portfolio summary',
                        ])->required(),
                    ]),
                    Section::make('Notifications')->columns(2)->components([
                        TextInput::make('staff_notification_email')->label('Team email for new enquiries and submissions')->email()->maxLength(190),
                        Toggle::make('customer_email_notifications')->label('Email customers about case updates')->helperText('Updates always appear in the customer\'s profile. SMS updates need DLT-registered templates and are not sent.'),
                    ]),
                ]),
                Tab::make('Assessment')->schema([
                    Section::make('Engine assumptions')->description('Stored with every calculation, so changing them never alters earlier snapshots or reports.')->columns(3)->components([
                        TextInput::make('gst_rate_percent')->label('GST on charges and fees (%)')->numeric()->minValue(0)->maxValue(30)->required(),
                        TextInput::make('revolving_minimum_payment_percent')->label('Card minimum payment for FOIR (% of balance)')->numeric()->minValue(0)->maxValue(20)->required(),
                        TextInput::make('default_tenure_months')->label('Assumed tenure when unknown (months)')->numeric()->minValue(6)->maxValue(360)->required(),
                        TextInput::make('rate_mismatch_tolerance_points')->label('Flag rate mismatch above (points)')->numeric()->minValue(0.1)->maxValue(10)->required(),
                        TextInput::make('quotation_validity_days')->label('Typical quotation validity (days, shown to customers)')->numeric()->minValue(1)->maxValue(60)->required(),
                    ]),
                    Section::make('Standard comparison options')->description('Indicative terms compared when a case has no scenarios of its own, and in the public calculator. They are not lender offers.')->components([
                        Repeater::make('standard_scenarios')->hiddenLabel()->columns(5)->reorderable()->components([
                            Select::make('route')->options(FinancingRoute::class)->required(),
                            TextInput::make('label')->required()->maxLength(60),
                            TextInput::make('annual_interest_rate')->label('Rate %')->numeric()->required(),
                            TextInput::make('tenure_months')->label('Months')->numeric()->required(),
                            TextInput::make('processing_fee_percent')->label('Fee %')->numeric()->required(),
                        ]),
                    ]),
                ]),
                Tab::make('Disclaimers and consent')->schema([
                    Section::make('Disclaimers')->components([
                        $text('general_disclaimer', 'General disclaimer'),
                        $text('calculator_disclaimer', 'Calculator disclaimer'),
                        $text('suitability_disclaimer', 'Suitability disclaimer'),
                    ]),
                    Section::make('Consent wording')->description('Pending legal review (docs/debt-consolidation/09-security-compliance.md). Changing any wording starts a new consent version automatically. Use :lender where the lender\'s name goes.')->components([
                        TextInput::make('consent_version')->label('Current consent version')->disabled()->dehydrated(false),
                        $text('consent_assessment', 'Assessment consent'),
                        $text('consent_contact', 'Contact consent'),
                        $text('consent_lender_sharing', 'Lender-sharing consent'),
                        $text('consent_external_analysis', 'External analysis consent (future)'),
                    ]),
                ]),
                Tab::make('हिन्दी')->schema([
                    Section::make('Hindi wording')->description('What Hindi visitors see. Stored consent records always keep the English wording.')->components([
                        $hindiText('general_disclaimer', 'General disclaimer'),
                        $hindiText('calculator_disclaimer', 'Calculator disclaimer'),
                        $hindiText('suitability_disclaimer', 'Suitability disclaimer'),
                        $hindiText('consent_assessment', 'Assessment consent'),
                        $hindiText('consent_contact', 'Contact consent'),
                    ]),
                ]),
            ]),
        ]);
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $hindi = array_filter((array) Arr::pull($state, 'hi', []), fn (mixed $value): bool => filled($value));
        $current = Settings::all();
        $state['standard_scenarios'] = array_values(array_map(fn (array $row): array => [
            ...$row,
            'route' => $row['route'] instanceof FinancingRoute ? $row['route']->value : $row['route'],
            'annual_interest_rate' => (float) $row['annual_interest_rate'],
            'tenure_months' => (int) $row['tenure_months'],
            'processing_fee_percent' => (float) $row['processing_fee_percent'],
        ], (array) ($state['standard_scenarios'] ?? [])));

        $consentChanged = collect(self::CONSENT_KEYS)->contains(fn (string $key): bool => trim((string) ($state[$key] ?? '')) !== trim((string) ($current[$key] ?? '')));
        $state['consent_version'] = $consentChanged ? (string) ((int) $current['consent_version'] + 1) : $current['consent_version'];

        Setting::set(Settings::KEY, $state);
        Setting::set(Settings::KEY.'_hi', $hindi);

        $this->mount();

        Notification::make()->title('Debt Consolidation settings saved')->body($consentChanged ? 'Consent wording changed: new consents are recorded as version '.$state['consent_version'].'.' : null)->success()->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [Action::make('save')->label('Save')->action('save')];
    }
}
