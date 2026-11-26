<?php

namespace App\Filament\Pages;

use App\Filament\Clusters\Concerns\BelongsToAdminModule;
use App\Models\Setting;
use App\Support\Pages\DebtConsolidationPageContent;
use App\Support\Pages\DebtConsolidationPages;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;

/**
 * Website Settings → Debt Consolidation Pages: the shared sections of the
 * seven Debt Consolidation pages (hero badge, calls to action, trust points,
 * steps, "who it is for" lists, form headings) and which sections each page
 * shows. Each page's title, intro, body, SEO and FAQs are edited on its own
 * row in Website Pages → General Pages.
 */
class DebtConsolidationPagesSettings extends Page
{
    use BelongsToAdminModule;

    protected string $view = 'filament.pages.newsletter-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?string $navigationLabel = 'Debt Consolidation Pages';

    protected static ?string $title = 'Debt Consolidation Pages';

    protected static ?string $slug = 'debt-consolidation-pages-settings';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:DebtConsolidationPagesSettings');
    }

    public function mount(): void
    {
        $this->form->fill([...DebtConsolidationPageContent::resolve(), 'hi' => DebtConsolidationPageContent::hindi()]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema->statePath('data')->components([
            Tabs::make()->columnSpanFull()->tabs([
                Tab::make('English')->schema($this->fields('')),
                Tab::make('हिन्दी')->schema([
                    Section::make()->description('Leave any field blank to show the automatic translation of the English.')->components($this->fields('hi.', hindi: true)),
                ]),
                Tab::make('Sections per page')->schema(array_map(
                    fn (string $key): CheckboxList => CheckboxList::make('page_sections.'.$key)
                        ->label(str($key)->replace('_', ' ')->title().' — '.DebtConsolidationPages::PAGES[$key]['path'])
                        ->options(DebtConsolidationPageContent::SECTIONS)->columns(3),
                    array_keys(DebtConsolidationPages::PAGES),
                )),
            ]),
        ]);
    }

    /**
     * @return list<Component|Field>
     */
    private function fields(string $prefix, bool $hindi = false): array
    {
        $limit = fn (int $english): int => $hindi ? (int) (ceil($english * 1.5 / 5) * 5) : $english;
        $input = fn (string $key, string $label, int $max = 120) => TextInput::make($prefix.$key)->label($label)->maxLength($limit($max))->required(! $hindi);
        $area = fn (string $key, string $label, int $max = 300) => Textarea::make($prefix.$key)->label($label)->rows(2)->maxLength($limit($max))->required(! $hindi);
        $list = fn (string $key, string $label) => Repeater::make($prefix.$key)->label($label)->defaultItems(0)->components([TextInput::make('text')->hiddenLabel()->maxLength($limit(160))->required()]);

        return [
            Section::make('Hero')->columns(3)->components([
                $input('hero_badge', 'Badge'), $input('primary_cta_label', 'Primary button (assessment)', 60), $input('secondary_cta_label', 'Secondary button (debt analyser)', 60),
                $list('trust_points', 'Trust points')->columnSpanFull(),
            ]),
            Section::make('How it works')->components([
                $input('steps_heading', 'Heading'),
                Repeater::make($prefix.'steps')->label('Steps')->columns(2)->defaultItems(0)->components([
                    TextInput::make('title')->maxLength($limit(80))->required(),
                    Textarea::make('body')->rows(2)->maxLength($limit(300)),
                ]),
            ]),
            Section::make('Who it is for')->columns(2)->components([
                $input('audiences_heading', 'Heading')->columnSpanFull(),
                $input('personal_heading', 'Individuals — heading'), $input('business_heading', 'Businesses — heading'),
                $area('personal_summary', 'Individuals — summary'), $area('business_summary', 'Businesses — summary'),
                $list('personal_points', 'Debts covered — individuals'), $list('business_points', 'Debts covered — businesses'),
            ]),
            Section::make('Tools, enquiry form and related pages')->columns(2)->components([
                $input('tools_heading', 'Tools heading')->columnSpanFull(),
                $input('savings_card_title', 'Savings calculator card title'), $area('savings_card_body', 'Savings calculator card text'),
                $input('savings_cta_label', 'Savings calculator card button', 60)->columnSpanFull(),
                $input('calculator_card_title', 'Debt analyser card title'), $area('calculator_card_body', 'Debt analyser card text'),
                $input('assessment_card_title', 'Assessment card title'), $area('assessment_card_body', 'Assessment card text'),
                $input('enquiry_heading', 'Enquiry form heading'), $area('enquiry_subheading', 'Enquiry form text'),
                $input('related_heading', 'Related loan pages heading')->columnSpanFull(),
            ]),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();
        $hindi = Arr::pull($state, 'hi', []);

        Setting::set(DebtConsolidationPageContent::SETTING_KEY, self::plainLists($state));
        Setting::set(DebtConsolidationPageContent::HINDI_SETTING_KEY, self::plainLists((array) $hindi));

        Notification::make()->title('Debt Consolidation pages saved')->success()->send();
    }

    public function resetToDefaults(): void
    {
        Setting::set(DebtConsolidationPageContent::SETTING_KEY, null);
        Setting::set(DebtConsolidationPageContent::HINDI_SETTING_KEY, null);
        $this->mount();

        Notification::make()->title('Reset to the default wording')->success()->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('reset')->label('Reset to defaults')->color('gray')->requiresConfirmation()->action('resetToDefaults'),
            Action::make('save')->label('Save')->action('save'),
        ];
    }

    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    private static function plainLists(array $state): array
    {
        $plain = [];

        foreach ($state as $key => $value) {
            // Repeaters key their items by uuid; page_sections is keyed by page and must stay so.
            $plain[$key] = $key !== 'page_sections' && is_array($value) && ! array_is_list($value) ? array_values($value) : $value;
        }

        return $plain;
    }
}
