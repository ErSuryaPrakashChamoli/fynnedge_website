<?php

namespace App\Filament\Resources\NewsletterSubscribers\Tables;

use App\Mail\Newsletter\ConfirmationMail;
use App\Modules\Newsletter\Enums\SubscriberStatus;
use App\Modules\Newsletter\Enums\SubscriptionSource;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Mail;

class NewsletterSubscribersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('email')->label('Email')->searchable()->sortable()->copyable(),
                TextColumn::make('name')->label('Name')->searchable()->placeholder('—')->toggleable(),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('source')
                    ->label('Source')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => SubscriptionSource::tryFrom((string) $state)?->getLabel() ?? '—')
                    ->sortable(),
                TextColumn::make('source_url')->label('Page')->placeholder('—')->toggleable()->limit(40),
                TextColumn::make('subscribed_at')->label('Subscribed')->dateTime('d M Y')->sortable(),
                TextColumn::make('confirmed_at')->label('Confirmed')->dateTime('d M Y')->placeholder('—')->sortable()->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(SubscriberStatus::class)->multiple(),
                SelectFilter::make('source')->options(SubscriptionSource::options())->multiple(),
                Filter::make('subscribed_at')
                    ->schema([
                        DatePicker::make('from')->label('Subscribed from'),
                        DatePicker::make('until')->label('Subscribed until'),
                    ])
                    ->query(fn ($query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($query, $date) => $query->whereDate('subscribed_at', '>=', $date))
                        ->when($data['until'] ?? null, fn ($query, $date) => $query->whereDate('subscribed_at', '<=', $date))),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('resendConfirmation')
                    ->label('Resend confirmation')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn ($record): bool => $record->status === SubscriberStatus::Pending)
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        // A fresh token every time, so an old link in an inbox stops working.
                        $token = $record->issueToken('confirmation_token');
                        Mail::to($record->email)->send(new ConfirmationMail($record, $token));

                        Notification::make()->title('Confirmation email queued')->success()->send();
                    }),
                Action::make('unsubscribe')
                    ->label('Unsubscribe')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn ($record): bool => $record->status !== SubscriberStatus::Unsubscribed)
                    ->requiresConfirmation()
                    ->modalDescription('The record is kept — it is what stops this address being mailed again.')
                    ->action(function ($record): void {
                        $record->forceFill([
                            'status' => SubscriberStatus::Unsubscribed,
                            'unsubscribed_at' => now(),
                        ])->save();

                        Notification::make()->title('Subscriber unsubscribed')->success()->send();
                    }),
            ]);
    }
}
