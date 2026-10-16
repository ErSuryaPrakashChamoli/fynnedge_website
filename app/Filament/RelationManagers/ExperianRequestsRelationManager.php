<?php

namespace App\Filament\RelationManagers;

use App\Filament\Resources\ExperianCreditRequests\ExperianCreditRequestResource;
use App\Modules\Experian\Models\ExperianCreditRequest;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The "Experian" tab on a Customer and on an Application: every Experian score
 * and report request linked to that record, newest first, with its status and
 * score. Read-only; requests start from the page's "Experian" menu.
 */
class ExperianRequestsRelationManager extends RelationManager
{
    protected static string $relationship = 'experianRequests';

    protected static ?string $title = 'Experian';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('viewAny', ExperianCreditRequest::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('product')->label('Type')->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->description(fn (ExperianCreditRequest $record): ?string => match (true) {
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
                    ->visible(fn (ExperianCreditRequest $record): bool => (bool) auth()->user()?->can('view', $record))
                    ->url(fn (ExperianCreditRequest $record): string => ExperianCreditRequestResource::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
