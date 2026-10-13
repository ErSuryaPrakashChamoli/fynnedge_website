<?php

namespace App\Filament\Resources\LenderProductRates\RelationManagers;

use App\Filament\Resources\LenderProductRates\LenderProductRateResource;
use App\Filament\Resources\LenderProductRates\RateWorkflowActions;
use App\Models\LenderProductRate;
use App\Support\Rates\RateFormat;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Every version of the rate line being viewed, newest first, with who
 * published or archived each one. "Restore as new draft" copies an earlier
 * version back into the approval workflow — a rollback is reviewed like any
 * other change, never applied straight to the live site.
 */
class VersionsRelationManager extends RelationManager
{
    protected static string $relationship = 'versions';

    protected static ?string $title = 'Version history';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['publisher', 'editor']))
            ->defaultSort('version', 'desc')
            ->columns([
                TextColumn::make('version')->prefix('v'),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (LenderProductRate $record): string => $record->displayStatus())
                    ->color(fn (LenderProductRate $record): string => $record->displayStatusColor()),
                TextColumn::make('rate_min')
                    ->label('Rate')
                    ->formatStateUsing(fn (LenderProductRate $record): string => RateFormat::range((float) $record->rate_min, $record->rate_max !== null ? (float) $record->rate_max : null, $record->rate_unit))
                    ->description(fn (LenderProductRate $record): ?string => $record->promo_rate !== null ? 'Offer '.RateFormat::percent((float) $record->promo_rate) : null),
                TextColumn::make('last_verified_at')->label('Checked')->date('j M Y')->placeholder('Never'),
                TextColumn::make('publisher.name')->label('Published by')->placeholder('—'),
                TextColumn::make('published_at')->dateTime('j M Y, g:i a', LenderProductRate::TIMEZONE)->placeholder('—'),
                TextColumn::make('archived_at')->label('Archived')->dateTime('j M Y, g:i a', LenderProductRate::TIMEZONE)->placeholder('—'),
                TextColumn::make('editor.name')->label('Last edited by')->placeholder('System'),
            ])
            ->recordActions([
                Action::make('open')
                    ->label('Open')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('gray')
                    ->url(fn (LenderProductRate $record): string => LenderProductRateResource::getUrl('view', ['record' => $record])),
                RateWorkflowActions::newVersion(),
            ]);
    }
}
