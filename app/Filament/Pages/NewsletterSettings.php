<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Modules\Newsletter\Services\NewsletterSettings as Settings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Newsletter configuration, stored in the SAME key/value Setting table the rest
 * of the site uses — no second settings framework, and no redeploy to change a
 * sender name.
 *
 * Mail CREDENTIALS are deliberately absent: the transport stays in Laravel's
 * mail config and .env, so this page can never leak or change an SMTP password,
 * and the newsletter stays provider-agnostic.
 */
class NewsletterSettings extends Page
{
    protected string $view = 'filament.pages.newsletter-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|\UnitEnum|null $navigationGroup = 'Marketing';

    protected static ?string $navigationLabel = 'Newsletter Settings';

    protected static ?string $title = 'Newsletter Settings';

    protected static ?int $navigationSort = 6;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:NewsletterSettings');
    }

    public function mount(): void
    {
        $this->form->fill([
            'newsletter_enabled' => Settings::enabled(),
            'double_opt_in_enabled' => Settings::doubleOptInEnabled(),
            'welcome_email_enabled' => Settings::welcomeEmailEnabled(),
            'newsletter_sender_name' => Setting::get('newsletter_sender_name'),
            'newsletter_sender_email' => Setting::get('newsletter_sender_email'),
            'newsletter_reply_to' => Setting::get('newsletter_reply_to'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Newsletter')
                    ->components([
                        Toggle::make('newsletter_enabled')
                            ->label('Newsletter enabled')
                            ->helperText('Off removes every signup form from the website and stops the public subscribe endpoint. Existing subscribers and campaigns are untouched.'),
                        Toggle::make('double_opt_in_enabled')
                            ->label('Require email confirmation (double opt-in)')
                            ->helperText('Strongly recommended. New subscribers stay pending until they click a confirmation link, which is what proves consent and protects your sending reputation.'),
                        Toggle::make('welcome_email_enabled')
                            ->label('Send a welcome email')
                            ->helperText('Sent once, after a subscriber confirms.'),
                    ]),

                Section::make('Sender identity')
                    ->description('How newsletter emails appear in the inbox. Leave blank to use the application\'s configured mail sender. Mail server credentials are not set here — they live in the environment configuration.')
                    ->columns(2)
                    ->components([
                        TextInput::make('newsletter_sender_name')
                            ->label('From name')
                            ->maxLength(100)
                            ->placeholder(Settings::senderName()),
                        TextInput::make('newsletter_sender_email')
                            ->label('From address')
                            ->email()
                            ->maxLength(190)
                            ->placeholder(Settings::senderEmail())
                            ->helperText('Must be an address your mail provider is allowed to send from, or messages will fail SPF/DKIM checks.'),
                        TextInput::make('newsletter_reply_to')
                            ->label('Reply-to address')
                            ->email()
                            ->maxLength(190)
                            ->helperText('Where replies go. Defaults to the contact email from Settings.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::set($key, $value);
        }

        Notification::make()->title('Newsletter settings saved')->success()->send();
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
