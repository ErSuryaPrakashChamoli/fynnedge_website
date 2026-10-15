<?php

namespace App\Filament\Resources\LenderProductRates;

use App\Enums\RateSourceType;
use App\Enums\RateStatus;
use App\Enums\RateUnit;
use App\Enums\RateVerificationStatus;
use App\Models\LenderProductRate;
use App\Models\User;
use App\Support\Rates\RatePublicationRules;
use App\Support\Rates\RateWorkflow;
use App\Support\Rates\RateWorkflowException;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

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
        return [self::verify(), self::submit(), self::publish(), self::reject(), self::withdraw(), self::newVersion()];
    }

    /**
     * Records that a person checked a draft's rate on the lender's own page:
     * the figures as the lender states them, the link, the sentence that
     * states the rate and the date — then marks the draft Verified. A
     * publisher can publish it in the same step. This is the only way an
     * unverified rate becomes publishable; there is deliberately no bulk
     * "mark verified".
     */
    public static function verify(): Action
    {
        return Action::make('verify')
            ->label('Verify')
            ->icon(Heroicon::OutlinedShieldCheck)
            ->color('primary')
            ->visible(fn (LenderProductRate $record): bool => $record->status === RateStatus::Draft
                && $record->needsReverification()
                && (bool) self::user()?->can('update', $record))
            ->modalHeading(fn (LenderProductRate $record): string => "Verify {$record->offerLabel()}")
            ->modalDescription('Open the lender\'s own page, check the rate there, and record what it says. Comparison sites such as BankBazaar or Paisabazaar are not evidence.')
            ->modalSubmitActionLabel(fn (): string => 'Save as verified')
            ->fillForm(fn (LenderProductRate $record): array => [
                'rate_unit' => $record->rate_unit->value,
                'rate_min' => $record->rate_min,
                'rate_max' => $record->rate_max,
                'source_url' => $record->source_url,
                'source_name' => $record->source_type === RateSourceType::Official ? $record->source_name : null,
                'last_verified_at' => LenderProductRate::today()->toDateString(),
                'publish_now' => (bool) self::user()?->can('publish', $record),
            ])
            ->schema(fn (LenderProductRate $record): array => [
                Text::make(new HtmlString(
                    '<strong>What the audit found</strong><br>'.nl2br(e(Str::limit((string) ($record->source_notes ?: 'No notes on record.'), 900))),
                )),
                Grid::make(3)->schema([
                    Select::make('rate_unit')
                        ->label('Quoted as')
                        ->options(RateUnit::class)
                        ->required()
                        ->live(),
                    TextInput::make('rate_min')
                        ->label('Starting rate')
                        ->numeric()
                        ->step(0.01)
                        ->minValue(0.01)
                        ->maxValue(fn (Get $get): float => self::unitFrom($get('rate_unit'))->maximum())
                        ->suffix('%')
                        ->required(),
                    TextInput::make('rate_max')
                        ->label('Maximum rate')
                        ->numeric()
                        ->step(0.01)
                        ->gte('rate_min')
                        ->maxValue(fn (Get $get): float => self::unitFrom($get('rate_unit'))->maximum())
                        ->suffix('%'),
                ]),
                TextInput::make('source_url')
                    ->label('Link to the lender\'s own page')
                    ->url()
                    ->required()
                    ->maxLength(2048)
                    ->rule(fn (): Closure => function (string $attribute, mixed $value, Closure $fail): void {
                        if (! RatePublicationRules::isOfficialSourceUrl($value)) {
                            $fail('Use the full https:// link to the lender\'s own page.');
                        }
                    }),
                TextInput::make('source_name')
                    ->label('Name of that page')
                    ->placeholder('e.g. Bank of India — Interest rates on advances')
                    ->maxLength(255),
                Textarea::make('quote')
                    ->label('What the lender\'s page says')
                    ->helperText('Paste the exact sentence or table row that states the rate.')
                    ->rows(2)
                    ->required()
                    ->maxLength(1000),
                DatePicker::make('last_verified_at')
                    ->label('Checked on')
                    ->required()
                    ->maxDate(fn () => LenderProductRate::today()),
                Checkbox::make('confirmed')
                    ->label('I read this rate on the lender\'s own website, not a comparison site.')
                    ->accepted()
                    ->validationMessages(['accepted' => 'Confirm you checked the rate on the lender\'s own website.']),
                Toggle::make('publish_now')
                    ->label('Publish it now')
                    ->helperText('Otherwise it stays a verified draft for a publisher to approve.')
                    ->visible(fn (): bool => (bool) self::user()?->can('publish', $record)),
            ])
            ->action(function (LenderProductRate $record, array $data, Action $action): void {
                $user = self::actor();

                $record->update([
                    'rate_unit' => $data['rate_unit'],
                    'rate_min' => $data['rate_min'],
                    'rate_max' => filled($data['rate_max'] ?? null) ? $data['rate_max'] : null,
                    'source_url' => $data['source_url'],
                    'source_type' => RateSourceType::Official,
                    'source_name' => filled($data['source_name'] ?? null) ? $data['source_name'] : null,
                    'last_verified_at' => $data['last_verified_at'],
                    'verification_status' => RateVerificationStatus::Verified,
                    'source_notes' => "Checked on {$data['last_verified_at']} by {$user->name}: \"{$data['quote']}\"\n\n".$record->source_notes,
                ]);

                if (! ($data['publish_now'] ?? false)) {
                    $problems = RatePublicationRules::problems($record->fresh(['lenderProduct.loanProduct']));

                    Notification::make()
                        ->title($problems === [] ? 'Verified — ready to submit or publish' : 'Verified, but it cannot be published yet')
                        ->body($problems === [] ? null : implode("\n", $problems))
                        ->color($problems === [] ? 'success' : 'warning')
                        ->send();

                    return;
                }

                self::run($action, fn () => RateWorkflow::publish($record, $user), 'Verified and published — the website now shows this rate');
            });
    }

    public static function submit(): Action
    {
        return Action::make('submitForApproval')
            ->label('Submit for approval')
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->color('warning')
            ->visible(fn (LenderProductRate $record): bool => $record->status === RateStatus::Draft && (bool) self::user()?->can('submit', $record))
            ->requiresConfirmation()
            ->modalDescription('A publisher will review the evidence before the rate goes live.')
            ->action(fn (LenderProductRate $record, Action $action) => self::run(
                $action,
                fn () => RateWorkflow::submit($record, self::actor()),
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
                && (bool) self::user()?->can('publish', $record))
            ->requiresConfirmation()
            ->modalDescription(fn (LenderProductRate $record): string => "This replaces the live rate for {$record->offerLabel()} on the website. The current live version is kept in the history.")
            ->action(fn (LenderProductRate $record, Action $action) => self::run(
                $action,
                fn () => RateWorkflow::publish($record, self::actor()),
                'Published — the website now shows this rate',
            ));
    }

    public static function reject(): Action
    {
        return Action::make('reject')
            ->label('Send back')
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->visible(fn (LenderProductRate $record): bool => $record->status === RateStatus::PendingApproval && (bool) self::user()?->can('publish', $record))
            ->schema([
                Textarea::make('reason')
                    ->label('What needs to change?')
                    ->required()
                    ->maxLength(1000),
            ])
            ->action(fn (LenderProductRate $record, array $data, Action $action) => self::run(
                $action,
                fn () => RateWorkflow::reject($record, self::actor(), $data['reason']),
                'Sent back to draft',
            ));
    }

    public static function withdraw(): Action
    {
        return Action::make('withdraw')
            ->label('Withdraw from site')
            ->icon(Heroicon::OutlinedEyeSlash)
            ->color('danger')
            ->visible(fn (LenderProductRate $record): bool => $record->status === RateStatus::Published && (bool) self::user()?->can('publish', $record))
            ->requiresConfirmation()
            ->modalDescription('The lender\'s row will read "Check current rates" until a new version is published.')
            ->action(fn (LenderProductRate $record, Action $action) => self::run(
                $action,
                fn () => RateWorkflow::withdraw($record, self::actor()),
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
                && (bool) self::user()?->can('create', LenderProductRate::class))
            ->requiresConfirmation()
            ->modalDescription('Creates an editable draft copy. The live rate does not change until the draft is approved and published.')
            ->action(function (LenderProductRate $record, Action $action): void {
                try {
                    $draft = RateWorkflow::draftFrom($record, self::actor());
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
            ->visible(fn ($livewire): bool => (bool) self::user()?->can('publish', LenderProductRate::class)
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
                        RateWorkflow::publish($record, self::actor());
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

        if (collect($failures)->keys()->contains(fn (string $reason) => str_starts_with($reason, 'Mark the rate as verified'))) {
            $lines[] = '<strong>'.e('To verify a rate:').'</strong> '.e('open the row\'s ⋮ menu → Verify, after checking it on the lender\'s own page.');
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

    /**
     * A Select bound to an enum hands back the enum once hydrated, and its
     * raw string before that — accept both.
     */
    private static function unitFrom(mixed $value): RateUnit
    {
        return $value instanceof RateUnit ? $value : (RateUnit::tryFrom((string) $value) ?? RateUnit::PerAnnum);
    }

    /**
     * The signed-in admin, or null — e.g. right after a synchronous import,
     * whose job signs the request's guard out. Null never passes a check.
     */
    private static function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    /**
     * The admin an action acts for; refuses rather than erroring if there is none.
     */
    private static function actor(): User
    {
        return self::user() ?? throw new AuthorizationException('Sign in again to change interest rates.');
    }
}
