<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Modules\Wallet\Services\Wallet;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The "Wallet" tab on a Customer: the wallet ledger, newest first. Read-only;
 * staff move money only through the page's "Adjust wallet" action.
 */
class WalletTransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'walletTransactions';

    protected static ?string $title = 'Wallet';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('Date')->dateTime('d M Y, H:i'),
                TextColumn::make('type')->badge(),
                TextColumn::make('status')->badge(),
                TextColumn::make('amount_paise')->label('Amount')->formatStateUsing(fn (int $state): string => Wallet::format($state)),
                TextColumn::make('gst_paise')->label('GST')->formatStateUsing(fn (?int $state): string => $state ? Wallet::format($state) : '—')->placeholder('—'),
                TextColumn::make('description')->wrap(),
                TextColumn::make('createdBy.name')->label('By staff')->placeholder('—'),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
