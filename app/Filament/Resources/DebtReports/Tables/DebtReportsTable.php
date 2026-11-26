<?php

namespace App\Filament\Resources\DebtReports\Tables;

use App\Filament\Resources\DebtApplications\DebtApplicationResource;
use App\Modules\DebtConsolidation\Actions\GenerateDebtReport;
use App\Modules\DebtConsolidation\Enums\ReportType;
use App\Modules\DebtConsolidation\Models\DebtReport;
use Filament\Actions\Action;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DebtReportsTable
{
    public static function configure(Table $table, bool $withCase = true): Table
    {
        return $table
            ->columns(array_values(array_filter([
                $withCase ? TextColumn::make('application.reference')->label('Case')->searchable()->url(fn (DebtReport $record): string => DebtApplicationResource::getUrl('view', ['record' => $record->application]).'?relation=reports') : null,
                TextColumn::make('report_type')->label('Report')->badge(),
                TextColumn::make('version')->prefix('v'),
                TextColumn::make('generated_at')->label('Generated')->dateTime('j M Y, g:i a', 'Asia/Kolkata')->sortable(),
                TextColumn::make('generator.name')->label('By')->placeholder('System'),
                TextColumn::make('formula_version')->label('Formula')->placeholder('—'),
                IconColumn::make('is_customer_visible')->label('Customer can see')->boolean(),
                TextColumn::make('sha256')->label('SHA-256')->limit(12)->fontFamily('mono')->copyable(),
            ])))
            ->filters([SelectFilter::make('report_type')->label('Report')->options(ReportType::class)])
            ->recordActions([
                Action::make('download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (DebtReport $record): StreamedResponse {
                        $record->application->recordEvent('report_downloaded', ['report' => $record->public_id], auth()->id());

                        return response()->streamDownload(fn () => print $record->contents(), $record->downloadName(), ['Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store']);
                    }),
                Action::make('release')
                    ->label('Release to customer')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (DebtReport $record): bool => ! $record->is_customer_visible && ! $record->report_type->isInternal() && (bool) auth()->user()?->can('create', DebtReport::class))
                    ->requiresConfirmation()
                    ->action(fn (DebtReport $record) => app(GenerateDebtReport::class)->release($record, auth()->id())),
            ])
            ->defaultSort('generated_at', 'desc');
    }
}
