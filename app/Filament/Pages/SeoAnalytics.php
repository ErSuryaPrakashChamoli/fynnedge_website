<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Support\Analytics\TrackingScripts;
use App\Support\Seo\CrawlerPolicy;
use App\Support\Seo\SearchEngineIndexing;
use App\Support\Seo\SeoDefaults;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Every sitewide SEO, indexing, verification, tracking, crawler and consent
 * setting, in one tabbed page.
 *
 * Split onto its own page rather than added to Settings — the same reasoning as
 * StructuredData — so it carries its own Shield permission
 * (`View:SeoAnalytics`), grantable to an SEO role without also handing over the
 * branding, contact and appearance settings an editor has. Structured Data,
 * Redirects and the per-record SEO sections stay where they are: this page is
 * for what is true of the whole site, they are for what is true of one thing.
 *
 * The Advanced tab is gated a second time, on `super_admin`: its fields are
 * echoed into every public page unescaped (escaping them would make every
 * legitimate tracking tag inert), so editing them is equivalent to running
 * arbitrary JavaScript on every visitor's browser. Filament does not dehydrate
 * a hidden component, so those settings are simply absent from the form state
 * for everyone else and save() leaves the stored values untouched rather than
 * blanking them.
 */
class SeoAnalytics extends Page
{
    protected string $view = 'filament.pages.seo-analytics';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|\UnitEnum|null $navigationGroup = 'Website Settings';

    protected static ?string $navigationLabel = 'SEO & Tracking';

    protected static ?string $title = 'SEO & Tracking';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:SeoAnalytics');
    }

    public static function canEditCustomScripts(): bool
    {
        return (bool) auth()->user()?->hasRole(config('filament-shield.super_admin.name', 'super_admin'));
    }

    /**
     * Every setting this page owns, so mount() and save() can never disagree
     * about the key list.
     *
     * @return array<int, string>
     */
    private static function settingKeys(): array
    {
        return [
            'seo_meta_title', 'seo_meta_description', 'seo_canonical_base_url',
            'seo_default_og_title', 'seo_default_og_description', 'seo_twitter_card', 'seo_default_twitter_image',
            'google_search_console_verification', 'bing_webmaster_verification',
            'facebook_domain_verification', 'pinterest_verification', 'other_verification_meta',
            'google_analytics_measurement_id', 'google_tag_manager_container_id',
            'google_ads_conversion_id', 'microsoft_clarity_project_id', 'meta_pixel_id', 'linkedin_partner_id',
            'robots_extra_directives',
            'cookie_policy_url', 'privacy_policy_url', 'terms_url',
            'custom_head_scripts', 'custom_body_start_scripts', 'custom_body_end_scripts',
            'custom_css', 'custom_javascript',
        ];
    }

    public function mount(): void
    {
        $this->form->fill([
            ...collect(self::settingKeys())->mapWithKeys(fn (string $key): array => [$key => Setting::get($key)])->all(),
            'seo_indexing_enabled' => SearchEngineIndexing::enabled(),
            'robots_ai_crawlers_allowed' => CrawlerPolicy::aiCrawlersAllowed(),
            'google_analytics_enabled' => (bool) Setting::get('google_analytics_enabled', false),
            'google_tag_manager_enabled' => (bool) Setting::get('google_tag_manager_enabled', false),
            'cookie_consent_enabled' => (bool) Setting::get('cookie_consent_enabled', false),
            'analytics_consent_required' => (bool) Setting::get('analytics_consent_required', false),
            'marketing_consent_required' => (bool) Setting::get('marketing_consent_required', false),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Tabs::make()->columnSpanFull()->tabs([
                    Tab::make('SEO')->icon(Heroicon::OutlinedMagnifyingGlass)->schema(self::seoTab()),
                    Tab::make('Verification')->icon(Heroicon::OutlinedCheckBadge)->schema(self::verificationTab()),
                    Tab::make('Analytics & Tracking')->icon(Heroicon::OutlinedChartBar)->schema(self::analyticsTab()),
                    Tab::make('AI & Crawlers')->icon(Heroicon::OutlinedCpuChip)->schema(self::crawlersTab()),
                    Tab::make('Privacy & Consent')->icon(Heroicon::OutlinedShieldCheck)->schema(self::consentTab()),
                    Tab::make('Advanced')
                        ->icon(Heroicon::OutlinedCodeBracket)
                        ->visible(fn (): bool => self::canEditCustomScripts())
                        ->schema(self::advancedTab()),
                ]),
            ]);
    }

    /**
     * @return array<int, Section>
     */
    private static function seoTab(): array
    {
        return [
            Section::make('Search engine indexing')
                ->description('Whether search engines may index the public website at all.')
                ->components([
                    Toggle::make('seo_indexing_enabled')
                        ->label('Allow search engine indexing')
                        ->helperText('Turn this OFF to prevent search engines from indexing the public website. Every page then sends "noindex, nofollow", robots.txt disallows everything and the sitemap stops being served. The admin panel is unaffected.'),
                ]),

            Section::make('Default meta tags')
                ->description('Used on any page that has not set its own. A page\'s own SEO section always wins.')
                ->columns(2)
                ->components([
                    TextInput::make('seo_meta_title')
                        ->label('Default page title')
                        ->maxLength(60)
                        ->placeholder('Falls back to the website name')
                        ->helperText('Only used for pages that render no title of their own.'),
                    TextInput::make('seo_canonical_base_url')
                        ->label('Canonical base URL')
                        ->url()
                        ->placeholder('https://fynnedge.com')
                        ->helperText('Leave blank unless the site is served behind a proxy or second hostname — canonical URLs then use this host instead of the requested one.'),
                    Textarea::make('seo_meta_description')
                        ->label('Default meta description')
                        ->rows(2)
                        ->maxLength(160)
                        ->placeholder(SeoDefaults::DEFAULT_DESCRIPTION)
                        ->columnSpanFull(),
                ]),

            Section::make('Social sharing defaults')
                ->description('Open Graph and X (Twitter) previews. Each falls back to the page title and meta description when blank, so these only need filling in to override that.')
                ->columns(2)
                ->components([
                    TextInput::make('seo_default_og_title')
                        ->label('Default share title')
                        ->maxLength(70)
                        ->placeholder('Falls back to each page\'s own title'),
                    Select::make('seo_twitter_card')
                        ->label('X (Twitter) card type')
                        ->native(false)
                        ->options(SeoDefaults::TWITTER_CARDS)
                        ->placeholder('Automatic — large image when one exists'),
                    Textarea::make('seo_default_og_description')
                        ->label('Default share description')
                        ->rows(2)
                        ->maxLength(200)
                        ->placeholder('Falls back to each page\'s meta description')
                        ->columnSpanFull(),
                    FileUpload::make('seo_default_twitter_image')
                        ->label('Default X (Twitter) image')
                        ->image()
                        ->disk('public')
                        ->directory('seo')
                        ->maxSize(2048)
                        ->helperText('Optional. Only needed when X should show a different image from the site\'s default social image (set under Settings → SEO defaults).')
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return array<int, Section>
     */
    private static function verificationTab(): array
    {
        return [
            Section::make('Site ownership verification')
                ->description('Each renders a single <meta> tag, and only when filled in. Paste either the token or the whole tag the service gives you.')
                ->columns(2)
                ->components([
                    self::verificationField('google_search_console_verification', 'Google Search Console', 'Search Console → HTML tag method.'),
                    self::verificationField('bing_webmaster_verification', 'Bing Webmaster Tools', 'Bing Webmaster → Meta tag option. Renders msvalidate.01.'),
                    self::verificationField('facebook_domain_verification', 'Meta (Facebook) domain verification', 'Meta Business Suite → Brand safety → Domains.'),
                    self::verificationField('pinterest_verification', 'Pinterest', 'Pinterest Business → Claim website → Add HTML tag.'),
                    Textarea::make('other_verification_meta')
                        ->label('Other verification tags')
                        ->rows(3)
                        ->maxLength(2000)
                        ->helperText('One <meta> tag per line for any service without a field above. Only <meta> tags are rendered — anything else here is ignored, and tags carrying event handlers are dropped.')
                        ->rules([
                            fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                                if (filled($value) && ! preg_match('/<meta\s+[^<>]*>/i', (string) $value)) {
                                    $fail('Enter complete <meta ...> tags, one per line.');
                                }
                            },
                        ])
                        ->columnSpanFull(),
                ]),
        ];
    }

    /**
     * @return array<int, Section>
     */
    private static function analyticsTab(): array
    {
        return [
            Section::make('Google')
                ->description('Injected into every public page automatically once enabled — never paste these snippets into the Advanced tab as well.')
                ->columns(2)
                ->components([
                    Toggle::make('google_analytics_enabled')
                        ->label('Google Analytics enabled')
                        ->helperText('Enable GA4 tracking and enter your Measurement ID.')
                        ->live(),
                    TextInput::make('google_analytics_measurement_id')
                        ->label('GA4 Measurement ID')
                        ->placeholder('G-XXXXXXXXXX')
                        ->helperText('GA4 → Admin → Data streams. Tracking stays off until this is valid.')
                        ->required(fn (Get $get): bool => (bool) $get('google_analytics_enabled'))
                        ->rule('regex:/^G-[A-Z0-9]{4,20}$/i'),
                    Toggle::make('google_tag_manager_enabled')
                        ->label('Google Tag Manager enabled')
                        ->helperText('Enable Google Tag Manager and enter your Container ID.')
                        ->live(),
                    TextInput::make('google_tag_manager_container_id')
                        ->label('GTM Container ID')
                        ->placeholder('GTM-XXXXXXX')
                        ->helperText('Shown at the top of the GTM workspace. Tracking stays off until this is valid.')
                        ->required(fn (Get $get): bool => (bool) $get('google_tag_manager_enabled'))
                        ->rule('regex:/^GTM-[A-Z0-9]{4,20}$/i'),
                    TextInput::make('google_ads_conversion_id')
                        ->label('Google Ads conversion ID')
                        ->placeholder('AW-123456789')
                        ->helperText('Optional. Shares the GA4 tag loader, so enabling both does not double-count pageviews.')
                        ->rule('regex:/^AW-[0-9]{6,15}$/i')
                        ->columnSpanFull(),
                ]),

            Section::make('Other platforms')
                ->description('Leave a field blank to load nothing for that platform. Each tag is rendered from its ID — no snippets to paste.')
                ->columns(2)
                ->components([
                    TextInput::make('microsoft_clarity_project_id')
                        ->label('Microsoft Clarity project ID')
                        ->placeholder('abcdefghij')
                        ->helperText('Clarity → Settings → Overview. Counts as analytics for consent.')
                        ->rule('regex:/^[a-z0-9]{5,20}$/i'),
                    TextInput::make('meta_pixel_id')
                        ->label('Meta (Facebook) Pixel ID')
                        ->placeholder('123456789012345')
                        ->helperText('Events Manager → Data sources. Counts as marketing for consent.')
                        ->rule('regex:/^[0-9]{10,20}$/'),
                    TextInput::make('linkedin_partner_id')
                        ->label('LinkedIn Insight partner ID')
                        ->placeholder('1234567')
                        ->helperText('Campaign Manager → Insight tag. Counts as marketing for consent.')
                        ->rule('regex:/^[0-9]{4,12}$/'),
                ]),
        ];
    }

    /**
     * @return array<int, Section>
     */
    private static function crawlersTab(): array
    {
        return [
            Section::make('AI and answer engines')
                ->description('Controlled separately from Google and Bing: a site can rank in search while opting out of AI training and answer synthesis. Turning this off never affects normal search crawling.')
                ->components([
                    Toggle::make('robots_ai_crawlers_allowed')
                        ->label('Allow AI and answer-engine crawlers')
                        ->helperText('Covers '.implode(', ', array_keys(CrawlerPolicy::AI_CRAWLERS)).'. Off writes an explicit Disallow for each of them in robots.txt; Googlebot and Bingbot keep crawling either way.'),
                ]),

            Section::make('Extra robots.txt directives')
                ->description('Appended to the generated robots.txt. The admin panel, login, the application funnel, storage internals and signed previews are already disallowed — these are for anything else.')
                ->components([
                    Textarea::make('robots_extra_directives')
                        ->label('Additional directives')
                        ->rows(5)
                        ->maxLength(4000)
                        ->placeholder("User-agent: SomeBot\nDisallow: /private-campaign")
                        ->helperText('One directive per line. Only User-agent, Allow, Disallow, Crawl-delay, Sitemap, Host, Clean-param and # comments are emitted — any other line is ignored rather than written, since one malformed line can invalidate the group it sits in.'),
                ]),
        ];
    }

    /**
     * @return array<int, Section>
     */
    private static function consentTab(): array
    {
        return [
            Section::make('Cookie consent banner')
                ->description('When the banner is on, the categories marked as requiring consent are not rendered into the page at all until the visitor accepts — the scripts are blocked server-side, not hidden after loading.')
                ->columns(2)
                ->components([
                    Toggle::make('cookie_consent_enabled')
                        ->label('Show the cookie consent banner')
                        ->helperText('Off means no banner and no gating — the site behaves as it does today.')
                        ->live()
                        ->columnSpanFull(),
                    Toggle::make('analytics_consent_required')
                        ->label('Require consent for analytics')
                        ->helperText('Gates Google Analytics, Google Tag Manager and Microsoft Clarity.')
                        ->visible(fn (Get $get): bool => (bool) $get('cookie_consent_enabled')),
                    Toggle::make('marketing_consent_required')
                        ->label('Require consent for marketing')
                        ->helperText('Gates Meta Pixel, LinkedIn Insight, Google Ads and everything on the Advanced tab.')
                        ->visible(fn (Get $get): bool => (bool) $get('cookie_consent_enabled')),
                ]),

            Section::make('Policy links')
                ->description('Shown in the consent banner. Leave one blank to hide that link.')
                ->columns(3)
                ->components([
                    TextInput::make('cookie_policy_url')->label('Cookie policy URL')->url()->placeholder('https://fynnedge.com/privacy-policy'),
                    TextInput::make('privacy_policy_url')->label('Privacy policy URL')->url(),
                    TextInput::make('terms_url')->label('Terms URL')->url(),
                ]),
        ];
    }

    /**
     * @return array<int, Section>
     */
    private static function advancedTab(): array
    {
        return [
            Section::make('Custom tracking scripts')
                ->description('Advanced, super admins only. Raw HTML for tags with no dedicated field above — other verification tags, niche pixels, A/B testing tools. Rendered exactly as written, so a broken tag here breaks every page on the site. Gated by marketing consent when that is switched on.')
                ->components([
                    self::scriptField('custom_head_scripts', 'Head scripts', 'Rendered inside <head> on every public page.'),
                    self::scriptField('custom_body_start_scripts', 'Body start scripts', 'Rendered immediately after the opening <body> tag — where pixel <noscript> fallbacks belong.'),
                    self::scriptField('custom_body_end_scripts', 'Body end scripts', 'Rendered just before the closing </body> tag. Prefer this for anything that does not need to run before the page paints.'),
                ]),

            Section::make('Custom CSS & JavaScript')
                ->description('Injected without <style>/<script> wrappers of your own — write the CSS or JavaScript body only.')
                ->components([
                    Textarea::make('custom_css')
                        ->label('Custom CSS')
                        ->rows(6)
                        ->maxLength(20000)
                        ->helperText('Wrapped in a <style> tag in the head. Applies to the public site only, never the admin panel.'),
                    Textarea::make('custom_javascript')
                        ->label('Custom JavaScript')
                        ->rows(6)
                        ->maxLength(20000)
                        ->helperText('Wrapped in a <script> tag at the end of the body.'),
                ]),
        ];
    }

    private static function verificationField(string $key, string $label, string $helperText): TextInput
    {
        return TextInput::make($key)
            ->label($label)
            ->maxLength(255)
            ->helperText($helperText)
            ->dehydrateStateUsing(fn (?string $state): ?string => TrackingScripts::verificationTokenFrom($state))
            ->rules([
                fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                    if (filled($value) && ! TrackingScripts::verificationTokenFrom($value)) {
                        $fail('That does not look like a verification token. Paste the token itself, or the whole <meta> tag the service gives you.');
                    }
                },
            ]);
    }

    /**
     * Raw markup, validated only for the mistakes that would break the page or
     * the layout it is injected into. Anything stricter (stripping attributes,
     * allow-listing tags) would break the very snippets these fields exist to
     * carry, since every provider ships its own inline bootstrapping script.
     */
    private static function scriptField(string $key, string $label, string $helperText): Textarea
    {
        return Textarea::make($key)
            ->label($label)
            ->helperText($helperText.' Paste the provider\'s snippet exactly as given, including its <script> tags.')
            ->rows(6)
            ->maxLength(20000)
            ->rules(['regex:/^(?!.*<\?(?:php|=)).*$/is', 'regex:/^(?!.*<\/(?:body|html)\b).*$/is'])
            ->validationMessages([
                'regex' => 'Tracking scripts may not contain PHP tags or a closing </body> or </html> tag.',
            ])
            ->columnSpanFull();
    }

    public function save(): void
    {
        /*
         * Only the keys the form actually dehydrated are written, so a
         * non-super-admin saving this page cannot blank the custom scripts
         * their role never had access to.
         */
        foreach ($this->form->getState() as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()
            ->title('SEO & tracking settings saved')
            ->success()
            ->send();
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('save')
                ->label('Save')
                ->action('save'),
        ];
    }
}
