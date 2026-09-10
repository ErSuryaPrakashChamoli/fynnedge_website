<?php

namespace App\Filament\Resources\NewsletterCampaigns\Tables;

use App\Modules\Newsletter\Enums\CampaignStatus;
use App\Modules\Newsletter\Enums\RecipientStatus;
use App\Modules\Newsletter\Jobs\SendNewsletterCampaign;
use App\Modules\Newsletter\Models\NewsletterCampaign;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class NewsletterCampaignsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Campaign')->searchable()->sortable()->wrap(),
                TextColumn::make('subject')->label('Subject')->searchable()->toggleable()->limit(40),
                TextColumn::make('status')->label('Status')->badge()->sortable(),
                TextColumn::make('segment.name')->label('Audience')->placeholder('Everyone')->toggleable(),
                TextColumn::make('recipients_count')->label('Sent to')->counts('recipients')->sortable(),
                TextColumn::make('opened')
                    ->label('Opened')
                    ->state(fn (NewsletterCampaign $record): string => self::openRate($record))
                    // Image blocking means this is a floor, never an exact count.
                    ->tooltip('Opens are measured by a tracking image, which many mail clients block. Treat this as a minimum.'),
                TextColumn::make('scheduled_at')->label('Scheduled')->dateTime('d M Y H:i')->placeholder('—')->sortable()->toggleable(),
                TextColumn::make('sent_at')->label('Sent')->dateTime('d M Y H:i')->placeholder('—')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')->options(CampaignStatus::class)->multiple(),
            ])
            ->recordActions([
                Action::make('preview')
                    ->label('Preview')
                    ->icon('heroicon-o-eye')
                    ->color('gray')
                    ->modalHeading(fn (NewsletterCampaign $record): string => 'Preview: '.$record->subject)
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Close')
                    ->modalContent(fn (NewsletterCampaign $record) => view('filament.newsletter.campaign-preview', ['campaign' => $record])),
                EditAction::make()->visible(fn (NewsletterCampaign $record): bool => $record->isEditable()),
                Action::make('send')
                    ->label(fn (NewsletterCampaign $record): string => $record->scheduled_at ? 'Send now' : 'Send')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (NewsletterCampaign $record): bool => $record->isEditable())
                    ->requiresConfirmation()
                    ->modalHeading('Send this campaign?')
                    ->modalDescription(fn (NewsletterCampaign $record): string => 'This queues an email to '
                        .$record->audience()->count().' subscriber(s). It cannot be undone once sending starts.')
                    ->action(function (NewsletterCampaign $record): void {
                        /*
                         * The request only marks the campaign and dispatches ONE job.
                         * Everything else — building the audience, creating recipient
                         * rows, queuing each email — happens on the queue, so the size
                         * of the list never affects the admin's request.
                         */
                        $record->forceFill([
                            'status' => CampaignStatus::Scheduled,
                            'scheduled_at' => $record->scheduled_at ?? now(),
                        ])->save();

                        SendNewsletterCampaign::dispatch($record);

                        Notification::make()
                            ->title('Campaign queued for sending')
                            ->body('Emails are sent by the queue worker. Progress appears in the Sent to column.')
                            ->success()
                            ->send();
                    }),
                Action::make('cancel')
                    ->label('Cancel')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (NewsletterCampaign $record): bool => in_array(
                        $record->status,
                        [CampaignStatus::Scheduled, CampaignStatus::Sending],
                        strict: true,
                    ))
                    ->requiresConfirmation()
                    ->modalDescription('Emails already handed to the queue may still go out; this stops everything that has not been queued yet.')
                    ->action(function (NewsletterCampaign $record): void {
                        $record->forceFill(['status' => CampaignStatus::Cancelled])->save();

                        Notification::make()->title('Campaign cancelled')->success()->send();
                    }),
                DeleteAction::make()->visible(fn (NewsletterCampaign $record): bool => $record->status === CampaignStatus::Draft),
            ]);
    }

    private static function openRate(NewsletterCampaign $record): string
    {
        $sent = $record->recipients()->where('status', RecipientStatus::Sent)->count();

        if ($sent === 0) {
            return '—';
        }

        $opened = $record->recipients()->whereNotNull('opened_at')->count();

        return $opened.' ('.round($opened / $sent * 100).'%)';
    }
}
