<?php

namespace App\Filament\RelationManagers;

use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Database\QueryException;

/**
 * The same read-only History tab as AuditLogsRelationManager, plus a
 * "Restore this version" action on `updated` rows — content versioning
 * built ON TOP of the existing audit log rather than a second history store
 * (audit answers "who changed what and when", this adds "and can it be
 * undone"). Reverting sets the record's changed fields back to this row's
 * `old` values and saves, which itself writes a fresh, ordinary audit entry
 * — so a restore is provenanced exactly like any other edit.
 *
 * Deliberately opted into only for pure-content models with no
 * business-relevant numeric/eligibility fields (Article, Faq, Testimonial,
 * Banner, CompanyPhoto, MarketingSection) — never for LoanProduct, LenderProduct,
 * Lender, Application, or EligibilityRuleSet/Rule/Condition, where a value
 * restored outside normal form validation could produce an inconsistent
 * calculator/eligibility configuration. See .ai/rules.
 */
class RestorableAuditLogsRelationManager extends AuditLogsRelationManager
{
    protected function getHistoryRecordActions(): array
    {
        return [
            Action::make('restore')
                ->label('Restore this version')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('This sets every field below back to its previous value and saves — creating a new History entry for the restore itself.')
                ->visible(function (AuditLog $record): bool {
                    if ($record->action !== 'updated' || empty($record->changes)) {
                        return false;
                    }

                    $modelClass = class_basename($record->auditable_type);

                    return (bool) auth()->user()?->can("Update:{$modelClass}");
                })
                ->action(function (AuditLog $record): void {
                    $auditable = $record->auditable;

                    if (! $auditable) {
                        Notification::make()
                            ->title('The original record no longer exists')
                            ->danger()
                            ->send();

                        return;
                    }

                    $oldValues = collect($record->changes)
                        ->mapWithKeys(fn (array $change, string $key) => [$key => $change['old'] ?? null])
                        ->all();

                    try {
                        $auditable->update($oldValues);
                    } catch (QueryException) {
                        Notification::make()
                            ->title('Could not restore — a previous value now conflicts with another record (e.g. a reused slug)')
                            ->danger()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Restored to the version from '.$record->created_at->format('d M Y, H:i'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
