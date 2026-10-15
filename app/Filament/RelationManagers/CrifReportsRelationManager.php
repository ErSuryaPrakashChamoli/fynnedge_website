<?php

namespace App\Filament\RelationManagers;

use App\Filament\Resources\CrifReports\CrifReportResource;
use App\Modules\CreditBureau\Models\CrifReport;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The "CRIF reports" tab on a Customer and on an Application: every CRIF
 * attempt linked to that record, newest first, with its status — so the
 * current credit-report position is visible where the employee is working.
 * Read-only; requests start from the page's "Fetch CRIF Report" button.
 */
class CrifReportsRelationManager extends RelationManager
{
    protected static string $relationship = 'crifReports';

    protected static ?string $title = 'CRIF reports';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return (bool) auth()->user()?->can('viewAny', CrifReport::class);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('status')
                    ->badge()
                    ->description(fn (CrifReport $record): ?string => match (true) {
                        $record->isReusable() => 'Reusable until '.$record->reusableUntil()?->format('d M Y'),
                        default => $record->error_code?->getLabel(),
                    }),
                TextColumn::make('score')->placeholder('—'),
                TextColumn::make('pan_masked')->label('PAN'),
                TextColumn::make('request_type')->label('Type')->badge(),
                TextColumn::make('requestedBy.name')->label('Initiated by')->placeholder('—'),
                TextColumn::make('requested_at')->label('Requested')->dateTime('d M Y, H:i'),
            ])
            ->defaultSort('id', 'desc')
            ->headerActions([])
            ->recordActions([
                Action::make('open')
                    ->label('View')
                    ->icon(Heroicon::OutlinedEye)
                    ->visible(fn (CrifReport $record): bool => (bool) auth()->user()?->can('view', $record))
                    ->url(fn (CrifReport $record): string => CrifReportResource::getUrl('view', ['record' => $record])),
            ])
            ->toolbarActions([]);
    }
}
