<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Pages\AboutPageContent;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Callout;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

/**
 * The section copy on the public /about page, saved as one Setting through
 * AboutPageContent — which is also where the defaults live, so this form
 * opens showing exactly what the page currently says.
 *
 * The Hindi tab edits the same fields for Hindi visitors, saved beside it as
 * `about_page_hi`. A blank Hindi field shows the built-in Hindi (the English
 * looked up in lang/hi.json), which is what its placeholder previews.
 *
 * The page title, intro and rich-text body are edited on the "about" record
 * under Content → Pages; the founder's name and photo under Settings.
 */
class AboutPageSettings extends Page
{
    protected string $view = 'filament.pages.about-page-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingOffice2;

    protected static string|\UnitEnum|null $navigationGroup = 'Website Settings';

    protected static ?string $navigationLabel = 'About Page';

    protected static ?string $title = 'About Page';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:AboutPageSettings');
    }

    public function mount(): void
    {
        $this->fillForm();
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
                                    ->description('Leave a field blank to use the built-in Hindi shown in grey. A list left empty shows the Hindi of the English list, and a list removed on the English tab stays hidden in Hindi too.')
                                    ->info(),
                                ...$this->wordingSections(hindi: true),
                            ]),
                    ]),
            ]);
    }

    /**
     * The page's fields, once per language. The Hindi copy previews the
     * built-in Hindi of what the page says now, and allows a little more
     * length (Hindi needs more characters than English).
     *
     * @return array<int, Section>
     */
    private function wordingSections(bool $hindi): array
    {
        $defaults = AboutPageContent::defaults();
        $english = AboutPageContent::resolve();
        $placeholder = fn (string $key): string => $hindi ? self::builtInHindi($english[$key]) : $defaults[$key];
        $length = fn (int $english): int => $hindi ? (int) ceil($english * 1.5) : $english;
        $listHelp = fn (string $english): string => $hindi ? 'Leave empty to use the Hindi of the English list.' : $english;

        return [
            Section::make('Founder message')
                ->description($hindi ? null : 'The quote section beside the founder photo. The founder\'s name and photo are set under Settings → Founder message. Leave a field blank to use its default wording.')
                ->columns(2)
                ->components([
                    TextInput::make('founder_heading')
                        ->label('Headline')
                        ->maxLength($length(120))
                        ->placeholder($placeholder('founder_heading')),
                    TextInput::make('founder_heading_accent')
                        ->label('Headline (highlighted part)')
                        ->maxLength($length(80))
                        ->placeholder($placeholder('founder_heading_accent'))
                        ->helperText('Shown in the accent colour at the end of the headline.'),
                    Repeater::make('founder_points')
                        ->label('Points')
                        ->schema([
                            Textarea::make('text')
                                ->label('Point')
                                ->required()
                                ->rows(2)
                                ->maxLength($length(240)),
                        ])
                        ->maxItems(5)
                        ->defaultItems(0)
                        ->addActionLabel('Add point')
                        ->helperText($listHelp('Remove them all to hide the list.'))
                        ->columnSpanFull(),
                    TextInput::make('founder_quote')
                        ->label('Closing quote')
                        ->maxLength($length(120))
                        ->placeholder($placeholder('founder_quote')),
                    TextInput::make('founder_role')
                        ->label('Founder title')
                        ->maxLength($length(60))
                        ->placeholder($placeholder('founder_role'))
                        ->helperText('Shown under the founder\'s name.'),
                ]),

            Section::make('Mission & vision')
                ->description($hindi ? null : 'The two cards below the founder message.')
                ->columns(2)
                ->components([
                    TextInput::make('mission_label')
                        ->label('Mission badge')
                        ->maxLength($length(30))
                        ->placeholder($placeholder('mission_label')),
                    TextInput::make('vision_label')
                        ->label('Vision badge')
                        ->maxLength($length(30))
                        ->placeholder($placeholder('vision_label')),
                    TextInput::make('mission_title')
                        ->label('Mission headline')
                        ->maxLength($length(120))
                        ->placeholder($placeholder('mission_title')),
                    TextInput::make('vision_title')
                        ->label('Vision headline')
                        ->maxLength($length(120))
                        ->placeholder($placeholder('vision_title')),
                    Textarea::make('mission_body')
                        ->label('Mission description')
                        ->rows(3)
                        ->maxLength($length(300))
                        ->placeholder($placeholder('mission_body')),
                    Textarea::make('vision_body')
                        ->label('Vision description')
                        ->rows(3)
                        ->maxLength($length(300))
                        ->placeholder($placeholder('vision_body')),
                ]),

            Section::make('Life at FynnEdge')
                ->description($hindi ? null : 'The team section. Its photo strip comes from Content → Company Photos.')
                ->columns(2)
                ->components([
                    TextInput::make('life_eyebrow')
                        ->label('Small label')
                        ->maxLength($length(40))
                        ->placeholder($placeholder('life_eyebrow')),
                    TextInput::make('life_heading')
                        ->label('Headline')
                        ->maxLength($length(100))
                        ->placeholder($placeholder('life_heading')),
                    Textarea::make('life_description')
                        ->label('Introduction')
                        ->rows(2)
                        ->maxLength($length(300))
                        ->placeholder($placeholder('life_description'))
                        ->columnSpanFull(),
                    Repeater::make('values')
                        ->label('Values')
                        ->schema([
                            TextInput::make('title')
                                ->required()
                                ->maxLength($length(40)),
                            Textarea::make('body')
                                ->label('Description')
                                ->rows(2)
                                ->maxLength($length(200)),
                        ])
                        ->columns(2)
                        ->maxItems(6)
                        ->defaultItems(0)
                        ->addActionLabel('Add value')
                        ->helperText($listHelp('Remove them all to hide the row.'))
                        ->columnSpanFull(),
                ]),

            Section::make('Work with us')
                ->description($hindi ? null : 'The closing banner. Its button always opens the Careers page.')
                ->columns(2)
                ->components([
                    TextInput::make('work_heading')
                        ->label('Headline')
                        ->maxLength($length(60))
                        ->placeholder($placeholder('work_heading')),
                    TextInput::make('work_button_label')
                        ->label('Button label')
                        ->maxLength($length(30))
                        ->placeholder($placeholder('work_button_label')),
                    Textarea::make('work_description')
                        ->label('Description')
                        ->rows(2)
                        ->maxLength($length(300))
                        ->placeholder($placeholder('work_description'))
                        ->columnSpanFull(),
                ]),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $hindi = Arr::pull($state, 'hi', []);

        Setting::set(AboutPageContent::SETTING_KEY, self::plainLists($state));
        Setting::set(AboutPageContent::HINDI_SETTING_KEY, self::plainLists($hindi));

        Notification::make()->title('About page saved')->success()->send();
    }

    public function resetToDefaults(): void
    {
        Setting::set(AboutPageContent::SETTING_KEY, null);
        Setting::set(AboutPageContent::HINDI_SETTING_KEY, null);

        $this->fillForm();

        Notification::make()->title('About page reset to default wording')->success()->send();
    }

    private function fillForm(): void
    {
        $this->form->fill([
            ...AboutPageContent::resolve(),
            'hi' => AboutPageContent::hindi(),
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
     * is blank.
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
            Action::make('view')
                ->label('View page')
                ->color('gray')
                ->url(route('about'), shouldOpenInNewTab: true),
            Action::make('reset')
                ->label('Reset to defaults')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Every field on this page, English and Hindi, goes back to the built-in wording. This cannot be undone.')
                ->action('resetToDefaults'),
            Action::make('save')
                ->label('Save')
                ->action('save'),
        ];
    }
}
