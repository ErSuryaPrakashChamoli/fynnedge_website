<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Modules\CreditBureau\Services\CrifSettings as Settings;
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
 * CRIF report business rules, stored in the shared Setting table. The Surepass
 * bearer token is deliberately NOT editable or visible here — it lives in the
 * server environment (SUREPASS_BEARER_TOKEN); this page only says whether it is
 * set. Who may fetch fresh reports, reconcile or see raw JSON is managed per
 * role under Access Control → Roles (CrifReport permissions).
 */
class CrifSettings extends Page
{
    protected string $view = 'filament.pages.crif-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Credit Bureau';

    protected static ?string $navigationLabel = 'CRIF Settings';

    protected static ?string $title = 'CRIF Settings';

    protected static ?int $navigationSort = 3;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:CrifSettings');
    }

    public function mount(): void
    {
        $this->form->fill([
            Settings::ENABLED => Settings::enabled(),
            Settings::PUBLIC_SCORE_ENABLED => Settings::publicScoreEnabled(),
            Settings::REUSE_DAYS => Settings::reuseDays(),
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
                        ? 'The token is read from the server environment and is never shown here.'
                        : 'Set SUREPASS_BEARER_TOKEN in the server environment (not in this panel), then clear the config cache. Until then no CRIF request can be sent.')
                    ->status(Settings::isConfigured() ? 'success' : 'warning'),
                Section::make('Integration')
                    ->components([
                        Toggle::make(Settings::ENABLED)
                            ->label('CRIF report requests enabled')
                            ->helperText('Off stops every new paid request (existing reports stay viewable). Requests already queued are failed as "not configured" without calling Surepass.'),
                        Toggle::make(Settings::PUBLIC_SCORE_ENABLED)
                            ->label('Free CRIF Score page uses real scores')
                            ->helperText('The public /credit-score/crif page then shows a real CRIF score from Surepass instead of a demo one. Each NEW visitor check is a paid call (after OTP and consent); the same PAN and mobile checking again within the reuse period sees the stored score at no cost. Off keeps the demo score. The other bureau pages stay demo either way.'),
                    ]),
                Section::make('Duplicate prevention and consent')
                    ->columns(2)
                    ->components([
                        TextInput::make(Settings::REUSE_DAYS)
                            ->label('Reuse period (days)')
                            ->helperText('A successful report this recent is shown instead of paying for a new one. Fetching a fresh report inside this period needs the FetchFresh permission and a reason.')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(365)
                            ->required(),
                        TextInput::make(Settings::CONSENT_VALIDITY_DAYS)
                            ->label('Consent validity (days)')
                            ->helperText('A recorded consent older than this cannot authorise a new request.')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(365)
                            ->required(),
                    ]),
                Section::make('Retention and alerts')
                    ->columns(2)
                    ->components([
                        TextInput::make(Settings::RETENTION_DAYS)
                            ->label('Retention period (days)')
                            ->helperText('After this, report contents, the PDF and the customer\'s PAN, name and mobile are erased by the daily crif:enforce-retention job. Never shorter than the reuse period.')
                            ->integer()
                            ->minValue(30)
                            ->maxValue(3650)
                            ->required(),
                        TextInput::make(Settings::ALERT_EMAIL)
                            ->label('Operational alert email')
                            ->helperText('Told about requests needing reconciliation and token/balance failures. Leave blank for none.')
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

        Notification::make()->title('CRIF settings saved')->success()->send();
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
