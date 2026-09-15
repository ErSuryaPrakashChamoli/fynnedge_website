<?php

namespace App\Filament\Resources\PromoBars\Schemas;

use App\Enums\PromoBarDevice;
use App\Enums\PromoBarTrigger;
use App\Enums\PublishStatus;
use App\Models\PromoBar;
use App\Support\Faqs\FaqPlacements;
use App\Support\PromoBars\PromoBars;
use Closure;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PromoBarForm
{
    /**
     * The same strict check PromoBar::barStyle() applies again on render.
     */
    private const HEX_PATTERN = '/^#[0-9a-fA-F]{6}$/';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Message')
                    ->description('The bar slides up from the bottom of the page. One short, punchy line works best.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('name')
                            ->label('Internal name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Only admins see this, e.g. "Diwali personal loan offer".')
                            ->columnSpanFull(),
                        TextInput::make('headline')
                            ->required()
                            ->maxLength(90)
                            ->helperText('Wrap words in *asterisks* to highlight them, e.g. Get up to *₹50 Lakhs* starting at *9.99%*')
                            ->columnSpanFull(),
                        TagsInput::make('rotating_messages')
                            ->label('Rotating messages')
                            ->placeholder('Type a message and press Enter')
                            ->nestedRecursiveRules(['max:90'])
                            ->helperText('Optional. Extra lines that take turns with the headline every few seconds (paused while the visitor hovers). *Asterisks* highlight here too.')
                            ->columnSpanFull(),
                        TextInput::make('eyebrow')
                            ->label('Badge')
                            ->placeholder('Limited time offer')
                            ->maxLength(40)
                            ->helperText('Optional. A small pulsing tag above the headline.'),
                        Toggle::make('show_countdown')
                            ->label('Show a live countdown')
                            ->helperText('Counts down to "Stop showing after" under Publishing, and the bar disappears at zero.')
                            ->live(),
                        TextInput::make('cta_label')
                            ->label('Button text')
                            ->required()
                            ->maxLength(30),
                        TextInput::make('cta_url')
                            ->label('Button link')
                            ->required()
                            ->maxLength(255)
                            ->rule(fn () => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && ! PromoBar::isSafeCtaUrl($value)) {
                                    $fail('The button link must start with https://, http://, /, tel: or mailto:.');
                                }
                            })
                            ->helperText('A site path like /eligibility, a full URL, tel:+91… or mailto:…. The bar hides itself on the page it links to.'),
                        Toggle::make('cta_opens_new_tab')
                            ->label('Open the link in a new tab'),
                    ]),
                Section::make('Look')
                    ->description('Leave any colour blank to use the FynnEdge navy, white and gold.')
                    ->columns(3)
                    ->columnSpanFull()
                    ->components([
                        FileUpload::make('image_path')
                            ->label('Pop-out image')
                            ->image()
                            ->disk('public')
                            ->directory('promo-bars')
                            ->acceptedFileTypes(['image/png', 'image/webp'])
                            ->maxSize(1024)
                            ->helperText('Optional. A transparent PNG or WebP cut-out — a person or a product — that pops out above the bar. About 400 × 500px.')
                            ->columnSpan(2),
                        TextInput::make('image_alt')
                            ->label('Image alt text')
                            ->maxLength(255)
                            ->helperText('Leave blank if the image is decorative.'),
                        ColorPicker::make('background_color')
                            ->label('Background')
                            ->regex(self::HEX_PATTERN),
                        ColorPicker::make('background_color_to')
                            ->label('Background fades into')
                            ->regex(self::HEX_PATTERN)
                            ->helperText('Optional. Makes a left-to-right gradient.'),
                        ColorPicker::make('text_color')
                            ->label('Text')
                            ->regex(self::HEX_PATTERN),
                        ColorPicker::make('highlight_color')
                            ->label('*Highlighted* words')
                            ->regex(self::HEX_PATTERN),
                        ColorPicker::make('cta_bg_color')
                            ->label('Button background')
                            ->regex(self::HEX_PATTERN),
                        ColorPicker::make('cta_text_color')
                            ->label('Button text')
                            ->regex(self::HEX_PATTERN),
                    ]),
                Section::make('Where and when it appears')
                    ->description('One bar per page. A bar pinned to a page by name replaces a site-wide bar there; otherwise the lowest sort order wins.')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        Select::make('placements')
                            ->label('Show on these pages')
                            ->multiple()
                            ->required()
                            ->searchable()
                            ->options(PromoBars::options())
                            ->helperText('Pick "Every page on the website", or individual pages.'),
                        Select::make('excluded_placements')
                            ->label('Hide on these pages')
                            ->multiple()
                            ->searchable()
                            ->options(FaqPlacements::options())
                            ->helperText('Optional. Handy with "Every page", e.g. to keep application pages distraction-free.'),
                        Select::make('trigger')
                            ->label('Slides up')
                            ->options(PromoBarTrigger::class)
                            ->default(PromoBarTrigger::Scroll)
                            ->native(false)
                            ->required()
                            ->live()
                            ->helperText('Exit intent needs a mouse, so phones fall back to half-way down the page.'),
                        TextInput::make('trigger_value')
                            ->label(fn (callable $get): string => self::trigger($get('trigger')) === PromoBarTrigger::Delay
                                ? 'Seconds to wait'
                                : 'How far down the page (%)')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(fn (callable $get): int => self::trigger($get('trigger'))->maxValue())
                            ->default(25)
                            ->visible(fn (callable $get): bool => self::trigger($get('trigger')) !== PromoBarTrigger::ExitIntent)
                            ->required(fn (callable $get): bool => self::trigger($get('trigger')) !== PromoBarTrigger::ExitIntent),
                        Select::make('device')
                            ->label('Show on')
                            ->options(PromoBarDevice::class)
                            ->default(PromoBarDevice::All)
                            ->native(false)
                            ->required(),
                        TextInput::make('reshow_after_hours')
                            ->label('Once closed, show it again after (hours)')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(PromoBar::MAX_RESHOW_AFTER_HOURS)
                            ->default(24)
                            ->required()
                            ->helperText('0 brings it back on the visitor\'s next page view. Remembered in their browser only.'),
                    ]),
                Section::make('Publishing')
                    ->columns(2)
                    ->columnSpanFull()
                    ->components([
                        TextInput::make('sort_order')
                            ->numeric()
                            ->default(0)
                            ->helperText('Lower wins when several bars qualify. You can also drag to reorder in the list.'),
                        Select::make('status')
                            ->options(PublishStatus::class)
                            ->default(PublishStatus::Draft)
                            ->required()
                            ->live(),
                        DateTimePicker::make('published_at')
                            ->label('Start showing from')
                            ->helperText('Leave blank to publish immediately once status is Published.')
                            ->visible(fn (callable $get) => $get('status') === PublishStatus::Published->value),
                        DateTimePicker::make('expires_at')
                            ->label('Stop showing after')
                            ->required(fn (callable $get): bool => (bool) $get('show_countdown'))
                            ->helperText('Optional, unless the countdown is on — then this is what it counts down to.'),
                    ]),
            ]);
    }

    /**
     * The field's state is the enum on a freshly loaded record and its string
     * value once the admin has touched the select. Blank counts as scroll,
     * the default.
     */
    private static function trigger(mixed $state): PromoBarTrigger
    {
        if ($state instanceof PromoBarTrigger) {
            return $state;
        }

        return PromoBarTrigger::tryFrom((string) $state) ?? PromoBarTrigger::Scroll;
    }
}
