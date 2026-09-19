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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * The section copy on the public /about page, saved as one Setting through
 * AboutPageContent — which is also where the defaults live, so this form
 * opens showing exactly what the page currently says.
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
        $this->form->fill(AboutPageContent::resolve());
    }

    public function form(Schema $schema): Schema
    {
        $defaults = AboutPageContent::defaults();

        return $schema
            ->statePath('data')
            ->components([
                Section::make('Founder message')
                    ->description('The quote section beside the founder photo. The founder\'s name and photo are set under Settings → Founder message. Leave a field blank to use its default wording.')
                    ->columns(2)
                    ->components([
                        TextInput::make('founder_heading')
                            ->label('Headline')
                            ->maxLength(120)
                            ->placeholder($defaults['founder_heading']),
                        TextInput::make('founder_heading_accent')
                            ->label('Headline (highlighted part)')
                            ->maxLength(80)
                            ->placeholder($defaults['founder_heading_accent'])
                            ->helperText('Shown in the accent colour at the end of the headline.'),
                        Repeater::make('founder_points')
                            ->label('Points')
                            ->schema([
                                Textarea::make('text')
                                    ->label('Point')
                                    ->required()
                                    ->rows(2)
                                    ->maxLength(240),
                            ])
                            ->maxItems(5)
                            ->defaultItems(0)
                            ->addActionLabel('Add point')
                            ->helperText('Remove them all to hide the list.')
                            ->columnSpanFull(),
                        TextInput::make('founder_quote')
                            ->label('Closing quote')
                            ->maxLength(120)
                            ->placeholder($defaults['founder_quote']),
                        TextInput::make('founder_role')
                            ->label('Founder title')
                            ->maxLength(60)
                            ->placeholder($defaults['founder_role'])
                            ->helperText('Shown under the founder\'s name.'),
                    ]),

                Section::make('Mission & vision')
                    ->description('The two cards below the founder message.')
                    ->columns(2)
                    ->components([
                        TextInput::make('mission_label')
                            ->label('Mission badge')
                            ->maxLength(30)
                            ->placeholder($defaults['mission_label']),
                        TextInput::make('vision_label')
                            ->label('Vision badge')
                            ->maxLength(30)
                            ->placeholder($defaults['vision_label']),
                        TextInput::make('mission_title')
                            ->label('Mission headline')
                            ->maxLength(120)
                            ->placeholder($defaults['mission_title']),
                        TextInput::make('vision_title')
                            ->label('Vision headline')
                            ->maxLength(120)
                            ->placeholder($defaults['vision_title']),
                        Textarea::make('mission_body')
                            ->label('Mission description')
                            ->rows(3)
                            ->maxLength(300)
                            ->placeholder($defaults['mission_body']),
                        Textarea::make('vision_body')
                            ->label('Vision description')
                            ->rows(3)
                            ->maxLength(300)
                            ->placeholder($defaults['vision_body']),
                    ]),

                Section::make('Life at FynnEdge')
                    ->description('The team section. Its photo strip comes from Content → Company Photos.')
                    ->columns(2)
                    ->components([
                        TextInput::make('life_eyebrow')
                            ->label('Small label')
                            ->maxLength(40)
                            ->placeholder($defaults['life_eyebrow']),
                        TextInput::make('life_heading')
                            ->label('Headline')
                            ->maxLength(100)
                            ->placeholder($defaults['life_heading']),
                        Textarea::make('life_description')
                            ->label('Introduction')
                            ->rows(2)
                            ->maxLength(300)
                            ->placeholder($defaults['life_description'])
                            ->columnSpanFull(),
                        Repeater::make('values')
                            ->label('Values')
                            ->schema([
                                TextInput::make('title')
                                    ->required()
                                    ->maxLength(40),
                                Textarea::make('body')
                                    ->label('Description')
                                    ->rows(2)
                                    ->maxLength(200),
                            ])
                            ->columns(2)
                            ->maxItems(6)
                            ->defaultItems(0)
                            ->addActionLabel('Add value')
                            ->helperText('Remove them all to hide the row.')
                            ->columnSpanFull(),
                    ]),

                Section::make('Work with us')
                    ->description('The closing banner. Its button always opens the Careers page.')
                    ->columns(2)
                    ->components([
                        TextInput::make('work_heading')
                            ->label('Headline')
                            ->maxLength(60)
                            ->placeholder($defaults['work_heading']),
                        TextInput::make('work_button_label')
                            ->label('Button label')
                            ->maxLength(30)
                            ->placeholder($defaults['work_button_label']),
                        Textarea::make('work_description')
                            ->label('Description')
                            ->rows(2)
                            ->maxLength(300)
                            ->placeholder($defaults['work_description'])
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function save(): void
    {
        // Repeater state is keyed by item UUIDs; store plain lists.
        Setting::set(AboutPageContent::SETTING_KEY, array_map(
            fn (mixed $value): mixed => is_array($value) ? array_values($value) : $value,
            $this->form->getState(),
        ));

        Notification::make()->title('About page saved')->success()->send();
    }

    public function resetToDefaults(): void
    {
        Setting::set(AboutPageContent::SETTING_KEY, null);

        $this->form->fill(AboutPageContent::resolve());

        Notification::make()->title('About page reset to default wording')->success()->send();
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
                ->modalDescription('Every field on this page goes back to the built-in wording. This cannot be undone.')
                ->action('resetToDefaults'),
            Action::make('save')
                ->label('Save')
                ->action('save'),
        ];
    }
}
