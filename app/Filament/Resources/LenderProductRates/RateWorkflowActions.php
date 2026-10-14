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
use Illuminate\Support\HtmlString;

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
     * List tabs whose rows are all past publishing (live, lapsed or archived),
     * so the bulk publish button is not offered there.
     *
     * @var list<string>
     */
    private const TABS_WITHOUT_PUBLISHABLE_RATES = ['live', 'expired', 'history'];

    /**
     * Publishes every selected draft or submitted rate that passes the
     * publication rules. Selected rates that are already live or archived are
     * left alone and only counted — they are not failures. Rates that fail a
     * check are grouped by reason, so a large selection still gives a short,
     * readable summary.
     */
    public static function bulkPublish(): BulkAction
    {
        return BulkAction::make('bulkPublish')
            ->label('Approve & publish selected')
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->visible(fn ($livewire): bool => self::user()->can('publish', LenderProductRate::class)
                && ! in_array($livewire->activeTab ?? null, self::TABS_WITHOUT_PUBLISHABLE_RATES, true))
            ->requiresConfirmation()
            ->modalDescription('Each selected draft or submitted rate with verified evidence goes live. Rates that are already live are left as they are; anything that fails a check stays a draft.')
            ->deselectRecordsAfterCompletion()
            ->action(function (Collection $records): void {
                $publishable = $records->filter(fn (LenderProductRate $record) => in_array($record->status, [RateStatus::Draft, RateStatus::PendingApproval], true));
                $alreadyLive = $records->filter(fn (LenderProductRate $record) => $record->status === RateStatus::Published)->count();
                $archived = $records->count() - $publishable->count() - $alreadyLive;

                $published = 0;
                $failures = [];

                foreach ($publishable as $record) {
                    try {
                        RateWorkflow::publish($record, self::user());
                        $published++;
                    } catch (RateWorkflowException|AuthorizationException $exception) {
                        $reason = $exception instanceof RateWorkflowException ? $exception->problems[0] : $exception->getMessage();
                        $failures[$reason][] = $record->offerLabel();
                    }
                }

                $failed = array_sum(array_map('count', $failures));

                $notification = Notification::make()
                    ->title(match (true) {
                        $published > 0 => "Published {$published} ".str('rate')->plural($published),
                        $failed > 0 => 'No rates published',
                        default => 'Nothing to publish',
                    })
                    ->body(self::bulkPublishSummary($alreadyLive, $archived, $failures))
                    ->color(match (true) {
                        $failed > 0 => 'warning',
                        $published > 0 => 'success',
                        default => 'info',
                    });

                // Failures need reading and acting on; a clean result can fade away.
                if ($failed > 0) {
                    $notification->persistent();
                }

                $notification->send();
            });
    }

    /**
     * @param  array<string, list<string>>  $failures  Reason => offer labels.
     */
    private static function bulkPublishSummary(int $alreadyLive, int $archived, array $failures): ?HtmlString
    {
        $lines = [];

        if ($alreadyLive > 0) {
            $lines[] = e($alreadyLive.' selected '.str('rate')->plural($alreadyLive).' '.($alreadyLive === 1 ? 'was' : 'were').' already live and left unchanged.');
        }

        if ($archived > 0) {
            $lines[] = e($archived.' archived '.str('version')->plural($archived).' left as history.');
        }

        foreach (array_slice($failures, 0, 3, true) as $reason => $offers) {
            $names = implode(', ', array_slice($offers, 0, 3)).(count($offers) > 3 ? ' and '.(count($offers) - 3).' more' : '');
            $lines[] = '<strong>'.e(count($offers).' not published:').'</strong> '.e($reason).' <span>('.e($names).')</span>';
        }

        if (count($failures) > 3) {
            $lines[] = e('Others failed for '.(count($failures) - 3).' further reasons. Open each draft to see what is missing.');
        }

        return $lines === [] ? null : new HtmlString(implode('<br>', $lines));
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
