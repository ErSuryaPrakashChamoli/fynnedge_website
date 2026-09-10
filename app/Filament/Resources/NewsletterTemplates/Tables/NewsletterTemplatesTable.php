<?php

namespace App\Filament\Resources\NewsletterTemplates\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class NewsletterTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Name')->searchable()->sortable(),
                TextColumn::make('description')->label('Description')->placeholder('—')->limit(60)->toggleable(),
                IconColumn::make('is_active')->label('Available')->boolean(),
                TextColumn::make('updated_at')->label('Updated')->dateTime('d M Y')->sortable()->toggleable(),
            ])
            ->defaultSort('name')
            ->filters([TernaryFilter::make('is_active')->label('Available')])
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
