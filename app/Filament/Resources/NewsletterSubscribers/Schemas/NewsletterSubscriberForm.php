<?php

namespace App\Filament\Resources\NewsletterSubscribers\Schemas;

use App\Modules\Newsletter\Enums\NewsletterCategory;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Read-only by design. A subscriber's status is the outcome of what THEY did —
 * confirmed, unsubscribed, bounced — so it is changed through the explicit
 * actions on the list/view pages, which record the accompanying timestamps,
 * never by free-form editing that could leave "active" with no consent record.
 */
class NewsletterSubscriberForm
{
    /**
     * No stored preferences means "every topic" — that is what someone who used
     * the one-field signup form asked for, so it must not read as "none".
     */
    private static function topicSummary(mixed $record): string
    {
        if ($record->preferences->isEmpty()) {
            return 'All topics (no preferences set)';
        }

        $selected = $record->preferences
            ->where('is_subscribed', true)
            ->map(fn ($preference): string => $preference->category instanceof NewsletterCategory
                ? $preference->category->getLabel()
                : (string) $preference->category)
            ->join(', ');

        return $selected !== '' ? $selected : 'None selected';
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Subscriber')
                    ->columns(2)
                    ->components([
                        TextEntry::make('email')->label('Email')->copyable(),
                        TextEntry::make('name')->label('Name')->placeholder('Not provided'),
                        TextEntry::make('status')
                            ->label('Status')
                            ->badge()
                            ->formatStateUsing(fn (SubscriberStatus $state): string => $state->getLabel()),
                        TextEntry::make('preferences')
                            ->label('Topics')
                            ->state(self::topicSummary(...)),
                    ]),

                Section::make('Where they signed up')
                    ->columns(2)
                    ->components([
                        TextEntry::make('source')->label('Source')->placeholder('Unknown'),
                        TextEntry::make('source_url')->label('Page')->placeholder('Unknown'),
                    ]),

                Section::make('Consent & history')
                    ->columns(3)
                    ->components([
                        TextEntry::make('subscribed_at')->label('Subscribed')->dateTime('d M Y H:i')->placeholder('—'),
                        TextEntry::make('confirmed_at')->label('Confirmed')->dateTime('d M Y H:i')->placeholder('Not confirmed'),
                        TextEntry::make('unsubscribed_at')->label('Unsubscribed')->dateTime('d M Y H:i')->placeholder('—'),
                        TextEntry::make('consent_at')->label('Consent recorded')->dateTime('d M Y H:i')->placeholder('—'),
                        TextEntry::make('consent_ip')->label('Consent IP')->placeholder('—'),
                        TextEntry::make('created_at')->label('First seen')->dateTime('d M Y H:i'),
                    ]),
            ]);
    }
}
