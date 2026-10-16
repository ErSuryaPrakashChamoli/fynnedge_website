<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Modules\Experian\Services\ExperianSettings as Settings;
use App\Modules\Experian\Services\SurepassExperianService;
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
 * Experian business rules, stored in the shared Setting table and independent
 * of CRIF's and Equifax's. The Surepass bearer token is deliberately NOT
 * editable or visible here — it lives in the server environment
 * (SUREPASS_EXPERIAN_TOKEN, else the shared SUREPASS_BEARER_TOKEN); this page
 * only says whether and where it is set, and can run the free access check.
 * Who may fetch fresh results, reconcile, see raw JSON or download PDFs is
 * managed per role under Access Control → Roles (ExperianCreditRequest
 * permissions).
 */
class ExperianSettings extends Page
{
    protected string $view = 'filament.pages.experian-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Experian';

    protected static ?string $navigationLabel = 'Experian Settings';

    protected static ?string $title = 'Experian Settings';

    protected static ?int $navigationSort = 7;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:ExperianSettings');
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
                        ? 'The token is read from the server environment ('.Settings::tokenSource().') and is never shown here. Endpoints: '.app(SurepassExperianService::class)->baseUrl().'/'.SurepassExperianService::SCORE_PATH.', /'.SurepassExperianService::REPORT_PATH.' and /'.SurepassExperianService::PDF_PATH.'. Use "Check Surepass access" (free) to see which of them your plan includes.'
                        : 'Set SUREPASS_EXPERIAN_TOKEN (or the shared SUREPASS_BEARER_TOKEN) in the server environment, not in this panel, then clear the config cache. Until then no Experian request can be sent.')
                    ->status(Settings::isConfigured() ? 'success' : 'warning'),
                Section::make('Integration')
                    ->components([
                        Toggle::make(Settings::ENABLED)
                            ->label('Experian requests enabled (admin and internal API)')
                            ->helperText('Off stops every new paid request from staff, the API and other modules. Existing results stay viewable; queued requests fail as "not configured" without calling Surepass.'),
                        Toggle::make(Settings::PUBLIC_SCORE_ENABLED)
                            ->label('Free Experian Score page uses real scores')
                            ->helperText('The public /credit-score/experian page then shows a real Experian score from Surepass instead of a demo one. Each NEW visitor check is a paid call; the same PAN and mobile checking again within the score reuse period sees the stored score at no cost.'),
                    ]),
                Section::make('Duplicate prevention and consent')
                    ->columns(3)
                    ->components([
                        TextInput::make(Settings::SCORE_REUSE_DAYS)
                            ->label('Score reuse period (days)')
                            ->helperText('A score (or a full report or PDF) this recent answers a score request without a new pull.')
                            ->integer()->minValue(1)->maxValue(365)->required(),
                        TextInput::make(Settings::REPORT_REUSE_DAYS)
                            ->label('Report reuse period (days)')
                            ->helperText('A full report (or stored PDF) this recent is shown instead of paying for a new one. A fresh pull inside it needs the FetchFresh permission and a reason.')
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
                            ->helperText('After this, results, summaries, PDFs and the customer\'s PAN, name and mobile are erased by the daily experian:enforce-retention job. Never shorter than either reuse period.')
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

        Notification::make()->title('Experian settings saved')->success()->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('checkAccess')
                ->label('Check Surepass access')
                ->icon(Heroicon::OutlinedSignal)
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Sends an EMPTY request to each Experian endpoint. Surepass rejects it at input validation or at the token check, so no PAN is sent, no report is pulled and nothing is charged.')
                ->action(function (SurepassExperianService $surepass): void {
                    $lines = collect($surepass->checkAccess())
                        ->map(fn (array $result, string $path): string => basename($path).': '.match ($result['access']) {
                            'allowed' => 'allowed',
                            'not_enabled' => 'not enabled on your Surepass plan',
                            'token_rejected' => 'token rejected',
                            'not_configured' => 'token not configured',
                            'unreachable' => 'Surepass unreachable from this server',
                            'not_found' => 'endpoint not found',
                            default => 'unexpected answer',
                        }.($result['http_status'] ? ' (HTTP '.$result['http_status'].')' : ''));

                    Notification::make()
                        ->title('Surepass Experian access')
                        ->body($lines->implode("\n"))
                        ->info()
                        ->persistent()
                        ->send();
                }),
            Action::make('save')->label('Save')->action('save'),
        ];
    }
}
