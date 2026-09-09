<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Theme\SiteThemeStyles;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Settings extends Page
{
    protected string $view = 'filament.pages.settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'site_name' => Setting::get('site_name', 'FynnEdge'),
            'site_tagline' => Setting::get('site_tagline', 'Simplifying Loan, Amplifying Trust'),
            'site_logo' => Setting::get('site_logo'),
            'site_favicon' => Setting::get('site_favicon'),
            'hero_eyebrow' => Setting::get('hero_eyebrow', 'FynnEdge Advisory (OPC) Pvt Ltd'),
            'hero_heading' => Setting::get('hero_heading', 'Simplifying loans.'),
            'hero_heading_accent' => Setting::get('hero_heading_accent', 'Amplifying trust.'),
            'hero_subheading' => Setting::get('hero_subheading', 'FynnEdge connects you with suitable banks and NBFCs for personal loans, home loans, car loans, business loans and loans against property — with clear, upfront eligibility before you apply.'),
            'footer_legal_name' => Setting::get('footer_legal_name', 'FynnEdge Advisory (OPC) Pvt Ltd'),
            'footer_disclaimer' => Setting::get('footer_disclaimer', 'Loan approval is subject to lender policies, documentation and underwriting. Eligibility results shown on this site are indicative, not a guarantee of approval.'),
            'seo_default_og_image' => Setting::get('seo_default_og_image'),
            'contact_phone' => Setting::get('contact_phone'),
            'contact_email' => Setting::get('contact_email'),
            'contact_whatsapp' => Setting::get('contact_whatsapp'),
            'contact_address' => Setting::get('contact_address'),
            'contact_map_url' => Setting::get('contact_map_url'),
            'business_description' => Setting::get('business_description'),
            'business_street_address' => Setting::get('business_street_address'),
            'business_locality' => Setting::get('business_locality'),
            'business_region' => Setting::get('business_region'),
            'business_postal_code' => Setting::get('business_postal_code'),
            'business_country' => Setting::get('business_country', 'IN'),
            'business_area_served' => Setting::get('business_area_served'),
            'business_price_range' => Setting::get('business_price_range'),
            'founder_name' => Setting::get('founder_name'),
            'founder_photo' => Setting::get('founder_photo'),
            'social_instagram' => Setting::get('social_instagram'),
            'social_facebook' => Setting::get('social_facebook'),
            'social_whatsapp' => Setting::get('social_whatsapp'),
            'social_linkedin' => Setting::get('social_linkedin'),
            'social_x' => Setting::get('social_x'),
            ...self::themeSectionDefaults(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private static function themeSectionDefaults(): array
    {
        $defaults = [];

        foreach (['header', 'footer', 'main'] as $section) {
            $defaults["theme_{$section}_bg_color"] = Setting::get("theme_{$section}_bg_color");
            $defaults["theme_{$section}_font_color"] = Setting::get("theme_{$section}_font_color");
            $defaults["theme_{$section}_link_color"] = Setting::get("theme_{$section}_link_color");
            $defaults["theme_{$section}_font_family"] = Setting::get("theme_{$section}_font_family");
            $defaults["theme_{$section}_font_size"] = Setting::get("theme_{$section}_font_size");
            $defaults["theme_{$section}_font_weight"] = Setting::get("theme_{$section}_font_weight");
            $defaults["theme_{$section}_font_style"] = Setting::get("theme_{$section}_font_style");
        }

        foreach ([
            'theme_banner_font_color', 'theme_banner_font_family', 'theme_banner_font_size',
            'theme_banner_font_weight', 'theme_banner_font_style', 'theme_banner_button_color',
            'theme_banner_button_hover_color', 'theme_banner_button_text_color',
        ] as $key) {
            $defaults[$key] = Setting::get($key);
        }

        return $defaults;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Branding')
                    ->description('The name, logo and favicon shown across the site header, footer and browser tab.')
                    ->columns(2)
                    ->components([
                        TextInput::make('site_name')
                            ->label('Website name')
                            ->required()
                            ->maxLength(60),
                        TextInput::make('site_tagline')
                            ->label('Tagline')
                            ->required()
                            ->maxLength(80)
                            ->helperText('Shown under the logo in the header and footer.'),
                        FileUpload::make('site_logo')
                            ->label('Logo')
                            ->image()
                            ->imageEditor()
                            ->disk('public')
                            ->directory('branding')
                            ->acceptedFileTypes(['image/png', 'image/svg+xml', 'image/webp'])
                            ->maxSize(2048)
                            ->helperText('PNG, SVG or WebP, up to 2MB. Leave blank to keep the default FynnEdge mark.'),
                        FileUpload::make('site_favicon')
                            ->label('Favicon')
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->acceptedFileTypes(['image/png', 'image/x-icon', 'image/vnd.microsoft.icon'])
                            ->maxSize(512)
                            ->helperText('Square PNG or ICO, up to 512KB. Leave blank to keep the default icon.'),
                    ]),

                Section::make('Homepage hero')
                    ->description('The main headline visitors see at the top of the homepage.')
                    ->columns(2)
                    ->components([
                        TextInput::make('hero_eyebrow')
                            ->label('Eyebrow text')
                            ->maxLength(80)
                            ->columnSpanFull(),
                        TextInput::make('hero_heading')
                            ->label('Headline')
                            ->required()
                            ->maxLength(60),
                        TextInput::make('hero_heading_accent')
                            ->label('Headline (highlighted part)')
                            ->required()
                            ->maxLength(60)
                            ->helperText('Shown in the accent colour, right after the headline above.'),
                        Textarea::make('hero_subheading')
                            ->label('Subheading')
                            ->required()
                            ->maxLength(240)
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('Footer & legal')
                    ->columns(2)
                    ->components([
                        TextInput::make('footer_legal_name')
                            ->label('Registered company name')
                            ->required()
                            ->maxLength(120)
                            ->helperText('Shown in the footer and copyright line.')
                            ->columnSpanFull(),
                        Textarea::make('footer_disclaimer')
                            ->label('Footer disclaimer')
                            ->required()
                            ->maxLength(280)
                            ->rows(2)
                            ->columnSpanFull(),
                    ]),

                Section::make('SEO defaults')
                    ->description('Used when a specific page has no SEO image of its own set.')
                    ->components([
                        FileUpload::make('seo_default_og_image')
                            ->label('Default social share image')
                            ->image()
                            ->disk('public')
                            ->directory('seo')
                            ->acceptedFileTypes(['image/png', 'image/jpeg', 'image/webp'])
                            ->maxSize(2048)
                            ->helperText('Shown when a link to the site is shared on social media or messaging apps and the page itself has no image set. Recommended 1200×630px, up to 2MB.'),
                    ]),

                Section::make('Contact channels')
                    ->description('Used across the public site instead of hard-coded numbers/addresses — including the site footer.')
                    ->columns(3)
                    ->components([
                        TextInput::make('contact_phone')->tel()->label('Phone'),
                        TextInput::make('contact_email')->email()->label('Email'),
                        TextInput::make('contact_whatsapp')->tel()->label('WhatsApp'),
                        Textarea::make('contact_address')->label('Address')->rows(2)->columnSpanFull(),
                        TextInput::make('contact_map_url')
                            ->label('Map location URL')
                            ->url()
                            ->columnSpanFull()
                            ->placeholder('https://maps.google.com/maps?q=...')
                            ->helperText('Paste a Google Maps link for your location — Share → Copy link, or Share → Embed a map. Either works.'),
                    ]),

                Section::make('Business profile (structured data)')
                    ->description('Feeds the sitewide Organization schema that Google and AI assistants read to identify the business. Leave any field blank and it is simply omitted — never guessed.')
                    ->columns(3)
                    ->components([
                        Textarea::make('business_description')
                            ->label('Business description')
                            ->rows(2)
                            ->maxLength(300)
                            ->columnSpanFull()
                            ->helperText('One or two sentences describing what the business does.'),
                        TextInput::make('business_street_address')->label('Street address')->columnSpan(2),
                        TextInput::make('business_locality')->label('City / locality'),
                        TextInput::make('business_region')->label('State / region'),
                        TextInput::make('business_postal_code')->label('PIN code'),
                        TextInput::make('business_country')
                            ->label('Country code')
                            ->maxLength(2)
                            ->placeholder('IN')
                            ->helperText('Two-letter ISO code.'),
                        Textarea::make('business_area_served')
                            ->label('Areas served')
                            ->rows(4)
                            ->columnSpanFull()
                            ->helperText('One place per line, e.g. Delhi NCR, Karnataka, Maharashtra.'),
                        TextInput::make('business_price_range')
                            ->label('Loan amount range')
                            ->columnSpanFull()
                            ->placeholder('₹25,000 - ₹30,00,000')
                            ->helperText('The range of loan amounts arranged, shown to search engines as the price range.'),
                    ]),

                Section::make('Founder message')
                    ->description('Shown in the founder section of the About page.')
                    ->columns(2)
                    ->components([
                        TextInput::make('founder_name')->label('Founder name'),
                        FileUpload::make('founder_photo')
                            ->label('Founder photo')
                            ->image()
                            ->disk('public')
                            ->directory('founder')
                            ->maxSize(5120)
                            ->helperText('JPG or PNG, up to 5MB.'),
                    ]),

                Section::make('Social media')
                    ->description('Full links (including https://), shown as icon links in the site footer. Leave any of these blank to hide that icon.')
                    ->columns(3)
                    ->components([
                        TextInput::make('social_instagram')->label('Instagram')->url()->placeholder('https://instagram.com/fynnedge'),
                        TextInput::make('social_facebook')->label('Facebook')->url()->placeholder('https://facebook.com/fynnedge'),
                        TextInput::make('social_whatsapp')->label('WhatsApp')->url()->placeholder('https://wa.me/91XXXXXXXXXX'),
                        TextInput::make('social_linkedin')->label('LinkedIn')->url()->placeholder('https://linkedin.com/company/fynnedge'),
                        TextInput::make('social_x')->label('X (Twitter)')->url()->placeholder('https://x.com/fynnedge'),
                    ]),

                Section::make('Appearance — Header')
                    ->description('Leave any field blank to keep the default site theme for the header.')
                    ->columns(3)
                    ->components(self::themeSectionFields('header')),

                Section::make('Appearance — Footer')
                    ->description('Leave any field blank to keep the default site theme for the footer.')
                    ->columns(3)
                    ->components(self::themeSectionFields('footer')),

                Section::make('Appearance — Main content')
                    ->description('Applies to the homepage and every other page\'s main content area (not the header or footer). Leave any field blank to keep the default site theme.')
                    ->columns(3)
                    ->components(self::themeSectionFields('main')),

                Section::make('Appearance — Homepage banner')
                    ->description('Controls the text and button on the sliding banner in the top-right of the homepage. Its text is white by default because it sits over a photo — leave a field blank to keep that default. The button matches the site\'s other buttons unless you change it here.')
                    ->columns(3)
                    ->components([
                        ColorPicker::make('theme_banner_font_color')
                            ->label('Text colour')
                            ->hex()
                            ->helperText('Heading and subtitle. Default: white.'),
                        Select::make('theme_banner_font_family')
                            ->label('Font')
                            ->native(false)
                            ->options([
                                'display' => 'Fraunces — serif (default heading font)',
                                'sans' => 'IBM Plex Sans — sans serif',
                                'mono' => 'IBM Plex Mono — monospace',
                            ]),
                        Select::make('theme_banner_font_size')
                            ->label('Heading font size')
                            ->native(false)
                            ->options(array_combine(SiteThemeStyles::BANNER_FONT_SIZES, SiteThemeStyles::BANNER_FONT_SIZES)),
                        Select::make('theme_banner_font_weight')
                            ->label('Heading font weight')
                            ->native(false)
                            ->options([
                                '300' => '300 — Light',
                                '400' => '400 — Normal',
                                '500' => '500 — Medium',
                                '600' => '600 — Semibold',
                                '700' => '700 — Bold',
                                '800' => '800 — Extra bold',
                            ]),
                        Select::make('theme_banner_font_style')
                            ->label('Font style')
                            ->native(false)
                            ->options([
                                'normal' => 'Normal',
                                'italic' => 'Italic',
                            ]),
                        ColorPicker::make('theme_banner_button_color')
                            ->label('Button colour')
                            ->hex()
                            ->helperText('Default: the site accent, matching every other button.'),
                        ColorPicker::make('theme_banner_button_hover_color')
                            ->label('Button hover colour')
                            ->hex(),
                        ColorPicker::make('theme_banner_button_text_color')
                            ->label('Button text colour')
                            ->hex(),
                    ]),
            ]);
    }

    /**
     * @return array<int, ColorPicker|Select>
     */
    private static function themeSectionFields(string $section): array
    {
        return [
            ColorPicker::make("theme_{$section}_bg_color")
                ->label('Background colour')
                ->hex(),
            ColorPicker::make("theme_{$section}_font_color")
                ->label('Font colour')
                ->hex(),
            ColorPicker::make("theme_{$section}_link_color")
                ->label('Link & accent colour')
                ->hex()
                ->helperText('Also recolours accent buttons in this section.'),
            Select::make("theme_{$section}_font_family")
                ->label('Font')
                ->native(false)
                ->options([
                    'sans' => 'IBM Plex Sans — sans serif',
                    'display' => 'Fraunces — serif',
                    'mono' => 'IBM Plex Mono — monospace',
                ]),
            Select::make("theme_{$section}_font_size")
                ->label('Font size')
                ->native(false)
                ->options(array_combine(SiteThemeStyles::FONT_SIZES, SiteThemeStyles::FONT_SIZES)),
            Select::make("theme_{$section}_font_weight")
                ->label('Font weight')
                ->native(false)
                ->options([
                    '300' => '300 — Light',
                    '400' => '400 — Normal',
                    '500' => '500 — Medium',
                    '600' => '600 — Semibold',
                    '700' => '700 — Bold',
                    '800' => '800 — Extra bold',
                ]),
            Select::make("theme_{$section}_font_style")
                ->label('Font style')
                ->native(false)
                ->options([
                    'normal' => 'Normal',
                    'italic' => 'Italic',
                ]),
        ];
    }

    public function save(): void
    {
        $state = $this->form->getState();

        if (filled($state['contact_map_url'] ?? null)) {
            $state['contact_map_url'] = self::normalizeMapUrl($state['contact_map_url']);
        }

        foreach ($state as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()
            ->title('Settings saved')
            ->success()
            ->send();
    }

    /**
     * A plain Google Maps share link (google.com/maps?q=... or maps.google.com/maps?q=...)
     * serves the full Maps app, which sends X-Frame-Options: sameorigin and gets refused
     * when framed — only the dedicated embed variant (output=embed, or the /maps/embed?pb=...
     * src from Share > Embed a map) is safe to iframe. Admins reliably paste the former
     * (it's what "Share > Copy link" gives you), so normalize it here rather than relying
     * on everyone finding the Embed a map option.
     */
    private static function normalizeMapUrl(string $url): string
    {
        if (str_contains($url, '/maps/embed') || str_contains($url, 'output=embed')) {
            return $url;
        }

        return $url.(str_contains($url, '?') ? '&' : '?').'output=embed';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->action('save'),
        ];
    }
}
