<?php

namespace App\Filament\Resources\LenderProductRates;

use App\Enums\RateStatus;
use App\Models\LenderProductRate;
use App\Models\User;
use App\Support\Rates\RateWorkflow;
use App\Support\Rates\RateWorkflowException;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * The Filament buttons for RateWorkflow's transitions, shared by the list
 * table and the view page so both show the same choices. Visibility here only
 * tidies the UI; RateWorkflow re-checks the permission and the state, and its
 * reasons are shown to the editor if a transition is refused.
 */
final class RateWorkflowActions
{
    /**
     * @return list<Action>
     */
    public static function all(): array
    {
        return [self::submit(), self::publish(), self::reject(), self::withdraw(), self::newVersion()];
    }

    public static function submit(): Action
    {
        return Action::make('submitForApproval')
            ->label('Submit for approval')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('warning')
            ->visible(fn (LenderProductRate $record): bool => $record->status === RateStatus::Draft && self::user()->can('submit', $record))
            ->requiresConfirmation()
            ->modalDescription('A publisher will review the evidence before the rate goes live.')
            ->action(fn (LenderProductRate $record, Action $action) => self::run(
                $action,
                fn () => RateWorkflow::submit($record, self::user()),
                'Submitted for approval',
            ));
    }

    public static function publish(): Action
    {
        return Action::make('publish')
            ->label(fn (LenderProductRate $record): string => $record->status === RateStatus::PendingApproval ? 'Approve & publish' : 'Publish')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (LenderProductRate $record): bool => in_array($record->status, [RateStatus::Draft, RateStatus::PendingApproval], true)
                && self::user()->can('publish', $record))
            ->requiresConfirmation()
            ->modalDescription(fn (LenderProductRate $record): string => "This replaces the live rate for {$record->offerLabel()} on the website. The current live version is kept in the history.")
            ->action(fn (LenderProductRate $record, Action $action) => self::run(
                $action,
                fn () => RateWorkflow::publish($record, self::user()),
                'Published — the website now shows this rate',
            ));
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Send back')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->visible(fn (LenderProductRate $record): bool => $record->status === RateStatus::PendingApproval && self::user()->can('publish', $record))
            ->schema([
                Textarea::make('reason')
                    ->label('What needs to change?')
                    ->required()
                    ->maxLength(1000),
            ])
            ->action(fn (LenderProductRate $record, array $data, Action $action) => self::run(
                $action,
                fn () => RateWorkflow::reject($record, self::user(), $data['reason']),
                'Sent back to draft',
            ));
    }

    public static function withdraw(): Action
    {
        return Action::make('withdraw')
            ->label('Withdraw from site')
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('danger')
            ->visible(fn (LenderProductRate $record): bool => $record->status === RateStatus::Published && self::user()->can('publish', $record))
            ->requiresConfirmation()
            ->modalDescription('The lender\'s row will read "Check current rates" until a new version is published.')
            ->action(fn (LenderProductRate $record, Action $action) => self::run(
                $action,
                fn () => RateWorkflow::withdraw($record, self::user()),
                'Withdrawn from the website',
            ));
    }

    /**
     * "Edit as new version" on a live rate; "Restore as new draft" on an old
     * one. Either way the copy is a Draft that must be approved again.
     */
    public static function newVersion(): Action
    {
        return Action::make('newVersion')
            ->label(fn (LenderProductRate $record): string => $record->status === RateStatus::Archived ? 'Restore as new draft' : 'Edit as new version')
            ->icon(Heroicon::OutlinedDocumentDuplicate)
            ->color('gray')
            ->visible(fn (LenderProductRate $record): bool => in_array($record->status, [RateStatus::Published, RateStatus::Archived], true)
                && self::user()->can('create', LenderProductRate::class))
            ->requiresConfirmation()
            ->modalDescription('Creates an editable draft copy. The live rate does not change until the draft is approved and published.')
            ->action(function (LenderProductRate $record, Action $action): void {
                try {
                    $draft = RateWorkflow::draftFrom($record, self::user());
                } catch (RateWorkflowException|AuthorizationException $exception) {
                    self::refuse($action, $exception);

                    return;
                }

                Notification::make()->title("Draft version {$draft->version} created")->success()->send();
                $action->redirect(LenderProductRateResource::getUrl('edit', ['record' => $draft]));
            });
    }

    /**
     * Publishes every selected draft or submitted rate that passes the
     * publication rules, and reports the ones that did not, with reasons.
     */
    public static function bulkPublish(): BulkAction
    {
        return BulkAction::make('bulkPublish')
            ->label('Approve & publish selected')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn (): bool => self::user()->can('publish', LenderProductRate::class))
            ->requiresConfirmation()
            ->modalDescription('Each selected draft or submitted rate that has verified evidence goes live; anything that fails a check is skipped and listed.')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $published = 0;
                $skipped = [];

                foreach ($records as $record) {
                    try {
                        RateWorkflow::publish($record, self::user());
                        $published++;
                    } catch (RateWorkflowException|AuthorizationException $exception) {
                        $skipped[] = $record->offerLabel().': '.($exception instanceof RateWorkflowException ? $exception->problems[0] : $exception->getMessage());
                    }
                }

                Notification::make()
                    ->title("Published {$published} ".str('rate')->plural($published))
                    ->body($skipped === [] ? null : 'Skipped: '.implode(' · ', array_slice($skipped, 0, 8)).(count($skipped) > 8 ? ' · …and '.(count($skipped) - 8).' more' : ''))
                    ->color($skipped === [] ? 'success' : 'warning')
                    ->persistent()
                    ->send();
            });
    }

    /**
     * @param  callable(): mixed  $transition
     */
    private static function run(Action $action, callable $transition, string $successTitle): void
    {
        try {
            $transition();
        } catch (RateWorkflowException|AuthorizationException $exception) {
            self::refuse($action, $exception);

            return;
        }

        Notification::make()->title($successTitle)->success()->send();
    }

    private static function refuse(Action $action, RateWorkflowException|AuthorizationException $exception): void
    {
        Notification::make()
            ->title('Not done')
            ->body($exception instanceof RateWorkflowException ? implode("\n", $exception->problems) : $exception->getMessage())
            ->danger()
            ->persistent()
            ->send();

        $action->halt();
    }

    private static function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
