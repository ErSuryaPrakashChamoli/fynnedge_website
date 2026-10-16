<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\FormatsAuditChanges;
use App\Filament\Resources\EquifaxCreditRequests\EquifaxCreditRequestResource;
use App\Models\AuditLog;
use App\Modules\Equifax\Models\EquifaxCreditRequest;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Every recorded Equifax event in one feed — requests created, status changes,
 * reuse, blocked duplicates, report and raw-JSON views, PDF downloads,
 * reconciliations — read from the SAME audit_logs table that powers each
 * request's History tab (no second audit system), and gated on the same
 * View:AuditLog permission.
 */
class EquifaxAuditHistory extends Page implements HasTable
{
    use FormatsAuditChanges;
    use InteractsWithTable;

    protected string $view = 'filament.pages.equifax-audit-history';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|\UnitEnum|null $navigationGroup = 'Equifax';

    protected static ?string $navigationLabel = 'Equifax Audit History';

    protected static ?string $title = 'Equifax Audit History';

    protected static ?int $navigationSort = 6;

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->can('View:AuditLog');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(AuditLog::query()->where('auditable_type', EquifaxCreditRequest::class)->with(['user', 'auditable']))
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y, H:i:s')->sortable(),
                TextColumn::make('user.name')->label('Who')->placeholder('System'),
                TextColumn::make('action')
                    ->label('What')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str_replace('_', ' ', $state)),
                TextColumn::make('auditable.pan_masked')
                    ->label('PAN')
                    ->url(fn (AuditLog $record): ?string => $record->auditable ? EquifaxCreditRequestResource::getUrl('view', ['record' => $record->auditable]) : null),
                TextColumn::make('changes')
                    ->label('Details')
                    ->formatStateUsing(fn (AuditLog $record) => static::formatChanges($record->changes))
                    ->wrap()
                    ->html(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('action')
                    ->options(fn (): array => AuditLog::query()
                        ->where('auditable_type', EquifaxCreditRequest::class)
                        ->distinct()
                        ->orderBy('action')
                        ->pluck('action', 'action')
                        ->map(fn (string $action) => str_replace('_', ' ', $action))
                        ->all()),
                SelectFilter::make('user_id')->label('Who')->relationship('user', 'name'),
            ]);
    }
}
