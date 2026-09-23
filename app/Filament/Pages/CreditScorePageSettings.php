<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Modules\CreditScore\Enums\BureauName;
use App\Support\Pages\CreditScorePageContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Livewire\Attributes\Url;

/**
 * The copy on the public /credit-score/{bureau} pages. Each bureau page is
 * edited and saved on its own (`?bureau=experian` picks which), through
 * CreditScorePageContent — which is also where the defaults live, so this form
 * opens showing exactly what that page currently says.
 *
 * The check form's own steps (OTP, details, result) are not edited here.
 */
class CreditScorePageSettings extends Page
{
    protected string $view = 'filament.pages.credit-score-page-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|\UnitEnum|null $navigationGroup = 'Website Settings';

    protected static ?string $navigationLabel = 'Credit Score Page';

    protected static ?string $title = 'Credit Score Page';

    /**
     * Which bureau page is being edited — a BureauName value.
     */
    #[Url]
    public string $bureau = 'cibil';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:CreditScorePageSettings');
    }

    public function mount(): void
    {
        abort_if(BureauName::tryFrom($this->bureau) === null, 404);

        $this->form->fill(CreditScorePageContent::resolve($this->editingBureau()));
    }

    public function getTitle(): string
    {
        return 'Credit Score Page — '.$this->editingBureau()->getLabel();
    }

    public function editingBureau(): BureauName
    {
        return BureauName::from($this->bureau);
    }

    public function form(Schema $schema): Schema
    {
        $defaults = CreditScorePageContent::defaults();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Page heading')
                    ->description(fn (): string => 'Only the '.$this->editingBureau()->getLabel().' page uses this wording — switch bureau at the top to edit another. {bureau} is replaced with the bureau name. Leave a field blank to use its default wording.')
                    ->components([
                        TextInput::make('badge')
                            ->label('Badge')
                            ->maxLength(60)
                            ->placeholder($defaults['badge']),
                        TextInput::make('heading')
                            ->label('Headline')
                            ->maxLength(100)
                            ->placeholder($defaults['heading']),
                        Textarea::make('description')
                            ->label('Introduction')
                            ->rows(3)
                            ->maxLength(300)
                            ->placeholder($defaults['description']),
                    ]),

                Section::make('Benefits')
                    ->description('The ticked points under the introduction. Remove them all to hide this block.')
                    ->components([
                        TextInput::make('benefits_heading')
                            ->label('Heading')
                            ->maxLength(60)
                            ->placeholder($defaults['benefits_heading']),
                        Repeater::make('benefits')
                            ->label('Points')
                            ->schema([
                                TextInput::make('text')
                                    ->label('Point')
                                    ->required()
                                    ->maxLength(100),
                            ])
                            ->maxItems(6)
                            ->defaultItems(0)
                            ->addActionLabel('Add point'),
                    ]),

                Section::make('Highlights strip')
                    ->description('The short figures along the bottom, e.g. "Free — No cost, no hidden charges". Up to three; remove them all to hide the strip.')
                    ->components([
                        Repeater::make('stats')
                            ->hiddenLabel()
                            ->schema([
                                TextInput::make('value')
                                    ->label('Highlight')
                                    ->required()
                                    ->maxLength(20),
                                TextInput::make('label')
                                    ->label('Caption')
                                    ->maxLength(60),
                            ])
                            ->columns(2)
                            ->maxItems(3)
                            ->defaultItems(0)
                            ->addActionLabel('Add highlight'),
                    ]),

                Section::make('Search engines')
                    ->description('The browser tab title and description. {bureau} works here too.')
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
        Setting::set(CreditScorePageContent::settingKey($this->editingBureau()), array_map(
            fn (mixed $value): mixed => is_array($value) ? array_values($value) : $value,
            $this->form->getState(),
        ));

        Notification::make()->title($this->editingBureau()->getLabel().' score page saved')->success()->send();
    }

    public function resetToDefaults(): void
    {
        // An empty list, not null: null would fall back to the legacy shared copy.
        Setting::set(CreditScorePageContent::settingKey($this->editingBureau()), []);

        $this->form->fill(CreditScorePageContent::resolve($this->editingBureau()));

        Notification::make()->title($this->editingBureau()->getLabel().' score page reset to default wording')->success()->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            ActionGroup::make(array_map(
                fn (BureauName $bureau): Action => Action::make('edit_'.$bureau->value)
                    ->label($bureau->getLabel())
                    ->url(static::getUrl(['bureau' => $bureau->value])),
                BureauName::cases(),
            ))
                ->label('Bureau: '.$this->editingBureau()->getLabel())
                ->icon(Heroicon::OutlinedChevronDown)
                ->button()
                ->color('gray'),
            Action::make('view')
                ->label('View page')
                ->color('gray')
                ->url(route('credit-score.show', ['bureau' => $this->editingBureau()]), shouldOpenInNewTab: true),
            Action::make('reset')
                ->label('Reset to defaults')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Every field on this bureau page goes back to the built-in wording. The other bureau pages are not changed. This cannot be undone.')
                ->action('resetToDefaults'),
            Action::make('save')
                ->label('Save')
                ->action('save'),
        ];
    }
}
