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
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * CIBIL business rules and the public CIBIL page's switch, stored in the
 * shared Setting table and independent of the other bureaus'. The Surepass
 * token is deliberately NOT editable or visible here — it lives in the server
 * environment (SUREPASS_CIBIL_TOKEN, with no fallback to another bureau's
 * token); this page only says whether it is set and can run the free access
 * check. Which source answers the public page (the CIBIL bureau, or Experian
 * standing in) follows the server: CIBIL once SUREPASS_CIBIL_TOKEN is set,
 * unless CIBIL_PUBLIC_SCORE_SOURCE forces one.
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
            Settings::SCORE_REUSE_DAYS => Settings::scoreReuseDays(),
            Settings::REPORT_REUSE_DAYS => Settings::reportReuseDays(),
            Settings::CONSENT_VALIDITY_DAYS => Settings::consentValidityDays(),
            Settings::RETENTION_DAYS => Settings::retentionDays(),
            Settings::ALERT_EMAIL => Settings::alertEmail(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $source = Settings::sourceLabel();
        $forcedSource = config('services.cibil.public_score_source');

        // Worded from the same token check as the callout above, so the two can
        // never disagree: a configured token with Experian still answering the
        // page can only mean CIBIL_PUBLIC_SCORE_SOURCE forces it.
        $publicPage = match (true) {
            $source === null => 'CIBIL_PUBLIC_SCORE_SOURCE names no known source, so the page shows demo scores.',
            ! Settings::isStandIn() => 'Answered by the CIBIL bureau through Surepass; the visitor is asked their gender, which CIBIL requires.',
            Settings::isConfigured() => 'The CIBIL API token is configured, but CIBIL_PUBLIC_SCORE_SOURCE='.$forcedSource.' on the server keeps this page on '.$source.' scores (shown to the visitor as '.$source.' scores, never as CIBIL). Remove CIBIL_PUBLIC_SCORE_SOURCE, or set it to "cibil", and clear the config cache to answer the page with the CIBIL bureau.',
            filled($forcedSource) => 'The CIBIL API token is not configured, and CIBIL_PUBLIC_SCORE_SOURCE='.$forcedSource.' on the server keeps this page on '.$source.' scores (shown to the visitor as '.$source.' scores, never as CIBIL). To use the CIBIL bureau, set SUREPASS_CIBIL_TOKEN and remove CIBIL_PUBLIC_SCORE_SOURCE.',
            default => 'The CIBIL API token is not configured, so the page uses '.$source.' scores and tells the visitor they are '.$source.' scores, never CIBIL scores. It switches to the CIBIL bureau by itself once SUREPASS_CIBIL_TOKEN is set on the server.',
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
                            ->helperText('Off stops every new paid CIBIL request from staff, the API and other modules. Existing results stay viewable; queued requests fail as "not configured" without calling Surepass. Only one of this and "Free CIBIL Score page shows real scores" can be on: turning this on turns that off.')
                            ->live()
                            ->afterStateUpdated(function (bool $state, Set $set): void {
                                if ($state) {
                                    $set(Settings::PUBLIC_SCORE_ENABLED, false);
                                }
                            }),
                    ]),
                Section::make('Free CIBIL Score page')
                    ->description($source === null
                        ? $publicPage
                        : $publicPage.' '.(Settings::publicSourceConfigured()
                            ? 'The '.$source.' token is configured on this server.'
                            : 'The '.$source.' token is NOT configured on this server, so the page shows demo scores until it is.'))
                    ->components([
                        Toggle::make(Settings::PUBLIC_SCORE_ENABLED)
                            ->label('Free CIBIL Score page shows real scores')
                            ->helperText('Off: a clearly labelled demo score. On: the visitor\'s real '.($source ?? 'credit').' score. Each NEW visitor check is a paid Surepass call; the same PAN and mobile checking again within '.Settings::publicScoreReuseDays().' days sees the stored score at no cost. Independent of the other bureaus\' pages. Only one of this and "CIBIL requests enabled" can be on: turning this on turns that off.')
                            ->live()
                            ->afterStateUpdated(function (bool $state, Set $set): void {
                                if ($state) {
                                    $set(Settings::ENABLED, false);
                                }
                            }),
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

    /**
     * The two switches are exclusive (user decision, 2026-10-17): the toggles
     * turn each other off as they change, and this refuses a save with both on —
     * e.g. settings stored before the rule, re-saved unchanged.
     */
    public function save(): void
    {
        $state = $this->form->getState();

        if (($state[Settings::ENABLED] ?? false) && ($state[Settings::PUBLIC_SCORE_ENABLED] ?? false)) {
            throw ValidationException::withMessages([
                'data.'.Settings::PUBLIC_SCORE_ENABLED => 'Turn on either "CIBIL requests enabled" or "Free CIBIL Score page shows real scores" — not both.',
            ]);
        }

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
