<?php

namespace App\Filament\Resources\Customers\RelationManagers;

use App\Modules\Wallet\Models\CustomerPlan;
use App\Modules\Wallet\Services\Wallet;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The "Refresh plans" tab on a Customer. Read-only; customers buy plans from
 * their wallet in the portal.
 */
class PlansRelationManager extends RelationManager
{
    protected static string $relationship = 'plans';

    protected static ?string $title = 'Refresh plans';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('plan')->badge(),
                TextColumn::make('starts_at')->label('Starts')->dateTime('d M Y'),
                TextColumn::make('ends_at')->label('Ends')->dateTime('d M Y'),
                TextColumn::make('active')
                    ->label('Status')
                    ->badge()
                    ->state(fn (CustomerPlan $record): string => match (true) {
                        $record->starts_at->isFuture() => 'Upcoming',
                        $record->ends_at->isPast() => 'Expired',
                        default => 'Active',
                    }),
                TextColumn::make('price_paise')->label('Price')->formatStateUsing(fn (int $state, CustomerPlan $record): string => Wallet::format($state).' + GST '.Wallet::format($record->gst_paise)),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([])
            ->recordActions([])
            ->toolbarActions([]);
    }
}
