<?php

namespace App\Filament\Resources\LoanLandingPageSeo\Tables;

use App\Enums\LandingPageGroup;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LoanLandingPageSeoTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('loanProduct.name')
                    ->label('Loan product')
                    ->sortable(),
                TextColumn::make('group')
                    ->badge(),
                TextColumn::make('seoMeta.title')
                    ->label('SEO title')
                    ->placeholder('— uses page default —')
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('title')
            ->filters([
                SelectFilter::make('group')->options(LandingPageGroup::class),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
