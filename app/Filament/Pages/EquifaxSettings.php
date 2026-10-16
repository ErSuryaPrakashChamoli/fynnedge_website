<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Modules\Equifax\Services\EquifaxSettings as Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Equifax business rules, stored in the shared Setting table and independent
 * of CRIF's. The Surepass bearer token is deliberately NOT editable or visible
 * here — it lives in the server environment (SUREPASS_BEARER_TOKEN, shared with
 * CRIF); this page only says whether it is set. Who may fetch fresh results,
 * reconcile, see raw JSON or download PDFs is managed per role under Access
 * Control → Roles (EquifaxCreditRequest permissions).
 */
class EquifaxSettings extends Page
{
    protected string $view = 'filament.pages.equifax-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Equifax';

    protected static ?string $navigationLabel = 'Equifax Settings';

    protected static ?string $title = 'Equifax Settings';

    protected static ?int $navigationSort = 5;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:EquifaxSettings');
    }

    public function mount(): void
    {
        $this->form->fill([
            Settings::ENABLED => Settings::enabled(),
            Settings::PUBLIC_SCORE_ENABLED => Settings::publicScoreEnabled(),
            Settings::SCORE_REUSE_DAYS => Settings::scoreReuseDays(),
            Settings::REPORT_REUSE_DAYS => Settings::reportReuseDays(),
            Settings::CONSENT_VALIDITY_DAYS => Settings::consentValidityDays(),
            Settings::RETENTION_DAYS => Settings::retentionDays(),
            Settings::ALERT_EMAIL => Settings::alertEmail(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Callout::make(Settings::isConfigured() ? 'Surepass API token is configured on this server' : 'Surepass API token is NOT configured')
                    ->description(Settings::isConfigured()
                        ? 'The token is read from the server environment ('.Settings::tokenSource().') and is never shown here. Run `php artisan equifax:check-access` to see which Equifax APIs it may use.'
                        : 'Set SUREPASS_EQUIFAX_TOKEN (or the SUREPASS_BEARER_TOKEN shared with CRIF) in the server environment, not in this panel, then clear the config cache. Until then no Equifax request can be sent.')
                    ->status(Settings::isConfigured() ? 'success' : 'warning'),
                Section::make('Integration')
                    ->components([
                        Toggle::make(Settings::ENABLED)
                            ->label('Equifax requests enabled (admin and internal API)')
                            ->helperText('Off stops every new paid request from staff, the API and other modules. Existing results stay viewable; queued requests fail as "not configured" without calling Surepass.'),
                        Toggle::make(Settings::PUBLIC_SCORE_ENABLED)
                            ->label('Free Equifax Score page uses real scores')
                            ->helperText('The public /credit-score/equifax page then shows a real Equifax score from Surepass instead of a demo one (it also asks the visitor\'s gender, which Equifax requires). Each NEW visitor check is a paid call; the same PAN and mobile checking again within the score reuse period sees the stored score at no cost.'),
                    ]),
                Section::make('Duplicate prevention and consent')
                    ->columns(3)
                    ->components([
                        TextInput::make(Settings::SCORE_REUSE_DAYS)
                            ->label('Score reuse period (days)')
                            ->helperText('A score (or a full report) this recent answers a score request without a new pull.')
                            ->integer()->minValue(1)->maxValue(365)->required(),
                        TextInput::make(Settings::REPORT_REUSE_DAYS)
                            ->label('Report reuse period (days)')
                            ->helperText('A full report this recent is shown instead of paying for a new one. A fresh pull inside it needs the FetchFresh permission and a reason.')
                            ->integer()->minValue(1)->maxValue(365)->required(),
                        TextInput::make(Settings::CONSENT_VALIDITY_DAYS)
                            ->label('Consent validity (days)')
                            ->helperText('A recorded consent older than this cannot authorise a new request.')
                            ->integer()->minValue(1)->maxValue(365)->required(),
                    ]),
                Section::make('Retention and alerts')
                    ->columns(2)
                    ->components([
                        TextInput::make(Settings::RETENTION_DAYS)
                            ->label('Retention period (days)')
                            ->helperText('After this, results, summaries, PDFs and the customer\'s PAN, name and mobile are erased by the daily equifax:enforce-retention job. Never shorter than either reuse period.')
                            ->integer()->minValue(30)->maxValue(3650)->required(),
                        TextInput::make(Settings::ALERT_EMAIL)
                            ->label('Operational alert email')
                            ->helperText('Told about requests needing reconciliation and token / plan / balance failures. Leave blank for none.')
                            ->email()
                            ->maxLength(190),
                    ]),
            ]);
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()->title('Equifax settings saved')->success()->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')->label('Save')->action('save'),
        ];
    }
}
