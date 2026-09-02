<?php

namespace App\Filament\RelationManagers;

use App\Filament\Concerns\FormatsAuditChanges;
use App\Models\AuditLog;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * A single, reusable "History" tab for any model using the Auditable trait —
 * every audited resource (Applications, Lenders, Loan Products, Eligibility
 * Rule Sets, and anything audited later) points its getRelations() at this
 * one class instead of each keeping its own copy. Read-only by design (no
 * header/record/toolbar actions) — audit rows are never editable or
 * deletable from here, only viewable to those with the View:AuditLog
 * permission.
 */
class AuditLogsRelationManager extends RelationManager
{
    use FormatsAuditChanges;

    protected static string $relationship = 'auditLogs';

    protected static ?string $title = 'History';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('View:AuditLog');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('action')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'created' => 'success',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('user.name')->label('By')->placeholder('System'),
                TextColumn::make('changes')
                    ->label('Changes')
                    ->formatStateUsing(fn (AuditLog $record) => static::formatChanges($record->changes))
                    ->wrap()
                    ->html(),
                TextColumn::make('created_at')
                    ->label('Date')
                    ->dateTime('d M Y, H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([])
            ->recordActions($this->getHistoryRecordActions())
            ->toolbarActions([]);
    }

    /**
     * Extension point for subclasses (see RestorableAuditLogsRelationManager) —
     * empty by default, so History stays read-only wherever restore isn't
     * explicitly opted into.
     *
     * @return array<int, Action>
     */
    protected function getHistoryRecordActions(): array
    {
        return [];
    }
}
