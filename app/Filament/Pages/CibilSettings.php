<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Modules\Cibil\Services\CibilRoles;
use App\Modules\Cibil\Services\CibilSettings as Settings;
use App\Modules\Cibil\Services\SurepassCibilService;
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
 * CIBIL business rules and the public Get CIBIL Score page's two switches,
 * stored in the shared Setting table and independent of the other bureaus'.
 * "CIBIL requests enabled" (staff, the internal and external APIs) and the
 * page's switches are independent of each other: either can be on without
 * touching the other.
 *
 * The Surepass tokens are deliberately NOT editable or visible here — they
 * live in the server environment (SUREPASS_CIBIL_TOKEN, with no fallback to
 * another bureau's token; Experian's own); this page only says whether the
 * one the page needs is set, and can run the free access check.
 */
class CibilSettings extends Page
{
    protected string $view = 'filament.pages.cibil-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'CIBIL';

    protected static ?string $navigationLabel = 'CIBIL Settings';

    protected static ?string $title = 'CIBIL Settings';

    protected static ?int $navigationSort = 7;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can(CibilRoles::SETTINGS);
    }

    public function mount(): void
    {
        $this->form->fill([
            Settings::ENABLED => Settings::enabled(),
            Settings::PUBLIC_SCORE_ENABLED => Settings::publicScoreEnabled(),
            Settings::PUBLIC_SCORE_USE_EXPERIAN => Settings::publicScoreUsesExperian(),
            Settings::SCORE_REUSE_DAYS => Settings::scoreReuseDays(),
            Settings::REPORT_REUSE_DAYS => Settings::reportReuseDays(),
            Settings::CONSENT_VALIDITY_DAYS => Settings::consentValidityDays(),
            Settings::RETENTION_DAYS => Settings::retentionDays(),
            Settings::ALERT_EMAIL => Settings::alertEmail(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        // What visitors get right now, from the saved switches and the same token
        // checks as the callout above, so the two can never disagree.
        $publicPage = match (true) {
            ! Settings::publicScoreEnabled() => 'Visitors now see a labelled demo score; no bureau is called.',
            ! Settings::publicSourceConfigured() => 'The '.Settings::sourceLabel().' token is NOT configured on this server, so visitors still see a labelled demo score until it is set ('.(Settings::isStandIn() ? 'SUREPASS_EXPERIAN_TOKEN or SUREPASS_BEARER_TOKEN' : 'SUREPASS_CIBIL_TOKEN').').',
            Settings::isStandIn() => 'Visitors now get their real Experian score through Surepass Experian, labelled and stored as an Experian score — never as CIBIL.',
            default => 'Visitors now get their real CIBIL score through Surepass CIBIL; they are asked their gender, which CIBIL requires.',
        };

        return $schema
            ->statePath('data')
            ->components([
                Callout::make(Settings::isConfigured() ? 'CIBIL API token is configured on this server' : 'CIBIL API token is NOT configured')
                    ->description(Settings::isConfigured()
                        ? 'The token is read from the server environment (SUREPASS_CIBIL_TOKEN) and is never shown here. Endpoints: '.app(SurepassCibilService::class)->baseUrl().'/'.SurepassCibilService::SCORE_PATH.', /'.SurepassCibilService::REPORT_PATH.' and /'.SurepassCibilService::PDF_PATH.'. Use "Check Surepass access" (free) to see which of them your plan includes.'
                        : 'Set SUREPASS_CIBIL_TOKEN in the server environment (not in this panel), then clear the config cache. CIBIL never falls back to the shared or another bureau\'s token, so until it is set no CIBIL request can be sent.')
                    ->status(Settings::isConfigured() ? 'success' : 'warning'),
                Section::make('Integration')
                    ->components([
                        Toggle::make(Settings::ENABLED)
                            ->label('CIBIL requests enabled (admin and internal API)')
                            ->helperText('Off stops every new paid CIBIL request from staff, the internal and external (LMS) APIs and other modules. Existing results stay viewable; queued requests fail as "not configured" without calling Surepass. Independent of the Get CIBIL Score page below.'),
                    ]),
                Section::make('Get CIBIL Score page')
                    ->description($publicPage)
                    ->components([
                        Toggle::make(Settings::PUBLIC_SCORE_ENABLED)
                            ->label('Real CIBIL score enabled')
                            ->helperText('Off: visitors see a clearly labelled demo score and no bureau is called. On: visitors get their real score from the bureau chosen below — each new check is a paid Surepass call; the same PAN and mobile checking again within the bureau\'s score reuse period sees the stored score at no cost.'),
                        Toggle::make(Settings::PUBLIC_SCORE_USE_EXPERIAN)
                            ->label('Fetch Experian instead of CIBIL')
                            ->helperText('Off: the real score comes from CIBIL. On: from Experian, shown and stored as an Experian score. If the chosen bureau fails, the visitor is told so — the other bureau is never asked instead. Has no effect while "Real CIBIL score enabled" is off.'),
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
                            ->helperText('After this, results, summaries, PDFs and the customer\'s PAN, name and mobile are erased by the daily cibil:enforce-retention job. Never shorter than either reuse period.')
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
        $state = $this->form->getState();

        foreach ($state as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()->title('CIBIL settings saved')->success()->send();
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
                ->modalDescription('Sends an EMPTY request to each CIBIL endpoint. Surepass rejects it at input validation or at the token check, so no PAN is sent, no report is pulled and nothing is charged.')
                ->action(function (SurepassCibilService $surepass): void {
                    $lines = collect($surepass->checkAccess())
                        ->map(fn (array $result, string $path): string => basename($path).': '.match ($result['access']) {
                            'allowed' => 'allowed',
                            'not_enabled' => 'not enabled on your Surepass plan',
                            'token_rejected' => 'token rejected',
                            'not_configured' => 'SUREPASS_CIBIL_TOKEN not set',
                            'unreachable' => 'Surepass unreachable from this server',
                            'not_found' => 'endpoint not found',
                            default => 'unexpected answer',
                        }.($result['http_status'] ? ' (HTTP '.$result['http_status'].')' : ''));

                    Notification::make()
                        ->title('Surepass CIBIL access')
                        ->body($lines->implode("\n"))
                        ->info()
                        ->persistent()
                        ->send();
                }),
            Action::make('save')->label('Save')->action('save'),
        ];
    }
}
