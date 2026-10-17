<?php

namespace App\Filament\RelationManagers;

use App\Modules\CreditBureau\Services\PanNumber;
use App\Modules\CreditScore\Models\CreditScoreCheck;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The "Website score checks" tab on a Customer: the free credit-score checks
 * this customer-portal account ran (the checks it sees in /my-profile), newest
 * first. Read-only; the full report, when kept, is on the bureau's own tab.
 */
class CreditScoreChecksRelationManager extends RelationManager
{
    protected static string $relationship = 'creditScoreChecks';

    protected static ?string $title = 'Website score checks';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('viewAny', CreditScoreCheck::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('bureau')
                    ->badge()
                    ->description(fn (CreditScoreCheck $record): ?string => $record->sourceBureau() !== $record->bureau ? 'Answered by '.$record->sourceBureau()?->getLabel() : null),
                TextColumn::make('provider')
                    ->badge()
                    ->color(fn (string $state): string => $state === CreditScoreCheck::LIVE_PROVIDER ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state): string => $state === CreditScoreCheck::LIVE_PROVIDER ? 'Live' : 'Demo (not shown to customer)'),
                TextColumn::make('status')->badge(),
                TextColumn::make('score')->placeholder('—'),
                TextColumn::make('reused')
                    ->label('Answered from')
                    ->state(fn (CreditScoreCheck $record): string => $record->reusedFromId() !== null ? 'Stored score (no new pull)' : 'Bureau'),
                TextColumn::make('pan_number')->label('PAN')->formatStateUsing(fn (?string $state): string => PanNumber::mask((string) $state)),
                TextColumn::make('requested_at')->label('Requested')->dateTime('d M Y, H:i'),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
