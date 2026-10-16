<?php

namespace App\Filament\RelationManagers;

use App\Filament\Resources\EquifaxCreditRequests\EquifaxCreditRequestResource;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The "Equifax" tab on a Customer and on an Application: every Equifax score
 * and report request linked to that record, newest first, with its status and
 * score. Read-only; requests start from the page's "Equifax" menu.
 */
class EquifaxRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'equifaxRequests';

    protected static ?string $title = 'Equifax';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('viewAny', EquifaxCreditRequest::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product')->label('Type')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->description(fn (EquifaxCreditRequest $record): ?string => match (true) {
                        $record->isReusable() => 'Reusable until '.$record->reusableUntil()?->format('d M Y'),
                        default => $record->error_code?->getLabel(),
                    }),
                TextColumn::make('score')->placeholder('—'),
                TextColumn::make('pan_masked')->label('PAN'),
                TextColumn::make('request_type')->label('Request')->badge(),
                TextColumn::make('requestedBy.name')->label('Requested by')->placeholder('—'),
                TextColumn::make('requested_at')->label('Requested')->dateTime('d M Y, H:i'),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([])
            ->recordActions([
                Action::make('open')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn (EquifaxCreditRequest $record): bool => (bool) auth()->user()?->can('view', $record))
                    ->url(fn (EquifaxCreditRequest $record): string => EquifaxCreditRequestResource::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
