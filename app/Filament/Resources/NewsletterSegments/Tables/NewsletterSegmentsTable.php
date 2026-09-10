<?php

namespace App\Filament\Resources\NewsletterSegments\Tables;

use App\Modules\Newsletter\Models\NewsletterSegment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NewsletterSegmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Segment')->searchable()->sortable(),
                TextColumn::make('description')->label('Description')->placeholder('—')->limit(50)->toggleable(),
                TextColumn::make('size')
                    ->label('Subscribers')
                    ->state(fn (NewsletterSegment $record): int => $record->subscribers()->count()),
                IconColumn::make('is_active')->label('Available')->boolean(),
            ])
            ->defaultSort('name')
            ->recordActions([EditAction::make(), DeleteAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
