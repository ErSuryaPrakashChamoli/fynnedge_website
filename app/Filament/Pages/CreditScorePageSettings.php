<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Modules\CreditScore\Enums\BureauName;
use App\Support\Pages\CreditScoreIndexing;
use App\Support\Pages\CreditScorePageContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Livewire\Attributes\Url;

/**
 * The copy on the public /credit-score/{bureau} pages. Each bureau page is
 * edited and saved on its own (`?bureau=experian` picks which), through
 * CreditScorePageContent — which is also where the defaults live, so this form
 * opens showing exactly what that page currently says.
 *
 * The Hindi tab edits the same wording for Hindi visitors, saved beside it as
 * `credit_score_page.{bureau}_hi`. A blank Hindi field shows the built-in
 * Hindi (the English looked up in lang/hi.json), which its placeholder previews.
 *
 * Whether search engines may index the page is saved separately through
 * CreditScoreIndexing, so resetting the wording never changes it. The page's
 * "About" section is edited under Content → Credit Score Pages.
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

        $this->fillForm();
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
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make('Language')
                    ->columnSpanFull()
                    ->tabs([
                        Tab::make('English')
                            ->components($this->wordingSections(hindi: false)),
                        Tab::make('हिन्दी (Hindi)')
                            ->statePath('hi')
                            ->components([
                                Callout::make('What Hindi visitors read')
                                    ->description('Leave a field blank to use the built-in Hindi shown in grey. {bureau} works here too. A list left empty shows the Hindi of the English list, and a list removed on the English tab stays hidden in Hindi too.')
                                    ->info(),
                                ...$this->wordingSections(hindi: true),
                            ]),
                    ]),
            ]);
    }

    /**
     * The page's fields, once per language. The Hindi copy previews the
     * built-in Hindi of what the page says now and allows a little more length
     * (Hindi needs more characters than English); search engine indexing is
     * shared by both languages, so only the English tab has it.
     *
     * @return array<int, Section>
     */
    private function wordingSections(bool $hindi): array
    {
        $defaults = CreditScorePageContent::defaults();
        $english = CreditScorePageContent::resolve($this->editingBureau());
        $placeholder = fn (string $key): string => $hindi ? self::builtInHindi($english[$key]) : $defaults[$key];
        $length = fn (int $english): int => $hindi ? (int) ceil($english * 1.5) : $english;

        return [
            Section::make('Page heading')
                ->description(fn (): ?string => $hindi ? null : 'Only the '.$this->editingBureau()->getLabel().' page uses this wording — switch bureau at the top to edit another. {bureau} is replaced with the bureau name. Leave a field blank to use its default wording.')
                ->components([
                    TextInput::make('badge')
                        ->label('Badge')
                        ->maxLength($length(60))
                        ->placeholder($placeholder('badge')),
                    TextInput::make('heading')
                        ->label('Headline')
                        ->maxLength($length(100))
                        ->placeholder($placeholder('heading')),
                    Textarea::make('description')
                        ->label('Introduction')
                        ->rows(3)
                        ->maxLength($length(300))
                        ->placeholder($placeholder('description')),
                ]),

            Section::make('Benefits')
                ->description($hindi ? 'Leave the points empty to use the Hindi of the English points.' : 'The ticked points under the introduction. Remove them all to hide this block.')
                ->components([
                    TextInput::make('benefits_heading')
                        ->label('Heading')
                        ->maxLength($length(60))
                        ->placeholder($placeholder('benefits_heading')),
                    Repeater::make('benefits')
                        ->label('Points')
                        ->schema([
                            TextInput::make('text')
                                ->label('Point')
                                ->required()
                                ->maxLength($length(100)),
                        ])
                        ->maxItems(6)
                        ->defaultItems(0)
                        ->addActionLabel('Add point'),
                ]),

            Section::make('Highlights strip')
                ->description($hindi ? 'Leave empty to use the Hindi of the English highlights.' : 'The short figures along the bottom, e.g. "Free — No cost, no hidden charges". Up to three; remove them all to hide the strip.')
                ->components([
                    Repeater::make('stats')
                        ->hiddenLabel()
                        ->schema([
                            TextInput::make('value')
                                ->label('Highlight')
                                ->required()
                                ->maxLength($length(20)),
                            TextInput::make('label')
                                ->label('Caption')
                                ->maxLength($length(60)),
                        ])
                        ->columns(2)
                        ->maxItems(3)
                        ->defaultItems(0)
                        ->addActionLabel('Add highlight'),
                ]),

            Section::make('Search engines')
                ->description('The browser tab title and description. {bureau} works here too.')
                ->columns(2)
                ->components(array_values(array_filter([
                    $hindi ? null : Toggle::make('indexable')
                        ->label('Allow search engines to index this page')
                        ->helperText('Off by default: the page sends "noindex, nofollow", robots.txt blocks it and the sitemap leaves it out. Turning it on changes only this bureau page. The sitewide switch under Website Settings → SEO & Tracking still overrides this.')
                        ->columnSpanFull(),
                    TextInput::make('meta_title')
                        ->label('Page title')
                        ->maxLength($length(70))
                        ->placeholder($placeholder('meta_title')),
                    Textarea::make('meta_description')
                        ->label('Meta description')
                        ->rows(2)
                        ->maxLength($length(160))
                        ->placeholder($placeholder('meta_description')),
                ]))),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $hindi = Arr::pull($state, 'hi', []);

        CreditScoreIndexing::setIndexable($this->editingBureau(), (bool) Arr::pull($state, 'indexable', false));

        Setting::set(CreditScorePageContent::settingKey($this->editingBureau()), self::plainLists($state));
        Setting::set(CreditScorePageContent::hindiSettingKey($this->editingBureau()), self::plainLists($hindi));

        Notification::make()->title($this->editingBureau()->getLabel().' score page saved')->success()->send();
    }

    public function resetToDefaults(): void
    {
        // An empty list, not null: null would fall back to the legacy shared copy.
        Setting::set(CreditScorePageContent::settingKey($this->editingBureau()), []);
        Setting::set(CreditScorePageContent::hindiSettingKey($this->editingBureau()), null);

        $this->fillForm();

        Notification::make()->title($this->editingBureau()->getLabel().' score page reset to default wording')->success()->send();
    }

    private function fillForm(): void
    {
        $this->form->fill([
            ...CreditScorePageContent::resolve($this->editingBureau()),
            'indexable' => CreditScoreIndexing::isIndexable($this->editingBureau()),
            'hi' => CreditScorePageContent::hindi($this->editingBureau()),
        ]);
    }

    /**
     * Repeater state is keyed by item UUIDs; store plain lists.
     *
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private static function plainLists(array $state): array
    {
        return array_map(fn (mixed $value): mixed => is_array($value) ? array_values($value) : $value, $state);
    }

    /**
     * What a Hindi visitor reads for this English text while the Hindi field
     * is blank (`{bureau}` still in place).
     */
    private static function builtInHindi(string $english): string
    {
        $hindi = __($english, [], 'hi');

        return is_string($hindi) ? $hindi : $english;
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
                ->modalDescription('Every wording field on this bureau page, English and Hindi, goes back to the built-in text. Search engine indexing and the other bureau pages are not changed. This cannot be undone.')
                ->action('resetToDefaults'),
            Action::make('save')
                ->label('Save')
                ->action('save'),
        ];
    }
}
