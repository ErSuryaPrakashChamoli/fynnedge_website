<?php

namespace App\Filament\RelationManagers;

use App\Filament\Resources\CibilCreditRequests\CibilCreditRequestResource;
use App\Modules\Cibil\Models\CibilCreditRequest;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The "CIBIL" tab on a Customer and on an Application: every CIBIL score
 * and report request linked to that record, newest first, with its status and
 * score. Read-only; requests start from the page's "CIBIL" menu.
 */
class CibilRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'cibilRequests';

    protected static ?string $title = 'CIBIL';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('viewAny', CibilCreditRequest::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product')->label('Type')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->description(fn (CibilCreditRequest $record): ?string => match (true) {
                        $record->isReusable() => 'Reusable until '.$record->reusableUntil()?->format('d M Y'),
                        default => $record->error_code?->getLabel(),
                    }),
                TextColumn::make('score')->placeholder('—'),
                TextColumn::make('pan_masked')->label('PAN'),
                TextColumn::make('request_type')->label('Request')->badge(),
                TextColumn::make('requestedBy.name')
                    ->label('Requested by')
                    ->state(fn (CibilCreditRequest $record): ?string => $record->requestedBy?->name ?? ($record->channel === 'website' ? 'Website visitor' : null))
                    ->placeholder('—'),
                TextColumn::make('requested_at')->label('Requested')->dateTime('d M Y, H:i'),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([])
            ->recordActions([
                Action::make('open')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn (CibilCreditRequest $record): bool => (bool) auth()->user()?->can('view', $record))
                    ->url(fn (CibilCreditRequest $record): string => CibilCreditRequestResource::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
