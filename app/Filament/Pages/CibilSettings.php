<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Modules\Cibil\Services\CibilRoles;
use App\Modules\Cibil\Services\CibilSettings as Settings;
use App\Modules\Experian\Services\ExperianSettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The public Free CIBIL Score page's one switch. There is no CIBIL API yet, so
 * "real scores" here means the stand-in source (Experian through Surepass),
 * clearly labelled as Experian on the page. Which source is used is server
 * configuration (CIBIL_PUBLIC_SCORE_SOURCE), not an admin choice.
 */
class CibilSettings extends Page
{
    protected string $view = 'filament.pages.cibil-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'CIBIL';

    protected static ?string $navigationLabel = 'CIBIL Settings';

    protected static ?string $title = 'CIBIL Settings';

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
            Settings::PUBLIC_SCORE_ENABLED => Settings::publicScoreEnabled(),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $source = Settings::sourceLabel();

        return $schema
            ->statePath('data')
            ->components([
                Callout::make($source === null
                    ? 'No score source is configured for the CIBIL page'
                    : 'CIBIL API not connected — the page uses '.$source.' scores')
                    ->description($source === null
                        ? 'CIBIL_PUBLIC_SCORE_SOURCE names no known source, so the page shows demo scores.'
                        : 'FynnEdge has no CIBIL API yet. With real scores on, the Free CIBIL Score page gets the visitor\'s real '.$source.' score through Surepass and tells them it is an '.$source.' score — never a CIBIL score. '.(Settings::isConfigured()
                            ? 'The '.$source.' token is configured on this server.'
                            : 'The '.$source.' token is NOT configured on this server, so the page shows demo scores until it is.').' The source is set by CIBIL_PUBLIC_SCORE_SOURCE in the server environment; when a CIBIL API is added, it is switched there.')
                    ->status($source !== null && Settings::isConfigured() ? 'info' : 'warning'),
                Section::make('Free CIBIL Score page')
                    ->components([
                        Toggle::make(Settings::PUBLIC_SCORE_ENABLED)
                            ->label('Free CIBIL Score page shows real scores')
                            ->helperText('Off: a clearly labelled demo score. On: the visitor\'s real '.($source ?? 'stand-in').' score. Each NEW visitor check is a paid Surepass call; the same PAN and mobile checking again within '.ExperianSettings::scoreReuseDays().' days sees the stored score at no cost. Independent of the Experian page\'s own switch.'),
                    ]),
            ]);
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
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
            Action::make('save')->label('Save')->action('save'),
        ];
    }
}
